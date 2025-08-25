# 🚀 Configuración de Validación de Pagos Móviles

## 📋 Requisitos Previos

1. **Tabla `api_integrations`** debe existir en la base de datos
2. **Token/API Key** válido del servicio de validación de pagos
3. **Endpoint URL** correcto configurado

## 🛠️ Configuración de la Base de Datos

### Estructura de la tabla `api_integrations`:

```sql
CREATE TABLE api_integrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL UNIQUE,
    api_key TEXT NOT NULL,
    endpoint_url VARCHAR(500) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Configuración mínima requerida:

```sql
INSERT INTO api_integrations (service_name, api_key, endpoint_url, description, is_active)
VALUES (
    'payment_validator',
    'TU_API_KEY_AQUI', -- Reemplazar con tu token real
    'https://validator.movilpay.app/api/payments/validate/',
    'API para validación de pagos móviles',
    1
);
```

## 🔧 Pasos de Configuración

### 1. Verificar la configuración actual

Ejecutar el script de verificación:
```bash
# Abrir en el navegador:
http://localhost/reserve/check_api_integrations.php
```

### 2. Configurar el API Key

1. Acceder al panel de administración: `http://localhost/reserve/admin/`
2. Ir a la sección de integraciones (si existe)
3. O editar directamente la tabla en la base de datos:
   ```sql
   UPDATE api_integrations 
   SET api_key = 'TU_TOKEN_REAL_AQUI',
       updated_at = NOW()
   WHERE service_name = 'payment_validator';
   ```

### 3. Probar la validación

Probar el endpoint de validación:
```bash
# Probar con curl:
curl -X POST http://localhost/reserve/api/validate_payment.php \
  -H "Content-Type: application/json" \
  -d '{"amount": 50.00, "reference": "123456"}'
```

## 🎯 Funcionalidades Implementadas

### API Endpoint: `api/validate_payment.php`

**Método:** POST
**Content-Type:** application/json

**Parámetros requeridos:**
```json
{
  "amount": 50.00,
  "reference": "123456"
}
```

**Parámetros opcionales:**
```json
{
  "mobile": "04141234567",
  "sender": "Nombre del Remitente",
  "method": "Pago Móvil",
  "date": "2024-01-20 15:30:00"
}
```

### Respuesta Exitosa (HTTP 200):
```json
{
  "success": true,
  "message": "Pago validado exitosamente",
  "data": {
    "amount_usd": 50.00,
    "bank_origin_name": "Banco de Venezuela",
    "bank_destiny_name": "Banesco",
    "method_name": "Pago Móvil",
    "amount": 50.00,
    "reference": "123456",
    "validated_at": "2024-01-20 15:32:45"
  }
}
```

### Respuesta de Error:
```json
{
  "success": false,
  "message": "Error en la validación del pago"
}
```

## 💻 Integración con el Frontend

### JavaScript Functions:

1. **`validatePagomovilPayment()`** - Valida un pago móvil
2. **`validatePaymentWithAPI()`** - Función genérica de validación
3. **`showValidationDetails()`** - Muestra detalles de validación

### Flujo de Validación:

1. Usuario ingresa referencia de 6 dígitos
2. Sistema llama a `api/validate_payment.php`
3. API externa valida el pago
4. Resultados se muestran al usuario
5. Botón de completar pedido se habilita

## 🎨 Interfaz de Usuario

### Formulario de Pago Móvil:
- Campo para monto en bolívares (calculado automáticamente)
- Campo para referencia (6 dígitos, validación en tiempo real)
- Botón de validación con estados de carga
- Sección de detalles de validación

### Estados Visuales:
- **✅ Validado**: Verde con icono de check
- **⏳ Validando**: Spinner de carga
- **❌ Error**: Rojo con icono de error
- **ℹ️ Información**: Azul con detalles del pago

## 🔒 Consideraciones de Seguridad

1. **Validación de Entrada**: 
   - Referencia debe ser exactamente 6 dígitos
   - Monto debe ser numérico y positivo
   - Sanitización de datos de entrada

2. **Manejo de Errores**:
   - Logs de errores para debugging
   - Mensajes de error genéricos al usuario
   - Validación de respuestas de API externa

3. **Rate Limiting**:
   - Considerar implementar límites de intentos
   - Cache de respuestas válidas por corto tiempo

## 🐛 Troubleshooting

### Problemas Comunes:

1. **"API Key no configurada"**
   - Verificar que el API key esté en la tabla `api_integrations`
   - Confirmar que no sea el placeholder "YOUR_API_TOKEN_HERE"

2. **"Error de conexión"**
   - Verificar que el endpoint URL sea correcto
   - Confirmar conectividad a internet

3. **"Referencia inválida"**
   - Asegurar que la referencia tenga 6 dígitos exactos
   - Verificar formato numérico

4. **"Configuración no encontrada"**
   - Confirmar que exista registro con `service_name = 'payment_validator'`
   - Verificar que `is_active = 1`

### Logs de Depuración:

Los errores se registran en:
- PHP error_log
- Consola del navegador (F12)
- Respuestas JSON de la API

## 📞 Soporte

Para problemas con la API externa:
- Contactar al proveedor del servicio de validación
- Verificar documentación de la API externa
- Revisar logs de errores detallados

## 🔄 Actualizaciones Futuras

Características planeadas:
- [ ] Cache de validaciones exitosas
- [ ] Historial de validaciones
- [ ] Múltiples proveedores de validación
- [ ] Dashboard de estadísticas de pagos
- [ ] Webhooks para notificaciones
