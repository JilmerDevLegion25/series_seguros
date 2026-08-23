# Portal de Cancelaciones Series Seguros

Aplicacion Laravel del proyecto documentado en `docs/`.

Esta entrega corresponde a **SPRINT 01 QA/UAT**. El objetivo es que AssisPrex pueda clonar o descargar el proyecto y validarlo en un servidor con **PHP 8.3 + MySQL 8.0**, sin ejecutar Composer ni Node en el servidor.

## Contrato De Runtime

- PHP 8.3.
- Laravel 13.x.
- MySQL 8.0.
- `vendor/` debe viajar con el artefacto de entrega.
- El servidor no necesita Composer.
- El servidor no necesita Node, npm ni un build de assets.
- El DocumentRoot del virtual host debe apuntar a `/public`.
- Docker es solo el entorno local oficial de desarrollo/QA en Windows.

## Archivos De Entrega

- `.env` no debe versionarse ni compartirse.
- `.env.example` documenta variables sin secretos reales.
- `vendor/` no esta ignorado por `.gitignore` y debe incluirse en el artefacto Sprint 01.
- `public/assets/app.css`, `public/assets/app.js`, `public/assets/favicon.svg` y `public/img/*` son assets locales versionables.
- `storage/` y `bootstrap/cache/` deben existir y ser escribibles por PHP.
- `storage/app/private` es privado; no requiere `storage:link`.

## Instalacion QA En Servidor PHP 8.3 + MySQL 8.0

1. Copiar o clonar el proyecto completo, incluyendo `vendor/`.
2. Configurar el servidor web con DocumentRoot en:

```text
/ruta/del/proyecto/public
```

3. Crear `.env` a partir de `.env.example` y completar valores reales fuera del repositorio:

```bash
cp .env.example .env
```

4. Variables minimas esperadas en QA/produccion:

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:valor-generado-fuera-del-repositorio
APP_URL=https://dominio.example
BUSINESS_TIMEZONE=America/Bogota

DB_CONNECTION=mysql
DB_HOST=host_mysql
DB_PORT=3306
DB_DATABASE=cancelacion_series
DB_USERNAME=usuario
DB_PASSWORD=secreto_externo

SESSION_DRIVER=database
CACHE_STORE=database
FILESYSTEM_DISK=local
IMPORT_DISK=imports
EXPORT_DISK=exports

OTP_MAC_KEY=secreto_hmac_externo
SMS_DRIVER=provider
SMS_PROVIDER_ENDPOINT=https://endpoint-del-provider
SMS_PROVIDER_AUTHORIZATION=credencial_externa
SMS_PROVIDER_FROM=InfoSMS
```

5. Dar permisos de escritura a PHP:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```

6. Ejecutar migraciones normales:

```bash
php artisan migrate --force
```

7. Sincronizar el catalogo aprobado de permisos:

```bash
php artisan app:sync-permissions
```

8. Inicializar secuencias de radicado con valores humanos aprobados para el ambiente:

```bash
php artisan app:init-moto-radicado-sequence <next_moto_radicado>
php artisan app:init-credit-radicado-sequence <next_credit_radicado>
```

9. Crear cuentas Advisor reales cuando corresponda:

```bash
php artisan app:create-advisor ADVISOR_USUARIO "Nombre Asesor" --email=asesor@example.com --phone=3001234567
```

El comando muestra una contrasena temporal una sola vez en CLI. Guardarla fuera del repositorio.

10. Validar ambiente:

```bash
php artisan app:check-environment
```

El check valida PHP 8.3, extensiones criticas, APP_KEY, OTP_MAC_KEY, DB MySQL 8.0, permisos de escritura, cookies/debug en production y SMS driver. `SMS_DRIVER=fake` falla en production.

## Desarrollo Local Con Docker Desktop

El host Windows no necesita PHP, Composer ni MySQL instalados.

```bash
docker compose build app
docker compose run --rm app composer install
docker compose up -d
```

La aplicacion queda disponible en:

```text
http://localhost:8000
```

Comandos utiles dentro de Docker:

```bash
docker compose exec app php -v
docker compose exec app composer --version
docker compose exec app php artisan app:check-environment
docker compose exec app php artisan route:list --except-vendor
```

## Base De Datos QA Descartable

Para una base vacia sin datos reales:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan app:sync-permissions
```

Para reconstruir completamente el ambiente local/demo y perder toda la informacion existente:

```bash
docker compose exec app php artisan migrate:fresh --seed --force
```

`migrate:fresh --seed` es solo para QA/local descartable. No usarlo sobre bases con informacion real.

## Seeders De Desarrollo

`DatabaseSeeder` ejecuta `DevelopmentSeeder` solo fuera de production. En `APP_ENV=production` los seeders de desarrollo se omiten.

Credenciales demo reales:

```text
CLIENT 1
username: 1001234567
password: DevClient2026*

CLIENT 2
username: 1007654321
password: DevClient2026*

ADVISOR 1
username: ADVISOR_DEMO_1
password: DevAdvisor2026*

ADVISOR 2
username: ADVISOR_DEMO_2
password: DevAdvisor2026*
```

Dataset demo:

- 2 Clients activos.
- 2 Advisors activos.
- Roles V1: `CLIENT` y `ADVISOR`; no existe `ADMIN`.
- Ambos Advisors tienen el mismo permission set efectivo por Role `ADVISOR`.
- No existen direct User permissions.
- 5 solicitudes Moto con radicados `1001` a `1005`.
- 5 solicitudes Credit con radicados `2001` a `2005`.
- Estados `EN_GESTION` y `RESPUESTA_OBTENIDA`.
- Responses, Notifications read/unread, Activity, Audit y SmsAttempt de prueba.
- Asignacion distribuida entre `ADVISOR_DEMO_1` y `ADVISOR_DEMO_2`.
- No hay OTP activos reutilizables.

Despues del seeder, las secuencias QA quedan asi:

```text
MOTO next_value: 1006
CREDIT next_value: 2006
```

## Rutas QA Principales

Publicas:

- `GET /moto`
- `GET /credit`
- `POST /moto`
- `POST /credit`

Autenticacion:

- `GET /login`
- `POST /login`
- `POST /logout`
- `GET /password/recovery`

Client:

- `GET /dashboard`
- `GET /client/cancellations`
- `GET /client/notifications`

Advisor:

- `GET /dashboard`
- `GET /advisor/cancellations`
- `GET /advisor/moto/create`
- `GET /advisor/credit/create`
- `GET /advisor/responses/import`
- `GET /advisor/responses/import/template`
- `GET /advisor/cancellations/export`
- `GET /advisor/accounts`
- `GET /advisor/operational/audit`

## Importacion XLSX

La importacion de respuestas requiere que el Advisor seleccione producto `MOTO` o `CREDIT`.

Cabeceras requeridas:

```text
Radicado
Fecha cancelacion
Observaciones
```

La pantalla `GET /advisor/responses/import` incluye el boton para descargar la plantilla XLSX validada por el sistema.

## Exportacion XLSX

La exportacion requiere permission `cancellations.export`.

El flujo vigente de Sprint 01 exporta por tipo seleccionado:

- Moto: una hoja `Moto`.
- Credit: una hoja `Credit`.

El formulario usa rango `Fecha inicio` y `Fecha fin` sobre `created_at` de la solicitud. El archivo se genera en storage privado, se descarga con headers privados y se elimina despues de enviarse.

## SMS

Local/QA usa `SMS_DRIVER=fake` por defecto dentro de Docker.

Para pruebas manuales locales con provider configurable:

```env
SMS_DRIVER=provider
SMS_PROVIDER_ENDPOINT=https://tu-subdominio.api.infobip.com/sms/2/text/advanced
SMS_PROVIDER_AUTHORIZATION=Basic credencial_externa
SMS_PROVIDER_FROM=InfoSMS
PHONE_ALLOWED_COUNTRY_CODES=57,51
```

Recrear el contenedor `app` si se cambian variables inyectadas por Docker:

```bash
docker compose up -d --force-recreate app
docker compose exec app php artisan app:check-environment
```

No guardar credenciales reales en el repositorio. `INT-SMS-01` sigue pendiente como contrato/API productivo formal.

## Quality Gates Sprint 01

Ejecutar dentro de Docker:

```bash
docker compose exec app php artisan migrate:fresh --seed --force
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse
docker compose exec app composer validate --strict
docker compose exec app composer check-platform-reqs
docker compose exec app composer audit
docker compose exec app php artisan app:check-environment
```

Secret scan recomendado:

```bash
rg -n --hidden --glob '!vendor/**' --glob '!storage/**' --glob '!.git/**' "APP_KEY=base64:|OTP_MAC_KEY=.+|SMS_PROVIDER_AUTHORIZATION=.+|Authorization:|Basic [A-Za-z0-9+/=]{20,}|password|secret|token" .
```

El resultado esperado es no encontrar secretos reales versionables. Las variables placeholder de `.env.example`, contrasenas demo de seeders y nombres de config pueden aparecer y deben revisarse manualmente.

## Smoke QA Con Cuentas Demo

Despues de `migrate:fresh --seed --force`:

1. Entrar a `http://localhost:8000/login`.
2. Iniciar sesion como `ADVISOR_DEMO_1` / `DevAdvisor2026*`.
3. Verificar Dashboard Advisor y Workspace en `/advisor/cancellations`.
4. Revisar tabs Todos/Moto/Credit, filtros, ordenamiento, paginacion y selector 10/20/30/50.
5. Verificar importacion en `/advisor/responses/import` y descarga de plantilla.
6. Verificar exportacion en `/advisor/cancellations/export` seleccionando Moto y Credit por separado.
7. Verificar cuentas Advisor en `/advisor/accounts`.
8. Cerrar sesion.
9. Iniciar sesion como `1001234567` / `DevClient2026*`.
10. Verificar portal Client, solicitudes propias, detalle Moto/Credit y notificaciones.
11. Cerrar sesion.
12. Abrir `/moto` y `/credit` sin sesion para validar formularios publicos y flujo OTP con SMS fake/local.

## Limitaciones Sprint 01 QA/UAT

- Phase 13 y Phase 14 no estan incluidas.
- `INT-SMS-01` sigue pendiente para contrato/API SMS productivo definitivo.
- Los seeders y credenciales demo son solo para local/QA descartable.
- Los valores productivos de radicado deben ser definidos explicitamente por ambiente; no se infieren.
- No hay Redis, queues, cron obligatorio, Node runtime ni build pipeline.
- No hay `ADMIN`; roles aprobados: `CLIENT` y `ADVISOR`.
- No hay direct User permissions; permisos efectivos via Role.
- No existe tabla generica `cancellations`; Moto y Credit son agregados separados.
- No hay busqueda publica por cedula.
- El export Sprint 01 vigente es por tipo seleccionado, no consolidado Moto+Credit.
- El servidor productivo no debe usar `SMS_DRIVER=fake`, `APP_DEBUG=true` ni placeholders de secretos.

