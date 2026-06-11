# Fase 3 — Sidebar con acordeones (Req 2)

> Objetivo: del Módulo 2 en adelante, las secciones del listado izquierdo (focus mode sidebar) se muestran como acordeones desplegables para no listar demasiadas clases a la vez.
> Depende de: Fase 1. Se coordina con: Fase 2 (candados) y Fase 4 (renumeración de títulos).

## 3.1 Estrategia de override de templates

- [ ] Usar el filtro `learndash_template` (`sfwd-lms/includes/class-ld-lms.php:5071`) para redirigir templates ld30 a copias del plugin en `public/templates/ld30/`. Aplicar **solo si** el curso actual es 16242 (resolver con `learndash_get_course_id()`); cualquier otro curso usa los templates originales.
- [ ] Templates a sobrescribir (copiar desde `sfwd-lms/themes/ld30/templates/` y modificar mínimamente):
  - `widgets/navigation/section.php` → heading de sección clickeable (toggle del acordeón).
  - `widgets/navigation/lesson-row.php` → envolver filas en el contenedor de su sección + candado del gate (Fase 2).
  - Si la estructura DOM lo exige, también `widgets/navigation/rows.php`.

## 3.2 Lógica de agrupado

- [ ] Las secciones se obtienen con `learndash_30_get_course_sections( $course_id )` (`sfwd-lms/themes/ld30/includes/helpers.php:2426`) — devuelve headings keyed por el ID de la lección que precede cada sección.
- [ ] Reglas de comportamiento:
  - **Bienvenida y Módulo 1**: expandidos siempre (sin acordeón) — el brief pide acordeón "del Módulo 2 en adelante".
  - **Módulos 2–5**: colapsados por defecto.
  - La sección que contiene la **clase actual** se abre automáticamente.
  - Identificar a qué módulo pertenece cada sección vía `Acelera_Course_Map::module_for_lesson()` sobre la primera lección de la sección (no por título — los títulos cambiarán con la renumeración).
- [ ] **Importante**: si el usuario ya completó el formulario (Fase 4), el ORDEN visual de las secciones también cambia; mantener la lógica de acordeón independiente del orden.

## 3.3 Frontend (JS/CSS)

- [ ] `public/js/acelera-accordion.js` (vanilla, sin jQuery):
  - Toggle por click en el heading; chevron rotatorio; `aria-expanded` / `aria-controls` para accesibilidad.
  - Animación CSS (`max-height` o `grid-template-rows`) — sin librerías.
  - Persistir estado abierto/cerrado en `sessionStorage` para no perder el estado al navegar entre clases.
- [ ] `public/css/acelera-accordion.css`: estilos del heading clickeable, chevron, filas bloqueadas (`acelera-locked`), compatibles con los estilos ld30 existentes (clase base `ld-lesson-item-section-heading`).
- [ ] Encolar solo en páginas del curso 16242 (curso, lecciones, focus mode).

## 3.4 Hooks de coordinación

- [ ] En el template de sección, aplicar filtro propio `acelera_section_title` antes de imprimir el título → la Fase 4 lo usa para renumerar ("Módulo 5 …" → "Módulo 1 …").
- [ ] En `lesson-row.php`, consultar `Acelera_Welcome_Gate::is_lesson_locked()` para candado (Fase 2).

## Riesgos / notas

- El theme del sitio podría sobrescribir templates ld30 por su cuenta (carpeta `learndash/` en el theme). Verificar en el sitio real qué template gana y ajustar prioridad del filtro `learndash_template` (correr al final, prioridad alta).
- Probar tanto el **focus mode sidebar** (`focus/sidebar.php`) como el widget/listado de la página del curso; el brief habla del "listado izquierdo" = focus mode, pero validar el listing de la página del curso también.

## Criterio de hecho

- En el sidebar: Bienvenida y M1 siempre visibles; M2–M5 colapsados, expandibles con click, sección actual auto-abierta.
- Sin efectos en otros cursos del sitio.
- Navegación con teclado funcional (accesibilidad básica).
