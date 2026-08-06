<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Boleta;
use App\Models\Client;
use App\Models\Invoice;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Anulación (≤7 días desde emisión) y notas de crédito desde citas.
 * Regla 4A: NC / void de CPE no reabre ni cambia payment_status de la cita.
 */
class AppointmentDocumentCorrectionService
{
    public const VOID_WINDOW_DAYS = 7;

    public function __construct(private DocumentService $documentService)
    {
    }

    public function options(Appointment $appointment): array
    {
        $appointment->loadMissing(['client', 'boleta.client', 'invoice.client', 'company', 'branch']);

        $resolved = $this->resolveDocument($appointment);
        if (!$resolved) {
            return [
                'has_document' => false,
                'can_void' => false,
                'can_credit_note' => false,
                'void_window_days' => self::VOID_WINDOW_DAYS,
                'payment_status' => $appointment->payment_status,
                'message' => 'La cita no tiene comprobante emitido',
            ];
        }

        /** @var Invoice|Boleta $document */
        $document = $resolved['document'];
        $tipo = $resolved['tipo_documento'];
        $fechaEmision = Carbon::parse($document->fecha_emision)->startOfDay();
        $today = now()->startOfDay();
        $daysSince = (int) $fechaEmision->diffInDays($today);
        $withinWindow = $daysSince <= self::VOID_WINDOW_DAYS;
        $alreadyVoided = $this->isVoidedState((string) $document->estado_sunat);

        $canVoid = $withinWindow && !$alreadyVoided;
        $canCreditNote = !$alreadyVoided;
        $requiresRa = $canVoid && strtoupper((string) $document->estado_sunat) === 'ACEPTADO';

        return [
            'has_document' => true,
            'void_window_days' => self::VOID_WINDOW_DAYS,
            'days_since_emission' => $daysSince,
            'within_void_window' => $withinWindow,
            'can_void' => $canVoid,
            'can_credit_note' => $canCreditNote,
            'void_requires_comunicacion_baja' => $requiresRa,
            'void_is_local_only' => $canVoid && !$requiresRa,
            'payment_status' => $appointment->payment_status,
            'note_4a' => 'Anulación y NC no modifican el estado de cobro de la cita',
            'document' => $this->documentPayload($document, $tipo),
            'credit_note_series' => $this->resolveCreditNoteSerie($appointment, $tipo),
            'motivos_sugeridos' => [
                'total' => ['cod_motivo' => '01', 'des_motivo' => 'Anulación de la operación'],
                'partial' => ['cod_motivo' => '07', 'des_motivo' => 'Devolución por ítem'],
            ],
        ];
    }

    /**
     * Anulación total del CPE (RA si ACEPTADO; baja local si no enviado a SUNAT).
     * No altera payment_status.
     */
    public function voidDocument(Appointment $appointment, array $options = []): array
    {
        $opts = $this->options($appointment);
        if (!$opts['can_void']) {
            throw new Exception(
                $opts['has_document']
                    ? 'Fuera de la ventana de 7 días desde la emisión: use nota de crédito'
                    : 'La cita no tiene comprobante para anular'
            );
        }

        $paymentStatusBefore = $appointment->payment_status;
        $motivo = trim((string) ($options['motivo'] ?? 'Anulación de comprobante'));
        if ($motivo === '') {
            $motivo = 'Anulación de comprobante';
        }
        $sendSunat = (bool) ($options['send_to_sunat'] ?? $opts['void_requires_comunicacion_baja']);

        /** @var Invoice|Boleta $document */
        $document = $this->resolveDocument($appointment)['document'];
        $tipo = $opts['document']['tipo_documento'];
        $fechaReferencia = Carbon::parse($document->fecha_emision)->toDateString();

        return DB::transaction(function () use (
            $appointment,
            $document,
            $tipo,
            $fechaReferencia,
            $motivo,
            $sendSunat,
            $opts,
            $paymentStatusBefore
        ) {
            $voided = null;
            $sunatResult = null;

            if ($opts['void_requires_comunicacion_baja'] || $sendSunat) {
                $voided = $this->documentService->createVoidedDocument([
                    'company_id' => $appointment->company_id,
                    'branch_id' => $document->branch_id ?? $appointment->branch_id,
                    'fecha_referencia' => $fechaReferencia,
                    'motivo_baja' => $motivo,
                    'usuario_creacion' => 'appointment:' . $appointment->id,
                    'detalles' => [[
                        'tipo_documento' => $tipo,
                        'serie' => $document->serie,
                        'correlativo' => $this->normalizeCorrelativo((string) $document->correlativo),
                        'motivo_especifico' => mb_substr($motivo, 0, 250),
                    ]],
                ]);

                if ($sendSunat) {
                    $sunatResult = $this->documentService->sendVoidedDocumentToSunat($voided);
                    $voided = $sunatResult['document'] ?? $voided->fresh();
                }
            }

            $document->update([
                'estado_sunat' => 'ANULADO',
                'respuesta_sunat' => json_encode([
                    'anulacion' => 'appointment_void',
                    'motivo' => $motivo,
                    'voided_document_id' => $voided?->id,
                    'at' => now()->toIso8601String(),
                ]),
            ]);

            // Permite re-emitir CPE; NO toca cobro (payment_status).
            $appointment->update([
                'boleta_id' => null,
                'invoice_id' => null,
            ]);
            $appointment->refresh();

            if ($appointment->payment_status !== $paymentStatusBefore) {
                // Defensa: restaurar si algo externo tocó el cobro.
                $appointment->update(['payment_status' => $paymentStatusBefore]);
            }

            return [
                'action' => 'void',
                'void_mode' => $voided ? 'comunicacion_baja' : 'local',
                'voided_document' => $voided,
                'sunat' => $sunatResult,
                'source_document' => $this->documentPayload($document->fresh(), $tipo),
                'appointment' => $appointment->fresh(['client', 'pet']),
                'payment_status' => $appointment->fresh()->payment_status,
                'payment_status_unchanged' => true,
            ];
        });
    }

    /**
     * Nota de crédito total o parcial. No altera payment_status ni FKs de la cita.
     */
    public function issueCreditNote(Appointment $appointment, array $options = []): array
    {
        $opts = $this->options($appointment);
        if (!$opts['can_credit_note']) {
            throw new Exception(
                $opts['has_document']
                    ? 'El comprobante ya está anulado; no se puede emitir NC'
                    : 'La cita no tiene comprobante para nota de crédito'
            );
        }

        $paymentStatusBefore = $appointment->payment_status;
        $mode = $options['mode'] ?? 'total';
        if (!in_array($mode, ['total', 'partial'], true)) {
            throw new Exception('mode debe ser total o partial');
        }

        $resolved = $this->resolveDocument($appointment);
        /** @var Invoice|Boleta $document */
        $document = $resolved['document'];
        $tipo = $resolved['tipo_documento'];
        $document->loadMissing('client');

        $detalles = $mode === 'total'
            ? $this->normalizeDetallesForCreditNote($document->detalles ?? [])
            : $this->normalizeDetallesForCreditNote($options['detalles'] ?? []);

        if (empty($detalles)) {
            throw new Exception(
                $mode === 'partial'
                    ? 'Para NC parcial debe enviar detalles'
                    : 'El comprobante no tiene líneas para NC'
            );
        }

        $motivos = $opts['motivos_sugeridos'][$mode === 'partial' ? 'partial' : 'total'];
        $codMotivo = $options['cod_motivo'] ?? $motivos['cod_motivo'];
        $desMotivo = $options['des_motivo'] ?? $motivos['des_motivo'];
        $serie = $options['serie'] ?? $opts['credit_note_series'];
        $sendSunat = (bool) ($options['send_to_sunat'] ?? false);

        $client = $document->client ?? $appointment->client;
        if (!$client) {
            throw new Exception('No hay cliente asociado al comprobante');
        }

        $payload = [
            'company_id' => $appointment->company_id,
            'branch_id' => $document->branch_id ?? $appointment->branch_id,
            'serie' => $serie,
            'fecha_emision' => now()->toDateString(),
            'moneda' => $document->moneda ?? 'PEN',
            'tipo_doc_afectado' => $tipo,
            'num_doc_afectado' => $document->numero_completo ?? ($document->serie . '-' . $document->correlativo),
            'cod_motivo' => $codMotivo,
            'des_motivo' => $desMotivo,
            'client' => $this->clientPayload($client),
            'detalles' => $detalles,
            'usuario_creacion' => 'appointment:' . $appointment->id,
            'datos_adicionales' => [
                'appointment_id' => $appointment->id,
                'mode' => $mode,
                'source_document_id' => $document->id,
                'source_document_type' => $tipo,
            ],
        ];

        $creditNote = $this->documentService->createCreditNote($payload);
        $sunatResult = null;

        if ($sendSunat) {
            $sunatResult = $this->documentService->sendToSunat($creditNote, 'credit_note');
            $creditNote->refresh();
        }

        $appointment->refresh();
        if ($appointment->payment_status !== $paymentStatusBefore) {
            $appointment->update(['payment_status' => $paymentStatusBefore]);
        }

        return [
            'action' => 'credit_note',
            'mode' => $mode,
            'credit_note' => $creditNote->load(['client', 'branch']),
            'sunat' => $sunatResult,
            'appointment' => $appointment->fresh(['client', 'pet', 'boleta', 'invoice']),
            'payment_status' => $appointment->fresh()->payment_status,
            'payment_status_unchanged' => true,
        ];
    }

    /**
     * @return array{document: Invoice|Boleta, tipo_documento: string}|null
     */
    public function resolveDocument(Appointment $appointment): ?array
    {
        if ($appointment->invoice_id) {
            $invoice = $appointment->invoice ?: Invoice::find($appointment->invoice_id);
            if ($invoice) {
                return ['document' => $invoice, 'tipo_documento' => '01'];
            }
        }

        if ($appointment->boleta_id) {
            $boleta = $appointment->boleta ?: Boleta::find($appointment->boleta_id);
            if ($boleta) {
                return ['document' => $boleta, 'tipo_documento' => '03'];
            }
        }

        return null;
    }

    private function isVoidedState(string $estado): bool
    {
        return in_array(strtoupper($estado), ['ANULADO', 'BAJA', 'RECHAZADO_BAJA'], true);
    }

    private function documentPayload(Invoice|Boleta $document, string $tipo): array
    {
        return [
            'id' => $document->id,
            'tipo_documento' => $tipo,
            'tipo_nombre' => $tipo === '01' ? 'Factura' : 'Boleta',
            'serie' => $document->serie,
            'correlativo' => $document->correlativo,
            'numero_completo' => $document->numero_completo,
            'fecha_emision' => Carbon::parse($document->fecha_emision)->toDateString(),
            'estado_sunat' => $document->estado_sunat,
            'moneda' => $document->moneda ?? 'PEN',
            'total' => (float) ($document->mto_imp_venta ?? 0),
            'detalles' => $this->normalizeDetallesForCreditNote($document->detalles ?? []),
            'branch_id' => $document->branch_id,
        ];
    }

    private function resolveCreditNoteSerie(Appointment $appointment, string $tipoDocAfectado): string
    {
        $company = $appointment->company;
        $invoiceConfig = $company ? $company->getInvoiceConfig() : [];
        $series = $invoiceConfig['series'] ?? [];

        if (!empty($series['nota_credito'])) {
            return (string) $series['nota_credito'];
        }

        $branch = $appointment->branch;
        if ($branch && !empty($branch->series_nota_credito[0])) {
            return (string) $branch->series_nota_credito[0];
        }

        // Convención: FC** afecta factura, BC** afecta boleta.
        return $tipoDocAfectado === '01' ? 'FC01' : 'BC01';
    }

    private function clientPayload(Client $client): array
    {
        $tipo = (string) ($client->tipo_documento ?? '1');
        if (!in_array($tipo, ['1', '6', '4', '7'], true)) {
            $numero = preg_replace('/\D/', '', (string) $client->numero_documento);
            $tipo = strlen($numero) === 11 ? '6' : '1';
        }

        return [
            'tipo_documento' => $tipo,
            'numero_documento' => $client->numero_documento,
            'razon_social' => $client->razon_social ?: $client->nombre_comercial ?: 'Cliente',
            'nombre_comercial' => $client->nombre_comercial,
            'direccion' => $client->direccion,
            'ubigeo' => $client->ubigeo,
            'distrito' => $client->distrito,
            'provincia' => $client->provincia,
            'departamento' => $client->departamento,
            'telefono' => $client->telefono,
            'email' => $client->email,
        ];
    }

    private function normalizeDetallesForCreditNote(array $detalles): array
    {
        $lines = [];
        foreach ($detalles as $detalle) {
            if (!is_array($detalle)) {
                continue;
            }
            $valorUnit = (float) (
                $detalle['mto_valor_unitario']
                ?? $detalle['valor_unitario']
                ?? 0
            );
            $cantidad = (float) ($detalle['cantidad'] ?? 1);
            if ($valorUnit <= 0 || $cantidad <= 0) {
                continue;
            }

            $lines[] = [
                'codigo' => substr((string) ($detalle['codigo'] ?? 'NC'), 0, 30),
                'descripcion' => substr((string) ($detalle['descripcion'] ?? 'Ítem'), 0, 255),
                'unidad' => $detalle['unidad'] ?? 'NIU',
                'cantidad' => $cantidad,
                'mto_valor_unitario' => $valorUnit,
                'porcentaje_igv' => (float) ($detalle['porcentaje_igv'] ?? 18),
                'tip_afe_igv' => (string) ($detalle['tip_afe_igv'] ?? '10'),
                'product_id' => $detalle['product_id'] ?? null,
            ];
        }

        return $lines;
    }

    private function normalizeCorrelativo(string $correlativo): string
    {
        $digits = preg_replace('/\D/', '', $correlativo) ?: $correlativo;

        return str_pad(substr($digits, -8), 8, '0', STR_PAD_LEFT);
    }
}
