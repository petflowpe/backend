<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dispatch_guides')) {
            return;
        }

        if (Schema::hasColumn('dispatch_guides', 'codigo_hash')) {
            return;
        }

        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->string('codigo_hash')->nullable()->after('ticket');
            $table->index('codigo_hash');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('dispatch_guides')) {
            return;
        }

        if (!Schema::hasColumn('dispatch_guides', 'codigo_hash')) {
            return;
        }

        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->dropIndex(['codigo_hash']);
            $table->dropColumn('codigo_hash');
        });
    }
};

