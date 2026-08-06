<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega tipo y número de documento a `users`.
     * Permite iniciar sesión también con `document_number + password`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'document_type')) {
                $table->string('document_type', 10)->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'document_number')) {
                $table->string('document_number', 30)->nullable()->after('document_type');
                // No es estrictamente único a nivel global (puede haber el mismo número en distintas empresas),
                // pero sí lo será dentro de la combinación (company_id, document_type, document_number).
                $table->index(['document_type', 'document_number'], 'users_document_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'document_number')) {
                try { $table->dropIndex('users_document_idx'); } catch (\Throwable) {}
                $table->dropColumn('document_number');
            }
            if (Schema::hasColumn('users', 'document_type')) {
                $table->dropColumn('document_type');
            }
        });
    }
};
