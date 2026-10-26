<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resuelve la empresa del portal público (booking / chat) sin romper el fallback .env.
 */
class PublicCompanyResolver
{
    public function resolve(Request $request, ?string $companySlug = null): Company
    {
        $raw = $companySlug
            ?? $request->header('X-Booking-Slug')
            ?? $request->query('company')
            ?? $request->query('slug')
            ?? config('smartpet.public_booking_slug');

        $slug = is_string($raw) ? Str::slug(trim($raw)) : '';

        if ($slug !== '') {
            $company = Company::query()
                ->where('activo', true)
                ->where('booking_slug', $slug)
                ->first();

            if (! $company) {
                throw new NotFoundHttpException('Empresa de reservas no encontrada.');
            }

            return $company;
        }

        $fallbackId = (int) config('smartpet.public_company_id', 1);
        $company = Company::query()
            ->where('activo', true)
            ->whereKey($fallbackId)
            ->first();

        if (! $company) {
            throw new NotFoundHttpException('Empresa de reservas no configurada.');
        }

        return $company;
    }

    public function id(Request $request, ?string $companySlug = null): int
    {
        return (int) $this->resolve($request, $companySlug)->id;
    }
}
