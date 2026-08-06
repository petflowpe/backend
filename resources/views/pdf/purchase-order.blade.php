<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 0 0 12px; color: #333; }
        .meta { margin-bottom: 16px; }
        .meta td { padding: 2px 8px 2px 0; vertical-align: top; }
        .label { color: #555; width: 110px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th, table.items td { border: 1px solid #ccc; padding: 6px 8px; }
        table.items th { background: #f3f4f6; text-align: left; font-size: 11px; }
        .num { text-align: right; }
        .totals { margin-top: 12px; width: 100%; }
        .totals td { padding: 4px 0; }
        .footer { margin-top: 28px; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 8px; }
        .notes { margin-top: 14px; padding: 8px; background: #f9fafb; border: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <h1>ORDEN DE COMPRA</h1>
    <h2>{{ $order->order_number ?: ('#'.$order->id) }}</h2>

    <table class="meta">
        <tr>
            <td class="label">Empresa</td>
            <td>
                {{ $company->razon_social ?? $company->nombre_comercial ?? '—' }}
                @if(!empty($company->ruc))
                    <br>RUC: {{ $company->ruc }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Proveedor</td>
            <td>
                <strong>{{ $order->supplier->name ?? '—' }}</strong>
                @if(!empty($order->supplier->document_number))
                    <br>{{ strtoupper($order->supplier->document_type ?? 'RUC') }}: {{ $order->supplier->document_number }}
                @endif
                @if(!empty($order->supplier->contact_name))
                    <br>Contacto: {{ $order->supplier->contact_name }}
                @endif
                @if(!empty($order->supplier->phone))
                    <br>Tel: {{ $order->supplier->phone }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Fecha OC</td>
            <td>{{ optional($order->order_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Entrega est.</td>
            <td>{{ optional($order->delivery_date)->format('d/m/Y') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Estado</td>
            <td>{{ $statusLabel }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Código</th>
                <th>Producto</th>
                <th class="num">Cant.</th>
                <th class="num">Costo unit.</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->product->code ?? '—' }}</td>
                    <td>{{ $item->product->name ?? 'Producto' }}</td>
                    <td class="num">{{ number_format((float) $item->quantity, 3, '.', ',') }}</td>
                    <td class="num">{{ number_format((float) $item->unit_cost, 2, '.', ',') }}</td>
                    <td class="num">{{ number_format((float) $item->total_cost, 2, '.', ',') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td></td>
            <td class="num" style="width: 180px;"><strong>TOTAL S/</strong></td>
            <td class="num" style="width: 100px;"><strong>{{ number_format((float) $order->total, 2, '.', ',') }}</strong></td>
        </tr>
    </table>

    @if($order->notes)
        <div class="notes">
            <strong>Notas:</strong><br>
            {{ $order->notes }}
        </div>
    @endif

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} · Documento interno de compra (no es comprobante SUNAT)
    </div>
</body>
</html>
