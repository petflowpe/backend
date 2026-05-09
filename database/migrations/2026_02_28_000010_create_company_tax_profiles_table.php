<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_tax_profiles')) {
            return;
        }

        Schema::create('company_tax_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->char('country_code', 2); // ISO-3166-1 alpha-2 (CO, MX, AR, etc.)
            $table->string('tax_id', 30); // NIT / RFC / RUT / etc.
            $table->string('tax_id_dv', 5)->nullable(); // dígito verificador (CO) u otros

            $table->string('legal_name', 255);
            $table->string('trade_name', 255)->nullable();
            $table->string('email', 255)->nullable();

            $table->string('address_line', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('postal_code', 20)->nullable();

            $table->string('currency_code_default', 3)->default('COP');
            $table->string('locale_default', 10)->default('es-CO');

            $table->string('environment', 10)->default('test'); // test|prod
            $table->string('provider_slug', 50)->default('dian_stub');

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'country_code', 'active']);
            $table->unique(['company_id', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_tax_profiles');
    }
};

