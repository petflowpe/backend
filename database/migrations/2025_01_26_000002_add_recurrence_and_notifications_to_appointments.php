<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            // Campos de recurrencia
            if (!Schema::hasColumn('appointments', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('cancelled_at');
            }
            if (!Schema::hasColumn('appointments', 'recurrence_series_id')) {
                $table->string('recurrence_series_id', 100)->nullable()->after('is_recurring');
            }
            if (!Schema::hasColumn('appointments', 'recurrence_type')) {
                $table->enum('recurrence_type', ['daily', 'weekly', 'monthly'])->nullable()->after('recurrence_series_id');
            }
            if (!Schema::hasColumn('appointments', 'recurrence_occurrences')) {
                $table->integer('recurrence_occurrences')->nullable()->after('recurrence_type');
            }
            if (!Schema::hasColumn('appointments', 'recurrence_days')) {
                $table->json('recurrence_days')->nullable()->after('recurrence_occurrences'); // ['monday', 'wednesday', 'friday']
            }
            if (!Schema::hasColumn('appointments', 'recurrence_fixed_time')) {
                $table->boolean('recurrence_fixed_time')->default(true)->after('recurrence_days');
            }
            if (!Schema::hasColumn('appointments', 'parent_appointment_id')) {
                $table->foreignId('parent_appointment_id')->nullable()->constrained('appointments')->onDelete('cascade')->after('recurrence_fixed_time');
            }

            // Campos de notificaciones
            if (!Schema::hasColumn('appointments', 'reminder_sent')) {
                $table->boolean('reminder_sent')->default(false)->after('parent_appointment_id');
            }
            if (!Schema::hasColumn('appointments', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('reminder_sent');
            }
            if (!Schema::hasColumn('appointments', 'confirmation_sent')) {
                $table->boolean('confirmation_sent')->default(false)->after('reminder_sent_at');
            }
            if (!Schema::hasColumn('appointments', 'confirmation_sent_at')) {
                $table->timestamp('confirmation_sent_at')->nullable()->after('confirmation_sent');
            }

            // Índices
            $table->index('recurrence_series_id');
            $table->index('parent_appointment_id');
            $table->index(['is_recurring', 'date']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropColumn([
                    'is_recurring',
                    'recurrence_series_id',
                    'recurrence_type',
                    'recurrence_occurrences',
                    'recurrence_days',
                    'recurrence_fixed_time',
                    'parent_appointment_id',
                    'reminder_sent',
                    'reminder_sent_at',
                    'confirmation_sent',
                    'confirmation_sent_at',
                ]);
            });
        }
    }
};
