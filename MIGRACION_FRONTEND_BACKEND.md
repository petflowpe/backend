# 🚀 Migración: Frontend → Backend Laravel Centralizado

## ✅ Lo que se ha implementado

### 1. **Backend Laravel - Nuevas Tablas y Modelos**

#### Migraciones creadas:
- ✅ `2025_01_25_000001_create_pets_table.php` - Tabla de mascotas
- ✅ `2025_01_25_000002_create_appointments_table.php` - Tabla de citas
- ✅ `2025_01_25_000003_create_vehicles_table.php` - Tabla de vehículos
- ✅ `2025_01_25_000004_create_medical_records_table.php` - Tabla de registros médicos
- ✅ `2025_01_25_000005_create_vaccine_records_table.php` - Tabla de vacunas

#### Modelos creados:
- ✅ `Pet.php` - Modelo de mascotas
- ✅ `Appointment.php` - Modelo de citas
- ✅ `Vehicle.php` - Modelo de vehículos
- ✅ `MedicalRecord.php` - Modelo de registros médicos
- ✅ `VaccineRecord.php` - Modelo de vacunas
- ✅ `Client.php` - Actualizado con relaciones a pets y appointments

#### Controladores API creados:
- ✅ `PetController.php` - CRUD completo de mascotas
- ✅ `AppointmentController.php` - CRUD completo de citas
- ✅ `VehicleController.php` - CRUD completo de vehículos
- ✅ `MedicalRecordController.php` - CRUD completo de registros médicos

#### Rutas API agregadas:
```php
// Mascotas
Route::apiResource('pets', PetController::class);
Route::get('/clients/{clientId}/pets', [PetController::class, 'getByClient']);

// Citas
Route::apiResource('appointments', AppointmentController::class);

// Vehículos
Route::apiResource('vehicles', VehicleController::class);
Route::get('/companies/{company}/vehicles', [VehicleController::class, 'index']);

// Registros Médicos
Route::apiResource('medical-records', MedicalRecordController::class);
Route::get('/pets/{petId}/medical-records', [MedicalRecordController::class, 'index']);
Route::get('/clients/{clientId}/medical-records', [MedicalRecordController::class, 'index']);
```

### 2. **Frontend - Servicios de API**

#### Archivos creados:
- ✅ `PetSmart/src/utils/api/config.ts` - Configuración centralizada de la API
- ✅ `PetSmart/src/utils/api/client.ts` - Cliente HTTP reutilizable

#### Hooks actualizados:
- ✅ `useClients.ts` - Migrado a usar backend Laravel en lugar de Supabase

## 📋 Pasos para completar la migración

### Paso 1: Ejecutar migraciones en el backend

```bash
cd backend-grooming
php artisan migrate
```

### Paso 2: Configurar variables de entorno

**Backend (.env):**
```env
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tu_base_de_datos
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
```

**Frontend (.env):**
```env
VITE_API_URL=http://localhost:8000/api
```

### Paso 3: Actualizar hooks restantes del frontend

Los siguientes hooks necesitan ser migrados de Supabase a Laravel:

- [ ] `useAppointments.ts` - Usar `/appointments`
- [ ] `useProducts.ts` - Ya existe en backend (`/products`)
- [ ] `useVehicles.ts` - Usar `/vehicles`
- [ ] `useMedicalRecords.ts` - Usar `/medical-records`
- [ ] `useInventory.ts` - Usar `/products` y `/products/{id}/kardex`
- [ ] `useInvoices.ts` - Ya existe en backend (`/invoices`)

### Paso 4: Actualizar AuthContext

El `AuthContext` necesita usar Sanctum en lugar de Supabase:

```typescript
// Cambiar de:
import { supabase } from '../utils/supabase/client';

// A:
import { apiClient } from '../utils/api/client';

// Login:
const response = await apiClient.post('/auth/login', { email, password });
const { access_token, user } = response;
localStorage.setItem('auth_token', access_token);
apiClient.setToken(access_token);
```

### Paso 5: Eliminar dependencias de Supabase

Una vez migrado todo:

1. Eliminar `PetSmart/src/utils/supabase/`
2. Eliminar `PetSmart/src/supabase/`
3. Remover `@supabase/supabase-js` de `package.json`
4. Actualizar `App.tsx` para no usar Supabase Auth

## 🔄 Estructura de datos

### Mapeo Frontend ↔ Backend

**Client:**
- Frontend: `fullName` → Backend: `razon_social`
- Frontend: `documentType` → Backend: `tipo_documento` (1=DNI, 4=CE, 6=RUC)
- Frontend: `documentNumber` → Backend: `numero_documento`
- Frontend: `phone` → Backend: `telefono`
- Frontend: `address` → Backend: `direccion`
- Frontend: `district` → Backend: `distrito`

**Pet:**
- Frontend: `birthDate` → Backend: `birth_date`
- Frontend: `photoUrl` → Backend: `photo`

**Appointment:**
- Frontend: `serviceType` → Backend: `service_type`
- Frontend: `serviceName` → Backend: `service_name`
- Frontend: `serviceCategory` → Backend: `service_category`
- Frontend: `paymentStatus` → Backend: `payment_status`
- Frontend: `paymentMethod` → Backend: `payment_method`

## 🧪 Testing

Para probar las nuevas APIs:

```bash
# Backend
cd backend-grooming
php artisan serve

# Frontend
cd PetSmart
npm run dev
```

## 📝 Notas importantes

1. **Autenticación**: El backend usa Laravel Sanctum. El token se debe guardar en `localStorage` como `auth_token`.

2. **CORS**: Asegúrate de configurar CORS en el backend para permitir requests del frontend:
   ```php
   // config/cors.php
   'paths' => ['api/*'],
   'allowed_origins' => ['http://localhost:3000'],
   ```

3. **Paginación**: Laravel devuelve paginación en formato:
   ```json
   {
     "success": true,
     "data": [...],
     "meta": {
       "total": 100,
       "per_page": 20,
       "current_page": 1,
       "last_page": 5
     }
   }
   ```

4. **Errores**: Laravel devuelve errores en formato:
   ```json
   {
     "success": false,
     "message": "Error message",
     "errors": {...}
   }
   ```

## 🎯 Estado actual

- ✅ Backend: 100% listo (migraciones, modelos, controladores, rutas)
- ✅ Frontend: 20% migrado (solo useClients)
- ⏳ Pendiente: Migrar hooks restantes y AuthContext

## 🚀 Próximos pasos

1. Ejecutar migraciones
2. Configurar .env
3. Migrar hooks restantes
4. Actualizar AuthContext
5. Eliminar Supabase
6. Testing completo
