# AUDIT.md

## Alcance

**Fecha:** 2026-07-01
**Fase:** 1 - Diagnostico solamente
**Objetivo:** auditar seguridad, datos sensibles, backups, autenticacion, updater, performance y CI/CD del plugin Tati Pilates.

Se reviso el codigo PHP, SQL, vistas, scripts de release y workflows del
repositorio. Tambien se uso el entorno LocalWP disponible para comprobar
indices con `SHOW INDEX`, planes con `EXPLAIN` y la exposicion HTTP del
directorio de backups.

No se modifico codigo funcional, schema, configuracion, `CHANGELOG.md`,
`DECISIONS.md`, `TASKS.md` ni `PROGRESS.md`.

## Limitaciones

- La auditoria no tuvo acceso a las bases de datos ni a la configuracion web
  de staging o live. Los indices y pruebas HTTP descritos como runtime
  corresponden exclusivamente al entorno local.
- No es posible confirmar desde el repositorio si staging contiene actualmente
  datos reales. Si existe un procedimiento externo de clonacion o
  anonimizacion, no esta versionado ni documentado aqui.
- No se realizo una prueba de penetracion contra staging o live.
- La base local es pequena: 18 alumnas, 26 horarios, 50 reservas, 5
  recuperaciones, 19 pagos y 33 notificaciones. Los planes de ejecucion no
  representan por si solos el comportamiento con volumen de produccion.

## Resumen ejecutivo

- **Critico:** 1 hallazgo.
- **Alto:** 3 hallazgos.
- **Medio:** 7 hallazgos.
- **Bajo:** 2 hallazgos.

La prioridad principal es la confidencialidad de los backups. El plugin guarda
copias completas en un directorio publico de uploads y depende de `.htaccess`
para bloquearlas. En el Nginx local esa proteccion no aplica y una URL directa
conocida devolvio el JSON sin autenticacion.

---

## Critico

### AUD-01 - Los backups automaticos pueden descargarse por URL directa

**Area:** Backups JSON
**Estado:** Confirmado en LocalWP con Nginx

**Evidencia**

- El backup incluye `SELECT *` de todas las tablas del plugin, incluida
  `tp_alumnas`, y agrega identificadores, nombres y correos de WordPress:
  `includes/class-backups.php:27`, `includes/class-backups.php:46`,
  `includes/class-backups.php:51`, `includes/class-backups.php:55`.
- Los backups automaticos se escriben como JSON bajo
  `wp-content/uploads/tatipilates-backups`:
  `includes/class-backups.php:108`, `includes/class-backups.php:120`,
  `includes/class-backups.php:123`, `includes/class-backups.php:588`,
  `includes/class-backups.php:595`.
- El unico bloqueo de acceso creado por el plugin es un `.htaccess` con
  `Deny from all`: `includes/class-backups.php:601`,
  `includes/class-backups.php:606`.
- `.htaccess` es una configuracion de Apache y Nginx no la interpreta. En la
  instalacion local, una peticion sin sesion a la URL de un backup existente
  respondio `HTTP 200`, `Content-Type: application/json` y 82.669 bytes.
- Los archivos nuevos incorporan un sufijo aleatorio de 12 caracteres, pero
  siguen siendo publicos si la URL aparece en logs, historial, monitoreo,
  referers o una configuracion permite listar el directorio:
  `includes/class-backups.php:121`.

**Impacto**

Una URL filtrada permite descargar sin autenticacion nombres, correos, datos
medicos, alergias, pagos, reservas y notificaciones. La retencion automatica es
de 30 dias, por lo que puede haber varias copias expuestas simultaneamente:
`includes/class-backups.php:20`, `includes/class-backups.php:619`.

**Respuesta directa**

El backup manual se transmite como descarga y no se persiste por ese flujo:
`includes/class-backups.php:97`. Los backups automaticos y los generados bajo
demanda si se guardan dentro de uploads y su privacidad depende de la
configuracion del servidor. En el Nginx local son accesibles por URL directa.

---

## Alto

### AUD-02 - Los campos medicos se almacenan en texto plano y con acceso amplio

**Area:** Datos sensibles

**Evidencia**

- `historia_medica`, `alergias` y `motivo_pilates` son columnas `TEXT`:
  `includes/class-activator.php:94`, `includes/class-activator.php:100`.
- Los valores se sanitizan como texto, pero se insertan y actualizan
  directamente, sin cifrado de aplicacion:
  `includes/class-alumnas.php:149`, `includes/class-alumnas.php:156`,
  `includes/class-alumnas.php:223`, `includes/class-alumnas.php:229`,
  `includes/class-alumnas.php:703`, `includes/class-alumnas.php:708`.
- La consulta general usa `SELECT a.*`, por lo que recupera esos campos incluso
  en pantallas que solo necesitan datos de listado:
  `includes/class-alumnas.php:37`, `includes/class-alumnas.php:47`.
- La misma capability, `tp_manage_pilates`, habilita toda la administracion. La
  reciben el rol `Admin Pilates` y los administradores de WordPress:
  `includes/class-roles.php:76`, `includes/class-roles.php:81`,
  `includes/class-roles.php:87`, `includes/class-roles.php:93`.
- El exportador incluye la fila completa de cada alumna:
  `includes/class-backups.php:51`.

**Impacto**

Una lectura de la base de datos, un backup expuesto o una cuenta administrativa
comprometida revela informacion de salud en forma legible. No existe una
capability separada para limitar el acceso medico por principio de menor
privilegio.

**Respuesta directa**

El campo medico vive en `wp_tp_alumnas.historia_medica` junto con `alergias` y
`motivo_pilates`. No esta cifrado en reposo por el plugin. Puede leerlo cualquier
usuario al que se le haya asignado `tp_manage_pilates`: administradores de
WordPress y usuarios con rol `Admin Pilates`.

### AUD-03 - La importacion valida solo la envoltura del JSON

**Area:** Backups JSON

**Evidencia**

- El archivo completo se carga en memoria con `file_get_contents`, sin verificar
  previamente su tamano: `includes/class-backups.php:143`,
  `includes/class-backups.php:148`.
- La validacion exige solamente un objeto JSON, `plugin=tatipilates` y un arreglo
  `tables`: `includes/class-backups.php:154`,
  `includes/class-backups.php:156`.
- No se comprueban version/schema compatible, columnas permitidas, campos
  obligatorios, tipos, longitudes, cantidad maxima de usuarios o filas.
- Cada fila que sea un arreglo se remapea y se envia al upsert:
  `includes/class-backups.php:190`, `includes/class-backups.php:195`,
  `includes/class-backups.php:200`, `includes/class-backups.php:203`.
- El upsert entrega las claves recibidas directamente a `$wpdb->update()` o
  `$wpdb->insert()`: `includes/class-backups.php:356`,
  `includes/class-backups.php:366`, `includes/class-backups.php:370`,
  `includes/class-backups.php:378`.
- El handler solo comprueba que exista `tmp_name`; no valida `error`, `size`,
  MIME real ni que sea un upload HTTP valido:
  `admin/class-admin.php:1649`, `admin/class-admin.php:1655`,
  `admin/class-admin.php:1657`, `admin/class-admin.php:1660`.
- `accept="application/json,.json"` es solo una restriccion del navegador:
  `admin/views/configuracion.php:165`, `admin/views/configuracion.php:170`.

**Impacto**

Un backup malformado o excesivo, cargado por una cuenta con capability
administrativa, puede agotar memoria, causar una importacion parcial/fallida o
introducir datos semanticamente invalidos. La base impone algunas restricciones,
pero no sustituye la validacion del contrato de importacion.

**Respuesta directa**

No existe validacion estricta del esquema ni un limite de tamano propio del
plugin. Solo aplican indirectamente los limites globales de PHP/servidor.

### AUD-04 - El generador demo puede crear una cuenta privilegiada predecible

**Area:** Best practices / autenticacion administrativa

**Evidencia**

- Las contrasenas demo estan fijadas en codigo:
  `includes/class-test-data.php:15`, `includes/class-test-data.php:17`.
- El seeder crea un usuario `Admin Pilates` con correo y password conocidos:
  `includes/class-test-data.php:53`, `includes/class-test-data.php:59`.
- La configuracion muestra publicamente esas credenciales a quien acceda a la
  pantalla: `admin/views/configuracion.php:180`,
  `admin/views/configuracion.php:188`, `admin/views/configuracion.php:203`.
- La accion exige capability y nonce, pero no tiene una restriccion por entorno
  que impida ejecutarla en live:
  `admin/class-admin.php:1579`, `admin/class-admin.php:1580`,
  `admin/class-admin.php:1581`, `admin/class-admin.php:1583`.

**Impacto**

Si un administrador ejecuta accidentalmente el seeder en live, queda disponible
una cuenta administrativa con credenciales conocidas por cualquiera que pueda
leer el plugin o su historial. El riesgo requiere esa ejecucion previa, pero el
resultado es acceso privilegiado predecible.

---

## Medio

### AUD-05 - No existe un control versionado de anonimizacion para staging

**Area:** Datos sensibles

**Evidencia**

- La busqueda completa del repositorio no encontro un script, comando, workflow
  ni runbook que anonimice alumnas al clonar produccion.
- El unico mecanismo de datos no reales encontrado es el seeder demo, que crea
  perfiles sinteticos: `includes/class-test-data.php:20`,
  `includes/class-test-data.php:74`.
- Los backups contienen datos completos y pueden importarse en otra instancia:
  `includes/class-backups.php:46`, `includes/class-backups.php:136`.

**Impacto**

El repositorio no impide que una restauracion o clonacion lleve nombres,
correos y datos medicos reales a staging. No se confirma que esto este ocurriendo
actualmente; se confirma la ausencia de un control reproducible y auditable.

**Respuesta directa**

No puede determinarse desde el codigo si staging usa hoy datos reales. No hay un
proceso de anonimizacion documentado o automatizado en el proyecto.

### AUD-06 - El rate limiting del login permite bloqueo dirigido y cobertura parcial

**Area:** Autenticacion del portal

**Evidencia**

- Existe un limite de 5 fallos durante 10 minutos:
  `public/class-portal.php:837`, `public/class-portal.php:845`,
  `public/class-portal.php:859`.
- La clave combina el login con `wp_get_session_token()`:
  `public/class-portal.php:837`. Antes de autenticar, normalmente no existe token
  de sesion, por lo que el contador queda compartido por identificador de login,
  no vinculado de forma efectiva a IP o dispositivo.
- El reset de password no tiene un contador equivalente y puede intentar enviar
  correo en cada solicitud valida:
  `public/class-portal.php:890`, `public/class-portal.php:902`,
  `public/class-portal.php:909`, `public/class-portal.php:940`.

**Impacto**

Cinco intentos contra un correo conocido pueden bloquear temporalmente a esa
persona. A la vez, un atacante puede distribuir intentos entre identificadores.
El endpoint de reset puede utilizarse para generar correo repetido a una cuenta.

**Respuesta directa**

Si existe rate limiting para login, pero su alcance no es robusto. No se encontro
rate limiting para solicitud de recuperacion.

### AUD-07 - La sesion recordada dura 90 dias y SameSite no se garantiza

**Area:** Autenticacion del portal

**Evidencia**

- El plugin extiende a 90 dias las sesiones recordadas de estudiantes:
  `public/class-portal.php:275`, `public/class-portal.php:283`,
  `public/class-portal.php:294`.
- `wp_signon()` recibe `is_ssl()` como parametro de cookie segura:
  `public/class-portal.php:850`, `public/class-portal.php:856`.
- El plugin delega la emision de la cookie a WordPress y no define explicitamente
  una politica `SameSite` en ningun archivo del repositorio.

**Impacto**

En HTTPS, el flujo solicita cookie `Secure` y WordPress maneja `HttpOnly`. Sin
embargo, el atributo `SameSite` depende de la version/configuracion de WordPress
y no queda garantizado por el plugin. Noventa dias amplian la ventana de abuso
si una cookie se filtra desde un dispositivo compartido o comprometido.

**Respuesta directa**

`Secure` queda condicionado correctamente a HTTPS. `HttpOnly` se delega al core.
No hay una configuracion explicita de `SameSite` en el plugin.

### AUD-08 - El PAT de GitHub queda en `wp_options` sin cifrado de aplicacion

**Area:** Updater privado

**Evidencia**

- El updater define la opcion `tp_updater_config` y la lee con `get_option()`:
  `includes/class-updater.php:15`, `includes/class-updater.php:17`,
  `includes/class-updater.php:40`.
- El token se guarda como un valor del arreglo mediante `update_option()`:
  `includes/class-updater.php:60`, `includes/class-updater.php:68`,
  `includes/class-updater.php:76`, `includes/class-updater.php:81`.
- El ultimo argumento `false` evita autoload, pero no cifra el valor:
  `includes/class-updater.php:83`.
- El token se adjunta como `Bearer` solo a endpoints de assets del repositorio
  configurado y a llamadas de la API de GitHub:
  `includes/class-updater.php:190`, `includes/class-updater.php:191`,
  `includes/class-updater.php:201`, `includes/class-updater.php:355`.

**Impacto**

Una lectura de la base de datos, un volcado completo de WordPress o codigo con
acceso a opciones puede recuperar el PAT en texto legible. El alcance real
depende de los permisos configurados para ese token en GitHub.

**Respuesta directa**

El PAT no se obtiene de una constante de `wp-config.php`; vive en
`wp_options.tp_updater_config` sin cifrado de aplicacion.

### AUD-09 - El portal repite lecturas de reservas y notificaciones

**Area:** Performance

**Evidencia**

- El render del portal calcula agenda, asistencia publica y reservas propias
  antes de decidir que vista mostrara:
  `public/class-portal.php:354`, `public/class-portal.php:359`,
  `public/class-portal.php:365`, `public/class-portal.php:369`.
- `agenda_semana()` hace una agregacion semanal y otra consulta para las
  reservas de la alumna:
  `public/class-portal.php:1265`, `public/class-portal.php:1283`,
  `public/class-portal.php:1300`.
- `TP_Asistencia::semana()` vuelve a leer las reservas de la semana:
  `includes/class-asistencia.php:30`, `includes/class-asistencia.php:36`.
- `reservas_estudiante_semana()` vuelve a leer las reservas de esa alumna y
  semana: `public/class-portal.php:1347`, `public/class-portal.php:1352`.
- En cada render tambien se consultan 50 notificaciones, 5 pendientes y el
  conteo no visto por separado:
  `public/class-portal.php:450`, `public/class-portal.php:451`,
  `public/class-portal.php:452`.
- `cupo_plan_semana()` realiza una consulta:
  `includes/class-reservas.php:459`, `includes/class-reservas.php:489`.
  En el render normal se invoca una vez:
  `public/class-portal.php:434`.

**Impacto**

Una sola carga ejecuta varias consultas solapadas aun cuando el usuario no abre
las vistas que consumen esos datos. El costo actual es bajo por el volumen local,
pero crece con reservas, notificaciones y trafico concurrente.

**Respuesta directa sobre `cupo_plan_semana()`**

No se encontro una repeticion aislada de esa funcion en el render normal del
portal. La repeticion relevante ocurre en operaciones por lote: cada reserva de
un mes o de varios horarios vuelve a ejecutar `intentar_reserva()`:
`includes/class-reservas.php:261`, `includes/class-reservas.php:276`,
`admin/class-admin.php:1235`, `admin/class-admin.php:1250`. Esa funcion vuelve a
cargar alumna, horario, cupo, pago, duplicado y cupo semanal en cada iteracion:
`includes/class-reservas.php:52`, `includes/class-reservas.php:83`,
`includes/class-reservas.php:94`, `includes/class-reservas.php:101`,
`includes/class-reservas.php:111`.

### AUD-10 - El admin repite queries y las listas principales no paginan

**Area:** Performance

**Evidencia**

- El dashboard consulta el mismo rango semanal tres veces: detalle completo,
  actividad reciente y resumen agregado:
  `admin/views/dashboard.php:48`, `admin/views/dashboard.php:112`,
  `admin/views/dashboard.php:127`.
- Las notificaciones se cargan incluso si el tab activo es `resumen`:
  `admin/views/dashboard.php:191`, `admin/views/dashboard.php:197`.
- La pantalla de reserva manual recorre todos los horarios y llama una o dos
  veces a `cupos_disponibles()` por horario:
  `admin/class-admin.php:563`, `admin/class-admin.php:566`,
  `admin/class-admin.php:569`.
- Cada `cupos_disponibles()` vuelve a consultar el horario y luego cuenta
  reservas: `includes/class-reservas.php:327`,
  `includes/class-reservas.php:347`, `includes/class-reservas.php:348`,
  `includes/class-reservas.php:354`.
- `TP_Alumnas::obtener_todas()` no acepta `LIMIT`, `OFFSET` ni filtros:
  `includes/class-alumnas.php:37`, `includes/class-alumnas.php:45`.
- La vista de alumnas carga la lista completa:
  `admin/views/alumnas.php:12`, `admin/views/alumnas.php:13`.
- Pagos carga el estado mensual y, adicionalmente, toda la lista de alumnas:
  `admin/views/pagos.php:12`, `admin/views/pagos.php:14`,
  `admin/views/pagos.php:15`.

**Impacto**

Al crecer la cantidad de alumnas y reservas, aumentaran memoria, transferencia
SQL y tiempo de render. Reservas y agenda estan acotadas a una semana, pero
alumnas y selectores administrativos cargan todo de una vez.

**Respuesta directa**

La lista de alumnas no tiene paginacion. Las vistas de reservas estan limitadas
por semana, no paginadas. Notificaciones y algunos historiales usan limites
fijos, no navegacion paginada.

### AUD-11 - CI/CD no ejecuta validaciones en push o pull request

**Area:** CI/CD

**Evidencia**

- Los dos workflows solo declaran `workflow_dispatch`:
  `.github/workflows/publish-staging-release.yml:3`,
  `.github/workflows/promote-stable-release.yml:3`.
- Ambos actualizan version, crean commit/tag, construyen ZIP y publican el
  release, pero no ejecutan lint ni tests:
  `.github/workflows/publish-staging-release.yml:26`,
  `.github/workflows/publish-staging-release.yml:70`,
  `.github/workflows/publish-staging-release.yml:79`,
  `.github/workflows/promote-stable-release.yml:26`,
  `.github/workflows/promote-stable-release.yml:70`,
  `.github/workflows/promote-stable-release.yml:79`.
- El script local de pre-release valida sintaxis PHP, BOM, patrones de debug,
  carpeta de release y version:
  `pre-release-check.sh:15`, `pre-release-check.sh:118`,
  `pre-release-check.sh:142`, `pre-release-check.sh:166`,
  `pre-release-check.sh:181`, `pre-release-check.sh:189`.
- No se encontro suite de tests automatizados en el repositorio.

**Impacto**

Un push puede introducir errores sin feedback automatico. Incluso el workflow de
publicacion puede crear commit, tag y release sin ejecutar el chequeo local ni
pruebas de comportamiento.

**Respuesta directa**

No corren lint o tests en cada push. Los workflows se ejecutan manualmente al
publicar y tampoco incluyen esos controles.

---

## Bajo

### AUD-12 - El updater no conserva auditoria de comprobaciones o cambios

**Area:** Updater privado

**Evidencia**

- Guardar configuracion exige capability administrativa y nonce:
  `admin/class-admin.php:722`, `admin/class-admin.php:723`,
  `admin/class-admin.php:724`.
- El guardado no registra usuario, fecha, canal anterior o resultado:
  `admin/class-admin.php:726`, `admin/class-admin.php:731`.
- La comprobacion usa cache de 30 minutos, pero no conserva historial:
  `includes/class-updater.php:243`, `includes/class-updater.php:244`,
  `includes/class-updater.php:289`.
- Los errores HTTP o respuestas invalidas se convierten silenciosamente en
  `null` o cadena vacia:
  `includes/class-updater.php:272`, `includes/class-updater.php:278`,
  `includes/class-updater.php:329`, `includes/class-updater.php:335`.

**Impacto**

No puede responderse desde WordPress quien cambio el canal/token o quien forzo
una comprobacion, ni distinguir facilmente entre cache, token invalido, permiso
insuficiente, asset ausente o error de red.

**Respuesta directa**

No hay un registro de comprobaciones forzadas ni del usuario que las inicio.

### AUD-13 - Algunas consultas semanales requieren ordenamiento temporal

**Area:** Performance

**Evidencia**

- El schema define indices adecuados para los filtros principales:
  `tp_reservas` tiene `(alumna_id, fecha)`,
  `(alumna_id, estado, fecha)`, `(fecha, estado, horario_id)` y
  `(fecha, created_at, id)`:
  `includes/class-activator.php:115`, `includes/class-activator.php:126`,
  `includes/class-activator.php:128`, `includes/class-activator.php:129`,
  `includes/class-activator.php:130`, `includes/class-activator.php:131`.
- `tp_horarios` tiene `(activo, dia_semana, hora_inicio)`:
  `includes/class-activator.php:80`, `includes/class-activator.php:91`.
- `SHOW INDEX` en LocalWP confirmo que esos indices existen realmente.
- `EXPLAIN` local mostro:
  - conteo de plan semanal: indice `alumna_fecha`;
  - conteo de cupo por slot: indice `fecha_estado_horario`;
  - reservas de alumna por semana: indice `alumna_fecha`, con
    `Using temporary; Using filesort` por el orden que incluye
    `h.hora_inicio`;
  - dashboard semanal: indice `fecha_estado_horario`, tambien con tabla
    temporal/filesort para el orden solicitado.

**Impacto**

No se comprobo un indice faltante critico para los filtros solicitados. Los
ordenamientos temporales estan acotados a una semana, pero deben vigilarse con
volumen real. La existencia de estos indices en staging/live no puede
confirmarse sin ejecutar `SHOW INDEX` alli.

---

## Respuestas consolidadas

### 1. Datos sensibles

- Los campos medicos estan en `tp_alumnas` y quedan en texto plano.
- Los backups tambien los exportan en texto plano.
- `Admin Pilates` y `administrator` pueden leerlos mediante
  `tp_manage_pilates`; no existe capability medica separada.
- No hay anonimizacion de staging versionada. El estado real de staging no pudo
  verificarse.

### 2. Backups JSON

- Automaticos y bajo demanda se escriben en
  `wp-content/uploads/tatipilates-backups`.
- En Nginx local son accesibles sin autenticacion si se conoce la URL.
- La importacion no valida un esquema estricto.
- No existe limite de tamano propio del plugin.

### 3. Autenticacion

- Login tiene bloqueo de 5 intentos/10 minutos, con una clave susceptible a
  bloqueo dirigido y cobertura incompleta.
- Login y reset usan mensajes genericos que evitan enumerar correos:
  `public/class-portal.php:859`, `public/class-portal.php:895`,
  `public/class-portal.php:904`, `public/class-portal.php:960`.
- `Secure` depende correctamente de HTTPS; `HttpOnly` se delega a WordPress;
  `SameSite` no se fija en el plugin.

### 4. Updater privado

- El PAT vive en `wp_options`, no en `wp-config.php`, y no esta cifrado por la
  aplicacion.
- No existe auditoria de comprobaciones forzadas, actor o resultado.

### 5. Performance

- Hay consultas solapadas en portal y dashboard, y patron N+1 en cupos y
  reservas por lote.
- Los indices principales existen y fueron usados por `EXPLAIN` en LocalWP.
- Alumnas no tiene paginacion; reservas se acotan por semana; otras listas usan
  limites fijos.

### 6. CI/CD

- Los workflows solo se ejecutan manualmente para releases.
- No ejecutan lint ni tests y no hay suite automatizada versionada.

## Plan de accion

**Fase:** 2 - Propuesta, sin implementacion
**Alcance aprobado para planificar:** AUD-01, AUD-02, AUD-04, AUD-03 y AUD-08
**Orden propuesto de implementacion:** el mismo orden indicado arriba.

La implementacion debe detenerse despues de cada hallazgo para validar el
resultado antes de continuar con el siguiente. Ninguno de estos items esta
aprobado todavia para implementacion.

### AUD-01 - Almacenamiento privado de backups

**Objetivo**

Impedir que un servidor web pueda entregar un backup como archivo estatico,
incluso si un tercero conoce el nombre exacto. El formato JSON no necesita
cambiar para resolver este hallazgo.

#### Alternativa A - Mover los backups fuera del webroot y servirlos con PHP

**Recomendada para este proyecto.**

**Cambio exacto**

- Cambiar `TP_Backups::directorio_backups()` en
  `includes/class-backups.php` para resolver una ruta privada configurable:
  1. constante `TP_BACKUP_DIR` definida en `wp-config.php`, si existe;
  2. por defecto, un directorio hermano del webroot, por ejemplo
     `dirname(ABSPATH) . '/tatipilates-private/backups'`;
  3. si ninguna ruta privada es escribible, no crear el backup y mostrar un
     error administrativo. No volver silenciosamente a `uploads`.
- Mantener `TP_Backups::crear_archivo_diario()` y
  `TP_Backups::limpiar_backups_antiguos()`, pero hacer que trabajen solo con la
  ruta privada resuelta.
- Agregar en `TP_Backups` una funcion que reciba un nombre de backup, lo valide
  contra una allowlist generada por el propio directorio y lo transmita con
  `Content-Disposition: attachment`, sin aceptar rutas arbitrarias.
- Agregar en `admin/class-admin.php` un handler `admin_post` protegido por
  `tp_manage_pilates`, nonce y validacion del nombre para descargar un backup
  guardado.
- Actualizar `admin/views/configuracion.php` para que cualquier enlace a un
  backup use el handler autenticado y nunca una URL fisica.
- Incorporar una migracion de archivos idempotente que mueva los
  `tatipilates-backup-*.json` existentes desde uploads al directorio privado.
  Cada movimiento debe verificarse antes de borrar el origen.
- Registrar en el log administrativo los archivos que no pudieron moverse y
  dejar una advertencia persistente mientras queden JSON legacy en uploads.
- Conservar el bloqueo `.htaccess` del directorio antiguo durante la transicion,
  pero tratarlo solo como defensa adicional, no como control principal.

**Pros**

- El servidor web no puede entregar el archivo estaticamente.
- Funciona igual con Apache y Nginx una vez que la ruta privada es escribible.
- No exige cifrar o cambiar el formato del backup.
- Conserva el flujo actual de backup automatico, retencion y descarga manual.

**Contras**

- Algunos hostings compartidos no permiten escribir fuera del webroot.
- La ruta debe configurarse y respaldarse como parte de la infraestructura.
- Hace falta migrar y comprobar los archivos legacy ya creados en uploads.

**Migracion y reversibilidad**

- No requiere migracion de base de datos ni cambio de schema.
- Si requiere una migracion de filesystem para los backups existentes.
- Es reversible moviendo los archivos al directorio anterior, pero ese rollback
  reabre la exposicion y no debe considerarse seguro.
- Antes de mover, se conservaran nombre, tamano y hash; el origen solo se
  eliminara despues de verificar el destino.

**Compatibilidad**

- No rompe backups JSON existentes ni instalaciones actuales.
- Si una instalacion no puede escribir fuera del webroot, dejara de generar
  backups automaticos hasta que se configure `TP_BACKUP_DIR`; el fallo sera
  visible, no silencioso.
- Restaurar backups antiguos seguira siendo posible porque su contenido no
  cambia.

**Riesgo estimado:** medio.

El riesgo principal es una ruta o permisos incorrectos que detengan los backups.
El riesgo de perdida se reduce con copia, hash y borrado posterior.

#### Alternativa B - Mantenerlos en uploads, cifrarlos y servirlos con PHP

**Cambio exacto**

- Mantener `TP_Backups::directorio_backups()` bajo uploads.
- Cifrar el payload completo antes de `file_put_contents()` usando cifrado
  autenticado y una clave `TP_BACKUP_ENCRYPTION_KEY` ubicada fuera de la base de
  datos y del repositorio.
- Cambiar la extension y cabecera del formato para identificar un contenedor
  versionado, por ejemplo `tatipilates-backup-v2.enc`.
- Agregar el mismo handler PHP autenticado descrito en la alternativa A para
  leer, autenticar, descifrar y descargar el contenido.
- Migrar cada JSON legacy: leerlo, validarlo, cifrarlo, verificar que puede
  descifrarse y luego eliminar el original.
- Rechazar la creacion de backups si falta la clave; nunca guardar JSON plano
  como fallback.

**Pros**

- Compatible con hostings donde solo se puede escribir dentro de uploads.
- Una URL directa conocida expone ciphertext, no datos personales.
- El handler conserva control de capability y nonce para la descarga legible.

**Contras**

- Introduce administracion de claves y un nuevo formato de backup.
- Perder la clave vuelve irrecuperables todos los backups cifrados.
- Tiene mas complejidad de migracion, soporte y recuperacion ante desastres.
- El archivo y sus metadatos siguen estando dentro del webroot.
- Un handler autenticado por si solo no bloquea acceso estatico; la seguridad
  depende del cifrado. Reescrituras de WordPress o `.htaccess` no son
  suficientes en Nginx.

**Migracion y reversibilidad**

- No requiere migracion de base de datos.
- Requiere migrar todos los archivos JSON legacy al contenedor cifrado.
- Es reversible solo mientras la clave exista y se conserve una herramienta
  capaz de descifrar el formato.

**Compatibilidad**

- Rompe la lectura directa de los JSON nuevos por herramientas externas.
- El importador tendria que aceptar tanto JSON legacy como el contenedor
  cifrado.
- Las instalaciones existentes seguirian funcionando solo despues de configurar
  una clave valida.

**Riesgo estimado:** alto.

El riesgo se concentra en custodia, rotacion y perdida de la clave.

#### Decision solicitada para AUD-01

Se recomienda **Alternativa A**. Para Tati Pilates, la menor complejidad
operativa y la compatibilidad con el JSON actual pesan mas que mantener los
archivos dentro de uploads. La alternativa B queda como fallback para un hosting
que demuestre que no permite una ruta privada escribible.

**Estado de aprobacion:** cerrado el 2026-07-02. Se confirmo que no quedan JSON
legacy en uploads y que el directorio conserva `.htaccess` e `index.php`.

### AUD-02 - Cifrado de campos medicos y acceso minimo

**Objetivo**

Guardar `historia_medica`, `alergias` y `motivo_pilates` con cifrado autenticado
en la base, evitar recuperarlos en listados que no los necesitan y mantener una
transicion legible para datos existentes.

**Cambio exacto**

- Crear `includes/class-sensitive-data.php` con una clase
  `TP_Sensitive_Data` responsable de:
  - obtener una clave de 32 bytes desde `TP_DATA_ENCRYPTION_KEY` en
    `wp-config.php` o una variable de entorno;
  - cifrar con un formato versionado, por ejemplo
    `tpenc:v1:<nonce+ciphertext>`;
  - usar cifrado autenticado XChaCha20-Poly1305 mediante libsodium;
  - distinguir de forma segura valores legacy en texto plano;
  - descifrar solo formatos conocidos y fallar de forma cerrada ante clave
    ausente, ciphertext alterado o version desconocida;
  - no registrar nunca plaintext, clave, nonce o ciphertext completo.
- Cargar la nueva clase desde `tatipilates.php`.
- Modificar `TP_Alumnas::crear()` y `TP_Alumnas::actualizar()` en
  `includes/class-alumnas.php` para cifrar los tres campos antes de persistirlos.
- Modificar `TP_Alumnas::obtener()` y cualquier funcion que entregue la ficha
  completa para descifrarlos solamente al construir esa ficha.
- Cambiar `TP_Alumnas::obtener_todas()` para reemplazar `a.*` por una lista
  explicita de columnas no medicas. Los listados, pagos, reservas y selectores
  no recibiran los campos sensibles.
- Mantener la sanitizacion actual antes de cifrar y el escape de salida en
  `admin/views/alumnas.php`.
- Separar la capability medica:
  - agregar `tp_view_medical_data` en `includes/class-roles.php`;
  - asignarla inicialmente a `administrator`;
  - decidir explicitamente que usuarios `Admin Pilates` la reciben, en lugar de
    heredarla de `tp_manage_pilates`;
  - ocultar los campos y rechazar su lectura/edicion en
    `admin/class-admin.php` y `admin/views/alumnas.php` si falta la capability.
- Agregar una migracion idempotente y por lotes, coordinada desde
  `TP_Activator::ensure_schema()` en `includes/class-activator.php`, con una
  opcion de version/progreso independiente. La migracion solo empezara si la
  clave y libsodium estan disponibles.
- Actualizar `TP_Backups::exportar()` e `importar_archivo()` en
  `includes/class-backups.php`:
  - exportar los valores logicos descifrados dentro del backup privado para
    conservar portabilidad;
  - aceptar backups legacy con plaintext;
  - cifrar con la clave del destino antes de insertar;
  - no copiar ciphertext de una instalacion a otra.
- Exigir que AUD-01 este implementado y verificado antes de comenzar esta
  migracion, porque el backup portable seguira conteniendo valores sensibles.

**Migracion y reversibilidad**

- No requiere alterar las columnas `TEXT`; el schema fisico es suficiente.
- Si requiere transformar todos los valores existentes en las tres columnas.
- La migracion sera por lotes, reanudable e idempotente. Durante la transicion,
  la lectura aceptara plaintext legacy y ciphertext `tpenc:v1`; toda escritura
  nueva sera cifrada.
- Es criptograficamente reversible solo mientras se conserve la clave. Un
  rollback controlado puede descifrar los valores y restaurar plaintext, pero
  no debe ejecutarse sin un backup privado previo.
- Perder `TP_DATA_ENCRYPTION_KEY` implica perdida irreversible de esos campos.
  La custodia y recuperacion de la clave son prerrequisitos de implementacion.

**Compatibilidad**

- No rompe inmediatamente instalaciones existentes: el lector dual permite
  operar antes y durante la migracion.
- Backups JSON actuales seguiran importandose como formato legacy y se cifraran
  al persistir.
- Backups nuevos conservaran el valor logico y podran restaurarse con una clave
  distinta en el destino, siempre bajo el almacenamiento privado de AUD-01.
- Una instalacion sin libsodium o sin clave no podra escribir/editar campos
  medicos; el resto del plugin debe continuar operativo con una alerta
  administrativa.
- Introducir `tp_view_medical_data` cambia quien puede ver la ficha medica. Es
  una restriccion intencional, pero requiere asignar la capability a las personas
  autorizadas antes de desplegar.

**Riesgo estimado:** alto.

El riesgo proviene de migrar informacion sensible, custodiar la clave y mantener
lectura/escritura compatibles durante el despliegue. Debe probarse con una copia
de produccion anonimizada y con restauracion completa antes de live.

**Estado de aprobacion:** Entrega 1 confirmada y cerrada por el usuario el
2026-07-02. Entrega 2 bloqueada hasta definir la custodia de
`TP_DATA_ENCRYPTION_KEY`.

### AUD-04 - Bloqueo seguro del generador demo

**Objetivo**

Impedir que live cree o mantenga cuentas con credenciales conocidas, sin perder
la utilidad del seeder en desarrollo.

**Cambio exacto**

- Agregar `TP_Test_Data::is_available()` en
  `includes/class-test-data.php`. Solo devolvera `true` cuando:
  - `wp_get_environment_type()` sea `local` o `development`; y
  - exista opt-in explicito mediante `TP_ALLOW_DEMO_DATA=true`.
- Hacer que `TP_Test_Data::seed()` rechace la ejecucion si
  `is_available()` es falso. La validacion debe vivir en el dominio, no solo en
  la vista.
- Repetir la validacion en `TP_Admin::generar_datos_prueba()` dentro de
  `admin/class-admin.php`, ademas de capability y nonce.
- Ocultar por completo el panel de datos demo en
  `admin/views/configuracion.php` cuando el entorno no este autorizado.
- Eliminar `PASSWORD_ALUMNAS` y `PASSWORD_ADMIN` fijos. Generar passwords
  criptograficamente aleatorios en cada seed y mostrarlos una sola vez mediante
  un transient asociado al usuario administrativo que ejecuto la accion.
- Agregar una comprobacion de upgrade para produccion que busque cuentas
  `*.demo@tatipilates.test`:
  - rotar inmediatamente sus passwords a valores aleatorios no mostrados;
  - remover el rol `Admin Pilates` de
    `admin.pilates.demo@tatipilates.test`;
  - mostrar un aviso con la lista de cuentas detectadas para que un
    administrador decida si eliminarlas junto con sus datos.
- No eliminar automaticamente alumnas, reservas o pagos demo, porque esa
  eliminacion seria destructiva y podria confundirse con datos usados en una
  demostracion autorizada.

**Migracion y reversibilidad**

- No requiere cambio de schema ni migracion de tablas del plugin.
- Si requiere una migracion de seguridad sobre usuarios demo existentes en
  produccion: rotacion de passwords y retiro del rol privilegiado.
- La rotacion de password no recupera la credencial anterior, de forma
  intencional.
- El cambio de rol es reversible reasignando manualmente `Admin Pilates` despues
  de confirmar que la cuenta es legitima.

**Compatibilidad**

- No afecta instalaciones de produccion que nunca generaron datos demo.
- Los entornos local/development deberan declarar
  `TP_ALLOW_DEMO_DATA=true`.
- Scripts o instrucciones que dependan de passwords fijos dejaran de funcionar;
  deberan consumir las credenciales mostradas para cada ejecucion.
- Los datos demo existentes no se borran y las relaciones de tablas se
  conservan.

**Riesgo estimado:** medio.

El mayor riesgo es retirar privilegios a una cuenta demo que alguien estuviera
usando deliberadamente. El dominio de correo reservado y el aviso previo hacen
el alcance identificable.

**Estado de aprobacion:** implementado y verificado localmente el 2026-07-02;
pendiente de confirmacion del usuario antes de continuar con AUD-03.

### AUD-03 - Contrato estricto y limites para importar backups

**Objetivo**

Rechazar el archivo completo antes de abrir una transaccion si su estructura,
tamano, tipos o relaciones no cumplen el contrato de backup.

**Cambio exacto**

- Agregar a `TP_Backups` en `includes/class-backups.php`:
  - una constante de formato, por ejemplo `BACKUP_FORMAT_VERSION = 2`;
  - un limite por defecto de 10 MiB, configurable por filtro;
  - limites por defecto de 5.000 usuarios y 50.000 filas totales,
    configurables por filtro;
  - una definicion allowlist por tabla con columnas, campos requeridos, tipos,
    enums, longitudes y nulabilidad;
  - `validar_payload()` y normalizadores por tabla que devuelvan un payload
    limpio o `WP_Error`;
  - validacion de fechas, horas, IDs positivos, flags booleanos y referencias
    entre tablas;
  - rechazo de columnas desconocidas y versiones futuras no soportadas.
- Incluir `format_version` en `TP_Backups::exportar()`.
- Tratar los backups actuales sin `format_version` como legacy v1:
  normalizarlos con reglas explicitas y convertirlos en memoria al contrato
  actual antes de importar.
- En `TP_Admin::importar_backup()` dentro de `admin/class-admin.php`, validar:
  `UPLOAD_ERR_OK`, `is_uploaded_file()`, tamano declarado, tamano real y MIME
  detectado. La extension y `accept` no se consideraran controles de seguridad.
- Comprobar el limite antes de `file_get_contents()` y usar
  `json_decode(..., JSON_THROW_ON_ERROR)` para distinguir JSON invalido.
- Ejecutar toda la validacion y normalizacion antes de
  `START TRANSACTION`.
- Verificar el retorno de cada `START TRANSACTION`, `SET
  FOREIGN_KEY_CHECKS`, insert, update, commit y rollback. Restaurar
  `FOREIGN_KEY_CHECKS=1` mediante un bloque equivalente a `finally`.
- Cambiar `upsert_fila()` para recibir solo filas ya normalizadas y formatos
  definidos por tabla, no formatos inferidos de claves arbitrarias.
- Actualizar `admin/views/configuracion.php` para mostrar el limite efectivo y
  los formatos soportados.
- Agregar fixtures y pruebas para: backup valido v1/v2, JSON truncado, archivo
  excesivo, tabla/campo desconocido, tipo incorrecto, string demasiado largo,
  enum invalido, referencia ausente y rollback completo.

**Migracion y reversibilidad**

- No requiere migracion de base de datos ni cambio de schema.
- No modifica backups existentes.
- Es reversible a nivel de codigo, aunque volver al importador laxo reabriria el
  riesgo.

**Compatibilidad**

- Los backups actuales del plugin seguiran aceptandose mediante el normalizador
  legacy v1.
- Payloads manuales que antes dependian de campos extra, tipos coercionados o
  estructuras incompletas seran rechazados. Esa incompatibilidad es
  intencional.
- Un backup legitimo mayor al limite necesitara aumentar el filtro antes de
  importarlo; el error debe indicar tamano actual y maximo.
- Versiones futuras desconocidas se rechazaran en lugar de importarse
  parcialmente.

**Riesgo estimado:** medio.

El riesgo es rechazar un backup historico legitimo por una regla demasiado
estricta. Se controla con fixtures de backups reales anonimizados y soporte
explicito para legacy v1.

**Estado de aprobacion:** confirmado y cerrado por el usuario el 2026-07-02.

### AUD-08 - Sacar el PAT de GitHub de `wp_options`

**Objetivo**

Resolver el token desde configuracion segura del servidor y eliminar su copia en
la base sin interrumpir el updater durante la transicion.

**Cambio exacto**

- Definir como fuente preferida `TP_GITHUB_TOKEN` en `wp-config.php`. Permitir
  como segunda fuente una variable de entorno del mismo nombre.
- Agregar `TP_Updater::token()` en `includes/class-updater.php` para resolver el
  secreto con esta precedencia:
  1. constante;
  2. variable de entorno;
  3. valor legacy de `tp_updater_config`, solo durante una version de
     transicion.
- Cambiar `TP_Updater::configuracion()` para que devuelva `enabled`, `channel` y
  un estado booleano del token, nunca el token.
- Cambiar `TP_Updater::github_headers()` y
  `TP_Updater::autorizar_descarga_github()` para obtener el valor exclusivamente
  mediante `TP_Updater::token()`.
- Cambiar `TP_Updater::guardar_configuracion()` para dejar de aceptar o
  persistir `token` y `clear_token`.
- Actualizar `admin/views/configuracion.php`:
  - eliminar el input de password del PAT;
  - mostrar solo `Configurado por servidor`, `Usando configuracion legacy` o
    `No configurado`;
  - mostrar la instruccion exacta para definir la constante, sin imprimir el
    valor.
- Mantener capability y nonce en
  `TP_Admin::guardar_updater_config()` dentro de `admin/class-admin.php` para
  canal y estado habilitado.
- Cuando se detecte `TP_GITHUB_TOKEN` valido, eliminar la clave `token` del
  arreglo `tp_updater_config`, conservando `enabled` y `channel`.
- Mostrar una advertencia administrativa mientras se use el fallback legacy.
  La eliminacion definitiva de ese fallback se haria en una version posterior,
  como una decision separada.
- Documentar que el PAT debe tener el alcance minimo necesario para leer el
  repositorio/release privado y no permisos de escritura.

**Migracion y reversibilidad**

- No requiere migracion de schema.
- La migracion del secreto es manual porque PHP no debe editar
  `wp-config.php`: el operador define la constante o variable de entorno,
  comprueba el updater y despues el plugin limpia la copia legacy.
- La limpieza del valor en `wp_options` es reversible solo volviendo a
  configurar el secreto en el servidor o reinsertandolo manualmente mientras
  exista el fallback. El rollback recomendado es conservar la constante, no
  devolver el PAT a la base.

**Compatibilidad**

- No hay corte inmediato: instalaciones existentes pueden usar el token legacy
  durante una version de transicion.
- Canal, cache y formato de releases no cambian.
- Si se elimina el valor legacy antes de definir la constante, el updater privado
  dejara de consultar GitHub; el dashboard debe advertirlo claramente.
- Hostings que no permiten editar variables de entorno pueden usar
  `wp-config.php`.

**Riesgo estimado:** medio.

No hay riesgo para datos funcionales, pero una precedencia o despliegue
incorrectos pueden dejar el updater sin autenticacion. La transicion dual evita
un corte inmediato.

**Estado de aprobacion:** confirmado y cerrado por el usuario el 2026-07-02.

## Estado de la auditoria

Fase 1 aprobada. AUD-01, AUD-04, AUD-03, AUD-08 y AUD-02 Entrega 1 estan
confirmados y cerrados. AUD-02 Entrega 2 sigue bloqueada hasta definir la
custodia de `TP_DATA_ENCRYPTION_KEY`.
