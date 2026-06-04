<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'appointment_id')) {
                    $table->foreignId('appointment_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
                }
                if (!Schema::hasColumn('payments', 'gateway')) {
                    $table->string('gateway', 30)->default('manual')->after('method');
                }
                if (!Schema::hasColumn('payments', 'status')) {
                    $table->string('status', 20)->default('completed')->after('gateway');
                }
                if (!Schema::hasColumn('payments', 'external_id')) {
                    $table->string('external_id', 120)->nullable()->after('reference');
                }
                if (!Schema::hasColumn('payments', 'fee')) {
                    $table->decimal('fee', 12, 2)->default(0)->after('amount');
                }
                if (!Schema::hasColumn('payments', 'net_amount')) {
                    $table->decimal('net_amount', 12, 2)->nullable()->after('fee');
                }
                if (!Schema::hasColumn('payments', 'metadata')) {
                    $table->json('metadata')->nullable()->after('notes');
                }
            });

            return;
        }

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();

            $table->decimal('amount', 12, 2);
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('PEN');

            $table->string('method', 30);
            $table->string('gateway', 30)->default('manual');
            $table->string('status', 20)->default('completed');
            $table->string('reference', 120)->nullable();
            $table->string('external_id', 120)->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['gateway', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
