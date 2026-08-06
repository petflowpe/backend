<?php

use App\Models\Payment;
use App\Services\AppointmentPaymentStatusService;
use App\Services\PaymentLedgerSyncService;

it('syncAppointmentStatus no llama sync sin appointment_id', function () {
    $statusService = Mockery::mock(AppointmentPaymentStatusService::class);
    $statusService->shouldNotReceive('sync');

    $svc = new PaymentLedgerSyncService($statusService);

    $payment = new Payment([
        'status' => 'completed',
        'method' => 'card',
        'appointment_id' => null,
    ]);
    $payment->id = 1;

    $svc->syncAppointmentStatus($payment);
});

it('no crea movimiento si el pago no está completed', function () {
    $statusService = Mockery::mock(AppointmentPaymentStatusService::class);
    $svc = new PaymentLedgerSyncService($statusService);

    $payment = new Payment([
        'status' => 'pending',
        'company_id' => 1,
        'amount' => 10,
    ]);
    $payment->id = 99;

    expect($svc->ensureIncomeCashMovement($payment))->toBeNull();
});
