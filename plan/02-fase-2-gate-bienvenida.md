# Fase 2 — Gate de Bienvenida (Req 1)

> Objetivo: las clases de los Módulos 1–5 no son accesibles hasta completar las 4 clases de Bienvenida (16243–16246). Superado el gate, el curso sigue en modo libre.
> Depende de: Fase 1 (`Acelera_Course_Map`).

## 2.1 Helper de estado del gate

Crear `includes/gate/class-acelera-welcome-gate.php`:

- [ ] `is_welcome_completed( $user_id ): bool`
  - Itera `Acelera_Course_Map::WELCOME_LESSONS` y llama `learndash_is_lesson_complete( $user_id, $lesson_id, COURSE_ID )` (`sfwd-lms/includes/course/ld-course-progress.php:1292`).
  - **Caché por request** (propiedad estática) — el sidebar lo consulta una vez por fila de lección.
  - **Caso especial clase 16246 (Formulario)**: definir cómo se marca completa. Opción elegida: al completar el formulario ACELERA (Fase 4), el plugin llama `learndash_process_mark_complete( $user_id, 16246, ... )` automáticamente. Así el gate y el formulario quedan sincronizados. Documentar que también puede marcarla con el botón "Marcar completa" estándar.
- [ ] `is_lesson_locked( $lesson_id, $user_id ): bool` → `true` si la lección pertenece a M1–M5 (`Acelera_Course_Map::module_for_lesson()`) y el gate no está superado.
- [ ] Bypass: admins y usuarios con `manage_options`, y el filtro propio `acelera_welcome_gate_bypass` para excepciones futuras.

## 2.2 Bloqueo de acceso (server-side, doble capa)

**Capa A — redirect duro** (la garantía real):

- [ ] Hook `template_redirect`: si `is_singular('sfwd-lessons')` (y topics si existieran) y `is_lesson_locked()` → `wp_safe_redirect()` a la primera clase de Bienvenida incompleta, con query arg `?acelera_gate=1`.
- [ ] En la clase destino, mostrar notice: "Debes completar el módulo de Bienvenida antes de acceder al resto del curso." (hook `learndash-focus-content-before` o `the_content` prepend).

**Capa B — filtros LearnDash** (consistencia visual/lógica):

- [ ] Filtro `learndash_can_user_read_step` (`sfwd-lms/includes/classes/class-ldlms-model-course.php:264`): devolver `false` para lecciones bloqueadas. Nota: en LD 5.1.4 este filtro corre siempre, pero el enforcement completo depende de `LEARNDASH_COURSE_STEP_READ_CHECK`; por eso la Capa A es la garantía.
- [ ] NO usar `ld_lesson_access_from` como mecanismo principal (está pensado para drip por fechas y genera mensajes de "disponible el {fecha}" confusos).

## 2.3 Señalización en sidebar y listado

- [ ] Filtro sobre las filas del sidebar (se coordina con el override de `lesson-row.php` de la Fase 3): si la lección está bloqueada, agregar clase CSS `acelera-locked` + icono candado, y reemplazar el `href` por `#` con tooltip "Completa Bienvenida primero".
- [ ] Mismo tratamiento en el listado de la página del curso (`themes/ld30/templates/course/listing.php` rows) si aplica.
- [ ] CSS: estilo atenuado (opacity) para filas bloqueadas.

## 2.4 Compatibilidad con modo libre

- [ ] No tocar la opción `course_disable_lesson_progression` del curso: el curso sigue configurado libre en LearnDash; el gate es 100% del plugin.
- [ ] Verificar que al completar la 4ª clase de Bienvenida el desbloqueo es inmediato (sin caché stale): invalidar la caché estática en el hook `learndash_lesson_completed`.

## Criterio de hecho

- Usuario nuevo: solo puede abrir las 4 clases de Bienvenida; cualquier URL directa a clases de M1–M5 redirige con aviso.
- Usuario con Bienvenida completa: acceso libre a todo, sin redirecciones ni candados.
- Admin: nunca bloqueado.
