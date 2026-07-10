# Roadmap

Lista viva de mejoras futuras. Cada item deberia convertirse en una tanda separada con release candidate.

## Corto plazo

- Mejorar experiencia mobile del portal segun pruebas reales.
- Crear `docs/testing-checklist.md` mas detallado con capturas esperadas.
- Mejorar emails de credenciales y reset con template HTML simple.
- Agregar logs visibles solo para admin de errores de envio de email.

## Medio plazo

- Preferencias de notificaciones por alumna.
- Mejoras de accesibilidad en portal mobile.
- Dashboard admin con metricas de ocupacion y pagos.
- Export de agenda semanal.
- Mejorar flujo de recuperaciones disponibles.
- Crear pruebas automatizadas basicas para helpers y reservas.
- Mejorar pantalla de backups con historial descargable y restauracion selectiva.

## Largo plazo

- Sistema de waitlist para horarios llenos.
- Integracion con calendario.
- Integracion con proveedor SMTP/API desde configuracion.
- Panel de perfil editable por alumna.
- Reportes mensuales.

## Deuda tecnica

- Extraer mas preparacion de vistas desde `admin/views/*`.
- Reducir CSS monolitico en portal.
- Automatizar minificacion de CSS.
- Agregar lint de JS/CSS al pre-release.
- Agregar validacion de YAML workflows al pre-release.
- Agregar smoke test automatizado con Playwright para `/mi-pilates`.

## Reglas para priorizar

Alta prioridad:

- bugs que bloquean reservas/pagos/login;
- problemas de seguridad;
- problemas de update/deploy;
- fallas mobile que impiden usar el portal.

Media prioridad:

- mejoras UX;
- reportes;
- optimizaciones admin.

Baja prioridad:

- pulido visual no bloqueante;
- features experimentales;
- automatizaciones internas no urgentes.
