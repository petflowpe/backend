<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'critical_note')) {
                $table->text('critical_note')->nullable()->after('notas');
            }
            if (!Schema::hasColumn('clients', 'critical_pet_note')) {
                $table->text('critical_pet_note')->nullable()->after('critical_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'critical_pet_note')) {
                $table->dropColumn('critical_pet_note');
            }
            if (Schema::hasColumn('clients', 'critical_note')) {
                $table->dropColumn('critical_note');
            }
        });
    }
};
