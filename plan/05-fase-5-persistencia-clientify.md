# Fase 5 — Persistencia + Integración Clientify (Req 4)

> Objetivo: respuestas guardadas en DB asociadas al usuario (ya cubierto por Fase 1/4) + envío del contacto y nota a Clientify CRM, con credenciales configurables en admin.
> Depende de: Fase 4 (hook `acelera_form_completed`). Docs API: https://newapi.clientify.com/

## 5.1 Cliente Clientify

Crear `includes/crm/class-acelera-clientify.php` usando `wp_remote_post` (timeout 15s, header `Authorization: Token {api_key}` — verificar formato exacto del header en la doc al implementar):

**Paso 1 — Crear contacto** (responde `id`):

```
POST https://api-plus.clientify.com/v2/contacts/
{
  "first_name": "...",            // split del nombre completo P0.1 (primera palabra / resto)
  "last_name": "...",
  "owner": "<setting clientify_owner>",          // default info@cafecitoconcata.com
  "phone": "<P0.3>",
  "email": "<P0.2>",
  "tags": ["<settings clientify_tags, array>"],
  "contact_source": "plugin_acelera_da",
  "marketing_status": 2
}
```

**Paso 2 — Crear nota:**

```
POST https://api-plus.clientify.com/v2/contacts/{id}/note/
{
  "name": "Formulario Acelera PRO VERSION",
  "comment": "<HTML básico>"
}
```

- [ ] Builder del `comment` HTML: recorrer bloques/preguntas respondidas con `<b>etiqueta:</b> respuesta<br>`; incluir al final: orden de módulos resultante, scores por ruta, flags (`bloqueador_migratorio`, `revision_asesor`) y link al CV si se subió. Solo HTML básico (`<br>`, `<b>`).
- [ ] Si el contacto ya existe (email duplicado): manejar respuesta de la API (4xx con id existente o búsqueda previa `GET /v2/contacts/?query=email`) — decidir al implementar según comportamiento real de la API; en el peor caso, crear nota sobre el contacto existente.

## 5.2 Envío asíncrono y resiliencia

- [ ] Listener del hook `acelera_form_completed` (disparado por `/submit` en Fase 4). El submit del usuario NUNCA espera a Clientify:
  - Programar evento one-off: `wp_schedule_single_event( time(), 'acelera_send_to_clientify', [ $submission_id ] )`.
  - Cron handler ejecuta Paso 1 + Paso 2 y actualiza la fila: `clientify_contact_id`, `clientify_status` (`sent` | `error`).
- [ ] Reintento: si falla, reprogramar hasta 3 intentos con backoff (5 min, 30 min, 2 h); tras agotar → `clientify_status = 'error'` + log.
- [ ] Log de errores: `error_log` con prefijo `[acelera-clientify]` + guardar último error en la fila (columna `flags` o transient) para diagnóstico.
- [ ] Si `clientify_api_key` está vacía → skip silencioso con `clientify_status = 'skipped'` (el formulario funciona sin CRM).

## 5.3 Admin

- [ ] Completar la pestaña **Clientify** de la página de ajustes (campos ya registrados en Fase 1: API key, owner, tags).
- [ ] Botón **"Probar conexión"** (AJAX admin): hace un GET simple autenticado y muestra OK/error.
- [ ] (Opcional, si hay tiempo) Columna de estado: tabla mínima en admin que liste sumisiones con su `clientify_status` y botón "Reenviar" para filas en error.

## Criterio de hecho

- Al completar el formulario: contacto creado en Clientify con tags/owner de settings + nota con todas las respuestas en HTML legible.
- El usuario nunca percibe latencia ni errores de Clientify.
- Fallos quedan registrados y son reintentados/reenviables.
