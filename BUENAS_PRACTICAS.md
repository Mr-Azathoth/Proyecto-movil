# Buenas Prácticas para Reparo
> Guía de referencia para desarrollar, asegurar y publicar este proyecto de forma profesional.
> Stack: PHP 8+, MySQL/MariaDB, JavaScript Vanilla, Apache (XAMPP → producción)

---

## Índice

1. [Estructura y organización del código](#1-estructura-y-organización-del-código)
2. [Control de versiones (Git)](#2-control-de-versiones-git)
3. [Seguridad en PHP y base de datos](#3-seguridad-en-php-y-base-de-datos)
4. [Seguridad en el frontend](#4-seguridad-en-el-frontend)
5. [Autenticación y sesiones](#5-autenticación-y-sesiones)
6. [API y validación de datos](#6-api-y-validación-de-datos)
7. [Base de datos](#7-base-de-datos)
7b. [Datos maestros vs datos de empresa](#7b-datos-maestros-vs-datos-de-empresa)
8. [QA y pruebas](#8-qa-y-pruebas)
9. [Preparación para producción](#9-preparación-para-producción)
10. [Monitoreo y logs](#10-monitoreo-y-logs)
11. [Checklist antes de publicar](#11-checklist-antes-de-publicar)

---

## 1. Estructura y organización del código

### Separación de responsabilidades
Cada archivo debe tener un único propósito. Evitar mezclar lógica de negocio, consultas SQL y HTML en el mismo archivo.

```
reparo/
├── includes/        # Configuración, helpers, funciones reutilizables
├── api/             # Solo endpoints (reciben → procesan → responden JSON)
├── assets/
│   ├── css/
│   └── js/
├── views/           # (futuro) Plantillas HTML separadas del PHP
└── logs/            # Archivos de log (NO públicos, excluir del servidor web)
```

### Nombrado consistente
- Archivos PHP: `snake_case.php`
- Variables PHP: `$snake_case`
- Funciones PHP: `camelCase()` o `snake_case()` — elegir uno y no mezclar
- Variables JS: `camelCase`
- Constantes: `MAYUSCULAS_CON_GUION`
- Tablas en DB: `snake_case` en plural (`reparaciones`, `usuarios`)

### Evitar código duplicado
Si la misma lógica aparece en 2+ lugares, extraerla a una función en `includes/`.

---

## 2. Control de versiones (Git)

### Repositorio
- **URL:** https://github.com/Mr-Azathoth/Proyecto-movil
- **Rama principal:** `main`
- **Inicializado:** 2026-06-24 — commit `a4293fa`

### Flujo de trabajo diario
```bash
# 1. Ver qué cambió
git status
git diff

# 2. Agregar solo los archivos modificados (nunca git add -A sin revisar)
git add api/reparaciones.php assets/js/app.js   # ejemplo

# 3. Commit con mensaje descriptivo
git commit -m "feat: descripción del cambio"

# 4. Subir a GitHub
git push
```

### Estructura de commits (Conventional Commits)
Formato: `tipo: descripción corta`

| Tipo | Cuándo usarlo |
|------|--------------|
| `feat` | Nueva funcionalidad |
| `fix` | Corrección de bug |
| `refactor` | Mejora de código sin cambiar comportamiento |
| `security` | Corrección de vulnerabilidad |
| `docs` | Documentación |
| `chore` | Tareas de mantenimiento (dependencias, config) |

Ejemplos:
```
feat: agregar filtro por fecha en listado de reparaciones
fix: corregir cálculo de total en presupuesto
security: reemplazar MD5 por bcrypt en autenticación
```

### Archivo .gitignore (configurado)
Los siguientes archivos están excluidos del repositorio:

```gitignore
# Configuración sensible — NUNCA subir
includes/config.php
.env
*.env

# Base de datos (backups y seeds)
*.sql
logs/
*.log

# Archivos del sistema
.DS_Store
Thumbs.db
desktop.ini

# Claude Code (sesiones internas)
.claude/

# Dependencias (por si se agregan)
vendor/
node_modules/
```

### Plantilla de configuración
`includes/config.example.php` está versionado como referencia. Al clonar el proyecto:
```bash
cp includes/config.example.php includes/config.php
# Editar config.php con las credenciales reales
```

**Regla de oro:** Nunca hacer commit de credenciales, contraseñas ni datos de conexión a base de datos. Verificar siempre con `git status` antes de hacer `git add`.

### Variables de entorno
Mover credenciales a un archivo `.env` o a variables de servidor Apache. Nunca hardcodearlas en el código.

```php
// En lugar de esto:
$dsn = "mysql:host=localhost;dbname=reparo_db";
$user = "root";
$pass = "mipassword123";

// Hacer esto:
$dsn  = "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
```

---

## 3. Seguridad en PHP y base de datos

### Nunca confiar en datos del usuario
Todo lo que llega por `$_GET`, `$_POST`, `$_COOKIE`, `$_SERVER` es potencialmente malicioso.

### Prepared statements — siempre
```php
// MAL — vulnerable a SQL Injection
$q = "SELECT * FROM reparaciones WHERE id = " . $_GET['id'];

// BIEN — prepared statement con PDO
$stmt = $pdo->prepare("SELECT * FROM reparaciones WHERE id = :id");
$stmt->execute([':id' => (int) $_GET['id']]);
```

### Tipos de datos en consultas
Forzar el tipo antes de usar en SQL:
```php
$id     = (int) $_GET['id'];         // Entero
$precio = (float) $_POST['precio']; // Decimal
$texto  = trim($_POST['nombre']);    // String limpio
```

### Proteger rutas y archivos sensibles
```apache
# En .htaccess — bloquear acceso directo a includes/
<Directory "/includes">
    Order deny,allow
    Deny from all
</Directory>
```

Los archivos en `includes/` nunca deben ser accesibles desde el navegador.

### Deshabilitar display_errors en producción
```ini
; php.ini o .htaccess en producción
display_errors = Off
log_errors = On
error_log = /ruta/a/logs/php_errors.log
```

Los errores de PHP no deben mostrarse al usuario — revelan estructura interna del sistema.

### Headers de seguridad HTTP
Agregar en cada respuesta PHP o via `.htaccess`:
```php
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
// En producción con HTTPS:
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
```

---

## 4. Seguridad en el frontend

### Escapar siempre al renderizar HTML desde JS
```javascript
// MAL — XSS si `nombre` contiene <script>
div.innerHTML = `<td>${data.nombre}</td>`;

// BIEN — escapar antes de insertar
function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
div.innerHTML = `<td>${escapeHtml(data.nombre)}</td>`;
```

### No exponer datos sensibles en el frontend
- No incluir contraseñas, tokens completos ni datos de otros usuarios en las respuestas JSON.
- La respuesta de la API debe retornar solo lo que el cliente necesita mostrar.

### Content Security Policy (CSP)
Limita qué recursos puede cargar la página, reduciendo el impacto de inyecciones:
```apache
# .htaccess
Header set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com;"
```

### HTTPS obligatorio en producción
Todas las comunicaciones deben ir cifradas. Con Let's Encrypt es gratuito.

---

## 5. Autenticación y sesiones

### Reemplazar MD5 por bcrypt
MD5 está roto criptográficamente. Para contraseñas siempre usar `password_hash()`:

```php
// Al crear/cambiar contraseña:
$hash = password_hash($password_plano, PASSWORD_BCRYPT);

// Al verificar login:
if (password_verify($password_plano, $hash_de_db)) {
    // login exitoso
}
```

### Regenerar ID de sesión tras login
Previene ataques de session fixation:
```php
session_start();
// ... verificar credenciales ...
session_regenerate_id(true); // Siempre después de autenticar
$_SESSION['user_id'] = $usuario['id'];
```

### Configurar sesiones de forma segura
```php
// Antes de session_start()
ini_set('session.cookie_httponly', 1);    // JS no puede leer la cookie
ini_set('session.cookie_secure', 1);      // Solo HTTPS (en producción)
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
```

### Tiempo de expiración de sesión
Implementar logout automático por inactividad:
```php
$timeout = 3600; // 1 hora
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_destroy();
    header('Location: /index.php?expired=1');
    exit;
}
$_SESSION['last_activity'] = time();
```

### Protección CSRF en formularios
Todo formulario que modifique datos debe incluir un token CSRF:
```php
// Generar token al cargar formulario
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Validar al procesar
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    die('Token inválido');
}
```

### Limitar intentos de login (rate limiting básico)
```php
// Guardar en sesión o DB los intentos fallidos por IP
$intentos = $_SESSION['login_intentos'] ?? 0;
if ($intentos >= 5) {
    // Bloquear por N minutos o mostrar CAPTCHA
}
```

---

## 6. API y validación de datos

### Validar en el servidor, siempre
La validación del frontend (JS) es para UX, no para seguridad. El backend debe validar todo independientemente.

```php
// Ejemplo de validación robusta en API
$nombre = trim($_POST['nombre'] ?? '');
$precio = $_POST['precio'] ?? null;

$errores = [];
if (empty($nombre))                  $errores[] = "Nombre requerido";
if (strlen($nombre) > 100)           $errores[] = "Nombre muy largo";
if (!is_numeric($precio) || $precio < 0) $errores[] = "Precio inválido";

if (!empty($errores)) {
    http_response_code(422);
    echo json_encode(['error' => $errores]);
    exit;
}
```

### Códigos HTTP correctos
```
200 OK              — Respuesta exitosa (GET)
201 Created         — Recurso creado (POST)
400 Bad Request     — Datos inválidos enviados por el cliente
401 Unauthorized    — No autenticado
403 Forbidden       — Autenticado pero sin permisos
404 Not Found       — Recurso no existe
422 Unprocessable   — Datos con formato válido pero contenido inválido
500 Server Error    — Error interno (nunca revelar detalles al cliente)
```

### No revelar errores internos en las respuestas
```php
// MAL — expone estructura de la DB
echo json_encode(['error' => $e->getMessage()]);

// BIEN — log interno, mensaje genérico al cliente
error_log($e->getMessage());
echo json_encode(['error' => 'Error interno, intente más tarde']);
```

### Verificar método HTTP
```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
```

---

## 7. Base de datos

### Índices en columnas frecuentemente consultadas
```sql
-- Las búsquedas por nombre de cliente, estado y empresa son frecuentes
ALTER TABLE reparaciones ADD INDEX idx_estado (estado);
ALTER TABLE reparaciones ADD INDEX idx_empresa (id_empresa);
ALTER TABLE reparaciones ADD INDEX idx_cliente (nombre_cliente);
```

### Usar ENUM para campos con valores fijos
```sql
-- En lugar de VARCHAR libre
estado ENUM('Ingresado','En Reparación','Reparado','Entregado','Garantía') NOT NULL
```

Previene valores inválidos a nivel de base de datos.

### Usuario de base de datos con permisos mínimos
No usar `root` en la aplicación. Crear un usuario con solo los permisos necesarios:
```sql
CREATE USER 'reparo_app'@'localhost' IDENTIFIED BY 'password_fuerte';
GRANT SELECT, INSERT, UPDATE ON reparo_db.* TO 'reparo_app'@'localhost';
-- Sin DELETE ni DROP para proteger datos críticos
```

### Soft delete en lugar de DELETE físico
```sql
-- En lugar de borrar registros, marcarlos como inactivos
ALTER TABLE reparaciones ADD COLUMN activo TINYINT(1) DEFAULT 1;
-- UPDATE reparaciones SET activo = 0 WHERE id = ?
```

Permite recuperar datos borrados por error.

### Backups regulares
Antes de cualquier despliegue importante:
```bash
mysqldump -u root -p reparo_db > backup_$(date +%Y%m%d).sql
```

---

## 7b. Datos maestros vs datos de empresa

### Concepto
**Datos maestros** (catálogos compartidos): tablas que definen entidades universales del negocio, independientes de cualquier empresa/cliente. No tienen `id_empresa`. Ejemplos: marcas, modelos, categorías de producto.

**Datos transaccionales**: registros que pertenecen a una empresa específica. Siempre tienen `id_empresa`. Ejemplos: inventario, reparaciones, usuarios.

### Cuándo usar cada uno

| Tipo de dato | Patrón | Ejemplos |
|---|---|---|
| Catálogo compartido | Sin `id_empresa` | `marcas_cat`, `modelos_cat`, `categorias_cat` |
| Dato de empresa | Con `id_empresa` | `inventario`, `reparaciones`, `usuarios` |

La regla práctica: si el dato tiene sentido para cualquier taller (una marca "Samsung" es universal), es dato maestro. Si pertenece a un cliente específico (el stock de una tienda), es transaccional.

### Patrón de tablas para catálogos globales
Sufijo `_cat` para catálogos globales. Siempre incluir `activo` para soft-delete:

```sql
CREATE TABLE marcas_cat (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL UNIQUE,
    activo  TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE modelos_cat (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_marca  INT UNSIGNED NOT NULL,
    nombre    VARCHAR(100) NOT NULL,
    activo    TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (id_marca) REFERENCES marcas_cat(id),
    UNIQUE KEY uq_marca_modelo (id_marca, nombre)
);
```

El inventario referencia al catálogo pero pertenece a la empresa:
```sql
CREATE TABLE inventario (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_empresa  INT UNSIGNED NOT NULL,
    id_modelo   INT UNSIGNED NOT NULL,  -- referencia a catálogo global
    stock       INT NOT NULL DEFAULT 0,
    FOREIGN KEY (id_modelo) REFERENCES modelos_cat(id)
);
```

### Acceso controlado
- **Consultar** datos maestros: cualquier usuario autenticado puede hacerlo (son datos públicos del sistema).
- **Modificar** datos maestros: solo admins, o usuarios autenticados con log de auditoría obligatorio.

```php
// En endpoints que modifican catálogos globales
if ($_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Solo administradores pueden modificar catálogos']);
    exit;
}
// Alternativa: permitir a cualquier autenticado pero loguear siempre
logAccion($pdo, 'nueva_marca', null, ['nombre' => $nombre]);
```

### Evitar duplicación de datos maestros
Al insertar marcas o modelos, usar idempotencia para no crear duplicados:

```sql
-- Opción 1: INSERT IGNORE (si hay UNIQUE KEY)
INSERT IGNORE INTO marcas_cat (nombre) VALUES ('Samsung');

-- Opción 2: SELECT primero, INSERT si no existe
SELECT id FROM marcas_cat WHERE nombre = ? LIMIT 1;
-- Si no retorna filas, entonces INSERT
```

En PHP:
```php
function obtenerOCrearMarca(PDO $pdo, string $nombre): int {
    $stmt = $pdo->prepare("SELECT id FROM marcas_cat WHERE nombre = ? LIMIT 1");
    $stmt->execute([$nombre]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];

    $pdo->prepare("INSERT INTO marcas_cat (nombre) VALUES (?)")->execute([$nombre]);
    return (int) $pdo->lastInsertId();
}
```

---

## 8. QA y pruebas

> URL de prueba local: **http://localhost/reparo/app.php**
> Siempre probar después de cada cambio antes de continuar al siguiente.

---

### 8.1 Prueba de inicio de sesión

Ir a `http://localhost/reparo/index.php`

| Caso | Acción | Resultado esperado |
|---|---|---|
| Login correcto | Usuario y contraseña válidos | Redirige a `app.php`, muestra nombre de usuario |
| Password incorrecto | Contraseña errónea | Mensaje de error, no entra |
| Bloqueo por intentos | 5 intentos fallidos seguidos | Mensaje de espera de 15 minutos |
| Sesión expirada | Esperar 1 hora sin actividad | Redirige a login con `?expired=1` |
| Logout | Clic en ícono de salida | Redirige a login, sesión destruida |

---

### 8.2 Prueba de la vista Servicios

Ir a `http://localhost/reparo/app.php` (vista activa por defecto).

**Tabla de servicios:**
- [ ] Se cargan los registros correctamente
- [ ] Las tarjetas de estadísticas muestran conteos correctos
- [ ] Hacer clic en una tarjeta filtra la tabla por ese estado
- [ ] El buscador filtra en tiempo real al escribir (≥300ms debounce)
- [ ] El filtro de estado funciona en conjunto con el buscador
- [ ] El botón "Limpiar filtros" aparece cuando no hay resultados y funciona
- [ ] El **doble clic** en una fila abre el modal de edición
- [ ] El **botón lápiz** (ícono editar) abre el modal con un solo clic
- [ ] El **botón WhatsApp** abre `wa.me/NÚMERO` en pestaña nueva sin mensaje predefinido

**Modal "Registrar ingreso de equipo":**
- [ ] Botón "Ingresar equipo" abre el modal completamente visible sin scroll
- [ ] Los 4 campos del cliente están en una fila (Nombre, Teléfono, RUT, Tipo)
- [ ] El select de Marca carga el catálogo con buscador funcional
- [ ] Escribir en el buscador filtra marcas en tiempo real
- [ ] Seleccionar una marca carga los modelos correspondientes automáticamente
- [ ] Seleccionar "+ Agregar nueva marca..." muestra campo de texto
- [ ] Seleccionar "+ Agregar nuevo modelo..." muestra campo de texto
- [ ] Enviar sin marca/modelo muestra error toast
- [ ] Enviar con todos los campos crea el registro y recarga la tabla
- [ ] Cancelar/X cierra el modal sin crear registro

**Modal de edición (doble clic en fila):**
- [ ] El modal es visible al 100% de zoom sin hacer scroll
- [ ] Se muestran todos los datos del servicio (cliente, equipo, falla)
- [ ] La línea de tiempo carga los cambios históricos
- [ ] Cambiar estado y guardar actualiza la tabla
- [ ] Solo admins pueden editar el valor monetario
- [ ] Agregar nota técnica la guarda y aparece en la línea de tiempo
- [ ] El botón WhatsApp del header abre el chat directo

---

### 8.3 Prueba de la vista Inventario

Clic en "Inventario" en el sidebar.

- [ ] La tabla carga todos los repuestos
- [ ] El buscador filtra por nombre, marca o modelo
- [ ] El stock se colorea: verde (>5), naranja (1-5), rojo (0)
- [ ] Admin: botones `+` y `−` modifican el stock correctamente
- [ ] Admin: botón "Agregar repuesto" abre el modal
- [ ] El datalist de marca en el modal sugiere marcas del catálogo
- [ ] Guardar un repuesto nuevo lo agrega a la tabla

---

### 8.4 Prueba de seguridad básica

Ejecutar con el navegador en modo incógnito o con DevTools.

```
# Acceso directo a la API sin sesión → debe devolver 401
GET http://localhost/reparo/api/reparaciones.php

# Intentar inyección SQL en búsqueda
Buscar: ' OR '1'='1

# Intentar XSS en nombre del cliente
Nombre: <script>alert(1)</script>
→ Debe guardarse y mostrarse como texto, sin ejecutar el script

# Acceso directo a archivos protegidos
http://localhost/reparo/includes/config.php  → 403 o página en blanco
http://localhost/reparo/db_updates.sql       → 403
```

---

### 8.5 Prueba de CSP (Content Security Policy)

Abrir DevTools → Consola antes de cada prueba de interfaz.

- [ ] No debe aparecer ningún error de tipo `Refused to execute inline script`
- [ ] No debe aparecer ningún error de tipo `Refused to load script`
- [ ] Todo el JavaScript carga desde `/reparo/assets/js/app.js`

> **Regla:** Si aparece un error CSP en consola tras un cambio, el cambio introdujo un `onclick=`, `onsubmit=` u otro handler inline. Moverlo siempre a `addEventListener` en `app.js`.

---

### 8.6 Protocolo de prueba al hacer un cambio de código

Cada vez que se modifica `app.js`, `app.php` o un archivo de la API:

1. **Guardar el archivo**
2. **Hacer hard-reload** en el navegador: `Ctrl+Shift+R` (limpia caché)
3. **Abrir DevTools → Consola** y verificar que no hay errores en rojo
4. **Probar el flujo afectado** con los casos del apartado correspondiente (8.2, 8.3 o 8.4)
5. **Probar que los flujos NO afectados siguen funcionando** (regresión mínima)

> Si un cambio en el modal de ingreso se hace, probar también el modal de edición — comparten código de marca/modelo.

---

### 8.7 Datos de prueba

El archivo `seed_test.php` inserta 10 reparaciones y 10 repuestos de inventario.  
**Solo ejecutar una vez** desde `http://localhost/reparo/seed_test.php` (solo funciona desde localhost).

Para limpiar y volver a sembrar:
```sql
-- En phpMyAdmin → reparo_db → SQL
DELETE FROM observaciones WHERE id_registro >= 1;
DELETE FROM historial WHERE id_reparacion >= 1;
DELETE FROM reparaciones WHERE ingresado_por = 'admin';
DELETE FROM inventario WHERE id_empresa = 1;
ALTER TABLE reparaciones AUTO_INCREMENT = 1;
ALTER TABLE inventario AUTO_INCREMENT = 1;
```

---

### 8.8 Herramientas recomendadas

| Herramienta | Para qué usarla |
|---|---|
| DevTools → Consola | Errores JS, CSP violations, errores de red |
| DevTools → Network | Ver request/response de cada llamada a la API |
| DevTools → Application → Cookies | Verificar que la cookie de sesión tiene `HttpOnly` y `SameSite` |
| phpMyAdmin | Revisar datos insertados, correr SQL de limpieza |
| OWASP ZAP (gratuito) | Scan de seguridad antes de publicar a producción |

---

## 9. Preparación para producción

### Diferencias entre local y producción

| Aspecto | Local (XAMPP) | Producción |
|---------|--------------|------------|
| `display_errors` | On | **Off** |
| `error_log` | Opcional | **Obligatorio** |
| Contraseñas DB | Simples | **Complejas y únicas** |
| HTTPS | No | **Obligatorio** |
| Debug mode | Activo | **Desactivado** |
| `root` en DB | Aceptable | **Nunca** |

### Hardening de Apache (.htaccess)
```apache
# Ocultar versión de Apache y PHP
ServerTokens Prod
ServerSignature Off

# Deshabilitar listado de directorios
Options -Indexes

# Bloquear acceso a archivos sensibles
<FilesMatch "\.(sql|log|env|md|gitignore)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Forzar HTTPS (en producción)
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### Variables de entorno en producción
En Apache, agregar al `VirtualHost` o al `.htaccess` del servidor:
```apache
SetEnv DB_HOST     "localhost"
SetEnv DB_NAME     "reparo_db"
SetEnv DB_USER     "reparo_app"
SetEnv DB_PASS     "contraseña_segura_unica"
SetEnv APP_ENV     "production"
```

### Estructura de permisos de archivos en Linux
```bash
# Archivos PHP: solo lectura para el servidor web
find /var/www/reparo -name "*.php" -exec chmod 644 {} \;

# Directorios
find /var/www/reparo -type d -exec chmod 755 {} \;

# Archivos de configuración: restrictivos
chmod 640 includes/config.php

# Carpeta de uploads (si se agrega): escritura para el servidor
chmod 750 uploads/
chown www-data:www-data uploads/
```

---

## 10. Monitoreo y logs

### Log de acciones críticas
Registrar eventos importantes en base de datos o archivo:
```php
function logAccion(PDO $pdo, string $accion, int $id_reparacion = null): void {
    $stmt = $pdo->prepare("
        INSERT INTO log_acciones (id_usuario, accion, id_reparacion, ip, fecha)
        VALUES (:uid, :accion, :idrep, :ip, NOW())
    ");
    $stmt->execute([
        ':uid'    => $_SESSION['user_id'],
        ':accion' => $accion,
        ':idrep'  => $id_reparacion,
        ':ip'     => $_SERVER['REMOTE_ADDR'],
    ]);
}

// Uso:
logAccion($pdo, 'cambio_estado', $id);
logAccion($pdo, 'login_exitoso');
logAccion($pdo, 'login_fallido');
```

### Qué loguear
- Logins exitosos y fallidos (con IP)
- Cambios de estado en reparaciones
- Modificaciones de precio
- Acciones de admin (crear usuarios, cambiar permisos)
- Errores de validación repetidos (pueden indicar un ataque)

### Qué NO loguear
- Contraseñas (ni hasheadas)
- Datos sensibles del cliente (RUT, contraseña del dispositivo) en texto plano en logs

---

## 11. Checklist antes de publicar

### Seguridad
- [ ] Contraseñas hasheadas con bcrypt (`password_hash`)
- [ ] `display_errors = Off` en producción
- [ ] HTTPS configurado con certificado válido
- [ ] Headers de seguridad HTTP activos
- [ ] Protección CSRF en todos los formularios
- [ ] Usuario de DB con permisos mínimos (no root)
- [ ] Archivos `.env`, `*.sql`, `*.log` inaccesibles desde el navegador
- [ ] Listado de directorios deshabilitado en Apache
- [ ] Validación server-side en todos los endpoints

### Calidad
- [ ] Todos los flujos críticos probados manualmente
- [ ] Casos borde probados (vacíos, caracteres especiales, IDs inválidos)
- [ ] Sin `console.log` de depuración en el JS final
- [ ] Sin `var_dump` / `print_r` en el PHP final
- [ ] Mensajes de error amigables para el usuario (no técnicos)

### Base de datos
- [ ] Backup completo antes del deploy
- [ ] Índices creados en columnas de búsqueda frecuente
- [ ] Credenciales de producción distintas a las de desarrollo
- [ ] Schema de producción revisado (sin datos de prueba)

### Operacional
- [ ] Logs de error configurados y accesibles
- [ ] Forma de hacer rollback documentada
- [ ] Contraseñas de producción guardadas en gestor de contraseñas seguro (no en WhatsApp ni email)

---

*Documento vivo — actualizar a medida que el proyecto evoluciona.*
