<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Boleta;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Invoice;
use Exception;

class AppointmentBillingService
{
    public function __construct(private DocumentService $documentService)
    {
    }

    public function preview(Appointment $appointment): array
    {
        $appointment->load(['client', 'pet', 'items.product', 'branch', 'vehicle']);

        $tipo = $this->resolveDocumentType($appointment->client);
        $payload = $this->buildDocumentPayload($appointment, $tipo);

        return [
            'appointment_id' => $appointment->id,
            'tipo_documento' => $tipo,
            'tipo_nombre' => $tipo === '01' ? 'Factura' : 'Boleta',
            'serie' => $payload['serie'],
            'client' => $payload['client'],
            'detalles' => $payload['detalles'],
            'total' => (float) $appointment->total,
            'already_issued' => $appointment->boleta_id || $appointment->invoice_id,
            'numero_existente' => $this->existingDocumentNumber($appointment),
        ];
    }

    public function issue(Appointment $appointment, array $options = []): array
    {
        if ($appointment->boleta_id || $appointment->invoice_id) {
            throw new Exception('La cita ya tiene un comprobante emitido');
        }

        if (!in_array($appointment->status, ['Completada', 'En Proceso', 'Confirmada'], true)) {
            throw new Exception('Solo se puede facturar citas confirmadas, en proceso o completadas');
        }

        $appointment->load(['client', 'pet', 'items.product', 'branch']);

        $tipo = $options['tipo'] ?? 'auto';
        if ($tipo === 'auto') {
            $tipo = $this->resolveDocumentType($appointment->client);
        }
        if (!in_array($tipo, ['01', '03'], true)) {
            throw new Exception('Tipo de comprobante no válido');
        }

        $payload = $this->buildDocumentPayload($appointment, $tipo, $options['serie'] ?? null);
        $sendSunat = (bool) ($options['send_to_sunat'] ?? false);

        if ($tipo === '01') {
            $payload['tipo_operacion'] = '0101';
            $payload['forma_pago_tipo'] = $payload['forma_pago_tipo'] ?? 'Contado';
            $invoice = $this->documentService->createInvoice($payload);
            $invoice->update(['appointment_id' => $appointment->id]);

            if ($sendSunat) {
                $this->documentService->sendToSunat($invoice, 'invoice');
                $invoice->refresh();
            }

            $appointment->update([
                'invoice_id' => $invoice->id,
                'payment_status' => 'Pagado',
            ]);

            return [
                'tipo_documento' => '01',
                'document' => $invoice->load(['client', 'branch']),
                'numero_completo' => $invoice->numero_completo,
            ];
        }

        $boleta = $this->documentService->createBoleta($payload);
        $boleta->update(['appointment_id' => $appointment->id]);

        if ($sendSunat) {
            $this->documentService->sendToSunat($boleta, 'boleta');
            $boleta->refresh();
        }

        $appointment->update([
            'boleta_id' => $boleta->id,
            'payment_status' => 'Pagado',
        ]);

        return [
            'tipo_documento' => '03',
            'document' => $boleta->load(['client', 'branch']),
            'numero_completo' => $boleta->numero_completo,
        ];
    }

    private function resolveDocumentType(?Client $client): string
    {
        if (!$client) {
            return '03';
        }

        $tipo = (string) ($client->tipo_documento ?? '1');
        $numero = preg_replace('/\D/', '', (string) $client->numero_documento);

        if ($tipo === '6' || strlen($numero) === 11) {
            return '01';
        }

        return '03';
    }

    private function buildDocumentPayload(Appointment $appointment, string $tipo, ?string $serieOverride = null): array
    {
        $client = $appointment->client;
        if (!$client) {
            throw new Exception('La cita no tiene cliente asociado');
        }

        $branchId = $appointment->branch_id
            ?? Branch::where('company_id', $appointment->company_id)->where('activo', true)->value('id');

        if (!$branchId) {
            throw new Exception('No hay sucursal configurada para emitir el comprobante');
        }

        $company = $appointment->company ?? $client->company;
        $invoiceConfig = $company ? $company->getInvoiceConfig() : [];
        $series = $invoiceConfig['series'] ?? [];

        $serie = $serieOverride
            ?: ($tipo === '01'
                ? ($series['factura'] ?? 'F001')
                : ($series['boleta'] ?? 'B001'));

        $petLabel = $appointment->pet?->name ? " — Mascota: {$appointment->pet->name}" : '';
        $detalles = $this->buildDetalles($appointment, $petLabel);

        $paymentMethod = $appointment->payment_method ?? 'Efectivo';
        $formaPago = str_contains(strtolower($paymentMethod), 'credito') ? 'Credito' : 'Contado';

        return [
            'company_id' => $appointment->company_id,
            'branch_id' => $branchId,
            'serie' => strtoupper($serie),
            'fecha_emision' => ($appointment->date ?? now())->format('Y-m-d'),
            'moneda' => 'PEN',
            'metodo_envio' => 'individual',
            'forma_pago_tipo' => $formaPago,
            'client' => [
                'tipo_documento' => $this->normalizeTipoDoc($client),
                'numero_documento' => $client->numero_documento,
                'razon_social' => $client->razon_social ?: $client->nombre_comercial ?: 'Cliente',
                'nombre_comercial' => $client->nombre_comercial,
                'direccion' => $client->direccion ?: $appointment->address,
                'ubigeo' => $client->ubigeo,
                'distrito' => $client->distrito ?: $appointment->district,
                'provincia' => $client->provincia ?: $appointment->province,
                'departamento' => $client->departamento ?: $appointment->department,
                'telefono' => $client->telefono,
                'email' => $client->email,
            ],
            'detalles' => $detalles,
            'datos_adicionales' => [
                'appointment_id' => $appointment->id,
                'tracking_code' => $appointment->tracking_code,
                'vehicle_id' => $appointment->vehicle_id,
            ],
            'usuario_creacion' => 'appointment:' . $appointment->id,
        ];
    }

    private function buildDetalles(Appointment $appointment, string $petSuffix): array
    {
        $lines = [];

        if ($appointment->items->isNotEmpty()) {
            foreach ($appointment->items as $item) {
                $subtotal = (float) ($item->subtotal ?? ($item->price * $item->quantity));
                $lines[] = $this->taxLine(
                    'SRV' . $item->id,
                    ($item->name ?: 'Servicio') . $petSuffix,
                    $subtotal,
                    (float) max(1, $item->quantity),
                    $item->product_id
                );
            }

            return $lines;
        }

        $total = (float) ($appointment->total ?: $appointment->price);
        $desc = ($appointment->service_name ?: $appointment->service_type ?: 'Servicio veterinario móvil') . $petSuffix;

        return [$this->taxLine('CITA' . $appointment->id, $desc, $total, 1.0, $appointment->service_id)];
    }

    private function taxLine(string $codigo, string $descripcion, float $totalConIgv, float $cantidad, $productId = null): array
    {
        $cantidad = max(0.01, $cantidad);
        $valorUnit = round(($totalConIgv / 1.18) / $cantidad, 2);

        return [
            'codigo' => substr($codigo, 0, 30),
            'descripcion' => substr($descripcion, 0, 255),
            'unidad' => 'NIU',
            'cantidad' => $cantidad,
            'mto_valor_unitario' => $valorUnit,
            'porcentaje_igv' => 18,
            'tip_afe_igv' => '10',
            'product_id' => $productId,
        ];
    }

    private function normalizeTipoDoc(Client $client): string
    {
        $tipo = (string) ($client->tipo_documento ?? '1');
        if (in_array($tipo, ['1', '6'], true)) {
            return $tipo;
        }
        $numero = preg_replace('/\D/', '', (string) $client->numero_documento);

        return strlen($numero) === 11 ? '6' : '1';
    }

    private function existingDocumentNumber(Appointment $appointment): ?string
    {
        if ($appointment->boleta_id) {
            return Boleta::find($appointment->boleta_id)?->numero_completo;
        }
        if ($appointment->invoice_id) {
            return Invoice::find($appointment->invoice_id)?->numero_completo;
        }

        return null;
    }
}
