<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                if (!Schema::hasColumn('clients', 'portal_booking_enabled')) {
                    $table->boolean('portal_booking_enabled')->default(false)->after('activo');
                }
                if (!Schema::hasColumn('clients', 'portal_approval_status')) {
                    $table->string('portal_approval_status', 20)->default('approved')->after('portal_booking_enabled');
                }
                if (!Schema::hasColumn('clients', 'portal_registered_at')) {
                    $table->timestamp('portal_registered_at')->nullable()->after('portal_approval_status');
                }
            });
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (!Schema::hasColumn('appointments', 'booking_source')) {
                    $table->string('booking_source', 30)->default('staff')->after('tracking_code');
                }
                if (!Schema::hasColumn('appointments', 'advance_amount')) {
                    $table->decimal('advance_amount', 10, 2)->nullable()->after('payment_method');
                }
                if (!Schema::hasColumn('appointments', 'advance_paid_at')) {
                    $table->timestamp('advance_paid_at')->nullable()->after('advance_amount');
                }
                if (!Schema::hasColumn('appointments', 'advance_payment_method')) {
                    $table->string('advance_payment_method', 50)->nullable()->after('advance_paid_at');
                }
                if (!Schema::hasColumn('appointments', 'advance_payment_reference')) {
                    $table->string('advance_payment_reference', 100)->nullable()->after('advance_payment_method');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                foreach (['portal_registered_at', 'portal_approval_status', 'portal_booking_enabled'] as $col) {
                    if (Schema::hasColumn('clients', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                foreach ([
                    'advance_payment_reference',
                    'advance_payment_method',
                    'advance_paid_at',
                    'advance_amount',
                    'booking_source',
                ] as $col) {
                    if (Schema::hasColumn('appointments', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
