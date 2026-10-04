<?php

use App\Models\Appointment;
use App\Services\AppointmentStockService;
use App\Services\ProductService;
use App\Services\StockReservationService;
use Illuminate\Database\Eloquent\Collection;

uses(Tests\TestCase::class);

it('no descuenta de nuevo si alreadyDeducted y consume la reserva', function () {
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldNotReceive('adjustStock');
    $reservations = Mockery::mock(StockReservationService::class);
    $reservations->shouldReceive('consume')->once()->with(AppointmentStockService::RESERVATION_SOURCE, 8003);

    $svc = Mockery::mock(AppointmentStockService::class, [$productService, $reservations])->makePartial();
    $svc->shouldReceive('alreadyDeducted')->once()->andReturn(true);

    $appointment = new Appointment();
    $appointment->id = 8003;
    $appointment->setRelation('items', new Collection());

    $svc->deductOnInvoice($appointment);
});

it('assertStockAvailable no lanza sin insumos ni productos', function () {
    $productService = Mockery::mock(ProductService::class);
    $reservations = Mockery::mock(StockReservationService::class);
    $svc = new AppointmentStockService($productService, $reservations);

    $appointment = new Appointment(['service_id' => null]);
    $appointment->id = 8004;
    $appointment->setRelation('items', new Collection());

    $svc->assertStockAvailable($appointment);

    expect(true)->toBeTrue();
});
