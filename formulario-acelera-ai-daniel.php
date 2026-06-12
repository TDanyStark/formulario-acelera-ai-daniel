<?php
/**
 * Plugin Name:       Formulario Acelera AI Daniel
 * Plugin URI:        https://danielamado.com
 * Description:       Formulario integrado con LearnDash que funciona como una clase dentro de un curso. Define la ruta (módulo) que más le conviene al usuario y conecta los datos con Clientify (CRM).
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Daniel Amado
 * Author URI:        https://danielamado.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       formulario-acelera-ai-daniel
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/TDanyStark/formulario-acelera-ai-daniel
 * Primary Branch:    main
 *
 * @package FormularioAceleraAiDaniel
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'FORMULARIO_ACELERA_AI_DANIEL_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-formulario-acelera-ai-daniel-activator.php
 */
function activate_formulario_acelera_ai_daniel() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-formulario-acelera-ai-daniel-activator.php';
	Formulario_Acelera_Ai_Daniel_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-formulario-acelera-ai-daniel-deactivator.php
 */
function deactivate_formulario_acelera_ai_daniel() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-formulario-acelera-ai-daniel-deactivator.php';
	Formulario_Acelera_Ai_Daniel_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_formulario_acelera_ai_daniel' );
register_deactivation_hook( __FILE__, 'deactivate_formulario_acelera_ai_daniel' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-formulario-acelera-ai-daniel.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * Runs on `plugins_loaded` (priority 5) so the LearnDash guard
 * (`LEARNDASH_VERSION`) is reliable regardless of plugin load order.
 *
 * @since    1.0.0
 */
function run_formulario_acelera_ai_daniel() {

	$plugin = new Formulario_Acelera_Ai_Daniel();
	$plugin->run();

}
add_action( 'plugins_loaded', 'run_formulario_acelera_ai_daniel', 5 );
