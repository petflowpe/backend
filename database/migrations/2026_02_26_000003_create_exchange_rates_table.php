<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('exchange_rates'); // por si falló antes y la tabla quedó sin índice
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 18, 6);
            $table->date('effective_at');
            $table->timestamps();
            $table->index(['company_id', 'from_currency', 'to_currency', 'effective_at'], 'ex_rates_company_currencies_effective');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
