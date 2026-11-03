# Despliegue en OpenLiteSpeed

Esta guía asume una instalación de OpenLiteSpeed con PHP 8.5 (`lsphp85`) y el proyecto en `/var/www/willy`.

## 1. Preparar el proyecto

```bash
cd /var/www
git clone <url-del-repositorio> willy
cd willy
composer install --no-dev --optimize-autoloader
cp .env.example .env
php bin/hash-password.php
```

Edita `.env` con la URL real, el hash de la contraseña, las credenciales SMTP y las de Telegram.

## 2. Permisos

El usuario que ejecuta OpenLiteSpeed debe poder leer el proyecto y escribir en `storage/`. El nombre puede variar (`nobody`, `lsadm`, `www-data`); ajústalo según tu instalación.

```bash
chown -R nobody:nogroup /var/www/willy/storage
chmod -R 750 /var/www/willy/storage
chown nobody:nogroup /var/www/willy/.env
chmod 600 /var/www/willy/.env
```

No des permisos de escritura a `app/`, `public/` ni `vendor/`.

## 3. Virtual host

El docroot debe apuntar a `public/`, nunca a la raíz del proyecto.

```apacheconf
virtualhost willy {
  vhRoot                  /var/www/willy
  configFile              $SERVER_ROOT/conf/vhosts/willy/vhconf.conf
  allowSymbolLink         1
  enableScript            1
  restrained              1
  documentRoot            /var/www/willy/public

  rewrite  {
    enable                1
    autoLoadHtaccess      1
  }

  index  {
    useServer             0
    indexFiles            index.php
  }

  scriptHandler lsphp {
    add                   lsapi:lsphp85
  }

  phpIniOverride  {
    php_value upload_max_filesize 25M
    php_value post_max_size 30M
    php_value max_execution_time 300
    php_value memory_limit 512M
    php_value max_file_uploads 20
  }

  errorlog $VH_ROOT/logs/error.log {
    useServer             0
    logLevel              ERROR
    rollingSize           10M
  }

  accesslog $VH_ROOT/logs/access.log {
    useServer             0
    rollingSize           10M
    keepDays              30
  }
}
```

Si no usas `autoLoadHtaccess`, aplica estas reglas manualmente:

```apacheconf
rewrite  {
  enable 1
  rules <<<END_rules
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
END_rules
}
```

Comprueba que el handler `lsphp85` existe en `$SERVER_ROOT/conf/httpd_config.conf` y que la extensión `lsphp85` está instalada.

## 4. HTTPS

Configura el certificado en el listener de OpenLiteSpeed y usa `APP_URL=https://tu-dominio.com`. Con `https`, la aplicación marca la cookie de sesión como `Secure` y envía HSTS.

No publiques la aplicación solo por HTTP en producción: la contraseña y el token CSRF viajan en las solicitudes.

## 5. Worker sin cron

`WORKER_MODE=auto` intenta lanzar `bin/worker.php` en segundo plano al iniciar un trabajo:

```bash
nohup /usr/local/lsws/lsphp85/bin/php /var/www/willy/bin/worker.php <job-id> >> <job-dir>/worker.log 2>&1 &
```

Requisitos:

- La función `exec` no debe estar en `disable_functions`.
- El binario `nohup` debe existir.
- `PHP_CLI_BINARY` debe apuntar al PHP CLI correcto, por ejemplo `/usr/local/lsws/lsphp85/bin/php`.

Verifica la ruta con:

```bash
/usr/local/lsws/lsphp85/bin/php -v
/usr/local/lsws/lsphp85/bin/php -m | grep -E 'gd|curl|fileinfo|mbstring'
```

Si el servidor no puede lanzar procesos, deja `WORKER_MODE=auto` o usa `inline`. En modo `inline` la respuesta HTTP se cierra primero con `litespeed_finish_request()` y el procesamiento continúa en la misma solicitud. En ese caso `max_execution_time` debe ser suficiente (300 segundos o más).

No se necesita cron. La limpieza se ejecuta al abrir la aplicación, como máximo una vez cada 5 minutos, y elimina trabajos vencidos, sesiones antiguas y recorta el log.

## 6. Telegram

1. Crea un bot con [@BotFather](https://t.me/BotFather) y copia el token en `TELEGRAM_BOT_TOKEN`.
2. Inicia una conversación con el bot o añádelo al canal.
3. Obtén el `chat_id` y colócalo en `TELEGRAM_CHAT_ID`.

La aplicación usa `sendDocument`. Si el PDF supera el límite del bot, Telegram rechazará el envío y el trabajo mostrará el error; el PDF seguirá disponible para descarga durante una hora.

## 7. Correo SMTP

Configura `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM` y `MAIL_TO`.

- Puerto 587 con STARTTLS: `MAIL_ENCRYPTION=tls` o `starttls`.
- Puerto 465 con TLS implícito: `MAIL_ENCRYPTION=ssl` o `smtps`.
- SMTP sin cifrar (solo red interna): `MAIL_ENCRYPTION=none`.

`MAIL_TO` acepta varias direcciones separadas por comas. Si `MAIL_FROM` está vacío se usa `MAIL_USER`.

Algunos proveedores exigen que el remitente coincida con la cuenta autenticada; en ese caso deja `MAIL_FROM` vacío.

## 8. Verificación

```bash
cd /var/www/willy
composer check
php bin/hash-password.php
```

Después del despliegue:

1. Abre `https://tu-dominio.com/login` e inicia sesión.
2. Genera un PDF con dos o tres capturas.
3. Confirma que el PDF se descarga, que Telegram y el correo lo reciben, y que `storage/jobs/<id>/originals` quedó vacío.
4. Revisa `storage/logs/app.log` y `storage/jobs/<id>/worker.log` si algo falla.

## 9. Mantenimiento

- `systemctl restart lsws` después de cambiar la configuración del virtual host.
- Los PDFs se conservan una hora; no hace falta borrarlos a mano.
- El log de la aplicación se recorta a 5 MB.
- Las sesiones inactivas se eliminan después de 24 horas.
- Para reprocesar un trabajo atascado, ejecuta manualmente:
  ```bash
  /usr/local/lsws/lsphp85/bin/php /var/www/willy/bin/worker.php <job-id>
  ```

## 10. Solución de problemas

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| `Error de configuración` al abrir | Falta `APP_URL` o `APP_PASSWORD_HASH`, o `.env` no es legible | Revisa `.env` y permisos |
| `No se pudo crear el directorio` | `storage/` sin permisos | Ajusta propietario y `chmod 750` |
| El trabajo queda en `queued` | El worker no se lanzó y el modo es `background` | Revisa `exec`, `nohup` y `PHP_CLI_BINARY`; usa `auto` o `inline` |
| Error 404 en `/api/...` | Falta la reescritura de URLs | Activa `autoLoadHtaccess` o aplica las reglas |
| Telegram no envía | Token, chat o límite de tamaño | Revisa `storage/logs/app.log` |
| El correo no llega | Puerto, cifrado o remitente rechazado | Prueba `tls`/`ssl` y revisa el error del trabajo |
| Las fotos no suben | Límites de PHP | Ajusta `upload_max_filesize` y `post_max_size` |
| El PDF tarda demasiado | Procesamiento en línea con muchas fotos | Aumenta `max_execution_time` o habilita el worker CLI |
| La sesión no persiste | `storage/sessions` sin permisos o cookie `Secure` sobre HTTP | Corrige permisos o usa HTTPS |
