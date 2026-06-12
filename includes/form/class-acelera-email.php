<?php

/**
 * Result email for the ACELERA diagnostic form.
 *
 * @link       https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelera_Ai_Daniel
 * @subpackage Formulario_Acelera_Ai_Daniel/includes/form
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Sends the personalized "orden de ejecución" email after a submission.
 *
 * Recipient is the email answered in P0.2 (falls back to the WP user
 * email). Body lists ONLY the 5 modules in the personalized order, each
 * linking to the module's first lesson — never individual lessons.
 *
 * Called explicitly from the /submit REST handler (Part B); no hooks.
 *
 * @since      1.0.0
 * @package    Formulario_Acelera_Ai_Daniel
 * @subpackage Formulario_Acelera_Ai_Daniel/includes/form
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Acelera_Email {

	/**
	 * Send the result email for a completed submission.
	 *
	 * @since  1.0.0
	 * @param  int   $user_id Submitting user ID.
	 * @param  array $answers Sanitized answers keyed by question ID
	 *                        (uses p0_1 name and p0_2 email).
	 * @param  array $scoring Output of Acelera_Scoring::score()
	 *                        (uses 'module_order').
	 * @return bool True when wp_mail() reported success.
	 */
	public static function send_result( $user_id, array $answers, array $scoring ): bool {
		$user = get_userdata( (int) $user_id );

		// Recipient: P0.2 answer, fallback WP user email.
		$to = '';

		if ( ! empty( $answers['p0_2'] ) && is_email( $answers['p0_2'] ) ) {
			$to = $answers['p0_2'];
		} elseif ( $user && is_email( $user->user_email ) ) {
			$to = $user->user_email;
		}

		if ( '' === $to ) {
			error_log( sprintf( '[Acelera] Email de resultado NO enviado: sin destinatario válido (user_id %d).', (int) $user_id ) );
			return false;
		}

		// Greeting name: prefer the first name only (P0.1a), then the
		// legacy/derived P0.1 full name, then the WP display name.
		$name = '';

		if ( ! empty( $answers['p0_1a'] ) && is_string( $answers['p0_1a'] ) ) {
			$name = $answers['p0_1a'];
		} elseif ( ! empty( $answers['p0_1'] ) && is_string( $answers['p0_1'] ) ) {
			$name = $answers['p0_1'];
		} elseif ( $user ) {
			$name = $user->display_name;
		}

		$modules = self::build_module_items( $scoring );

		if ( array() === $modules ) {
			error_log( sprintf( '[Acelera] Email de resultado NO enviado: module_order vacío o inválido (user_id %d).', (int) $user_id ) );
			return false;
		}

		$subject = Acelera_Settings::get( 'email_subject', 'Tu resultado del diagnóstico ACELERA' );
		$body    = self::render_template(
			array(
				'greeting_name' => $name,
				'modules'       => $modules,
			)
		);

		if ( '' === $body ) {
			error_log( sprintf( '[Acelera] Email de resultado NO enviado: plantilla vacía o no encontrada (user_id %d).', (int) $user_id ) );
			return false;
		}

		// Temporary filters: HTML content type + configured from-name.
		add_filter( 'wp_mail_content_type', array( __CLASS__, 'filter_mail_content_type' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'filter_mail_from_name' ) );

		$sent = wp_mail( $to, $subject, $body );

		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'filter_mail_content_type' ) );
		remove_filter( 'wp_mail_from_name', array( __CLASS__, 'filter_mail_from_name' ) );

		if ( ! $sent ) {
			error_log(
				sprintf(
					'[Acelera] wp_mail devolvió false al enviar el resultado a %s (user_id %d, orden %s).',
					$to,
					(int) $user_id,
					isset( $scoring['module_order'] ) ? $scoring['module_order'] : '?'
				)
			);
		}

		return (bool) $sent;
	}

	/**
	 * Build the renumbered module list for the template.
	 *
	 * Each item: [ 'number' => 1, 'label' => 'Módulo 1. {label temático}',
	 * 'url' => permalink of the module's first lesson ].
	 *
	 * @since  1.0.0
	 * @param  array $scoring Scoring result with 'module_order' CSV.
	 * @return array<int, array{number:int, label:string, url:string}>
	 */
	private static function build_module_items( array $scoring ): array {
		$map   = Acelera_Course_Map::modules();
		$order = isset( $scoring['module_order'] ) ? (string) $scoring['module_order'] : '';
		$keys  = array_filter( array_map( 'trim', explode( ',', $order ) ) );

		// Defensive fallback: natural order when the CSV is unusable.
		if ( array() === $keys ) {
			$keys = array_keys( $map );
		}

		$items  = array();
		$number = 1;

		foreach ( $keys as $key ) {
			if ( ! isset( $map[ $key ] ) ) {
				continue;
			}

			$url = get_permalink( $map[ $key ]['first_lesson'] );

			$items[] = array(
				'number' => $number,
				'label'  => sprintf( 'Módulo %d. %s', $number, $map[ $key ]['label'] ),
				'url'    => $url ? $url : '',
			);

			$number++;
		}

		return $items;
	}

	/**
	 * Render the HTML email template with the given variables.
	 *
	 * @since  1.0.0
	 * @param  array $args Template variables: greeting_name (string),
	 *                     modules (array of number/label/url items).
	 * @return string Rendered HTML, '' when the template is missing.
	 */
	private static function render_template( array $args ): string {
		$template = plugin_dir_path( dirname( __FILE__, 2 ) ) . 'public/partials/email-resultado.php';

		if ( ! file_exists( $template ) ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled keys only.
		extract( $args, EXTR_SKIP );

		ob_start();
		include $template;

		return (string) ob_get_clean();
	}

	/**
	 * Temporary wp_mail_content_type filter callback.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function filter_mail_content_type(): string {
		return 'text/html';
	}

	/**
	 * Temporary wp_mail_from_name filter callback.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function filter_mail_from_name(): string {
		return (string) Acelera_Settings::get( 'email_from_name', 'Cafecito con Cata' );
	}

}
