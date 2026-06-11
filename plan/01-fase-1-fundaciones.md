# Fase 1 — Fundaciones

> Objetivo: dejar el esqueleto WPPB listo para las fases funcionales: mapa de IDs, tabla de base de datos, página de ajustes y limpieza de stubs demo.
> Depende de: nada. Bloquea: todas las demás fases.

## 1.1 Limpieza del boilerplate

- [ ] Eliminar/vaciar los métodos demo de enqueue en `admin/class-formulario-acelara-ai-daniel-admin.php` y `public/class-formulario-acelara-ai-daniel-public.php` (los comentarios "for demonstration purposes only").
- [ ] Mantener el patrón loader: todos los hooks nuevos se registran en `includes/class-formulario-acelara-ai-daniel.php` → `define_admin_hooks()` / `define_public_hooks()`.
- [ ] Encolar CSS/JS **solo** cuando se está dentro del curso 16242 o sus lecciones (chequear `learndash_get_course_id()` en el enqueue público).
- [ ] Agregar guard: si LearnDash no está activo (`!defined('LEARNDASH_VERSION')`), mostrar admin notice y no registrar hooks públicos.

## 1.2 Mapa de configuración del curso

Crear `includes/config/class-acelera-course-map.php` con constantes/métodos estáticos:

```php
Acelera_Course_Map::COURSE_ID                 // 16242
Acelera_Course_Map::WELCOME_LESSONS           // [16243, 16244, 16245, 16246]
Acelera_Course_Map::FORM_LESSON_ID            // 16246
Acelera_Course_Map::modules()                 // array por clave 'm1'..'m5':
//  [
//    'm1' => [
//      'label'        => 'Decisión de Emigrar',
//      'route'        => 'migratoria',
//      'first_lesson' => 16247,
//      'last_lesson'  => 16258,
//      'lessons'      => [16247,16248,16249,16250,16251,16252,16253,16256,16257,16258],
//    ],
//    'm2' => [... route 'empresa',     first 16254, last 16276, lessons 16254 + 16259..16276],
//    'm3' => [... route 'profesional', first 16255, last 16282, lessons 16255 + 16277..16282],
//    'm4' => [... route 'softlanding', first 16283, last 16297, lessons 16283..16297],
//    'm5' => [... route 'inversion',   first 16298, last 16302, lessons 16298..16302],
//  ]
Acelera_Course_Map::module_for_lesson( $lesson_id )   // 'm1'..'m5' | 'welcome' | null
Acelera_Course_Map::all_module_lessons()              // flat array de IDs M1–M5
```

- [ ] IDs por **lista explícita** (no rangos): los IDs no son contiguos por módulo (ver overview).
- [ ] Toda referencia a IDs en el resto del plugin pasa por esta clase. Cero números mágicos fuera de aquí.

## 1.3 Base de datos (Activator)

En `includes/class-formulario-acelara-ai-daniel-activator.php`:

- [ ] Crear tabla con `dbDelta()`:

```sql
CREATE TABLE {$wpdb->prefix}acelera_form_submissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  answers LONGTEXT NOT NULL,            -- JSON: respuestas crudas por pregunta (P0.1, P1.1, ...)
  scores LONGTEXT NULL,                 -- JSON: puntaje por ruta {migratoria: 80, ...}
  module_order VARCHAR(50) NULL,        -- ej. "m2,m1,m4,m3,m5"
  flags LONGTEXT NULL,                  -- JSON: bloqueador_migratorio, revision_asesor, etc.
  cv_url VARCHAR(500) NULL,
  clientify_contact_id BIGINT NULL,
  clientify_status VARCHAR(20) NULL,    -- pending | sent | error
  status VARCHAR(20) NOT NULL DEFAULT 'completed',  -- in_progress | completed | reset
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY user_id (user_id),
  KEY status (status)
) {$charset_collate};
```

- [ ] Guardar `acelera_db_version` en options para futuras migraciones.
- [ ] **Reset = soft**: al resetear se marca `status = 'reset'` y se conserva la fila (historial); la sumisión activa es la última con `status = 'completed'`.
- [ ] Crear clase repositorio `includes/class-acelera-submissions-repo.php`: `get_active_for_user()`, `insert()`, `mark_reset()`, `update_clientify()`.

## 1.4 Página de ajustes (Settings API)

En `admin/class-formulario-acelara-ai-daniel-admin.php`:

- [ ] Menú propio: `add_menu_page( 'Acelera', 'Curso Acelera', 'manage_options', 'acelera-settings', ... )` (o submenú bajo Ajustes — decidir al implementar).
- [ ] Registrar grupo de opciones `acelera_settings` (una opción array serializada) con pestañas:

| Pestaña | Campos | Se usa en |
|---|---|---|
| **Clientify** | `clientify_api_key` (password), `clientify_owner` (default `info@cafecitoconcata.com`), `clientify_tags` (texto, separado por comas) | Fase 5 |
| **LLM** | `llm_provider` (select: claude/chatgpt), `anthropic_api_key`, `openai_api_key`, `llm_model` (texto, con defaults sensatos) | Fase 6 |
| **Prompts** | `prompt_m1` … `prompt_m5` (textareas) | Fase 6 |
| **Email** | `email_subject`, `email_from_name` (opcionales, con defaults) | Fase 4 |

- [ ] Sanitización en `register_setting` callback; API keys nunca se imprimen completas (mostrar últimos 4 chars).
- [ ] Helper de acceso global: `Acelera_Settings::get( 'clientify_api_key' )`.

## 1.5 Uninstall

En `uninstall.php`:

- [ ] Borrar opción `acelera_settings`, `acelera_db_version`.
- [ ] Borrar user_meta con prefijo `acelera_` (feedback LLM cacheado, etc.).
- [ ] `DROP TABLE {$wpdb->prefix}acelera_form_submissions`.

## Criterio de hecho

- Plugin activa sin errores; tabla creada; página de ajustes guarda y recupera valores.
- `Acelera_Course_Map` es la única fuente de IDs.
- Sin restos del código demo del boilerplate.
