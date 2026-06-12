=== Formulario Acelara AI Daniel ===
Contributors: danielamado
Donate link: https://danielamado.com/
Tags: learndash, acelera, formulario, clientify, llm
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Plugin a medida para el curso ACELERA en LearnDash: gate de Bienvenida, sidebar con acordeones, diagnóstico con scoring, Clientify y feedback LLM por módulo.

== Description ==

**Formulario Acelara AI Daniel** es un plugin a medida para el curso **ACELERA** de LearnDash, curso ID `16242`.

El plugin adapta la experiencia del curso para que cada alumno complete primero la sección de Bienvenida, responda un diagnóstico y reciba un orden personalizado de módulos según sus respuestas.

Funcionalidades principales:

* **Gate de Bienvenida:** las clases de los módulos 1 a 5 quedan bloqueadas hasta completar las clases de Bienvenida del curso.
* **Sidebar con acordeones:** las secciones del curso se muestran como acordeones para reducir el ruido visual en la navegación de LearnDash.
* **Formulario diagnóstico:** shortcode `[acelera_form]` para la clase `16246`; calcula scoring por reglas, guarda la sumisión y define el orden personalizado de módulos del alumno.
* **Renumeración personalizada:** los módulos se presentan al alumno en el orden recomendado por el diagnóstico, sin cambiar la estructura base del curso.
* **Integración Clientify:** crea/actualiza el contacto y registra una nota con el resumen del diagnóstico.
* **Feedback LLM por módulo:** shortcode `[acelera_feedback module="mX"]` para mostrar feedback generado por Claude o ChatGPT; el resultado se cachea por alumno y módulo.

== Installation ==

1. Sube la carpeta `formulario-acelara-ai-daniel` a `/wp-content/plugins/`.
2. Activa el plugin desde **Plugins** en WordPress.
3. Verifica que LearnDash esté activo y que el curso ACELERA use el ID `16242`.
4. Configura el plugin desde **Curso Acelera** en el administrador de WordPress.
5. Coloca `[acelera_form]` en la clase de diagnóstico `16246`.
6. Coloca `[acelera_feedback module="m1"]` a `[acelera_feedback module="m5"]` en las clases donde deba mostrarse el feedback de cada módulo.

== Configuración ==

La configuración vive en el menú de administración **Curso Acelera**.

= Ajustes > Clientify =

Campos disponibles:

* **API Key de Clientify:** clave usada para autenticar contra Clientify.
* **Owner (email):** email del propietario asignado a los contactos creados. Valor por defecto: `info@cafecitoconcata.com`.
* **Tags:** tags separados por comas que se aplican a los contactos.

La pestaña incluye el botón **Probar conexión** para validar la API key guardada desde el administrador.

= Ajustes > LLM =

Campos disponibles:

* **Proveedor:** `Claude (Anthropic)` o `ChatGPT (OpenAI)`.
* **API Key de Anthropic:** clave para Claude.
* **API Key de OpenAI:** clave para ChatGPT.
* **Modelo:** identificador opcional del modelo. Si queda vacío, el plugin usa el default del proveedor activo.

También incluye una herramienta de soporte para **Regenerar feedback**: borra el feedback cacheado de un alumno por ID o email y por módulo (`m1` a `m5`) o todos los módulos. El feedback se vuelve a generar en la próxima visita del alumno a una clase con el shortcode.

= Ajustes > Prompts =

Cada módulo (`m1` a `m5`) tiene su propio prompt de sistema para generar el feedback LLM. Si el prompt de un módulo queda vacío, el shortcode de ese módulo no genera feedback y se oculta.

Placeholders disponibles en los prompts:

* `{nombre}`: nombre del alumno.
* `{modulo}`: nombre temático del módulo indicado en el shortcode.
* `{ruta_principal}`: primer módulo del orden personalizado del alumno.
* `{respuestas_resumen}`: resumen compacto del diagnóstico, incluyendo objetivo, situación y orden con puntajes.

`{ruta_principal}` y `{respuestas_resumen}` quedan vacíos si el alumno todavía no completó el formulario diagnóstico.

= Ajustes > Email =

Campos disponibles:

* **Asunto:** asunto del email de resultado enviado al completar el diagnóstico.
* **Nombre del remitente:** nombre visible del remitente. Valor por defecto: `Cafecito con Cata`.

= Sumisiones =

El submenú **Curso Acelera > Sumisiones** lista las respuestas guardadas y el estado de sincronización con Clientify por fila.

Desde esta pantalla se puede reenviar a Clientify una sumisión con estado `Error` u `Omitido`. Las sumisiones ya enviadas no se reenvían desde la UI.

== Shortcodes ==

= `[acelera_form]` =

Muestra el formulario diagnóstico ACELERA. Debe usarse en la clase `16246`, que corresponde a la clase de diagnóstico dentro de Bienvenida.

= `[acelera_feedback module="mX"]` =

Muestra el feedback LLM del módulo indicado. Reemplaza `mX` por `m1`, `m2`, `m3`, `m4` o `m5`.

Ejemplos:

* `[acelera_feedback module="m1"]`
* `[acelera_feedback module="m5"]`

El feedback se genera una sola vez por alumno y módulo, queda cacheado y puede regenerarse desde la pestaña **LLM** de los ajustes.

== Notas operativas ==

* La sincronización con Clientify se procesa de forma asíncrona mediante WP-Cron. En producción se recomienda configurar un cron real del servidor para ejecutar WP-Cron de forma confiable.
* Después de guardar la API key de Clientify, usa **Curso Acelera > Ajustes > Clientify > Probar conexión** para validar la integración.
* Usa **Curso Acelera > Sumisiones** para revisar estados, errores y reprogramar el envío de sumisiones fallidas u omitidas.
* Los módulos nuevos o clases nuevas de LearnDash no se incorporan automáticamente a la lógica del plugin: deben estar en el mapa de IDs documentado abajo.

== Mantenimiento del mapa de IDs ==

Los IDs del curso y de sus clases viven **solamente** en `includes/config/class-acelera-course-map.php`.

Si se agregan, eliminan o cambian clases del curso ACELERA, actualiza esa clase para mantener sincronizados el gate de Bienvenida, la renumeración personalizada, los acordeones, el formulario y el feedback por módulo.

Las clases nuevas que queden fuera de este mapa quedan fuera del gate y sin renumeración personalizada. Ese es un comportamiento definido del plugin.

== Frequently Asked Questions ==

= ¿Para qué curso está hecho este plugin? =

Para el curso ACELERA de LearnDash, curso ID `16242`.

= ¿El formulario diagnóstico usa IA para decidir el orden de módulos? =

No. El orden personalizado se calcula por scoring con reglas. La IA solo se usa para el feedback LLM por módulo.

= ¿Dónde se configura Clientify? =

En **Curso Acelera > Ajustes > Clientify**.

= ¿Dónde se configuran los prompts del feedback LLM? =

En **Curso Acelera > Ajustes > Prompts**.

= ¿Qué pasa si un prompt de módulo queda vacío? =

No se genera feedback para ese módulo y el shortcode se oculta.

= ¿Se puede regenerar el feedback LLM de un alumno? =

Sí. En **Curso Acelera > Ajustes > LLM**, usa la herramienta **Regenerar feedback** indicando ID o email del alumno y el módulo correspondiente.

== Changelog ==

= 1.0.0 =
* Versión inicial del plugin a medida para el curso ACELERA.
* Gate de Bienvenida para el curso LearnDash `16242`.
* Sidebar con acordeones para navegación del curso.
* Formulario diagnóstico `[acelera_form]` con scoring y orden personalizado de módulos.
* Persistencia de sumisiones e integración asíncrona con Clientify.
* Ajustes de Clientify, LLM, Prompts y Email en **Curso Acelera**.
* Feedback LLM por módulo mediante `[acelera_feedback module="mX"]`.

== Upgrade Notice ==

= 1.0.0 =
Versión inicial del plugin a medida para el curso ACELERA.
