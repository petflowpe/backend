<?php

return [
    /**
     * Empresa por defecto para reservas públicas (portal invitado).
     * Preferir booking_slug por empresa; este ID es fallback legacy.
     * Configurar en .env: SMARTPET_PUBLIC_COMPANY_ID=1
     */
    'public_company_id' => (int) env('SMARTPET_PUBLIC_COMPANY_ID', 1),

    /**
     * Slug por defecto si el front no envía empresa (opcional).
     * SMARTPET_PUBLIC_BOOKING_SLUG=mi-clinica
     */
    'public_booking_slug' => env('SMARTPET_PUBLIC_BOOKING_SLUG'),
];
