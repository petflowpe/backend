<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\BoletaController;
use App\Http\Controllers\Api\DailySummaryController;
use App\Http\Controllers\Api\CreditNoteController;
use App\Http\Controllers\Api\DebitNoteController;
use App\Http\Controllers\Api\RetentionController;
use App\Http\Controllers\Api\VoidedDocumentController;
use App\Http\Controllers\Api\DispatchGuideController;
use App\Http\Controllers\Api\PdfController;
use App\Http\Controllers\Api\CompanyConfigController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CorrelativeController;
use App\Http\Controllers\Api\CashSessionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\KardexController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CashMovementController;
use App\Http\Controllers\Api\OptimizationRecordController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\GreCredentialsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsultaCpeController;
use App\Http\Controllers\Api\SetupController;
use App\Http\Controllers\Api\UbigeoController;
use App\Http\Controllers\Api\ConsultaCpeControllerMejorado;
use App\Http\Controllers\Api\PetController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\PetConfigurationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ZoneController;
use App\Http\Controllers\Api\RoutePlanController;
use App\Http\Controllers\Api\AccountingEntryController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\CoreController;
use App\Http\Middleware\EnsureUserCompanyScope;

// ========================
// RUTAS PÚBLICAS (SIN AUTENTICACIÓN)
// ========================

// Información del sistema
Route::get('/system/info', [AuthController::class, 'systemInfo']);

// Setup del sistema
Route::prefix('setup')->group(function () {
    Route::post('/migrate', [SetupController::class, 'migrate']);
    Route::post('/seed', [SetupController::class, 'seed']);
    Route::get('/status', [SetupController::class, 'status']);
});

// Inicialización del sistema
Route::post('/auth/initialize', [AuthController::class, 'initialize']);

// Autenticación
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/auth/request-access', [AuthController::class, 'requestAccess']);

// ========================
// RUTAS PROTEGIDAS (CON AUTENTICACIÓN)
// ========================
Route::prefix('v1')->middleware(['auth:sanctum', EnsureUserCompanyScope::class])->group(function () {

    // ========================
    // AUTENTICACIÓN Y USUARIO
    // ========================
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/create-user', [AuthController::class, 'createUser']);

    // Core: monedas y módulos activos
    Route::get('/core/currencies', [CoreController::class, 'currencies']);
    Route::get('/core/modules', [CoreController::class, 'modules']);

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Perfil y configuración del usuario autenticado
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::put('/settings', [SettingsController::class, 'update']);

    // Roles y permisos (lectura)
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::get('/permissions', [PermissionController::class, 'index']);

    // ========================
    // AUDITORÍA
    // ========================
    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    // ========================
    // ZONAS Y RUTAS (OPERACIONES)
    // ========================
    Route::apiResource('zones', ZoneController::class);
    Route::get('/route-plans', [RoutePlanController::class, 'index']);
    Route::post('/route-plans', [RoutePlanController::class, 'store']);
    Route::get('/route-plans/{route}', [RoutePlanController::class, 'show']);
    Route::put('/route-plans/{route}', [RoutePlanController::class, 'update']);
    Route::delete('/route-plans/{route}', [RoutePlanController::class, 'destroy']);

    // ========================
    // CONTABILIDAD (ASIENTOS)
    // ========================
    Route::get('/accounting-entries', [AccountingEntryController::class, 'index']);
    Route::post('/accounting-entries', [AccountingEntryController::class, 'store']);
    Route::get('/accounting-entries/{accounting_entry}', [AccountingEntryController::class, 'show']);

    // ========================
    // BÚSQUEDA GLOBAL
    // ========================
    Route::get('/search', [SearchController::class, 'index']);

    // Usuario autenticado
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // ========================
    // SETUP AVANZADO
    // ========================
    Route::prefix('setup')->group(function () {
        Route::post('/complete', [SetupController::class, 'setup']);
        Route::post('/configure-sunat', [SetupController::class, 'configureSunat']);
    });

    // ========================
    // GESTIÓN DE UBIGEOS
    // ========================
    Route::prefix('ubigeos')->group(function () {
        Route::get('/regiones', [UbigeoController::class, 'getRegiones']);
        Route::get('/provincias', [UbigeoController::class, 'getProvincias']);
        Route::get('/distritos', [UbigeoController::class, 'getDistritos']);
        Route::get('/search', [UbigeoController::class, 'searchUbigeo']);
        Route::get('/{id}', [UbigeoController::class, 'getUbigeoById']);
    });

    // ========================
    // EMPRESAS Y CONFIGURACIONES
    // ========================

    // Empresas
    Route::apiResource('companies', CompanyController::class);
    Route::post('/companies/{company}/activate', [CompanyController::class, 'activate']);
    Route::post('/companies/{company}/toggle-production', [CompanyController::class, 'toggleProductionMode']);

    // Configuraciones de empresas
    Route::prefix('companies/{company_id}/config')->group(function () {
        Route::get('/', [CompanyConfigController::class, 'show']);
        Route::get('/{section}', [CompanyConfigController::class, 'getSection']);
        Route::put('/{section}', [CompanyConfigController::class, 'updateSection']);
        Route::get('/validate/services', [CompanyConfigController::class, 'validateServices']);
        Route::post('/reset', [CompanyConfigController::class, 'resetToDefaults']);
        Route::post('/migrate', [CompanyConfigController::class, 'migrateCompany']);
        Route::delete('/cache', [CompanyConfigController::class, 'clearCache']);
    });

    // Configuraciones generales
    Route::prefix('config')->group(function () {
        Route::get('/defaults', [CompanyConfigController::class, 'getDefaults']);
        Route::get('/summary', [CompanyConfigController::class, 'getSummary']);
    });

    // ========================
    // CREDENCIALES GRE
    // ========================

    // Credenciales GRE por empresa
    Route::prefix('companies/{company}/gre-credentials')->group(function () {
        Route::get('/', [GreCredentialsController::class, 'show']);
        Route::put('/', [GreCredentialsController::class, 'update']);
        Route::post('/test-connection', [GreCredentialsController::class, 'testConnection']);
        Route::delete('/clear', [GreCredentialsController::class, 'clear']);
        Route::post('/copy', [GreCredentialsController::class, 'copy']);
    });

    // Credenciales GRE - Configuraciones globales
    Route::prefix('gre-credentials')->group(function () {
        Route::get('/defaults/{mode}', [GreCredentialsController::class, 'getDefaults'])
            ->where('mode', 'beta|produccion');
    });

    // ========================
    // SUCURSALES
    // ========================
    Route::apiResource('branches', BranchController::class);
    Route::post('/branches/{branch}/activate', [BranchController::class, 'activate']);
    Route::get('/companies/{company}/branches', [BranchController::class, 'getByCompany']);

    // ========================
    // CLIENTES
    // ========================
    Route::apiResource('clients', ClientController::class);
    Route::post('/clients/{client}/activate', [ClientController::class, 'activate']);
    Route::get('/companies/{company}/clients', [ClientController::class, 'getByCompany']);
    Route::post('/clients/search-by-document', [ClientController::class, 'searchByDocument']);

    // ========================
    // PRODUCTOS / SERVICIOS (CATÁLOGO)
    // ========================
    Route::apiResource('products', ProductController::class)->except(['destroy']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    Route::post('/products/{product}/activate', [ProductController::class, 'activate']);
    Route::get('/companies/{company}/products', [ProductController::class, 'getByCompany']);
    Route::get('/companies/{company}/products/kpis', [ProductController::class, 'getKPIs']);
    Route::get('/products/low-stock', [ProductController::class, 'getLowStock']);
    Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock']);
    Route::get('/products/{product}/kardex', [KardexController::class, 'index']);

    // ========================
    // CATEGORÍAS DE PRODUCTOS
    // ========================
    Route::apiResource('categories', CategoryController::class);
    Route::post('/categories/{category}/toggle-active', [CategoryController::class, 'toggleActive']);

    // ========================
    // UNIDADES DE MEDIDA
    // ========================
    Route::apiResource('units', UnitController::class);
    Route::post('/units/{unit}/toggle-active', [UnitController::class, 'toggleActive']);

    // ========================
    // ÁREAS DE ALMACENAMIENTO
    // ========================
    Route::apiResource('areas', AreaController::class);
    Route::post('/areas/{area}/toggle-active', [AreaController::class, 'toggleActive']);

    // ========================
    // MARCAS
    // ========================
    Route::apiResource('brands', BrandController::class);
    Route::post('/brands/{brand}/toggle-active', [BrandController::class, 'toggleActive']);
    Route::get('/brands/kpis', [BrandController::class, 'getKPIs']);

    // ========================
    // PROVEEDORES
    // ========================
    Route::apiResource('suppliers', SupplierController::class);
    Route::post('/suppliers/{supplier}/toggle-active', [SupplierController::class, 'toggleActive']);
    Route::get('/suppliers/kpis', [SupplierController::class, 'getKPIs']);

    // ========================
    // ÓRDENES DE COMPRA
    // ========================
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::get('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show']);
    Route::put('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'update']);
    Route::delete('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'destroy']);
    Route::patch('/purchase-orders/{purchase_order}/status', [PurchaseOrderController::class, 'changeStatus']);
    Route::post('/purchase-orders/{purchase_order}/complete', [PurchaseOrderController::class, 'complete']);

    // ========================
    // CORRELATIVOS
    // ========================
    Route::get('/branches/{branch}/correlatives', [CorrelativeController::class, 'index']);
    Route::post('/branches/{branch}/correlatives', [CorrelativeController::class, 'store']);
    Route::put('/branches/{branch}/correlatives/{correlative}', [CorrelativeController::class, 'update']);
    Route::delete('/branches/{branch}/correlatives/{correlative}', [CorrelativeController::class, 'destroy']);
    Route::post('/branches/{branch}/correlatives/batch', [CorrelativeController::class, 'createBatch']);
    Route::post('/branches/{branch}/correlatives/{correlative}/increment', [CorrelativeController::class, 'increment']);

    // Catálogos de correlativos
    Route::get('/correlatives/document-types', [CorrelativeController::class, 'getDocumentTypes']);

    // ========================
    // CAJA Y PAGOS
    // ========================
    Route::get('/cash-sessions', [CashSessionController::class, 'index']);
    Route::post('/cash-sessions/open', [CashSessionController::class, 'open']);
    Route::post('/cash-sessions/{cashSession}/close', [CashSessionController::class, 'close']);

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);

    Route::get('/reports/sales', [ReportController::class, 'salesSummary']);
    Route::get('/reports/stats', [ReportController::class, 'dashboardStats']);
    Route::get('/reports/products', [ReportController::class, 'productAnalytics']);
    Route::get('/reports/clients', [ReportController::class, 'clientAnalytics']);

    Route::apiResource('cash-movements', CashMovementController::class);
    Route::apiResource('optimization-records', OptimizationRecordController::class);

    // ========================
    // DOCUMENTOS ELECTRÓNICOS SUNAT
    // ========================

    // PDF Formatos
    Route::prefix('pdf')->group(function () {
        Route::get('/formats', [PdfController::class, 'getAvailableFormats']);
    });

    // Facturas
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::post('/', [InvoiceController::class, 'store']);
        Route::get('/{id}', [InvoiceController::class, 'show']);
        Route::delete('/{id}', [InvoiceController::class, 'destroy']);
        Route::post('/{id}/send-sunat', [InvoiceController::class, 'sendToSunat']);
        Route::get('/{id}/download-xml', [InvoiceController::class, 'downloadXml']);
        Route::get('/{id}/download-cdr', [InvoiceController::class, 'downloadCdr']);
        Route::get('/{id}/download-pdf', [InvoiceController::class, 'downloadPdf']);
        Route::post('/{id}/generate-pdf', [InvoiceController::class, 'generatePdf']);
    });

    // Boletas
    Route::prefix('boletas')->group(function () {
        Route::get('/', [BoletaController::class, 'index']);
        Route::post('/', [BoletaController::class, 'store']);
        Route::get('/{id}', [BoletaController::class, 'show']);
        Route::post('/{id}/send-sunat', [BoletaController::class, 'sendToSunat']);
        Route::get('/{id}/download-xml', [BoletaController::class, 'downloadXml']);
        Route::get('/{id}/download-cdr', [BoletaController::class, 'downloadCdr']);
        Route::get('/{id}/download-pdf', [BoletaController::class, 'downloadPdf']);
        Route::post('/{id}/generate-pdf', [BoletaController::class, 'generatePdf']);

        // Funciones de resumen diario desde boletas
        Route::get('/pending-for-summary', [BoletaController::class, 'getBoletsasPendingForSummary']);
        Route::post('/create-daily-summary', [BoletaController::class, 'createDailySummaryFromDate']);
        Route::post('/summary/{id}/send-sunat', [BoletaController::class, 'sendSummaryToSunat']);
        Route::post('/summary/{id}/check-status', [BoletaController::class, 'checkSummaryStatus']);
    });

    // Resúmenes Diarios
    Route::prefix('daily-summaries')->group(function () {
        Route::get('/', [DailySummaryController::class, 'index']);
        Route::post('/', [DailySummaryController::class, 'store']);
        Route::get('/{id}', [DailySummaryController::class, 'show']);
        Route::post('/{id}/send-sunat', [DailySummaryController::class, 'sendToSunat']);
        Route::post('/{id}/check-status', [DailySummaryController::class, 'checkStatus']);
        Route::get('/{id}/download-xml', [DailySummaryController::class, 'downloadXml']);
        Route::get('/{id}/download-cdr', [DailySummaryController::class, 'downloadCdr']);
        Route::get('/{id}/download-pdf', [DailySummaryController::class, 'downloadPdf']);
        Route::post('/{id}/generate-pdf', [DailySummaryController::class, 'generatePdf']);

        // Funciones de gestión masiva
        Route::get('/pending', [DailySummaryController::class, 'getPendingSummaries']);
        Route::post('/check-all-pending', [DailySummaryController::class, 'checkAllPendingStatus']);
    });

    // Notas de Crédito
    Route::prefix('credit-notes')->group(function () {
        Route::get('/', [CreditNoteController::class, 'index']);
        Route::post('/', [CreditNoteController::class, 'store']);
        Route::get('/{id}', [CreditNoteController::class, 'show']);
        Route::post('/{id}/send-sunat', [CreditNoteController::class, 'sendToSunat']);
        Route::get('/{id}/download-xml', [CreditNoteController::class, 'downloadXml']);
        Route::get('/{id}/download-cdr', [CreditNoteController::class, 'downloadCdr']);
        Route::get('/{id}/download-pdf', [CreditNoteController::class, 'downloadPdf']);
        Route::post('/{id}/generate-pdf', [CreditNoteController::class, 'generatePdf']);

        // Catálogo de motivos
        Route::get('/catalogs/motivos', [CreditNoteController::class, 'getMotivos']);
    });

    // Notas de Débito
    Route::prefix('debit-notes')->group(function () {
        Route::get('/', [DebitNoteController::class, 'index']);
        Route::post('/', [DebitNoteController::class, 'store']);
        Route::get('/{id}', [DebitNoteController::class, 'show']);
        Route::post('/{id}/send-sunat', [DebitNoteController::class, 'sendToSunat']);
        Route::get('/{id}/download-xml', [DebitNoteController::class, 'downloadXml']);
        Route::get('/{id}/download-cdr', [DebitNoteController::class, 'downloadCdr']);
        Route::get('/{id}/download-pdf', [DebitNoteController::class, 'downloadPdf']);
        Route::post('/{id}/generate-pdf', [DebitNoteController::class, 'generatePdf']);

        // Catálogo de motivos
        Route::get('/catalogs/motivos', [DebitNoteController::class, 'getMotivos']);
    });

    // Comprobantes de Retención
    Route::prefix('retentions')->group(function () {
        Route::get('/', [RetentionController::class, 'index']);
        Route::post('/', [RetentionController::class, 'store']);
        Route::get('/{id}', [RetentionController::class, 'show']);
        Route::post('/{id}/send-sunat', [RetentionController::class, 'sendToSunat']);
        Route::get('/{id}/download-xml', [RetentionController::class, 'downloadXml']);
        Route::get('/{id}/download-cdr', [RetentionController::class, 'downloadCdr']);
        Route::get('/{id}/download-pdf', [RetentionController::class, 'downloadPdf']);
        Route::post('/{id}/generate-pdf', [RetentionController::class, 'generatePdf']);
    });

    // Comunicaciones de Baja
    Route::prefix('voided-documents')->group(function () {
        Route::get('/', [VoidedDocumentController::class, 'index']);
        Route::post('/', [VoidedDocumentController::class, 'store']);
        Route::get('/available-documents', [VoidedDocumentController::class, 'getDocumentsForVoiding']);
        Route::get('/{id}', [VoidedDocumentController::class, 'show']);
        Route::post('/{id}/send-sunat', [VoidedDocumentController::class, 'sendToSunat']);
        Route::post('/{id}/check-status', [VoidedDocumentController::class, 'checkStatus']);
        Route::get('/{id}/download-xml', [VoidedDocumentController::class, 'downloadXml']);
        Route::get('/{id}/download-cdr', [VoidedDocumentController::class, 'downloadCdr']);
    });

    // Guías de Remisión
    Route::prefix('dispatch-guides')->group(function () {
        Route::get('/', [DispatchGuideController::class, 'index']);
        Route::post('/', [DispatchGuideController::class, 'store']);
        Route::get('/{id}', [DispatchGuideController::class, 'show']);
        Route::post('/{id}/send-sunat', [DispatchGuideController::class, 'sendToSunat']);
        Route::post('/{id}/check-status', [DispatchGuideController::class, 'checkStatus']);
        Route::get('/{id}/download-xml', [DispatchGuideController::class, 'downloadXml']);
        Route::get('/{id}/download-cdr', [DispatchGuideController::class, 'downloadCdr']);
        Route::get('/{id}/download-pdf', [DispatchGuideController::class, 'downloadPdf']);
        Route::post('/{id}/generate-pdf', [DispatchGuideController::class, 'generatePdf']);

        // Catálogos
        Route::get('/catalogs/transfer-reasons', [DispatchGuideController::class, 'getTransferReasons']);
        Route::get('/catalogs/transport-modes', [DispatchGuideController::class, 'getTransportModes']);
    });

    // ========================
    // CONSULTA DE COMPROBANTES ELECTRÓNICOS (CPE)
    // ========================
    Route::prefix('consulta-cpe')->group(function () {
        // Consultas individuales por tipo de documento
        Route::post('/factura/{id}', [ConsultaCpeController::class, 'consultarFactura']);
        Route::post('/boleta/{id}', [ConsultaCpeController::class, 'consultarBoleta']);
        Route::post('/nota-credito/{id}', [ConsultaCpeController::class, 'consultarNotaCredito']);
        Route::post('/nota-debito/{id}', [ConsultaCpeController::class, 'consultarNotaDebito']);

        // Consulta masiva
        Route::post('/masivo', [ConsultaCpeController::class, 'consultarDocumentosMasivo']);

        // Estadísticas de consultas
        Route::get('/estadisticas', [ConsultaCpeController::class, 'estadisticasConsultas']);
    });

    // ========================
    // MASCOTAS (PETS)
    // ========================
    Route::get('/pets/export/pdf', [PetController::class, 'exportPdf']);
    Route::get('/pets/reminders', [PetController::class, 'reminders']);
    Route::get('/pets/duplicates', [PetController::class, 'duplicates']);
    Route::apiResource('pets', PetController::class);
    Route::post('/pets/{id}/photos', [PetController::class, 'storePhotos']);
    Route::get('/pets/{id}/timeline', [PetController::class, 'timeline']);
    Route::get('/pets/{id}/audit-history', [PetController::class, 'auditHistory']);
    Route::get('/clients/{clientId}/pets', [PetController::class, 'getByClient']);

    // Configuraciones de Mascotas (Razas, Temperamentos, Comportamientos)
    Route::get('/pet-configurations', [PetConfigurationController::class, 'index']);
    Route::get('/pet-configurations/all', [PetConfigurationController::class, 'getAll']);
    Route::post('/pet-configurations', [PetConfigurationController::class, 'store']);
    Route::post('/pet-configurations/add-item', [PetConfigurationController::class, 'addItem']);
    Route::delete('/pet-configurations/{id}', [PetConfigurationController::class, 'destroy']);

    // ========================
    // CITAS (APPOINTMENTS)
    // ========================
    Route::apiResource('appointments', AppointmentController::class);
    Route::post('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
    Route::post('/appointments/{appointment}/change-status', [AppointmentController::class, 'changeStatus']);
    Route::post('/appointments/{appointment}/send-reminder', [AppointmentController::class, 'sendReminder']);
    Route::post('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
    Route::get('/clients/{clientId}/appointments', [AppointmentController::class, 'getByClient']);
    Route::get('/appointments/recurring-series/{seriesId}', [AppointmentController::class, 'getRecurringSeries']);

    // ========================
    // VEHÍCULOS
    // ========================
    Route::apiResource('vehicles', VehicleController::class);
    Route::get('/companies/{company}/vehicles', [VehicleController::class, 'index']);

    // ========================
    // REGISTROS MÉDICOS
    // ========================
    Route::apiResource('medical-records', MedicalRecordController::class);
    Route::get('/pets/{petId}/medical-records', [MedicalRecordController::class, 'index']);
    Route::get('/clients/{clientId}/medical-records', [MedicalRecordController::class, 'index']);

    // ========================
    // NOTIFICACIONES
    // ========================
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });
});
