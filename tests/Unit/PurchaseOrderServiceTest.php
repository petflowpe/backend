<?php

it('receive clamp: no excede pendiente', function () {
    $ordered = 10.0;
    $already = 7.0;
    $requestQty = 5.0;
    $pending = max(0, $ordered - $already);
    $toReceive = min($requestQty, $pending);
    expect($toReceive)->toBe(3.0);
});

it('payment status paid cuando cubre total', function () {
    $total = 100.0;
    $paid = 40.0;
    $amount = 60.0;
    $newPaid = $paid + $amount;
    $status = $newPaid + 0.01 >= $total ? 'paid' : 'partial';
    expect($status)->toBe('paid');
});
