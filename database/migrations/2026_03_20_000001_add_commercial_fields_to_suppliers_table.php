<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('supplier_type', 50)->nullable()->after('document_number');
            $table->string('contact_name', 255)->nullable()->after('phone');
            $table->string('bank_name', 100)->nullable()->after('contact_name');
            $table->string('bank_account', 100)->nullable()->after('bank_name');
            $table->string('billing_email', 255)->nullable()->after('bank_account');
            $table->unsignedSmallInteger('credit_days')->default(0)->after('billing_email');
            $table->string('accounting_account_code', 20)->nullable()->after('credit_days');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_type',
                'contact_name',
                'bank_name',
                'bank_account',
                'billing_email',
                'credit_days',
                'accounting_account_code',
            ]);
        });
    }
};
