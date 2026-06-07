<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Concerns\BelongsToCompany;
class CompanyConfiguration extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'config_type',
        'environment',
        'service_type',
        'config_data',
        'is_active',
        'description',
        'priority',
    ];

    protected $casts = [
        'config_data' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    // ===================== RELACIONES =====================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ===================== SCOPES =====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $configType)
    {
        return $query->where('config_type', $configType);
    }

    public function scopeByEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    public function scopeByService($query, string $serviceType)
    {
        return $query->where('service_type', $serviceType);
    }

    public function scopeCredentials($query, string $serviceType = null, string $environment = null)
    {
        $query = $query->where('config_type', 'sunat_credentials');
        
        if ($serviceType) {
            $query->where('service_type', $serviceType);
        }
        
        if ($environment) {
            $query->where('environment', $environment);
        }
        
        return $query;
    }

    // ===================== CONSTANTES =====================

    const CONFIG_TYPES = [
        'sunat_credentials' => 'Credenciales SUNAT',
        'service_endpoints' => 'Endpoints de Servicios',
        'tax_settings' => 'Configuraciones de Impuestos',
        'invoice_settings' => 'Configuraciones de FacturaciÃ³n',
        'gre_settings' => 'Configuraciones de GuÃ­as de RemisiÃ³n',
        'file_settings' => 'Configuraciones de Archivos',
        'document_settings' => 'Configuraciones de Documentos',
        'summary_settings' => 'Configuraciones de ResÃºmenes',
        'void_settings' => 'Configuraciones de Bajas',
        'notification_settings' => 'Configuraciones de Notificaciones',
        'security_settings' => 'Configuraciones de Seguridad',
    ];

    const ENVIRONMENTS = [
        'general' => 'General',
        'beta' => 'Beta/Pruebas',
        'produccion' => 'ProducciÃ³n',
    ];

    const SERVICE_TYPES = [
        'general' => 'General',
        'facturacion' => 'FacturaciÃ³n',
        'guias_remision' => 'GuÃ­as de RemisiÃ³n',
        'resumenes_diarios' => 'ResÃºmenes Diarios',
        'comunicaciones_baja' => 'Comunicaciones de Baja',
        'retenciones' => 'Retenciones',
    ];

    // ===================== MÃ‰TODOS HELPER =====================

    /**
     * Obtener configuraciÃ³n por clave especÃ­fica
     */
    public function getConfigValue(string $key, $default = null)
    {
        return data_get($this->config_data, $key, $default);
    }

    /**
     * Establecer configuraciÃ³n por clave especÃ­fica
     */
    public function setConfigValue(string $key, $value): bool
    {
        $data = $this->config_data ?? [];
        data_set($data, $key, $value);
        
        return $this->update(['config_data' => $data]);
    }

    /**
     * Verificar si tiene una configuraciÃ³n especÃ­fica
     */
    public function hasConfigValue(string $key): bool
    {
        return data_get($this->config_data, $key) !== null;
    }

    /**
     * Fusionar configuraciones con nuevos datos
     */
    public function mergeConfigData(array $newData): bool
    {
        $currentData = $this->config_data ?? [];
        $mergedData = array_merge_recursive($currentData, $newData);
        
        return $this->update(['config_data' => $mergedData]);
    }

    /**
     * Obtener nombre legible del tipo de configuraciÃ³n
     */
    public function getConfigTypeName(): string
    {
        return self::CONFIG_TYPES[$this->config_type] ?? $this->config_type;
    }

    /**
     * Obtener nombre legible del ambiente
     */
    public function getEnvironmentName(): string
    {
        return self::ENVIRONMENTS[$this->environment] ?? $this->environment;
    }

    /**
     * Obtener nombre legible del tipo de servicio
     */
    public function getServiceTypeName(): string
    {
        return self::SERVICE_TYPES[$this->service_type] ?? $this->service_type;
    }

    /**
     * Verificar si es configuraciÃ³n de credenciales
     */
    public function isCredentialsConfig(): bool
    {
        return $this->config_type === 'sunat_credentials';
    }

    /**
     * Verificar si es configuraciÃ³n de ambiente especÃ­fico
     */
    public function isEnvironmentSpecific(): bool
    {
        return in_array($this->environment, ['beta', 'produccion']);
    }

    /**
     * Obtener credenciales de forma segura (sin exponer secretos)
     */
    public function getSafeCredentials(): array
    {
        if (!$this->isCredentialsConfig()) {
            return [];
        }

        $data = $this->config_data;
        $sensitiveFields = ['client_secret', 'clave_sol', 'password', 'token'];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = '***' . substr($data[$field], -4);
            }
        }

        return $data;
    }

    /**
     * Validar estructura de datos segÃºn el tipo de configuraciÃ³n
     */
    public function validateConfigData(): array
    {
        $errors = [];
        $data = $this->config_data;

        switch ($this->config_type) {
            case 'sunat_credentials':
                if (empty($data['client_id'])) {
                    $errors[] = 'client_id es requerido para credenciales SUNAT';
                }
                if (empty($data['client_secret'])) {
                    $errors[] = 'client_secret es requerido para credenciales SUNAT';
                }
                if ($this->environment === 'produccion') {
                    if (empty($data['usuario_sol'])) {
                        $errors[] = 'usuario_sol es requerido en producciÃ³n';
                    }
                    if (empty($data['clave_sol'])) {
                        $errors[] = 'clave_sol es requerido en producciÃ³n';
                    }
                }
                break;

            case 'tax_settings':
                if (isset($data['igv_porcentaje']) && (!is_numeric($data['igv_porcentaje']) || $data['igv_porcentaje'] < 0)) {
                    $errors[] = 'igv_porcentaje debe ser un nÃºmero positivo';
                }
                break;
        }

        return $errors;
    }
}