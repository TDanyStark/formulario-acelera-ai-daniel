=== Formulario Acelara AI Daniel ===
Contributors: danielamado
Donate link: https://danielamado.com/
Tags: learndash, formulario, crm, clientify, lms
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Formulario integrado con LearnDash que funciona como una clase dentro de un curso, define la ruta (módulo) ideal del usuario y conecta los datos con Clientify (CRM).

== Description ==

**Formulario Acelara AI Daniel** añade un formulario que se integra con **LearnDash** y funciona como una **clase dentro de un curso**. A partir de las respuestas del usuario, el formulario determina la **ruta** (módulo del curso) que más le conviene y la activa o recomienda.

Además, la información capturada se sincroniza con **Clientify**, el CRM de la organización, para el seguimiento comercial y de marketing.

**Conceptos clave**

* **Curso (LearnDash):** contenedor principal donde vive el formulario como una clase.
* **Módulos = Rutas:** cada módulo del curso representa una ruta posible para el usuario.
* **Formulario:** recoge respuestas y determina la ruta recomendada.
* **Clientify (CRM):** destino de los datos del formulario para seguimiento comercial.

== Installation ==

1. Sube la carpeta `formulario-acelara-ai-daniel` al directorio `/wp-content/plugins/`.
2. Activa el plugin desde el menú **Plugins** de WordPress.
3. Asegúrate de tener **LearnDash** activo.
4. Configura las credenciales de **Clientify** en los ajustes del plugin.

== Frequently Asked Questions ==

= ¿Requiere LearnDash para funcionar? =

Sí. El formulario funciona como una clase dentro de un curso de LearnDash y usa sus módulos como rutas.

= ¿Qué hace con los datos del formulario? =

Los envía a Clientify (CRM) para crear o actualizar el contacto y dar seguimiento comercial.

= ¿Cómo se decide la ruta del usuario? =

Según las respuestas del formulario, el plugin evalúa y recomienda el módulo (ruta) más adecuado dentro del curso.

== Changelog ==

= 1.0.0 =
* Versión inicial.
* Estructura base sobre WordPress Plugin Boilerplate.
* Integración con GitHub Updater (GitHub Plugin URI / Primary Branch).

== Upgrade Notice ==

= 1.0.0 =
Versión inicial del plugin.
