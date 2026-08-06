<?php

use App\Models\Appointment;
use App\Services\AppointmentPaymentStatusService;

it('resuelve Pendiente cuando no hay cobros', function () {
    $appointment = new Appointment([
        'total' => 100,
        'payment_status' => 'Pendiente',
        'advance_amount' => null,
        'advance_paid_at' => null,
    ]);
    $appointment->id = 900001;

    $service = new AppointmentPaymentStatusService();

    expect($service->resolveStatus($appointment, 0.0))->toBe('Pendiente');
});

it('resuelve Parcial cuando el cobro es menor al total', function () {
    $appointment = new Appointment([
        'total' => 100,
        'payment_status' => 'Pendiente',
    ]);
    $appointment->id = 900002;

    $service = new AppointmentPaymentStatusService();

    expect($service->resolveStatus($appointment, 40.0))->toBe('Parcial');
});

it('resuelve Pagado solo al 100% o más', function () {
    $appointment = new Appointment([
        'total' => 100,
        'payment_status' => 'Pendiente',
    ]);
    $appointment->id = 900003;

    $service = new AppointmentPaymentStatusService();

    expect($service->resolveStatus($appointment, 100.0))->toBe('Pagado');
    expect($service->resolveStatus($appointment, 100.01))->toBe('Pagado');
    expect($service->resolveStatus($appointment, 99.99))->toBe('Parcial');
});

it('conserva Reembolsado', function () {
    $appointment = new Appointment([
        'total' => 100,
        'payment_status' => 'Reembolsado',
    ]);
    $appointment->id = 900004;

    $service = new AppointmentPaymentStatusService();

    expect($service->resolveStatus($appointment, 100.0))->toBe('Reembolsado');
});

it('mapea métodos de caja al vocabulario Payment', function () {
    $service = new AppointmentPaymentStatusService();

    expect($service->mapCashMethodToPayment('Efectivo'))->toBe('cash');
    expect($service->mapCashMethodToPayment('Tarjeta'))->toBe('card');
    expect($service->mapCashMethodToPayment('Yape'))->toBe('yape');
    expect($service->mapCashMethodToPayment('Plin'))->toBe('plin');
    expect($service->mapCashMethodToPayment('Transferencia'))->toBe('transfer');
});
