# Instrucciones para subir el proyecto a GitHub

## Estado actual ✅
- ✅ Git inicializado
- ✅ Archivos agregados y commitados (101 archivos)
- ✅ GitHub CLI instalado
- ✅ Configuración de Git completada (usuario: danilosilv22@gmail.com)

## Pasos a seguir después del reinicio:

### 1. Verificar GitHub CLI
```bash
gh --version
```

### 2. Autenticarse con GitHub
```bash
gh auth login
```
- Selecciona "GitHub.com"
- Selecciona "HTTPS"
- Selecciona "Login with a web browser"
- Copia el código que aparece
- Presiona Enter para abrir el navegador
- Pega el código en GitHub
- Autoriza GitHub CLI

### 3. Crear el repositorio privado en GitHub
```bash
gh repo create sistema-pedidos-restaurante --private --source=. --remote=origin --push
```

### 4. Verificar que se subió correctamente
```bash
git remote -v
gh repo view
```

## Comandos alternativos si hay problemas:

### Si prefieres crear el repo manualmente:
1. Ve a https://github.com/new
2. Nombre: `sistema-pedidos-restaurante`
3. Descripción: `Sistema completo de pedidos para restaurante con PHP y MySQL`
4. Selecciona "Private"
5. NO inicialices con README (ya tenemos uno)
6. Crea el repositorio

### Luego conecta el repositorio local:
```bash
git remote add origin https://github.com/TU_USUARIO/sistema-pedidos-restaurante.git
git branch -M main
git push -u origin main
```

## Información del proyecto:
- **Nombre sugerido**: sistema-pedidos-restaurante
- **Descripción**: Sistema completo de pedidos para restaurante con PHP y MySQL
- **Tipo**: Repositorio privado
- **Archivos incluidos**: 101 archivos
- **Tecnologías**: PHP, MySQL, JavaScript, Bootstrap, API REST

## Estructura del proyecto subido:
```
sistema-pedidos-restaurante/
├── admin/              # Panel de administración
├── api/               # API REST endpoints
├── assets/            # CSS, JS, imágenes
├── config/            # Configuración de base de datos
├── uploads/           # Carpetas para archivos subidos
├── index.php          # Página principal
├── README.md          # Documentación
├── setup.sql          # Script de base de datos
└── .gitignore         # Archivos ignorados por Git
```

## Notas importantes:
- Los archivos sensibles están en .gitignore (config/database.php)
- Los archivos de desarrollo/testing están excluidos
- Las carpetas de uploads mantienen su estructura con .gitkeep
- El repositorio será privado por defecto

¡Después del reinicio, ejecuta los comandos en orden y tu proyecto estará en GitHub!
