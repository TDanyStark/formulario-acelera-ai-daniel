<?php
/**
 * Plugin Name:       Formulario Acelara AI Daniel
 * Plugin URI:        https://danielamado.com
 * Description:       Formulario integrado con LearnDash que funciona como una clase dentro de un curso. Define la ruta (módulo) que más le conviene al usuario y conecta los datos con Clientify (CRM).
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Daniel Amado
 * Author URI:        https://danielamado.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       formulario-acelara-ai-daniel
 * Domain Path:       /languages
 *
 * @package FormularioAcelaraAiDaniel
 */

// Si este archivo se llama directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Versión actual del plugin.
 */
define( 'FAAD_VERSION', '1.0.0' );

/**
 * Identificador / slug del plugin.
 */
define( 'FAAD_SLUG', 'formulario-acelara-ai-daniel' );

/**
 * Ruta absoluta al directorio del plugin (con barra final).
 */
define( 'FAAD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL al directorio del plugin (con barra final).
 */
define( 'FAAD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Ruta al archivo principal del plugin.
 */
define( 'FAAD_PLUGIN_FILE', __FILE__ );

/**
 * Código que se ejecuta durante la activación del plugin.
 */
function faad_activate() {
	// TODO: lógica de activación (crear tablas, opciones por defecto, etc.).
}
register_activation_hook( __FILE__, 'faad_activate' );

/**
 * Código que se ejecuta durante la desactivación del plugin.
 */
function faad_deactivate() {
	// TODO: lógica de desactivación (limpiar cron, etc.).
}
register_deactivation_hook( __FILE__, 'faad_deactivate' );

/**
 * Carga la clase principal e inicia la ejecución del plugin.
 */
function faad_run() {
	require_once FAAD_PLUGIN_DIR . 'includes/class-faad-plugin.php';

	$plugin = new FAAD_Plugin();
	$plugin->run();
}
faad_run();
