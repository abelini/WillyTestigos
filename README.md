# Testigos

Aplicación web en PHP 8.5 que recibe capturas JPEG/PNG, genera un PDF mensual en A4 horizontal, lo envía por Telegram y correo, y elimina las fotos originales.

La portada dice `TESTIGOS DEL MES DE {MES}` con el subtítulo `Portal: Proyecto Z`. Las fotos empiezan en la segunda hoja, con una cuadrícula de 4×2 (8 por hoja), márgenes de 6 mm y escala proporcional para no deformar las imágenes.

## Características

- Un solo trabajo activo a la vez, con historial de trabajos anteriores.
- Selector de los 12 meses. El mes detectado se preselecciona; los días 1 y 2 usan el mes anterior (enero 1–2 usa diciembre).
- Subida secuencial foto por foto con barra de progreso y validación de MIME, tamaño y dimensiones.
- Procesamiento en segundo plano sin cron. Si el servidor no puede lanzar el worker CLI, se procesa en línea al cerrar la respuesta.
- Envío con Telegram Bot API (`sendDocument`) y SMTP con PHPMailer.
- Limpieza automática al entrar a la aplicación, sin tareas programadas.
- Estado en `job.json` con bloqueos de archivo; no requiere base de datos.
- Contraseña con `password_hash`, sesión segura, CSRF y límite de intentos por IP.
- `storage/` fuera del docroot, secretos en `.env` y cabeceras de seguridad.

## Requisitos

- PHP 8.5 con `curl`, `fileinfo`, `gd`, `mbstring`, `zip` y `zlib`.
- Composer 2.
- Servidor web con reescritura de URLs y docroot en `public/` (OpenLiteSpeed, Apache o Nginx).
- Opcional: bot de Telegram y una cuenta SMTP.

No se necesita Node, SQLite ni ImageMagick.

## Instalación

```bash
git clone <url-del-repositorio> willy
cd willy
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

Genera el hash de la contraseña:

```bash
php bin/hash-password.php
```

Copia el hash en `APP_PASSWORD_HASH` y completa el resto de `.env`. Después ajusta permisos:

```bash
chown -R usuario-del-servidor:grupo-del-servidor storage
chmod -R 750 storage
chmod 600 .env
```

En Windows, asegúrate de que el usuario de OpenLiteSpeed/XAMPP pueda escribir en `storage/`.

## Variables de entorno

| Variable | Descripción | Valor por defecto |
| --- | --- | --- |
| `APP_ENV` | `production`, `development` o `testing`. | `production` |
| `APP_URL` | URL pública completa. Con `https` la cookie de sesión usa `Secure`. | obligatorio |
| `APP_TIMEZONE` | Zona IANA usada para el mes detectado y las fechas. | `America/Mazatlan` |
| `APP_PASSWORD_HASH` | Hash generado con `bin/hash-password.php`. | obligatorio |
| `COVER_SUBTITLE` | Subtítulo de la portada. | `Portal: Proyecto Z` |
| `STORAGE_PATH` | Ruta de estado, relativa al proyecto o absoluta. | `storage` |
| `MAX_FILES` | Máximo de capturas por trabajo. | `200` |
| `MAX_FILE_MB` | Tamaño máximo por captura en MB. | `25` |
| `MAX_TOTAL_MB` | Tamaño máximo total del trabajo en MB. | `1024` |
| `MAX_PDF_MB` | Límite de referencia para el PDF. | `16` |
| `JPEG_QUALITY` | Calidad de las imágenes procesadas (40–95). | `82` |
| `WORKER_MODE` | `auto`, `background` o `inline`. | `auto` |
| `PHP_CLI_BINARY` | Binario PHP para el worker. Vacío usa `PHP_BINARY`. | vacío |
| `TELEGRAM_BOT_TOKEN` | Token del bot de Telegram. | vacío |
| `TELEGRAM_CHAT_ID` | Chat o canal de destino. | vacío |
| `MAIL_HOST` | Servidor SMTP. | vacío |
| `MAIL_PORT` | Puerto SMTP. | `587` |
| `MAIL_USER` | Usuario SMTP. | vacío |
| `MAIL_PASS` | Contraseña SMTP. | vacío |
| `MAIL_ENCRYPTION` | `tls`, `starttls`, `ssl`, `smtps`, `none` o vacío. | `tls` |
| `MAIL_FROM` | Remitente. Vacío usa `MAIL_USER`. | vacío |
| `MAIL_FROM_NAME` | Nombre del remitente. | `Testigos` |
| `MAIL_TO` | Destinatarios separados por comas. | vacío |

`MAIL_ENCRYPTION` acepta `tls`/`starttls` para STARTTLS (normalmente puerto 587), `ssl`/`smtps` para TLS implícito (normalmente puerto 465), y `none` para SMTP sin cifrar.

Si `TELEGRAM_*` o `MAIL_*` están incompletos, ese canal se marca como omitido y el PDF queda disponible para descarga.

## Desarrollo local

```bash
composer serve
```

Abre `http://127.0.0.1:8000`. El comando usa `bin/dev-router.php` para que las rutas de la API funcionen con el servidor embebido.

Para ejecutar todas las verificaciones:

```bash
composer check
```

El comando ejecuta PHP-CS-Fixer, PHPStan, PHPUnit y los chequeos propios de `null`, FQDN y funciones fuera de sus wrappers.

## Flujo de trabajo

1. El usuario inicia sesión con la contraseña configurada.
2. Elige el mes y selecciona las capturas.
3. Cada foto se sube en una solicitud independiente, en el orden seleccionado.
4. Al terminar, se crea el trabajo y se lanza el worker.
5. El worker procesa las imágenes, genera el PDF, borra las fotos y envía el documento.
6. El PDF queda disponible durante una hora. Al expirar, la limpieza lo elimina.
7. La limpieza se ejecuta como máximo cada 5 minutos al abrir la aplicación.

El PDF nunca agrega una página final vacía: la portada es la hoja 1 y las fotos empiezan en la hoja 2, con `ceil(n / 8)` hojas adicionales.

## Estructura del proyecto

```text
app/
  Domain/       Enumeraciones y modelos inmutables
  Exception/    Excepciones de aplicación
  Http/         Request, Response, Router, Application y controladores
  Repository/   Persistencia de trabajos en job.json
  Service/      Subida, imágenes, PDF, entregas, limpieza y rate limit
  Support/      Config, entorno, archivos, sesión, CSRF, logger y procesos
bin/            Worker CLI, generador de hash y chequeos
public/         Docroot: index.php, assets y .htaccess
resources/      Vistas PHP
storage/        Estado, sesiones, logs, PDFs y pruebas
tests/          PHPUnit
docs/           Guía de despliegue
```

## Seguridad

- No se guardan contraseñas en texto plano.
- La sesión regenera el identificador al entrar y usa `HttpOnly`, `SameSite=Lax` y `Secure` cuando `APP_URL` es HTTPS.
- Todas las acciones de escritura verifican el token CSRF.
- El login se bloquea durante 15 minutos tras 5 fallos desde la misma IP; la IP se guarda hasheada con SHA-256.
- El docroot es `public/`; `.env` y `storage/` quedan fuera del alcance del servidor web.
- Las capturas se validan por MIME real con `finfo` y por dimensiones antes de guardarse.
- Los archivos se escriben de forma atómica y los trabajos usan bloqueos para evitar ejecuciones simultáneas.

## Despliegue

Consulta [docs/OPENLITESPEED.md](docs/OPENLITESPEED.md) para la configuración de OpenLiteSpeed, permisos, worker, SMTP, Telegram y solución de problemas.

## Git

El repositorio local usa la rama `main`. Para publicarlo:

```bash
git remote add origin git@github.com:usuario/willy.git
git push -u origin main
```

El archivo `.env`, `vendor/`, `storage/` y los PDFs generados están excluidos por `.gitignore`.
