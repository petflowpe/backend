# ⚡ Comandos Rápidos de Optimización

## 🚀 Optimización Rápida (Ejecutar en orden)

### 1. Optimizar Autoloader
```bash
composer install --optimize-autoloader --no-dev
```

### 2. Limpiar Cachés
```bash
php artisan optimize:clear
```

### 3. Generar Cachés Optimizados
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 4. Optimización Completa
```bash
php artisan optimize
```

## 📝 Scripts Automáticos

### Windows (PowerShell/CMD)
```bash
.\optimize.bat
```

### Linux/Mac
```bash
chmod +x optimize.sh
./optimize.sh
```

## 🔄 Comandos Individuales

### Limpiar todo
```bash
php artisan optimize:clear
```

### Cachear configuración
```bash
php artisan config:cache
```

### Cachear rutas
```bash
php artisan route:cache
```

### Cachear vistas
```bash
php artisan view:cache
```

### Cachear eventos
```bash
php artisan event:cache
```

## ⚠️ Importante

- **En desarrollo**: Usa `php artisan optimize:clear` para limpiar cachés
- **En producción**: Ejecuta `php artisan optimize` después de cada despliegue
- **No ejecutar en desarrollo**: Los comandos de cache pueden causar problemas si cambias código frecuentemente

## 🎯 Resultado Esperado

Después de ejecutar estos comandos, tu backend debería:
- ✅ Cargar más rápido
- ✅ Usar menos memoria
- ✅ Responder más rápido a las peticiones
- ✅ Tener mejor rendimiento general
