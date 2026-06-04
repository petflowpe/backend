<?php

return [
    /**
     * Empresa por defecto para reservas públicas (portal invitado).
     * Configurar en .env: SMARTPET_PUBLIC_COMPANY_ID=1
     */
    'public_company_id' => (int) env('SMARTPET_PUBLIC_COMPANY_ID', 1),
];
