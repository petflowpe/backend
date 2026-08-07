<?php

use App\Models\Appointment;
use App\Models\Boleta;
use App\Services\AppointmentDocumentCorrectionService;
use App\Services\DocumentService;
use Carbon\Carbon;
use Mockery;

uses(Tests\TestCase::class);

it('permite anular dentro de 7 días desde emisión y no después', function () {
    $documentService = Mockery::mock(DocumentService::class);
    $service = new AppointmentDocumentCorrectionService($documentService);

    $appointment = new Appointment([
        'payment_status' => 'Pagado',
        'boleta_id' => 1,
        'invoice_id' => null,
    ]);
    $appointment->id = 1;

    $boleta = new Boleta([
        'serie' => 'B001',
        'correlativo' => '000001',
        'numero_completo' => 'B001-000001',
        'fecha_emision' => Carbon::today()->subDays(3)->toDateString(),
        'estado_sunat' => 'PENDIENTE',
        'mto_imp_venta' => 118,
        'detalles' => [[
            'codigo' => 'X',
            'descripcion' => 'Servicio',
            'unidad' => 'NIU',
            'cantidad' => 1,
            'mto_valor_unitario' => 100,
            'tip_afe_igv' => '10',
            'porcentaje_igv' => 18,
        ]],
        'branch_id' => 1,
        'moneda' => 'PEN',
    ]);
    $boleta->id = 1;

    $appointment->setRelation('boleta', $boleta);
    $appointment->setRelation('invoice', null);
    $appointment->setRelation('client', null);
    $appointment->setRelation('company', null);
    $appointment->setRelation('branch', null);

    $opts = $service->options($appointment);
    expect($opts['can_void'])->toBeTrue()
        ->and($opts['can_credit_note'])->toBeTrue()
        ->and($opts['within_void_window'])->toBeTrue();

    $boleta->fecha_emision = Carbon::today()->subDays(8)->toDateString();
    $appointment->setRelation('boleta', $boleta);
    $optsOld = $service->options($appointment);

    expect($optsOld['can_void'])->toBeFalse()
        ->and($optsOld['can_credit_note'])->toBeTrue()
        ->and($optsOld['within_void_window'])->toBeFalse();
});
