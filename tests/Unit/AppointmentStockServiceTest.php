<?php

use App\Models\Appointment;
use App\Services\AppointmentStockService;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Collection;

uses(Tests\TestCase::class);

it('no descuenta de nuevo si alreadyDeducted', function () {
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldNotReceive('adjustStock');

    $svc = Mockery::mock(AppointmentStockService::class, [$productService])->makePartial();
    $svc->shouldReceive('alreadyDeducted')->once()->andReturn(true);

    $appointment = new Appointment();
    $appointment->id = 8003;
    $appointment->setRelation('items', new Collection());

    $svc->deductOnInvoice($appointment);
});

it('assertStockAvailable no lanza sin insumos ni productos', function () {
    $productService = Mockery::mock(ProductService::class);
    $svc = new AppointmentStockService($productService);

    $appointment = new Appointment(['service_id' => null]);
    $appointment->id = 8004;
    $appointment->setRelation('items', new Collection());

    $svc->assertStockAvailable($appointment);

    expect(true)->toBeTrue();
});
