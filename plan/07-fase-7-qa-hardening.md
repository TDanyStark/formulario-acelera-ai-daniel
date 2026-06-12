# Fase 7 — QA y Hardening

> Objetivo: validar los 5 requerimientos end-to-end y cerrar seguridad antes de entregar.
> Depende de: Fases 1–6.

## 7.1 Seguridad (revisión transversal)

- [ ] **Nonces**: todos los endpoints REST con `permission_callback` real + `X-WP-Nonce`; acciones AJAX admin con `check_ajax_referer`.
- [ ] **Capabilities**: settings y test de conexión → `manage_options`; endpoints de formulario → `is_user_logged_in()`.
- [ ] **Sanitización entrada**: `sanitize_text_field`, `sanitize_email`, `absint`, whitelist de valores para selects/multi (rechazar valores fuera del catálogo de opciones hardcodeado).
- [ ] **Escape salida**: `esc_html`, `esc_attr`, `esc_url` en todos los partials; `wp_kses_post` para HTML de LLM y email.
- [ ] **Upload CV**: whitelist MIME (`application/pdf`, `.doc/.docx`), límite 10 MB, nombre aleatorizado, `index.php` vacío en la carpeta de uploads.
- [ ] **SQL**: todo por `$wpdb->prepare` / API del repo.
- [ ] **API keys**: nunca expuestas al frontend (los JS no reciben keys; todas las llamadas LLM/Clientify son server-side).
- [ ] **Datos sensibles** (P3.8 condición médica): viajan solo a DB y Clientify; no se imprimen en frontend salvo pantalla de resultado del propio usuario.

## 7.2 Checklist funcional por requerimiento

### Req 1 — Gate Bienvenida
- [ ] Usuario nuevo: URL directa a clase de M1–M5 → redirect a Bienvenida con aviso.
- [ ] Completar 3 de 4 clases de Bienvenida → sigue bloqueado.
- [ ] Completar las 4 → desbloqueo inmediato (sin caché stale).
- [ ] Completar el formulario marca la clase 16246 como completa.
- [ ] Admin nunca bloqueado. Curso sigue libre tras el gate.

### Req 2 — Acordeones
- [ ] M2–M5 colapsados por defecto; Bienvenida y M1 expandidos.
- [ ] Sección de la clase actual auto-abierta; estado persiste al navegar (sessionStorage).
- [ ] Otros cursos del sitio: sidebar intacto.
- [ ] Mobile + teclado (aria-expanded) funcionan.

### Req 3 — Formulario
- [ ] Precarga nombre/email del usuario WP, editables.
- [ ] Probar las 15 condicionales del "RESUMEN DE CAMPOS CONDICIONALES" de la especificación (P2.3, P3.2–3.5, P3.7, P4.2, P4.5, bloques 6-A/6-B/6-C, P6A.5, P6B.3/6B.4, CV, P7.4).
- [ ] Omitir solo disponible en no-obligatorias; Atrás conserva respuestas; recarga de página recupera avance (localStorage).
- [ ] Repetidor de hijos: agregar/quitar N hijos.
- [ ] Scoring: 5 perfiles sintéticos (uno por ruta dominante) → orden esperado; perfil con bloqueador migratorio → M1 primero; perfil "no lo tengo claro" → flag diagnóstico abierto.
- [ ] Renumeración: sidebar muestra "Módulo 1" en el módulo prioritario del usuario; secciones reordenadas; Bienvenida primera siempre.
- [ ] Revisita de clase 16246 → pantalla resultado; Reset → formulario de cero y sidebar vuelve al orden natural.
- [ ] Email: llega, HTML correcto, 5 links de módulos → primera clase de cada uno, sin listar clases.

### Req 4 — Persistencia + Clientify
- [ ] Fila en `acelera_form_submissions` con answers/scores/module_order/flags correctos.
- [ ] Contacto creado en Clientify (owner/tags de settings, source `plugin_acelera_da`, marketing_status 2) + nota HTML legible.
- [ ] API key inválida → submit del usuario no se afecta; status `error` + reintentos; key vacía → `skipped`.
- [ ] Test de conexión en admin funciona.

### Req 5 — Feedback LLM
- [ ] Insertar `[acelera_feedback module="m1"]` ... `[acelera_feedback module="m5"]` desde el editor LearnDash en clases elegidas por el admin (antepenúltima, final del módulo o posterior) → primera visita genera feedback del módulo correcto.
- [ ] Segunda visita → desde caché (verificar en logs que no hay llamada API).
- [ ] Cambio de provider Claude↔ChatGPT funciona; prompts por módulo respetados; placeholders reemplazados.
- [ ] Sin API key / API caída → clase se ve normal, sin errores visibles.
- [ ] Doble pestaña simultánea → una sola llamada (lock).

## 7.3 Casos borde generales

- [ ] Usuario sin acceso al curso / deslogueado a mitad del formulario (sesión expirada → mensaje de re-login sin perder avance local).
- [ ] LearnDash desactivado → admin notice, sin fatals.
- [ ] Activar/desactivar/reactivar plugin → sin pérdida de datos; uninstall → limpieza completa.
- [ ] Lecciones agregadas al curso en el futuro (IDs fuera del mapa) → comportamiento definido: quedan fuera del gate y sin renumeración (documentar que hay que actualizar `Acelera_Course_Map`).

## 7.4 Entrega

- [ ] Actualizar `README.txt` con descripción real, configuración (settings) y nota de mantenimiento del mapa de IDs.
- [ ] Bump de versión + commit final etiquetado (el header tiene Git Updater apuntando a `TDanyStark/formulario-acelara-ai-daniel`).
