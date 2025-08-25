# Sistema de Pedidos de Restaurante

Un sistema completo de pedidos en línea para restaurantes desarrollado con PHP, MySQL y Bootstrap.

## Características

- ✅ Catálogo de productos con imágenes y descripciones
- ✅ Carrito de compras interactivo
- ✅ Proceso de checkout seguro
- ✅ Gestión de pedidos en tiempo real
- ✅ Diseño responsive para móviles
- ✅ Notificaciones toast para mejor experiencia de usuario
- ✅ API RESTful para integraciones futuras

## Tecnologías Utilizadas

- **Backend**: PHP 7.4+
- **Base de Datos**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework CSS**: Bootstrap 5
- **Iconos**: Font Awesome
- **API**: RESTful endpoints

## Instalación

### Requisitos Previos
- PHP 7.4 o superior
- MySQL 5.7 o superior
- Servidor web (Apache/Nginx)
- Composer (opcional)

### Pasos de Instalación

1. **Clonar el repositorio**
   ```bash
   git clone [URL_DEL_REPOSITORIO]
   cd sistema-pedidos-restaurante
   ```

2. **Configurar la base de datos**
   - Crear una base de datos MySQL llamada `restaurante_pedidos`
   - Importar el archivo `setup.sql`:
   ```bash
   mysql -u [usuario] -p restaurante_pedidos < setup.sql
   ```

3. **Configurar la conexión a la base de datos**
   - Editar el archivo `config/database.php`
   - Actualizar las credenciales:
   ```php
   $host = 'localhost';
   $username = 'tu_usuario';
   $password = 'tu_contraseña';
   $database = 'restaurante_pedidos';
   ```

4. **Configurar el servidor web**
   - Apuntar el document root al directorio del proyecto
   - Asegurarse de que PHP esté correctamente configurado

5. **Acceder al sistema**
   - Abrir el navegador en `http://localhost/index.php`

## Estructura del Proyecto

```
sistema-pedidos-restaurante/
├── api/
│   ├── products.php          # API para productos
│   └── orders.php            # API para pedidos
├── assets/
│   ├── css/
│   │   └── style.css         # Estilos personalizados
│   └── js/
│       └── app.js            # JavaScript de la aplicación
├── config/
│   └── database.php          # Configuración de base de datos
├── index.php                 # Página principal
├── setup.sql                 # Script de inicialización de BD
└── README.md                # Este archivo
```

## Uso del Sistema

### Para Clientes
1. **Explorar productos**: Ver el catálogo completo de productos
2. **Agregar al carrito**: Seleccionar productos y cantidades
3. **Realizar pedido**: Completar el formulario de checkout
4. **Confirmación**: Recibir confirmación del pedido

### Para Administradores
- Los pedidos se almacenan en la tabla `orders`
- Se puede acceder a los detalles de cada pedido
- El estado de los pedidos puede ser actualizado

## API Endpoints

### Productos
- `GET /api/products.php` - Obtener todos los productos

### Pedidos
- `POST /api/orders.php` - Crear nuevo pedido
- `GET /api/orders.php?id={id}` - Obtener detalles de un pedido

## Personalización

### Agregar nuevos productos
1. Usar el script SQL o la interfaz de administración
2. Asegurarse de incluir imagen, nombre, descripción y precio

### Modificar estilos
- Editar `assets/css/style.css`
- Los estilos están organizados por componentes

### Agregar funcionalidades
- Extender `assets/js/app.js`
- Agregar nuevos endpoints en la carpeta `api/`

## Seguridad

- Validación de entrada en el servidor
- Escapado de datos SQL
- Sanitización de datos del formulario
- HTTPS recomendado en producción

## Solución de Problemas

### Error de conexión a la base de datos
- Verificar credenciales en `config/database.php`
- Asegurar que MySQL esté ejecutándose
- Comprobar permisos de usuario

### Productos no se muestran
- Verificar que la tabla `products` tenga datos
- Revisar la consola del navegador para errores JavaScript
- Confirmar que la API esté accesible

### Errores de CORS
- Asegurar que el servidor web esté configurado correctamente
- Verificar las cabeceras CORS en los archivos PHP

## Contribuciones

Las contribuciones son bienvenidas. Por favor:
1. Fork el proyecto
2. Crear una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir un Pull Request

## Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## Soporte

Para soporte técnico o preguntas:
- Crear un issue en GitHub
- Contactar al equipo de desarrollo

## Demo

[Enlace a la demo en línea - si está disponible]
