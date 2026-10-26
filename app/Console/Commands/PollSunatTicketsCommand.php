<?php

namespace App\Console\Commands;

use App\Models\DailySummary;
use App\Models\VoidedDocument;
use App\Services\DocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollSunatTicketsCommand extends Command
{
    protected $signature = 'sunat:poll-tickets
                            {--company= : Solo una empresa}
                            {--limit=50 : Máximo de documentos por tipo}';

    protected $description = 'Consulta en SUNAT tickets pendientes de resúmenes diarios y comunicaciones de baja';

    public function handle(DocumentService $documents): int
    {
        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $limit = max(1, (int) $this->option('limit'));

        $summariesOk = 0;
        $summariesPending = 0;
        $summariesFail = 0;
        $voidedOk = 0;
        $voidedPending = 0;
        $voidedFail = 0;

        $summaryQuery = DailySummary::query()
            ->whereNotNull('ticket')
            ->where('ticket', '!=', '')
            ->whereIn('estado_sunat', ['PROCESANDO', 'ENVIADO'])
            ->orderBy('id');

        if ($companyId) {
            $summaryQuery->where('company_id', $companyId);
        }

        foreach ($summaryQuery->limit($limit)->get() as $summary) {
            try {
                $result = $documents->checkSummaryStatus($summary);
                if (! empty($result['pending'])) {
                    $summariesPending++;
                } elseif (! empty($result['success'])) {
                    $summariesOk++;
                    $this->line("Resumen #{$summary->id}: ACEPTADO");
                } else {
                    $summariesFail++;
                    $this->warn("Resumen #{$summary->id}: " . $this->errorMessage($result));
                }
            } catch (\Throwable $e) {
                $summariesFail++;
                Log::error('sunat:poll-tickets resumen', [
                    'summary_id' => $summary->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Resumen #{$summary->id}: {$e->getMessage()}");
            }
        }

        $voidedQuery = VoidedDocument::query()
            ->whereNotNull('ticket')
            ->where('ticket', '!=', '')
            ->whereIn('estado_sunat', ['PROCESANDO', 'ENVIADO'])
            ->orderBy('id');

        if ($companyId) {
            $voidedQuery->where('company_id', $companyId);
        }

        foreach ($voidedQuery->limit($limit)->get() as $voided) {
            try {
                $result = $documents->checkVoidedDocumentStatus($voided);
                if (! empty($result['pending'])) {
                    $voidedPending++;
                } elseif (! empty($result['success'])) {
                    $voidedOk++;
                    $estado = $voided->fresh()?->estado_sunat ?? '?';
                    $this->line("Baja #{$voided->id}: {$estado}");
                } else {
                    $voidedFail++;
                    $this->warn("Baja #{$voided->id}: " . $this->errorMessage($result));
                }
            } catch (\Throwable $e) {
                $voidedFail++;
                Log::error('sunat:poll-tickets baja', [
                    'voided_document_id' => $voided->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Baja #{$voided->id}: {$e->getMessage()}");
            }
        }

        $this->info(sprintf(
            'Resúmenes: ok=%d pending=%d fail=%d | Bajas: ok=%d pending=%d fail=%d',
            $summariesOk,
            $summariesPending,
            $summariesFail,
            $voidedOk,
            $voidedPending,
            $voidedFail
        ));

        return ($summariesFail + $voidedFail) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function errorMessage(array $result): string
    {
        $error = $result['error'] ?? null;
        if (is_object($error)) {
            return (string) ($error->message ?? $error->getMessage ?? json_encode($error));
        }
        if (is_array($error)) {
            return (string) ($error['message'] ?? json_encode($error));
        }
        if (is_string($error) && $error !== '') {
            return $error;
        }

        return 'error desconocido';
    }
}
