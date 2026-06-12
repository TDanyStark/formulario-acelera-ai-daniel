# Plan Maestro — Plugin Curso ACELERA

> Plugin a medida para WordPress + LearnDash 5.1.4. Curso: **1. PROGRAMA ACELERA** (ID `16242`, modo libre).
> Fuentes: `brief-plugin-acelera.md`, `Especificacion_Formulario_ACELERA.md`, `orden_curso.md`, código de referencia en `sfwd-lms/`.

## Fases

| Fase | Archivo | Requerimiento | Depende de |
|---|---|---|---|
| 1 | `01-fase-1-fundaciones.md` | Infraestructura base | — |
| 2 | `02-fase-2-gate-bienvenida.md` | Req 1 — Gate de Bienvenida | Fase 1 |
| 3 | `03-fase-3-sidebar-acordeones.md` | Req 2 — Acordeones sidebar | Fase 1 |
| 4 | `04-fase-4-formulario-acelera.md` | Req 3 — Formulario ACELERA | Fase 1 (tabla DB) |
| 5 | `05-fase-5-persistencia-clientify.md` | Req 4 — Clientify CRM | Fase 4 |
| 6 | `06-fase-6-feedback-llm.md` | Req 5 — Feedback LLM | Fase 1 (settings) |
| 7 | `07-fase-7-qa-hardening.md` | QA / seguridad | Todas |

Las fases 2, 3, 4 y 6 son independientes entre sí y pueden implementarse en paralelo una vez terminada la Fase 1.

## Decisiones confirmadas con el cliente

1. **Se reordenan los 5 módulos** según el scoring; la sección **Bienvenida nunca se reordena** (siempre primera).
2. **Scoring 100% por reglas** (sin IA en el formulario). El LLM solo se usa en el Req 5 (feedback por módulo).
3. **CV**: se sube el archivo a `uploads/` y se adjunta el link en la nota de Clientify. No se parsea.
4. **Formulario completo**: se implementan todos los Bloques 0–7 con toda la lógica condicional de la especificación.
5. Las preguntas van **hardcodeadas en PHP** (sin constructor). Cambios futuros = editar código.

## Arquitectura

Se extiende el esqueleto existente (WordPress Plugin Boilerplate):

```
formulario-acelera-ai-daniel.php      # Bootstrap (existente)
includes/
  class-...-daniel.php                # Core: registra todos los hooks (existente, se amplía)
  class-...-loader.php                # Loader WPPB (existente)
  class-...-activator.php             # Crea tabla DB (Fase 1)
  config/class-acelera-course-map.php # IDs hardcodeados del curso (Fase 1)
  gate/class-acelera-welcome-gate.php # Req 1 (Fase 2)
  form/                               # Req 3 (Fase 4)
    class-acelera-questions.php       #   Definición hardcodeada de preguntas
    class-acelera-scoring.php         #   Motor de reglas
    class-acelera-rest.php            #   Endpoints REST
    class-acelera-email.php           #   Email de resultado
    class-acelera-renaming.php        #   Renumeración de módulos por usuario
  crm/class-acelera-clientify.php     # Req 4 (Fase 5)
  llm/                                # Req 5 (Fase 6)
    class-acelera-llm-client.php
    class-acelera-module-feedback.php
admin/
  class-...-admin.php                 # Settings page (Fase 1, se completa en 5 y 6)
public/
  class-...-public.php                # Enqueues + overrides de templates LD
  partials/                           # Vistas del formulario y acordeón
  templates/ld30/                     # Overrides de templates ld30 (Fase 3)
  css/ · js/
```

**Convenciones**: prefijo `acelera_` para opciones/hooks propios; tabla `{$wpdb->prefix}acelera_form_submissions`; text domain existente `formulario-acelera-ai-daniel`; PHP 7.4+ compatible.

## Mapa del curso (IDs hardcodeados — fuente: `orden_curso.md`)

### Curso
- **Curso**: `16242` — modo libre (progresión lineal desactivada).

### Bienvenida (gate — nunca se reordena)
| Clase | ID |
|---|---|
| Onboarding | 16243 |
| Comunidad | 16244 |
| Acuerdo estudiantil | 16245 |
| Formulario de Diagnóstico (shortcode aquí) | 16246 |

### Módulos ↔ Rutas
| Módulo | Ruta del formulario | Primera clase | Última clase | Todas las clases (IDs) |
|---|---|---|---|---|
| M1 — Decisión de Emigrar | Migratoria | 16247 | 16258 | 16247–16253, 16256, 16257, 16258 |
| M2 — Empresa y Emprendimiento | Empresa / Emprendimiento | 16254 | 16276 | 16254, 16259–16276 |
| M3 — Profesional | Profesional | 16255 | 16282 | 16255, 16277–16282 |
| M4 — Reubicación y Softlanding | Softlanding Familiar | 16283 | 16297 | 16283–16297 |
| M5 — Inversión / Patrimonio | Inversión / Patrimonio | 16298 | 16302 | 16298–16302 |

> Nota: los IDs NO son contiguos por módulo (ej. 16254 es de M2 pero está entre IDs de M1). El mapa debe ser explícito por lista, no por rango.

## Puntos de integración LearnDash 5.1.4 (verificados en `sfwd-lms/`)

| Uso | Hook / función | Ubicación |
|---|---|---|
| ¿Lección completada? | `learndash_is_lesson_complete( $user_id, $lesson_id, $course_id )` | `includes/course/ld-course-progress.php:1292` |
| Bloquear acceso a un step | filtro `learndash_can_user_read_step` | `includes/classes/class-ldlms-model-course.php:264` |
| Bloqueo alternativo por fecha | filtro `ld_lesson_access_from` | `includes/course/ld-course-user-functions.php:715` |
| Override de cualquier template ld30 | filtro `learndash_template` | `includes/class-ld-lms.php:5071` |
| Secciones del curso (keyed por lesson ID) | `learndash_30_get_course_sections( $course_id )` | `themes/ld30/includes/helpers.php:2426` |
| Storage de secciones | post meta `course_sections` (JSON) en el curso | `includes/classes/class-ldlms-model-course-steps.php:1103` |
| Sidebar focus mode | `themes/ld30/templates/focus/sidebar.php` → `widgets/navigation/rows.php` | — |
| Heading de sección (sidebar) | `themes/ld30/templates/widgets/navigation/section.php` | — |
| Fila de lección (sidebar) | `themes/ld30/templates/widgets/navigation/lesson-row.php` | — |
| Heading de sección (página curso) | `themes/ld30/templates/lesson/partials/section.php` | — |

## Criterio de "hecho" global

- Los 5 requerimientos del brief funcionan en el curso 16242 sin tocar `sfwd-lms/` ni el theme.
- Todo lo configurable (Clientify, LLM, prompts) vive en la página de ajustes del plugin.
- Checklist de QA de la Fase 7 completado.
