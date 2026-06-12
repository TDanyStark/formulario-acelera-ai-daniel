<?php

/**
 * Per-module LLM feedback: shortcode + REST endpoint + cache (Fase 6).
 *
 * @link       https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelera_Ai_Daniel
 * @subpackage Formulario_Acelera_Ai_Daniel/includes/llm
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * [acelera_feedback module="mX"] shortcode and its REST backend.
 *
 * Placement is fully controlled by the admin: the shortcode is dropped
 * anywhere inside LearnDash lesson/topic content (no the_content
 * auto-injection, no last_lesson detection — per the Fase 6 redesign).
 *
 * Flow:
 * 1. The shortcode renders a skeleton container and enqueues
 *    acelera-feedback.js/.css (render time only, same pattern as
 *    Acelera_Form_Shortcode).
 * 2. The JS calls GET acelera/v1/feedback/{module} with the wp_rest
 *    nonce. Cache hit (user_meta acelera_llm_feedback_{module}) returns
 *    instantly; otherwise the LLM is called once, the sanitized HTML is
 *    cached, and later visits never hit the API again.
 * 3. Every failure path (no prompt, no API key, API error) answers
 *    `hide: true` and the JS removes the container — the lesson never
 *    shows an error to the student.
 *
 * Cache notes:
 * - The form /reset (Acelera_Rest::handle_reset) intentionally does NOT
 *   delete this meta — module feedback outlives form resets.
 * - uninstall.php deletes every user_meta with the `acelera_` prefix,
 *   which covers `acelera_llm_feedback_*`.
 * - Admins can clear it per user/module from the LLM settings tab
 *   (wp_ajax_acelera_llm_regenerate).
 *
 * @since      1.0.0
 * @package    Formulario_Acelera_Ai_Daniel
 * @subpackage Formulario_Acelera_Ai_Daniel/includes/llm
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Acelera_Module_Feedback {

	/**
	 * Shortcode tag.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const TAG = 'acelera_feedback';

	/**
	 * user_meta key prefix of the cached feedback (suffix: module key).
	 *
	 * Value shape: { html, generated_at, provider, model }.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_PREFIX = 'acelera_llm_feedback_';

	/**
	 * Transient prefix of the anti-double-call lock
	 * (suffix: {user_id}_{module}).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const LOCK_PREFIX = 'acelera_llm_lock_';

	/**
	 * Lock lifetime in seconds.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const LOCK_TTL = 60;

	/**
	 * The ID of this plugin (asset handle prefix).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name
	 */
	private $plugin_name;

	/**
	 * The version of this plugin (asset cache busting).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version
	 */
	private $version;

	/**
	 * Whether the assets were already enqueued on this request
	 * (multiple shortcodes on one page must not duplicate the
	 * localized payload).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool      $assets_enqueued
	 */
	private $assets_enqueued = false;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string $plugin_name The name of the plugin.
	 * @param    string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/* ---------------------------------------------------------------------
	 * Shortcode (6.2)
	 * ------------------------------------------------------------------- */

	/**
	 * Register the shortcode. Hooked to `init` through the loader.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_shortcode() {

		add_shortcode( self::TAG, array( $this, 'render' ) );

	}

	/**
	 * Shortcode callback.
	 *
	 * Invalid/missing `module` attribute or logged-out visitor → empty
	 * string (the lesson renders untouched).
	 *
	 * @since  1.0.0
	 * @param  array|string $atts Shortcode attributes.
	 * @return string Markup.
	 */
	public function render( $atts = array() ) {

		$atts   = shortcode_atts( array( 'module' => '' ), $atts, self::TAG );
		$module = sanitize_key( (string) $atts['module'] );

		if ( ! array_key_exists( $module, Acelera_Course_Map::modules() ) ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			return '';
		}

		$this->enqueue_assets();

		return sprintf(
			'<div class="acelera-feedback" data-module="%1$s" data-nonce-ready>' .
				'<div class="acelera-feedback-skeleton" aria-hidden="true">' .
					'<span class="acelera-feedback-skeleton-bar"></span>' .
					'<span class="acelera-feedback-skeleton-bar"></span>' .
					'<span class="acelera-feedback-skeleton-bar acelera-feedback-skeleton-bar--short"></span>' .
				'</div>' .
				'<p class="acelera-feedback-loading">%2$s</p>' .
				'<div class="acelera-feedback-content" hidden></div>' .
			'</div>',
			esc_attr( $module ),
			esc_html__( 'Generando tu feedback personalizado…', 'formulario-acelera-ai-daniel' )
		);

	}

	/**
	 * Cache-busting version for a plugin asset, based on its mtime.
	 *
	 * Production sits behind a CDN/browser caches; using filemtime makes
	 * the ?ver= change on every deploy so clients never run stale assets.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $relative_path Asset path relative to the plugin root.
	 * @return string Version string for wp_enqueue_*().
	 */
	private function asset_version( $relative_path ) {

		$file  = plugin_dir_path( dirname( __FILE__, 2 ) ) . $relative_path;
		$mtime = file_exists( $file ) ? filemtime( $file ) : false;

		return $mtime ? $this->version . '.' . $mtime : $this->version;

	}

	/**
	 * Enqueue the feedback assets + localized payload (render time only).
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function enqueue_assets() {

		if ( $this->assets_enqueued ) {
			return;
		}

		$this->assets_enqueued = true;

		$handle = $this->plugin_name . '-feedback';
		$base   = plugin_dir_url( dirname( __FILE__, 2 ) ) . 'public/';

		wp_enqueue_style( $handle, $base . 'css/acelera-feedback.css', array(), $this->asset_version( 'public/css/acelera-feedback.css' ), 'all' );
		wp_enqueue_script( $handle, $base . 'js/acelera-feedback.js', array(), $this->asset_version( 'public/js/acelera-feedback.js' ), true );

		wp_localize_script(
			$handle,
			'aceleraFeedback',
			array(
				'restUrl'      => esc_url_raw( rest_url( Acelera_Rest::REST_NAMESPACE . '/' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'retryDelayMs' => 5000,
				'strings'      => array(
					'loading' => __( 'Generando tu feedback personalizado…', 'formulario-acelera-ai-daniel' ),
				),
			)
		);

	}

	/* ---------------------------------------------------------------------
	 * REST endpoint (6.2 / 6.4)
	 * ------------------------------------------------------------------- */

	/**
	 * Register GET acelera/v1/feedback/{module}. Hooked to `rest_api_init`.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			Acelera_Rest::REST_NAMESPACE,
			'/feedback/(?P<module>m[1-5])',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_feedback' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

	}

	/**
	 * GET /feedback/{module} — cached feedback or first-time generation.
	 *
	 * Response contract consumed by acelera-feedback.js:
	 * - { html, cached }    → inject + reveal.
	 * - { html:'', hide }   → remove the container (silent fallback).
	 * - { html:'', pending }→ another request holds the lock; retry once.
	 *
	 * Always HTTP 200 — the student-facing JS never branches on status.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_feedback( WP_REST_Request $request ) {

		$module  = sanitize_key( (string) $request->get_param( 'module' ) );
		$user_id = get_current_user_id();

		// Defensive re-validation (the route regex already restricts m1–m5).
		if ( ! array_key_exists( $module, Acelera_Course_Map::modules() ) ) {
			return new WP_REST_Response( array( 'html' => '', 'hide' => true ), 200 );
		}

		// 1. Cache hit → instant, zero LLM calls.
		$cached = get_user_meta( $user_id, self::META_PREFIX . $module, true );

		if ( is_array( $cached ) && ! empty( $cached['html'] ) && is_string( $cached['html'] ) ) {
			return new WP_REST_Response(
				array(
					'html'   => $cached['html'],
					'cached' => true,
				),
				200
			);
		}

		// 2. No prompt configured for this module → feature off, hide.
		$prompt = trim( (string) Acelera_Settings::get( 'prompt_' . $module, '' ) );

		if ( '' === $prompt ) {
			return new WP_REST_Response( array( 'html' => '', 'hide' => true ), 200 );
		}

		// 3. No API key for the active provider → silent fallback.
		$provider    = (string) Acelera_Settings::get( 'llm_provider', 'claude' );
		$key_setting = ( 'chatgpt' === $provider ) ? 'openai_api_key' : 'anthropic_api_key';

		if ( '' === (string) Acelera_Settings::get( $key_setting, '' ) ) {
			return new WP_REST_Response( array( 'html' => '', 'hide' => true ), 200 );
		}

		// 4. Anti-double-call lock (double tab / concurrent fetches).
		$lock_key = self::LOCK_PREFIX . $user_id . '_' . $module;

		if ( get_transient( $lock_key ) ) {
			return new WP_REST_Response( array( 'html' => '', 'pending' => true ), 200 );
		}

		set_transient( $lock_key, 1, self::LOCK_TTL );

		// 5. Build context, call the LLM, sanitize, cache.
		$context      = $this->submission_context( $user_id );
		$display_name = $this->resolve_display_name( $user_id, $context );

		$system = self::replace_placeholders(
			$prompt,
			self::build_replacements( $module, $display_name, $context )
		);

		$user_content = self::build_user_content( $module, $display_name, $context );

		$text = Acelera_LLM_Client::generate( $system, $user_content );

		if ( is_wp_error( $text ) ) {
			// 6. Log (no API keys in WP_Error messages by client contract),
			// release the lock and hide — the student never sees an error.
			$data    = $text->get_error_data();
			$excerpt = ( is_array( $data ) && isset( $data['excerpt'] ) ) ? ' | ' . $data['excerpt'] : '';

			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'[acelera-llm] %s (user %d, module %s): %s%s',
					$text->get_error_code(),
					$user_id,
					$module,
					$text->get_error_message(),
					$excerpt
				)
			);

			delete_transient( $lock_key );

			return new WP_REST_Response( array( 'html' => '', 'hide' => true ), 200 );
		}

		$html = wp_kses_post( self::markdown_to_html( $text ) );

		if ( '' === trim( $html ) ) {
			delete_transient( $lock_key );

			return new WP_REST_Response( array( 'html' => '', 'hide' => true ), 200 );
		}

		update_user_meta(
			$user_id,
			self::META_PREFIX . $module,
			array(
				'html'         => $html,
				'generated_at' => current_time( 'mysql' ),
				'provider'     => $provider,
				'model'        => Acelera_LLM_Client::active_model(),
			)
		);

		delete_transient( $lock_key );

		return new WP_REST_Response(
			array(
				'html'   => $html,
				'cached' => false,
			),
			200
		);

	}

	/* ---------------------------------------------------------------------
	 * Cache admin helper (6.4)
	 * ------------------------------------------------------------------- */

	/**
	 * Delete the cached feedback (and locks) for a user.
	 *
	 * Used by the admin "Regenerar feedback" AJAX tool.
	 *
	 * @since  1.0.0
	 * @param  int      $user_id User ID.
	 * @param  string[] $modules Module keys to clear ('m1'..'m5').
	 * @return int Number of cache entries actually deleted.
	 */
	public static function delete_cache( $user_id, array $modules ) {

		$user_id = (int) $user_id;
		$valid   = array_keys( Acelera_Course_Map::modules() );
		$deleted = 0;

		foreach ( $modules as $module ) {
			if ( ! in_array( $module, $valid, true ) ) {
				continue;
			}

			delete_transient( self::LOCK_PREFIX . $user_id . '_' . $module );

			if ( delete_user_meta( $user_id, self::META_PREFIX . $module ) ) {
				$deleted++;
			}
		}

		return $deleted;

	}

	/* ---------------------------------------------------------------------
	 * Prompt + context (6.3)
	 * ------------------------------------------------------------------- */

	/**
	 * Decode the user's active submission into a context array.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $user_id User ID.
	 * @return array|null {order: string[], scores: array, answers: array,
	 *                    flags: array} or null without an active submission.
	 */
	private function submission_context( $user_id ) {

		$repo   = new Acelera_Submissions_Repo();
		$active = $repo->get_active_for_user( $user_id );

		if ( ! $active ) {
			return null;
		}

		// JSON LONGTEXT columns come back as raw strings from $wpdb.
		$answers = json_decode( (string) $active->answers, true );
		$scores  = json_decode( (string) $active->scores, true );
		$flags   = json_decode( (string) $active->flags, true );

		return array(
			'order'   => Acelera_Renaming::sanitize_order( (string) $active->module_order ),
			'scores'  => is_array( $scores ) ? $scores : array(),
			'answers' => is_array( $answers ) ? $answers : array(),
			'flags'   => is_array( $flags ) ? $flags : array(),
		);

	}

	/**
	 * Student name: P0.1 answer when a submission exists, else display_name.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int        $user_id User ID.
	 * @param  array|null $context Submission context.
	 * @return string
	 */
	private function resolve_display_name( $user_id, $context ) {

		if ( is_array( $context ) && ! empty( $context['answers']['p0_1a'] ) && is_string( $context['answers']['p0_1a'] ) ) {
			return trim( $context['answers']['p0_1a'] );
		}

		if ( is_array( $context ) && ! empty( $context['answers']['p0_1'] ) && is_string( $context['answers']['p0_1'] ) ) {
			return trim( $context['answers']['p0_1'] );
		}

		$user = get_userdata( $user_id );

		return $user ? (string) $user->display_name : '';

	}

	/**
	 * Build the placeholder → value map for the system prompt.
	 *
	 * Documented placeholders (Prompts tab): {nombre}, {modulo},
	 * {ruta_principal}, {respuestas_resumen}. Pure helper (CLI-testable):
	 * only touches Acelera_Course_Map / Acelera_Questions static data.
	 *
	 * @since  1.0.0
	 * @param  string     $module_key   Module of the shortcode ('m1'..'m5').
	 * @param  string     $display_name Student name.
	 * @param  array|null $context      Submission context or null.
	 * @return array<string, string>
	 */
	public static function build_replacements( $module_key, $display_name, $context ) {

		$map    = Acelera_Course_Map::modules();
		$module = isset( $map[ $module_key ] ) ? $map[ $module_key ] : null;

		$ruta_principal = '';

		if ( is_array( $context ) && ! empty( $context['order'][0] ) && isset( $map[ $context['order'][0] ] ) ) {
			$ruta_principal = $map[ $context['order'][0] ]['label'];
		}

		return array(
			'{nombre}'             => (string) $display_name,
			'{modulo}'             => $module ? sprintf( 'Módulo de %s', $module['label'] ) : '',
			'{ruta_principal}'     => $ruta_principal,
			'{respuestas_resumen}' => self::build_answers_summary( $context ),
		);

	}

	/**
	 * Apply the placeholder map to a prompt (simple str_replace, per plan).
	 *
	 * @since  1.0.0
	 * @param  string                $prompt       Raw prompt.
	 * @param  array<string, string> $replacements Placeholder → value.
	 * @return string
	 */
	public static function replace_placeholders( $prompt, array $replacements ) {

		return str_replace( array_keys( $replacements ), array_values( $replacements ), (string) $prompt );

	}

	/**
	 * Compact diagnostic summary for the {respuestas_resumen} placeholder.
	 *
	 * Objetivo (P1.1 option labels) + situación (P5.2 label) + full module
	 * order with scores. Empty string when there is no submission.
	 *
	 * @since  1.0.0
	 * @param  array|null $context Submission context or null.
	 * @return string
	 */
	public static function build_answers_summary( $context ) {

		if ( ! is_array( $context ) ) {
			return '';
		}

		$parts = array();

		$objetivo = self::answer_labels( 'p1_1', isset( $context['answers']['p1_1'] ) ? $context['answers']['p1_1'] : null );

		if ( array() !== $objetivo ) {
			$parts[] = 'Objetivo principal: ' . implode( '; ', $objetivo ) . '.';
		}

		$situacion = self::answer_labels( 'p5_2', isset( $context['answers']['p5_2'] ) ? $context['answers']['p5_2'] : null );

		if ( array() !== $situacion ) {
			$parts[] = 'Situación actual: ' . implode( '; ', $situacion ) . '.';
		}

		$order_line = self::order_with_scores_line( $context );

		if ( '' !== $order_line ) {
			$parts[] = 'Orden personalizado de módulos (con puntaje 0-100): ' . $order_line . '.';
		}

		return implode( ' ', $parts );

	}

	/**
	 * Deterministic plain-text context block sent as the user message.
	 *
	 * @since  1.0.0
	 * @param  string     $module_key   Module of the shortcode.
	 * @param  string     $display_name Student name.
	 * @param  array|null $context      Submission context or null.
	 * @return string
	 */
	public static function build_user_content( $module_key, $display_name, $context ) {

		$map    = Acelera_Course_Map::modules();
		$module = isset( $map[ $module_key ] ) ? $map[ $module_key ] : null;

		$lines   = array();
		$lines[] = 'Alumno: ' . ( '' !== (string) $display_name ? $display_name : '(sin nombre)' );
		$lines[] = 'Módulo de este feedback: ' . ( $module ? sprintf( 'Módulo de %s (%s)', $module['label'], $module_key ) : $module_key );

		if ( ! is_array( $context ) ) {
			$lines[] = 'Diagnóstico: el alumno aún no ha completado el formulario de diagnóstico.';

			return implode( "\n", $lines );
		}

		$order_line = self::order_with_scores_line( $context );

		if ( '' !== $order_line ) {
			$lines[] = 'Orden personalizado de módulos (con puntaje 0-100): ' . $order_line;
		}

		$answers_block = self::build_all_answers_block( $context );

		if ( '' !== $answers_block ) {
			$lines[] = '';
			$lines[] = 'Respuestas completas del formulario de diagnóstico:';
			$lines[] = $answers_block;
		}

		$active_flags = array();

		foreach ( (array) $context['flags'] as $flag => $value ) {
			if ( $value ) {
				$active_flags[] = is_int( $flag ) ? (string) $value : (string) $flag;
			}
		}

		if ( array() !== $active_flags ) {
			$lines[] = 'Flags del diagnóstico: ' . implode( ', ', $active_flags );
		}

		return implode( "\n", $lines );

	}

	/**
	 * "1. Label (82), 2. Label (65), …" line from the context order+scores.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $context Submission context.
	 * @return string Empty when the order is missing.
	 */
	private static function order_with_scores_line( array $context ) {

		if ( empty( $context['order'] ) || ! is_array( $context['order'] ) ) {
			return '';
		}

		$map    = Acelera_Course_Map::modules();
		$scores = isset( $context['scores'] ) && is_array( $context['scores'] ) ? $context['scores'] : array();

		$items  = array();
		$number = 1;

		foreach ( $context['order'] as $key ) {
			if ( ! isset( $map[ $key ] ) ) {
				continue;
			}

			$route = $map[ $key ]['route'];
			$score = isset( $scores[ $route ] ) ? sprintf( ' (%d)', (int) $scores[ $route ] ) : '';

			$items[] = sprintf( '%d. %s%s', $number, $map[ $key ]['label'], $score );
			$number++;
		}

		return implode( ', ', $items );

	}

	/**
	 * Full, labeled list of every answered question for LLM context.
	 *
	 * Iterates the canonical question registry in definition order and emits
	 * one plain-text line per answered, visible question ("Etiqueta: valor"),
	 * so the model receives the complete diagnostic — not just a curated
	 * subset. Type-aware formatting mirrors the CRM note serializer.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $context Submission context (with decoded 'answers').
	 * @return string Newline-joined lines, '' when there are no answers.
	 */
	private static function build_all_answers_block( array $context ) {

		if ( empty( $context['answers'] ) || ! is_array( $context['answers'] ) ) {
			return '';
		}

		$answers = $context['answers'];
		$lines   = array();

		foreach ( Acelera_Questions::all() as $question ) {

			if ( ! is_array( $question ) || empty( $question['id'] ) ) {
				continue;
			}

			$id = $question['id'];

			if ( 'cv_upload' === $id ) {
				continue;
			}

			if ( ! array_key_exists( $id, $answers ) ) {
				continue;
			}

			$formatted = self::format_answer_text( $question, $answers[ $id ] );

			if ( '' === $formatted ) {
				continue;
			}

			$label   = isset( $question['label'] ) ? (string) $question['label'] : $id;
			$lines[] = '- ' . $label . ': ' . $formatted;
		}

		return implode( "\n", $lines );

	}

	/**
	 * Type-aware plain-text formatting of a single stored answer.
	 *
	 * Plain-text counterpart of the CRM note formatter: resolves option
	 * slugs to Spanish labels, renders the children repeater, scales as
	 * "n/max" and passes free text through verbatim. No HTML/escaping, since
	 * the result is sent as the LLM user message.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $question Question definition.
	 * @param  mixed $raw      Stored answer value.
	 * @return string '' when unrenderable.
	 */
	private static function format_answer_text( array $question, $raw ) {

		$type = isset( $question['type'] ) ? (string) $question['type'] : 'text';

		switch ( $type ) {

			case 'single':
				return self::option_label( $question, (string) $raw );

			case 'multi':
				$values = is_array( $raw ) ? $raw : array( $raw );
				$labels = array();

				foreach ( $values as $value ) {
					$label = self::option_label( $question, (string) $value );

					if ( '' !== $label ) {
						$labels[] = $label;
					}
				}

				return implode( ', ', $labels );

			case 'repeater':
				if ( ! is_array( $raw ) || array() === $raw ) {
					return '';
				}

				$rows = array();

				foreach ( $raw as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}

					$nombre  = isset( $item['nombre'] ) ? (string) $item['nombre'] : '';
					$edad    = isset( $item['edad'] ) ? (int) $item['edad'] : 0;
					$estudia = ( isset( $item['estudia'] ) && 'si' === $item['estudia'] ) ? 'Sí' : 'No';

					$rows[] = sprintf( '%s (%d años, estudia: %s)', $nombre, $edad, $estudia );
				}

				return implode( '; ', $rows );

			case 'scale':
				$max = isset( $question['max'] ) ? (int) $question['max'] : 5;

				return (int) $raw . '/' . $max;

			case 'file':
				return (string) $raw;

			default:
				// text | textarea | email | tel | date.
				if ( is_array( $raw ) ) {
					return implode( ', ', array_map( 'strval', $raw ) );
				}

				return (string) $raw;
		}

	}

	/**
	 * Resolve an option value to its Spanish label.
	 *
	 * Falls back to the raw value when the option is unknown so no answer is
	 * silently lost.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array  $question Question definition (with 'options').
	 * @param  string $value    Stored option value.
	 * @return string
	 */
	private static function option_label( array $question, $value ) {

		if ( '' === (string) $value ) {
			return '';
		}

		if ( ! empty( $question['options'] ) && is_array( $question['options'] ) ) {
			foreach ( $question['options'] as $option ) {
				if ( isset( $option['value'] ) && (string) $option['value'] === (string) $value ) {
					return isset( $option['label'] ) ? (string) $option['label'] : (string) $value;
				}
			}
		}

		return (string) $value;

	}

	/**
	 * Human labels of the selected option(s) of a question.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string            $question_id Question ID ('p1_1', 'p5_2').
	 * @param  string|array|null $answer      Stored answer (value or values).
	 * @return string[] Labels (unknown raw values pass through as-is).
	 */
	private static function answer_labels( $question_id, $answer ) {

		if ( null === $answer || '' === $answer || array() === $answer ) {
			return array();
		}

		$values = is_array( $answer ) ? $answer : array( $answer );

		$question = Acelera_Questions::get( $question_id );
		$by_value = array();

		if ( is_array( $question ) && ! empty( $question['options'] ) && is_array( $question['options'] ) ) {
			foreach ( $question['options'] as $option ) {
				if ( isset( $option['value'], $option['label'] ) ) {
					$by_value[ $option['value'] ] = $option['label'];
				}
			}
		}

		$labels = array();

		foreach ( $values as $value ) {
			if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
				continue;
			}

			$value    = (string) $value;
			$labels[] = isset( $by_value[ $value ] ) ? $by_value[ $value ] : $value;
		}

		return $labels;

	}

	/* ---------------------------------------------------------------------
	 * Output sanitization (6.5)
	 * ------------------------------------------------------------------- */

	/**
	 * Minimal markdown → HTML conversion for LLM responses.
	 *
	 * Supported on purpose (nothing else, no library): **bold**, "- " /
	 * "* " unordered lists, and double-linebreak paragraphs (single
	 * linebreaks inside a paragraph become <br />). The result MUST still
	 * go through wp_kses_post() before caching/printing — any HTML the
	 * model emits passes through this function untouched.
	 *
	 * Pure PHP (CLI-testable).
	 *
	 * @since  1.0.0
	 * @param  string $text Raw model text.
	 * @return string HTML (pre-kses).
	 */
	public static function markdown_to_html( $text ) {

		$text = str_replace( array( "\r\n", "\r" ), "\n", trim( (string) $text ) );

		if ( '' === $text ) {
			return '';
		}

		$text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text );

		$html = '';

		foreach ( preg_split( '/\n{2,}/', $text ) as $block ) {
			$paragraph = array();
			$list      = array();

			// Within a block, group consecutive "- " lines into one <ul>.
			foreach ( explode( "\n", trim( $block ) ) as $line ) {
				if ( preg_match( '/^\s*[-*]\s+(.*)$/', $line, $m ) ) {
					if ( array() !== $paragraph ) {
						$html      = $html . '<p>' . implode( '<br />', $paragraph ) . '</p>';
						$paragraph = array();
					}
					$list[] = '<li>' . trim( $m[1] ) . '</li>';
				} else {
					if ( array() !== $list ) {
						$html = $html . '<ul>' . implode( '', $list ) . '</ul>';
						$list = array();
					}
					if ( '' !== trim( $line ) ) {
						$paragraph[] = trim( $line );
					}
				}
			}

			if ( array() !== $list ) {
				$html = $html . '<ul>' . implode( '', $list ) . '</ul>';
			}

			if ( array() !== $paragraph ) {
				$html = $html . '<p>' . implode( '<br />', $paragraph ) . '</p>';
			}
		}

		return $html;

	}

}
