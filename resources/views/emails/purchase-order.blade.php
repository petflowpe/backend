<x-mail::message>
# Orden de compra {{ $order->order_number ?: '#'.$order->id }}

Estimado/a **{{ $supplier->name ?? 'proveedor' }}**,

Adjunto el detalle de nuestra orden de compra.

**Empresa:** {{ $company->razon_social ?? $company->nombre_comercial ?? '—' }}  
**Fecha:** {{ optional($order->order_date)->format('d/m/Y') }}  
**Entrega estimada:** {{ optional($order->delivery_date)->format('d/m/Y') ?: '—' }}  
**Total:** S/ {{ number_format((float) $order->total, 2) }}

| Producto | Cant. | Costo | Subtotal |
|:---------|------:|------:|---------:|
@foreach($order->items as $item)
| {{ $item->product->name ?? 'Producto' }} | {{ number_format((float)$item->quantity, 2) }} | {{ number_format((float)$item->unit_cost, 2) }} | {{ number_format((float)$item->total_cost, 2) }} |
@endforeach

@if($order->notes)
**Notas:** {{ $order->notes }}
@endif

Gracias,<br>
{{ $company->razon_social ?? config('app.name') }}
</x-mail::message>
