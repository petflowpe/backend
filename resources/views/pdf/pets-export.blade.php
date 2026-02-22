<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Listado de Mascotas</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; padding: 12px; color: #1f2937; }
        h1 { font-size: 16px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: 600; }
        tr:nth-child(even) { background: #f9fafb; }
    </style>
</head>
<body>
    <h1>Listado de Mascotas - {{ now()->format('d/m/Y') }}</h1>
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Especie</th>
                <th>Raza</th>
                <th>Sexo</th>
                <th>Edad</th>
                <th>Peso (kg)</th>
                <th>Cliente</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pets as $p)
            <tr>
                <td>{{ $p->name ?? '' }}</td>
                <td>{{ $p->species ?? '' }}</td>
                <td>{{ $p->breed ?? '' }}</td>
                <td>{{ $p->gender ?? '' }}</td>
                <td>{{ $p->age !== null ? $p->age : '-' }}</td>
                <td>{{ $p->weight !== null ? $p->weight : '-' }}</td>
                <td>{{ $p->client->razon_social ?? '' }}</td>
                <td>{{ $p->fallecido ? 'Fallecido' : 'Activo' }}</td>
            </tr>
            @empty
            <tr><td colspan="8">No hay mascotas</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
