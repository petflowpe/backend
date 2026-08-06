<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Concerns\BelongsToCompany;
class CreditNote extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'branch_id',
        'client_id',
        'tipo_documento',
        'serie',
        'correlativo',
        'numero_completo',
        'tipo_doc_afectado',
        'num_doc_afectado',
        'cod_motivo',
        'des_motivo',
        'fecha_emision',
        'ubl_version',
        'moneda',
        'forma_pago_tipo',
        'forma_pago_cuotas',
        'valor_venta',
        'mto_oper_gravadas',
        'mto_oper_exoneradas',
        'mto_oper_inafectas',
        'mto_oper_gratuitas',
        'mto_igv',
        'mto_base_ivap',
        'mto_ivap',
        'mto_isc',
        'mto_icbper',
        'total_impuestos',
        'mto_imp_venta',
        'detalles',
        'leyendas',
        'guias',
        'datos_adicionales',
        'xml_path',
        'cdr_path',
        'pdf_path',
        'estado_sunat',
        'respuesta_sunat',
        'codigo_hash',
        'usuario_creacion',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'valor_venta' => 'decimal:2',
        'mto_oper_gravadas' => 'decimal:2',
        'mto_oper_exoneradas' => 'decimal:2',
        'mto_oper_inafectas' => 'decimal:2',
        'mto_oper_gratuitas' => 'decimal:2',
        'mto_igv' => 'decimal:2',
        'mto_base_ivap' => 'decimal:2',
        'mto_ivap' => 'decimal:2',
        'mto_isc' => 'decimal:2',
        'mto_icbper' => 'decimal:2',
        'total_impuestos' => 'decimal:2',
        'mto_imp_venta' => 'decimal:2',
        'forma_pago_cuotas' => 'array',
        'detalles' => 'array',
        'leyendas' => 'array',
        'guias' => 'array',
        'datos_adicionales' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function getTipoDocumentoNameAttribute(): string
    {
        return 'Nota de CrÃ©dito ElectrÃ³nica';
    }

    public function getDocumentoAfectadoNameAttribute(): string
    {
        return match($this->tipo_doc_afectado) {
            '01' => 'Factura',
            '03' => 'Boleta',
            default => 'Documento'
        };
    }

    public function getMotivoNameAttribute(): string
    {
        return match($this->cod_motivo) {
            '01' => 'AnulaciÃ³n de la operaciÃ³n',
            '02' => 'AnulaciÃ³n por error en el RUC',
            '03' => 'CorrecciÃ³n por error en la descripciÃ³n',
            '04' => 'Descuento global',
            '05' => 'Descuento por Ã­tem',
            '06' => 'DevoluciÃ³n total',
            '07' => 'DevoluciÃ³n por Ã­tem',
            '08' => 'BonificaciÃ³n',
            '09' => 'DisminuciÃ³n en el valor',
            '10' => 'Otros conceptos',
            '11' => 'Ajustes de operaciones de exportaciÃ³n',
            '12' => 'Ajustes afectos al IVAP',
            '13' => 'Ajustes - montos y/o fechas de pago',
            default => $this->des_motivo
        };
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($note) {
            if (empty($note->numero_completo)) {
                $note->numero_completo = $note->serie . '-' . $note->correlativo;
            }
        });
    }
}