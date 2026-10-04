<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Helpers de validación exists acotados a company_id.
 */
final class CompanyExists
{
    public static function in(string $table, ?int $companyId, string $column = 'id'): Exists
    {
        $rule = Rule::exists($table, $column);

        if ($companyId !== null && $companyId > 0) {
            return $rule->where('company_id', $companyId);
        }

        // Sin empresa no se permite referenciar FKs de otra compañía.
        return $rule->where(fn ($q) => $q->whereRaw('0 = 1'));
    }
}
