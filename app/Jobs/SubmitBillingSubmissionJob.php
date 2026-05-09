<?php

namespace App\Jobs;

use App\Billing\BillingProviderResolver;
use App\Models\BillingDocument;
use App\Models\BillingSubmission;
use App\Models\CompanyTaxProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SubmitBillingSubmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $submissionId)
    {
    }

    public function handle(BillingProviderResolver $resolver): void
    {
        $submission = BillingSubmission::find($this->submissionId);
        if (!$submission) {
            return;
        }

        $document = BillingDocument::with(['company'])->find($submission->billing_document_id);
        if (!$document) {
            $submission->update([
                'status' => 'error',
                'error_code' => 'DOC_NOT_FOUND',
                'error_message' => 'Documento no encontrado',
            ]);
            return;
        }

        $profile = CompanyTaxProfile::where('company_id', $document->company_id)
            ->where('country_code', 'CO')
            ->where('active', true)
            ->first();

        if (!$profile) {
            $submission->update([
                'status' => 'error',
                'error_code' => 'TAX_PROFILE_MISSING',
                'error_message' => 'Perfil fiscal CO no configurado',
            ]);
            $document->update(['status_fiscal' => 'error']);
            return;
        }

        try {
            $provider = $resolver->resolve($profile);
            $result = $provider->submit($document, $profile, $submission->idempotency_key);

            $status = strtolower((string) ($result['status'] ?? 'sent'));
            $normalized = in_array($status, ['accepted', 'rejected', 'sent'], true) ? $status : 'sent';

            $submission->update([
                'status' => $normalized,
                'external_id' => $result['externalId'] ?? $submission->external_id,
                'response_payload' => $result,
                'accepted_at' => $normalized === 'accepted' ? now() : null,
                'last_checked_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

            $document->update([
                'status_fiscal' => $normalized,
                'status' => $document->status === 'draft' ? 'issued' : $document->status,
            ]);
        } catch (\Throwable $e) {
            Log::error('billing submit job error', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);

            $submission->update([
                'status' => 'error',
                'error_code' => 'PROVIDER_ERROR',
                'error_message' => $e->getMessage(),
                'last_checked_at' => now(),
            ]);
            $document->update(['status_fiscal' => 'error']);
        }
    }
}

