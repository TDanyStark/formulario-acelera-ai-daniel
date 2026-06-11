# Fase 4 — Formulario ACELERA (Req 3)

> Objetivo: formulario de diagnóstico por pasos (shortcode en la clase 16246), scoring por reglas (sin IA), orden personalizado de los 5 módulos, renumeración por usuario, email con el resultado y opción de reset.
> Depende de: Fase 1 (tabla + repo + settings email). Alimenta: Fase 5 (Clientify).
> Especificación de preguntas: `Especificacion_Formulario_ACELERA.md` (Bloques 0–7, COMPLETOS).

## 4.1 Shortcode y estados

- [ ] Registrar `[acelera_form]` → se inserta manualmente en el contenido de la clase 16246.
- [ ] Render según estado del usuario (vía `Acelera_Submissions_Repo::get_active_for_user()`):
  1. **Sin sumisión activa** → formulario por pasos.
  2. **Con sumisión completada** → pantalla de resultado: orden personalizado de módulos (cards con nombre renumerado, link a primera clase) + botón **Resetear**.
  3. **No logueado** → mensaje (caso borde; la clase requiere login por LearnDash).
- [ ] **Resetear**: confirma con modal → `mark_reset()` en el repo, borra user_meta de renumeración → vuelve al estado 1. Siempre disponible en la pantalla de resultado.

## 4.2 Definición de preguntas (hardcodeadas)

Crear `includes/form/class-acelera-questions.php` — un array PHP que define TODO el formulario; el frontend lo recibe como JSON (`wp_localize_script`):

```php
[
  'id'        => 'p1_1',
  'block'     => 1,
  'type'      => 'multi',          // text|email|tel|date|single|multi|scale|repeater|file|textarea
  'label'     => '¿Cuál es tu objetivo principal en este momento?',
  'required'  => true,
  'max'       => 2,                // multi-selección máx. 2
  'options'   => [ ['value'=>'migratorio','label'=>'Resolver mi situación migratoria...'], ... ],
  'show_if'   => null,             // o condición: ['question'=>'p2_2','op'=>'in','value'=>['visa_vigente','en_proceso']]
  'skippable' => false,            // controla el botón Omitir
]
```

- [ ] Implementar TODOS los bloques de la especificación con su lógica condicional (checklist de la sección "RESUMEN DE CAMPOS CONDICIONALES"):
  - **B0** datos de contacto (P0.1–P0.6) — P0.1/P0.2 precargados desde el usuario WP (`display_name`, `user_email`), **editables**.
  - **B1** objetivo (P1.1 multi máx 2, P1.2).
  - **B2** migratorio (P2.1–P2.6; P2.3 condicional a P2.2).
  - **B3** familia (P3.1–P3.8; P3.2 **repetidor de hijos** [nombre, edad, estudia]; condicionales P3.2/3.3/3.4/3.5/3.7).
  - **B4** destino (P4.1–P4.6; condicionales P4.2/P4.5).
  - **B5** perfil económico (P5.1–P5.3) — bisagra de bloques 6.
  - **B6-A** profesional (condicional P5.2 ∈ empleado/independiente/sin actividad; P6A.5 condicional).
  - **B6-B** negocio (condicional P5.2 ∈ dueño/idea; P6B.3–6B.4 condicionales a "ya vende").
  - **B6-C** inversión (condicional P5.2 = inversiones **O** P5.3 ≥ $150k).
  - **CV** (condicional: objetivo migratorio/profesional O P5.2 empleado/independiente) — upload PDF/Word, opcional/saltable. Guardar en `uploads/acelera-cv/{user_id}/` vía `wp_handle_upload` (validar MIME/extensión y tamaño máx. 10 MB). Solo se guarda la URL (`cv_url`); no se parsea.
  - **B7** cierre (P7.1–P7.5; P7.4 condicional a egresado).

## 4.3 UX por pasos (frontend)

`public/js/acelera-form.js` + `public/css/acelera-form.css` (vanilla JS, sin framework):

- [ ] **Una pregunta por pantalla**; botones **Atrás / Omitir / Siguiente** (Omitir solo en preguntas no obligatorias; deshabilitar Siguiente hasta respuesta válida en obligatorias).
- [ ] Barra de progreso (% sobre preguntas visibles según condicionales evaluadas en vivo).
- [ ] Motor condicional en JS: evalúa `show_if` con las respuestas acumuladas; preguntas ocultas no cuentan ni se envían.
- [ ] Validaciones inline: email, teléfono, fecha, máx. 2 en P1.1.
- [ ] Repetidor de hijos: "+ Agregar hijo" con sub-campos.
- [ ] Autosave del avance en `localStorage` (recuperar si recarga la página antes de enviar). El envío real es uno solo al final.
- [ ] Soporte teclado (Enter = siguiente) y mobile-first.

## 4.4 Endpoints REST

`includes/form/class-acelera-rest.php` — namespace `acelera/v1`, todos con `permission_callback` (usuario logueado) + nonce (`X-WP-Nonce`):

| Endpoint | Método | Acción |
|---|---|---|
| `/submit` | POST | Valida server-side (reglas espejo de las condicionales), guarda en DB, ejecuta scoring, guarda renumeración, marca clase 16246 completa (`learndash_process_mark_complete`), dispara email + hook `acelera_form_completed` (Fase 5 escucha aquí) |
| `/upload-cv` | POST | `wp_handle_upload` del CV; devuelve URL |
| `/reset` | POST | Soft reset de la sumisión activa |
| `/result` | GET | Orden + scores de la sumisión activa (para re-render de la pantalla resultado) |

## 4.5 Motor de scoring por reglas (sin IA)

`includes/form/class-acelera-scoring.php` — puntúa cada ruta 0–100 según el Anexo de la especificación:

| Ruta (módulo) | Señales que suman (pesos orientativos, ajustables en una tabla interna) |
|---|---|
| **Migratoria (M1)** | P1.1 incluye migratorio (+40); P2.2 sin estatus (+20); **bloqueador**: P2.4=No Y P2.5="operar yo mismo" (+30 y flag `bloqueador_migratorio`); P1.2 urgencia alta (+10); P2.6 sin abogado (+10) |
| **Empresa (M2)** | P1.1 negocio (+40); P5.2 dueño/idea (+25); B6-B completo: etapa avanzada P6B.1 (+10), facturación (+10), dependencia (+5) |
| **Profesional (M3)** | P1.1 empleo (+40); P5.2 empleado/independiente (+25); P4.6 inglés bajo (señal de necesidad +10); B6-A: networking débil (+10), LinkedIn débil (+10); CV subido (+5) |
| **Softlanding (M4)** | P1.1 familia (+40); P3.1 con hijos (+25) / con pareja (+10); P3.3 "no decidido" (+10); P4.1 sin ciudad (+10); P4.4 sin red (+10) |
| **Inversión (M5)** | P1.1 invertir (+40); P5.2 vive de inversiones (+25); P5.3 ≥ $150k (+20); B6-C: horizonte inmediato (+10) |

Reglas especiales (del Anexo):
- [ ] Bloqueador migratorio: si flag activo, **M1 va primero** sin importar el puntaje del resto.
- [ ] "No estoy seguro" en preguntas críticas (P2.4, P2.5) → flag `revision_asesor` (se incluye en la nota de Clientify y en el email interno si se desea).
- [ ] P1.1 = "no lo tengo claro" → no fuerza ruta: ordenar solo por señales indirectas y flag `diagnostico_abierto`.
- [ ] Desempates: orden natural del curso (m1 < m2 < ... ). Salida: `module_order` ej. `"m2,m1,m4,m3,m5"` + `scores` JSON.
- [ ] Pesos en una constante/array única y comentada para ajustes futuros fáciles (las preguntas van hardcodeadas por decisión del brief).

## 4.6 Renumeración de módulos por usuario

`includes/form/class-acelera-renaming.php`:

- [ ] Al completar: guardar user_meta `acelera_module_order` (array) y mapa `acelera_module_labels` → ej. si su orden es `[m5,m2,...]`: m5 se muestra como "Módulo 1. Inversión / Patrimonio", m2 como "Módulo 2. ...", etc. (se conserva el nombre temático, cambia el número).
- [ ] Aplicar en:
  - Filtro propio `acelera_section_title` (headings del sidebar y página curso — coordina con Fase 3).
  - `the_title` filtrado con cuidado (solo en contexto del curso 16242, solo para headings de sección si los títulos de lecciones incluyen el número del módulo — validar en sitio real; si las lecciones llevan "Módulo X" en el título, filtrar también).
- [ ] **Reordenar visualmente** las secciones del sidebar según `module_order` (en el override de `rows.php`/datos de secciones, Fase 3). Bienvenida siempre primera.
- [ ] Sin sumisión activa → sin renumeración ni reorden (orden natural).

## 4.7 Email de resultado

`includes/form/class-acelera-email.php`:

- [ ] `wp_mail()` HTML al email que el usuario puso en P0.2 (no necesariamente el del user WP).
- [ ] Contenido: saludo con nombre + "Tu orden de ejecución del curso" + **solo los 5 módulos** en su orden personalizado, cada uno como botón/link a la **primera clase del módulo** (`Acelera_Course_Map::modules()[x]['first_lesson']` → `get_permalink()`). **No listar clases individuales.**
- [ ] Template HTML inline-styles (compatibilidad clientes de correo) en `public/partials/email-resultado.php`.
- [ ] Subject/from configurables (pestaña Email de Fase 1, con defaults).
- [ ] Header `Content-Type: text/html` vía filtro temporal; log si `wp_mail` devuelve false.

## Criterio de hecho

- Flujo E2E: usuario abre clase 16246 → responde paso a paso (condicionales correctas) → ve su orden personalizado → sidebar renumerado/reordenado → recibe email con links correctos → clase 16246 marcada completa (gate Fase 2 desbloquea si era la última).
- Reset funciona y vuelve todo al estado original.
- Revisita de la clase 16246 muestra siempre el resultado, no el formulario.
