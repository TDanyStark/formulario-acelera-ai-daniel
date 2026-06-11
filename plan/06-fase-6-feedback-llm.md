# Fase 6 — Feedback con LLM por módulo (Req 5)

> Objetivo: en la última clase de cada módulo se muestra un feedback generado por LLM (Claude o ChatGPT, configurable), con prompt por módulo editable en settings, ejecutado solo la primera vez (cacheado por usuario).
> Depende de: Fase 1 (settings LLM/prompts). Usa respuestas del formulario (Fase 4) como contexto si existen.

## 6.1 Cliente LLM multi-provider

Crear `includes/llm/class-acelera-llm-client.php`:

- [ ] Interfaz única `generate( string $system_prompt, string $user_content ): string|WP_Error` con dos drivers según setting `llm_provider`:
  - **Anthropic (Claude)**: `POST https://api.anthropic.com/v1/messages` — headers `x-api-key`, `anthropic-version: 2023-06-01`; body `{ model, max_tokens, system, messages:[{role:'user',content}] }`. Model default configurable (setting `llm_model`).
  - **OpenAI (ChatGPT)**: `POST https://api.openai.com/v1/chat/completions` — header `Authorization: Bearer`; body `{ model, messages:[{role:'system'},{role:'user'}] }`.
- [ ] `wp_remote_post` con timeout 30s; parse de respuesta y normalización a texto plano/HTML básico; errores → `WP_Error` (nunca excepción al frontend).
- [ ] Verificar nombres de modelos vigentes al implementar (consultar docs oficiales); defaults sensatos editables en settings.

## 6.2 Inyección en la última clase de cada módulo

Crear `includes/llm/class-acelera-module-feedback.php`:

- [ ] Detectar lección actual = `last_lesson` de algún módulo (`Acelera_Course_Map`): M1:16258, M2:16276, M3:16282, M4:16297, M5:16302.
- [ ] Filtro `the_content` (solo `is_singular('sfwd-lessons')`, solo esos 5 IDs, solo usuario logueado): añadir al final un contenedor `<div id="acelera-feedback" data-module="mX">` con estado "Generando tu feedback personalizado…".
- [ ] **Carga vía AJAX/REST** (`GET acelera/v1/feedback/{module}`) para no bloquear el render de la clase:
  1. Si existe caché → devolverla de inmediato.
  2. Si no → llamar al LLM, cachear, devolver.
  3. Si la API falla o no hay API key → devolver vacío y **ocultar el contenedor** (fallback silencioso: la clase nunca se rompe ni muestra error al alumno).
- [ ] Lock anti-doble-llamada: transient `acelera_llm_lock_{user}_{module}` (60s) para evitar llamadas concurrentes (doble pestaña).

## 6.3 Prompt y contexto

- [ ] System prompt = setting `prompt_mX` del módulo (textarea en pestaña Prompts, Fase 1). Sin prompt configurado → no se llama al LLM para ese módulo.
- [ ] User content (contexto que se envía): nombre del alumno, módulo terminado, y si existe sumisión del formulario: orden de rutas, scores y respuestas clave (objetivo P1.1, situación P5.2, etc.). Documentar placeholders disponibles para que el admin escriba sus prompts: `{nombre}`, `{modulo}`, `{ruta_principal}`, `{respuestas_resumen}` — reemplazo simple con `str_replace`.

## 6.4 Caché (una sola llamada por usuario+módulo)

- [ ] user_meta `acelera_llm_feedback_{module_key}` = `[ 'html' => ..., 'generated_at' => ..., 'provider' => ..., 'model' => ... ]`.
- [ ] La llamada al LLM ocurre **solo la primera vez** que el alumno abre esa clase; siguientes visitas leen user_meta.
- [ ] El **reset del formulario** (Fase 4) NO borra el feedback cacheado (es feedback del módulo, no del formulario). Sí se borra en uninstall.
- [ ] (Opcional admin) Botón en settings "Regenerar feedback de un usuario" para soporte.

## 6.5 Sanitización de salida

- [ ] Pasar la respuesta del LLM por `wp_kses_post` antes de cachear/imprimir.
- [ ] Si el LLM responde markdown, conversión mínima (negritas, listas, párrafos) o instruir HTML básico desde el prompt del sistema.

## Criterio de hecho

- Al abrir la última clase de un módulo por primera vez: aparece feedback personalizado en segundos (skeleton mientras carga).
- Visitas posteriores: feedback instantáneo desde caché, cero llamadas al LLM (verificable en logs).
- Cambiar provider Claude ↔ ChatGPT en settings funciona sin tocar código.
- Sin API key o con error de API: la clase se ve normal, sin rastro de error.
