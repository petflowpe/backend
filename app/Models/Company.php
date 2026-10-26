<?php

namespace App\Models;

use App\Traits\HasCompanyConfigurations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, HasCompanyConfigurations;

    protected $fillable = [
        'ruc',
        'razon_social',
        'nombre_comercial',
        'booking_slug',
        'direccion',
        'ubigeo',
        'distrito',
        'provincia',
        'departamento',
        'telefono',
        'email',
        'web',
        'usuario_sol',
        'clave_sol',
        'certificado_pem',
        'certificado_password',
        'gre_client_id_beta',
        'gre_client_secret_beta',
        'gre_client_id_produccion',
        'gre_client_secret_produccion',
        'gre_ruc_proveedor',
        'gre_usuario_sol',
        'gre_clave_sol',
        'endpoint_beta',
        'endpoint_produccion',
        'modo_produccion',
        'logo_path',
        'activo',
    ];

    protected $casts = [
        'modo_produccion' => 'boolean',
        'activo' => 'boolean',
    ];

    protected $hidden = [
        'clave_sol',
        'certificado_pem',
        'certificado_password',
        'gre_client_secret_beta',
        'gre_client_secret_produccion',
        'gre_clave_sol',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(CompanyConfiguration::class);
    }

    public function activeConfigurations(): HasMany
    {
        return $this->hasMany(CompanyConfiguration::class)->where('is_active', true);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function boletas(): HasMany
    {
        return $this->hasMany(Boleta::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function debitNotes(): HasMany
    {
        return $this->hasMany(DebitNote::class);
    }

    public function dispatchGuides(): HasMany
    {
        return $this->hasMany(DispatchGuide::class);
    }

    public function dailySummaries(): HasMany
    {
        return $this->hasMany(DailySummary::class);
    }

    public function voidedDocuments(): HasMany
    {
        return $this->hasMany(VoidedDocument::class);
    }

    public function getEndpointAttribute(): string
    {
        return $this->modo_produccion ? $this->endpoint_produccion : $this->endpoint_beta;
    }

    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($company) {
            if (empty($company->endpoint_beta)) {
                $company->endpoint_beta = 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';
            }

            if (empty($company->endpoint_produccion)) {
                $company->endpoint_produccion = 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService';
            }

            $company->activo = $company->activo ?? true;
            $company->modo_produccion = $company->modo_produccion ?? false;

            if (empty($company->booking_slug)) {
                $base = Str::slug((string) ($company->nombre_comercial ?: $company->razon_social ?: 'empresa'));
                if ($base === '') {
                    $base = 'empresa';
                }
                $slug = $base;
                $n = 2;
                while (static::where('booking_slug', $slug)->exists()) {
                    $slug = $base . '-' . $n;
                    $n++;
                }
                $company->booking_slug = $slug;
            }
        });

        // Trait HasCompanyConfigurations: service_endpoints, tax, documents, etc.
        static::created(function ($company) {
            $company->initializeDefaultConfigurations();
        });
    }
}
