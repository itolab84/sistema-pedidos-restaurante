# Sistema de Restaurante - Sabor Latino

Un sistema completo de gestión de restaurante con pedidos online, panel de administración y múltiples funcionalidades avanzadas.

## 🚀 Características Principales

### 🍽️ Sistema de Pedidos
- Catálogo de productos con categorías
- Carrito de compras interactivo
- Múltiples métodos de pago
- Seguimiento de pedidos en tiempo real
- Sistema de notificaciones

### 👨‍💼 Panel de Administración
- Gestión completa de productos y categorías
- Administración de pedidos y estados
- Sistema de clientes con historial
- Gestión de empleados y horarios
- Reportes y estadísticas
- Sistema de banners promocionales

### 💳 Sistema de Pagos
- Múltiples métodos de pago
- Integración con APIs de validación
- Gestión de cuentas bancarias
- Historial de transacciones

### 📱 Características Avanzadas
- Diseño responsive (móvil y desktop)
- PWA (Progressive Web App)
- Sistema de notificaciones push
- API REST completa
- Gestión de rutas de delivery
- Sistema de cambios y auditoría

## 📋 Requisitos del Sistema

### Servidor Web
- **PHP**: 7.4 o superior
- **MySQL**: 5.7 o superior (recomendado 8.0+)
- **Apache/Nginx**: Con mod_rewrite habilitado

### Extensiones PHP Requeridas
- `mysqli`
- `pdo`
- `pdo_mysql`
- `json`
- `mbstring`
- `gd` (opcional, para manipulación de imágenes)

### Permisos de Archivos
- Permisos de escritura en:
  - `config/`
  - `uploads/`
  - `assets/images/banners/`

## 🛠️ Instalación Automática

### Paso 1: Descargar y Extraer
1. Descarga el sistema completo
2. Extrae los archivos en tu servidor web
3. Asegúrate de que la carpeta tenga permisos de escritura

### Paso 2: Ejecutar el Instalador
1. Abre tu navegador web
2. Navega a: `http://tu-dominio.com/install.php`
3. Sigue el asistente de instalación paso a paso

### Paso 3: Configuración de Base de Datos
El instalador te pedirá:
- **Servidor**: `localhost` (generalmente)
- **Usuario**: Tu usuario de MySQL
- **Contraseña**: Tu contraseña de MySQL
- **Base de Datos**: `restaurante_pedidos` (se creará automáticamente)

### Paso 4: Completar Instalación
- El instalador creará todas las tablas necesarias
- Insertará datos de ejemplo
- Configurará el usuario administrador por defecto

## 🔐 Acceso Inicial

### Panel de Administración
- **URL**: `http://tu-dominio.com/admin/`
- **Usuario**: `admin`
- **Contraseña**: `admin123`

> ⚠️ **IMPORTANTE**: Cambia la contraseña después del primer login

### Sitio Web
- **URL**: `http://tu-dominio.com/`
- El sitio estará listo para recibir pedidos

## 🗂️ Estructura del Sistema

```
/
├── admin/                  # Panel de administración
│   ├── config/            # Configuraciones admin
│   ├── assets/            # CSS/JS del admin
│   ├── includes/          # Archivos comunes
│   ├── products/          # Gestión de productos
│   ├── orders/            # Gestión de pedidos
│   ├── customers/         # Gestión de clientes
│   ├── employees/         # Gestión de empleados
│   ├── banners/           # Gestión de banners
│   └── ...
├── api/                   # API REST
│   ├── products.php       # API de productos
│   ├── orders.php         # API de pedidos
│   ├── payment_methods.php # API de métodos de pago
│   └── ...
├── assets/                # Recursos del frontend
│   ├── css/               # Estilos
│   ├── js/                # JavaScript
│   └── images/            # Imágenes
├── config/                # Configuraciones generales
│   └── database.php       # Configuración de BD
├── install/               # Archivos del instalador
│   ├── database.sql       # Esquema de BD
│   ├── sample_data.sql    # Datos de ejemplo
│   └── config.php         # Configuración del instalador
├── uploads/               # Archivos subidos
│   ├── products/          # Imágenes de productos
│   └── additionals/       # Imágenes adicionales
├── install.php            # Instalador principal
└── index.php              # Página principal
```

## ⚙️ Configuración Post-Instalación

### 1. Seguridad
- [ ] Cambiar contraseña del administrador
- [ ] Eliminar o renombrar `install.php` e `install/`
- [ ] Configurar permisos de archivos apropiados

### 2. Información de la Empresa
- [ ] Actualizar datos de la empresa en el panel admin
- [ ] Configurar métodos de pago
- [ ] Establecer horarios de atención
- [ ] Configurar información de contacto

### 3. Productos y Categorías
- [ ] Agregar/editar categorías de productos
- [ ] Subir productos con imágenes
- [ ] Configurar precios y descripciones
- [ ] Establecer productos destacados

### 4. Configuraciones Avanzadas
- [ ] Configurar APIs de pago (opcional)
- [ ] Establecer zonas de delivery
- [ ] Configurar notificaciones
- [ ] Personalizar banners promocionales

## 🔧 Configuraciones Importantes

### Base de Datos
El archivo `config/database.php` contiene la configuración de conexión:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
define('DB_NAME', 'restaurante_pedidos');
```

### Configuraciones de la Empresa
Accede a `Admin > Configuraciones` para establecer:
- Nombre de la empresa
- Información de contacto
- Horarios de atención
- Costos de delivery
- Moneda y tasas de impuesto

## 🚨 Solución de Problemas

### Error de Conexión a Base de Datos
1. Verifica las credenciales en `config/database.php`
2. Asegúrate de que MySQL esté ejecutándose
3. Verifica que la base de datos exista

### Permisos de Archivos
```bash
chmod 755 config/
chmod 755 uploads/
chmod 755 assets/images/banners/
```

### Error 500 - Internal Server Error
1. Verifica los logs de error del servidor
2. Asegúrate de que todas las extensiones PHP estén instaladas
3. Verifica la sintaxis de los archivos .htaccess

### Problemas con Imágenes
1. Verifica permisos de la carpeta `uploads/`
2. Asegúrate de que la extensión `gd` esté habilitada
3. Verifica el tamaño máximo de subida en PHP

## 📚 Documentación Adicional

### APIs Disponibles
- `GET /api/products.php` - Obtener productos
- `POST /api/orders.php` - Crear pedido
- `GET /api/payment_methods.php` - Métodos de pago
- `GET /api/banners.php` - Banners activos

### Personalización
- Los estilos CSS están en `assets/css/style.css`
- Los scripts JavaScript en `assets/js/app_final.js`
- Las plantillas del admin en sus respectivas carpetas

## 🆘 Soporte

### Logs del Sistema
- Logs de PHP: Verifica los logs de tu servidor web
- Logs de MySQL: Revisa los logs de MySQL para errores de BD
- Logs del navegador: Usa las herramientas de desarrollador

### Backup y Restauración
```sql
-- Crear backup
mysqldump -u usuario -p restaurante_pedidos > backup.sql

-- Restaurar backup
mysql -u usuario -p restaurante_pedidos < backup.sql
```

## 📄 Licencia

Este sistema está desarrollado para uso comercial. Todos los derechos reservados.

## 🔄 Actualizaciones

Para futuras actualizaciones:
1. Realiza un backup completo
2. Actualiza los archivos del sistema
3. Ejecuta cualquier script de migración de BD si es necesario
4. Verifica que todo funcione correctamente

---

**¡Tu sistema de restaurante está listo para funcionar!** 🎉

Para soporte adicional o consultas, revisa la documentación técnica en el panel de administración.
