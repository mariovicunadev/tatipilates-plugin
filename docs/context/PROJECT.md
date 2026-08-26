# PROJECT.md

## Objetivo
Tati Pilates es un plugin privado de WordPress para gestionar la operacion de un estudio de Pilates. Centraliza estudiantes, horarios, reservas, pagos, asistencia, recuperaciones, logros, notificaciones y un portal PWA para alumnas.

## Problema que resuelve
Permite que el equipo administrativo gestione clases y seguimiento desde WordPress, mientras las alumnas reservan, consultan su agenda y administran ausencias desde `/mi-pilates`. Sustituye procesos manuales dispersos y mantiene reglas de cupo, plan y pago en un solo sistema.

## Stack técnico
- Lenguaje: PHP 8.0+, JavaScript vanilla, HTML y CSS.
- Framework: WordPress 6.0+ como plugin privado; APIs nativas de usuarios, roles, cron, HTTP, mail y base de datos.
- Base de datos: MySQL/MariaDB mediante `$wpdb`, tablas `tp_*`, opciones de WordPress y usuarios nativos.
- Hosting/Infra: WordPress en entornos local, staging y live; LocalWP para desarrollo; GitHub privado, Actions y Releases para distribución.

## Restricciones
- Tiempo: no hay una fecha límite global documentada; los cambios se entregan en tandas pequeñas.
- Presupuesto: no documentado; evitar dependencias pagas sin aprobación.
- Compatibilidad: WordPress 6.0+, PHP 8.0+, Safari iOS y Chrome mobile; preservar datos existentes y el flujo PWA.

## Estado general
En producción. Versión estable actual: `1.3.0`; el canal staging apunta a `1.3.0-rc.1`. El updater privado distribuye releases desde GitHub con token configurado en `wp-config.php` o variables de entorno.
