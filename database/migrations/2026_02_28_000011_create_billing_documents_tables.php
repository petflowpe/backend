<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('billing_documents')) {
            Schema::create('billing_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

                $table->string('document_type', 30)->default('invoice'); // internal: invoice, credit_note, etc.
                $table->dateTime('issue_datetime');
                $table->string('currency_code', 3)->default('COP');

                $table->string('number_prefix', 10)->nullable();
                $table->string('number', 20)->nullable();

                $table->json('totals'); // {subtotal, taxesTotal, total}
                $table->json('tax_breakdown')->nullable();
                $table->json('payload_snapshot')->nullable();

                $table->string('status', 20)->default('draft'); // draft|issued|voided
                $table->string('status_fiscal', 20)->default('not_sent'); // not_sent|queued|sent|accepted|rejected|error

                $table->timestamps();

                $table->index(['company_id', 'issue_datetime']);
                $table->index(['company_id', 'status_fiscal']);
                $table->index(['company_id', 'client_id']);
            });
        }

        if (!Schema::hasTable('billing_document_lines')) {
            Schema::create('billing_document_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('billing_document_id')->constrained('billing_documents')->cascadeOnDelete();
                $table->string('item_type', 20); // product|service
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('description', 255);
                $table->decimal('qty', 12, 3)->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('discount', 12, 2)->default(0);
                $table->json('taxes')->nullable();
                $table->decimal('line_total', 12, 2)->default(0);
                $table->timestamps();

                $table->index(['billing_document_id']);
            });
        }

        if (!Schema::hasTable('billing_submissions')) {
            Schema::create('billing_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('billing_document_id')->constrained('billing_documents')->cascadeOnDelete();
                $table->string('provider_slug', 50);
                $table->string('idempotency_key', 80)->unique();

                $table->json('request_payload');
                $table->json('response_payload')->nullable();

                $table->string('external_id', 100)->nullable();
                $table->string('status', 20)->default('queued'); // queued|sent|accepted|rejected|error
                $table->dateTime('accepted_at')->nullable();
                $table->dateTime('last_checked_at')->nullable();
                $table->string('error_code', 50)->nullable();
                $table->text('error_message')->nullable();

                $table->timestamps();

                $table->index(['billing_document_id', 'status']);
                $table->index(['provider_slug', 'external_id']);
            });
        }

        if (!Schema::hasTable('billing_artifacts')) {
            Schema::create('billing_artifacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('billing_document_id')->constrained('billing_documents')->cascadeOnDelete();
                $table->foreignId('billing_submission_id')->nullable()->constrained('billing_submissions')->nullOnDelete();
                $table->string('type', 20); // xml|pdf|zip|other
                $table->string('path', 500);
                $table->string('hash', 100)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['billing_document_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_artifacts');
        Schema::dropIfExists('billing_submissions');
        Schema::dropIfExists('billing_document_lines');
        Schema::dropIfExists('billing_documents');
    }
};

