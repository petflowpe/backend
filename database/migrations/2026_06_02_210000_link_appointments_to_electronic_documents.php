<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('boletas') && !Schema::hasColumn('boletas', 'appointment_id')) {
            Schema::table('boletas', function (Blueprint $table) {
                $table->foreignId('appointment_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'appointment_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('appointment_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (!Schema::hasColumn('appointments', 'boleta_id')) {
                    $table->foreignId('boleta_id')->nullable()->after('payment_method')->constrained()->nullOnDelete();
                }
                if (!Schema::hasColumn('appointments', 'invoice_id')) {
                    $table->foreignId('invoice_id')->nullable()->after('boleta_id')->constrained()->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (Schema::hasColumn('appointments', 'invoice_id')) {
                    $table->dropConstrainedForeignId('invoice_id');
                }
                if (Schema::hasColumn('appointments', 'boleta_id')) {
                    $table->dropConstrainedForeignId('boleta_id');
                }
            });
        }
        if (Schema::hasTable('boletas') && Schema::hasColumn('boletas', 'appointment_id')) {
            Schema::table('boletas', function (Blueprint $table) {
                $table->dropConstrainedForeignId('appointment_id');
            });
        }
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'appointment_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('appointment_id');
            });
        }
    }
};
