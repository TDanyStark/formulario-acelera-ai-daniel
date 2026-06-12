<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Formulario_Acelara_Ai_Daniel_Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * Clears every pending `acelera_send_to_clientify` one-off cron event
	 * (Fase 7 audit): with the plugin off the hook has no callback, so the
	 * queued events would only pollute the cron table.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {

		// wp_unschedule_hook() removes ALL events for the hook regardless
		// of their args (each event carries [submission_id, attempt]).
		wp_unschedule_hook( 'acelera_send_to_clientify' );

	}

}
