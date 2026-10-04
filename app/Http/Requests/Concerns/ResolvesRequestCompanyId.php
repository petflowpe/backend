<?php

namespace App\Http\Requests\Concerns;

/**
 * Resuelve company_id efectivo para validaciones multi-tenant:
 * body/query → scope del middleware → company_id del usuario.
 */
trait ResolvesRequestCompanyId
{
    protected function requestCompanyId(): ?int
    {
        $fromInput = $this->input('company_id');
        if ($fromInput !== null && $fromInput !== '' && (int) $fromInput > 0) {
            return (int) $fromInput;
        }

        $scoped = $this->attributes->get('scope_company_id');
        if ($scoped !== null && $scoped !== '' && (int) $scoped > 0) {
            return (int) $scoped;
        }

        $userCompany = $this->user()?->company_id ?? null;
        if ($userCompany !== null && (int) $userCompany > 0) {
            return (int) $userCompany;
        }

        return null;
    }
}
