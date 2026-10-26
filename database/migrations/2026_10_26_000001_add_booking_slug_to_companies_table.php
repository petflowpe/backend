<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('booking_slug', 64)->nullable()->after('nombre_comercial');
        });

        $companies = DB::table('companies')->orderBy('id')->get(['id', 'nombre_comercial', 'razon_social']);
        $used = [];

        foreach ($companies as $company) {
            $base = Str::slug((string) ($company->nombre_comercial ?: $company->razon_social));
            if ($base === '') {
                $base = 'empresa-' . $company->id;
            }
            $slug = $base;
            $n = 2;
            while (isset($used[$slug])) {
                $slug = $base . '-' . $n;
                $n++;
            }
            $used[$slug] = true;
            DB::table('companies')->where('id', $company->id)->update(['booking_slug' => $slug]);
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->unique('booking_slug');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['booking_slug']);
            $table->dropColumn('booking_slug');
        });
    }
};
