<?php

/**
 * Clientify CRM API client (Fase 5.1).
 *
 * @link       https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelera_Ai_Daniel
 * @subpackage Formulario_Acelera_Ai_Daniel/includes/crm
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * HTTP client for the Clientify v2 API + note HTML builder.
 *
 * Endpoints used (per plan/05-fase-5-persistencia-clientify.md):
 * - POST /contacts/            → create contact (returns `id`).
 * - GET  /contacts/?query=...  → find an existing contact by email.
 * - POST /contacts/{id}/note/  → attach the form answers as an HTML note.
 *
 * Duplicate-contact strategy: the client always TRIES the create first;
 * when Clientify rejects it with a 4xx (typically an email-uniqueness
 * error) the dispatcher falls back to {@see find_contact_by_email()} and
 * attaches the note to the existing contact instead. This avoids a GET
 * round-trip on the common path (new contact) while still converging on
 * the existing contact in the duplicate case.
 *
 * Authentication: `Authorization: Token {api_key}` per the Clientify API
 * docs (https://newapi.clientify.com/). The exact header format could not
 * be verified against a live account at implementation time, so the full
 * header value is exposed through the `acelera_clientify_auth_header`
 * filter — if Clientify ever expects e.g. `Bearer {key}` it can be fixed
 * from a mu-plugin without touching this file.
 *
 * The API key is read from Acelera_Settings ('clientify_api_key') and is
 * NEVER written to logs or error payloads.
 *
 * @since      1.0.0
 * @package    Formulario_Acelera_Ai_Daniel
 * @subpackage Formulario_Acelera_Ai_Daniel/includes/crm
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Acelera_Clientify {

	/**
	 * Clientify API base URL (no trailing slash).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const BASE_URL = 'https://api-plus.clientify.com/v2';

	/**
	 * Fixed name of the note created for every submission.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const NOTE_NAME = 'Formulario Acelera PRO VERSION';

	/**
	 * Value sent as `contact_source` on every contact.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const CONTACT_SOURCE = 'plugin_acelera_da';

	/**
	 * HTTP timeout for every request, in seconds.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const TIMEOUT = 15;

	/**
	 * Clientify API key.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $api_key;

	/**
	 * Initialize the client.
	 *
	 * @since 1.0.0
	 * @param string|null $api_key Optional. Explicit API key (tests). When
	 *                             null the 'clientify_api_key' setting is used.
	 */
	public function __construct( $api_key = null ) {

		$this->api_key = ( null === $api_key )
			? (string) Acelera_Settings::get( 'clientify_api_key' )
			: (string) $api_key;

	}

	/**
	 * Whether an API key is configured.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function has_api_key() {

		return '' !== trim( $this->api_key );

	}

	/**
	 * Create a contact.
	 *
	 * @since  1.0.0
	 * @param  array $payload Contact payload (see build_contact_payload()).
	 * @return array|WP_Error Decoded response body (with `id`) or WP_Error.
	 */
	public function create_contact( array $payload ) {

		return $this->request( 'POST', '/contacts/', $payload );

	}

	/**
	 * Find an existing contact by email.
	 *
	 * Used as the duplicate-handling fallback when create_contact() fails
	 * with a 4xx (see the class docblock for the strategy).
	 *
	 * @since  1.0.0
	 * @param  string $email Contact email.
	 * @return array|null|WP_Error First matching contact (array with `id`),
	 *                             null when no match, WP_Error on failure.
	 */
	public function find_contact_by_email( $email ) {

		$email = trim( (string) $email );

		if ( '' === $email ) {
			return null;
		}

		$response = $this->request( 'GET', '/contacts/?query=' . rawurlencode( $email ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['results'] ) || ! is_array( $response['results'] ) ) {
			return null;
		}

		// Prefer an exact email match over Clientify's fuzzy `query` search.
		foreach ( $response['results'] as $contact ) {
			if ( isset( $contact['email'] ) && 0 === strcasecmp( (string) $contact['email'], $email ) ) {
				return $contact;
			}
		}

		$first = reset( $response['results'] );

		return is_array( $first ) ? $first : null;

	}

	/**
	 * Attach a note to a contact.
	 *
	 * @since  1.0.0
	 * @param  int    $contact_id   Clientify contact ID.
	 * @param  string $html_comment Note body (basic HTML: <b>, <br>).
	 * @param  string $name         Optional. Note title.
	 * @return array|WP_Error Decoded response body or WP_Error.
	 */
	public function create_note( $contact_id, $html_comment, $name = self::NOTE_NAME ) {

		return $this->request(
			'POST',
			'/contacts/' . (int) $contact_id . '/note/',
			array(
				'name'    => (string) $name,
				'comment' => (string) $html_comment,
			)
		);

	}

	/**
	 * Lightweight authenticated request to validate the API key.
	 *
	 * @since  1.0.0
	 * @return true|WP_Error True when the API answered 2xx.
	 */
	public function test_connection() {

		if ( ! $this->has_api_key() ) {
			return new WP_Error(
				'acelera_clientify_no_key',
				__( 'No hay API key de Clientify configurada.', 'formulario-acelera-ai-daniel' )
			);
		}

		$response = $this->request( 'GET', '/contacts/?page_size=1' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;

	}

	/* ---------------------------------------------------------------------
	 * Payload / note builders (pure helpers, CLI-testable)
	 * ------------------------------------------------------------------- */

	/**
	 * Split a full name into first name (first word) and last name (rest).
	 *
	 * @since  1.0.0
	 * @param  string $full_name Full name as answered in P0.1.
	 * @return array{0: string, 1: string} [ first_name, last_name ].
	 */
	public static function split_name( $full_name ) {

		$full_name = trim( preg_replace( '/\s+/u', ' ', (string) $full_name ) );

		if ( '' === $full_name ) {
			return array( '', '' );
		}

		$parts = explode( ' ', $full_name, 2 );

		return array( $parts[0], isset( $parts[1] ) ? $parts[1] : '' );

	}

	/**
	 * Build the create-contact payload from the form answers + settings.
	 *
	 * @since  1.0.0
	 * @param  array $answers Sanitized answers keyed by question ID.
	 * @return array Payload for POST /contacts/.
	 */
	public static function build_contact_payload( array $answers ) {

		list( $first_name, $last_name ) = self::split_name(
			isset( $answers['p0_1'] ) ? (string) $answers['p0_1'] : ''
		);

		$tags_raw = (string) Acelera_Settings::get( 'clientify_tags' );
		$tags     = array_values( array_filter( array_map( 'trim', explode( ',', $tags_raw ) ) ) );

		return array(
			'first_name'       => $first_name,
			'last_name'        => $last_name,
			'owner'            => (string) Acelera_Settings::get( 'clientify_owner' ),
			'phone'            => isset( $answers['p0_3'] ) ? (string) $answers['p0_3'] : '',
			'email'            => isset( $answers['p0_2'] ) ? (string) $answers['p0_2'] : '',
			'tags'             => $tags,
			'contact_source'   => self::CONTACT_SOURCE,
			'marketing_status' => 2,
		);

	}

	/**
	 * Build the note HTML from the answers and the scoring result.
	 *
	 * Iterates the ANSWERED questions in definition order rendering
	 * `<b>{label}:</b> {answer}<br>` lines, resolving option values to
	 * their Spanish labels (single/multi) and formatting the children
	 * repeater readably. Appends the resulting module order (renumbered
	 * labels), the scores per route, the special flags (only when true)
	 * and the CV link when present. Only basic HTML is emitted (<b>, <br>,
	 * one <a> for the CV) and every user-provided fragment is escaped.
	 *
	 * @since  1.0.0
	 * @param  array  $answers Sanitized answers keyed by question ID.
	 * @param  array  $scoring Scoring result {scores, module_order, flags}.
	 * @param  string $cv_url  Optional. URL of the uploaded CV.
	 * @return string Note HTML.
	 */
	public static function build_note_html( array $answers, array $scoring, $cv_url = '' ) {

		$lines = array();

		foreach ( Acelera_Questions::all() as $question ) {
			$id = $question['id'];

			// The CV is appended at the end as a link, not as an answer line.
			if ( 'cv_upload' === $id ) {
				continue;
			}

			if ( ! array_key_exists( $id, $answers ) ) {
				continue;
			}

			$formatted = self::format_answer( $question, $answers[ $id ] );

			if ( '' === $formatted ) {
				continue;
			}

			$lines[] = '<b>' . esc_html( $question['label'] ) . ':</b> ' . $formatted;
		}

		// --- Resulting module order (renumbered labels). ------------------.
		$order  = isset( $scoring['module_order'] ) ? $scoring['module_order'] : '';
		$labels = Acelera_Renaming::build_labels( Acelera_Renaming::sanitize_order( $order ) );

		if ( array() !== $labels ) {
			$lines[] = '<b>' . esc_html__( 'Orden de módulos resultante', 'formulario-acelera-ai-daniel' ) . ':</b> '
				. esc_html( implode( ' → ', array_values( $labels ) ) );
		}

		// --- Scores per route. ---------------------------------------------.
		$scores = ( isset( $scoring['scores'] ) && is_array( $scoring['scores'] ) ) ? $scoring['scores'] : array();

		if ( array() !== $scores ) {
			$pairs = array();

			foreach ( $scores as $route => $score ) {
				$pairs[] = ucfirst( (string) $route ) . ': ' . (int) $score;
			}

			$lines[] = '<b>' . esc_html__( 'Scores por ruta', 'formulario-acelera-ai-daniel' ) . ':</b> '
				. esc_html( implode( ' | ', $pairs ) );
		}

		// --- Flags (only when true). ----------------------------------------.
		$flags = ( isset( $scoring['flags'] ) && is_array( $scoring['flags'] ) ) ? $scoring['flags'] : array();

		if ( ! empty( $flags['bloqueador_migratorio'] ) ) {
			$lines[] = '<b>' . esc_html__( 'Bloqueador migratorio', 'formulario-acelera-ai-daniel' ) . ':</b> '
				. esc_html__( 'Sí', 'formulario-acelera-ai-daniel' );
		}

		if ( ! empty( $flags['revision_asesor'] ) ) {
			$lines[] = '<b>' . esc_html__( 'Requiere revisión de asesor', 'formulario-acelera-ai-daniel' ) . ':</b> '
				. esc_html__( 'Sí', 'formulario-acelera-ai-daniel' );
		}

		// --- CV link. ---------------------------------------------------------.
		$cv_url = trim( (string) $cv_url );

		if ( '' === $cv_url ) {
			// Fall back to the answers (the cv answer IS the URL).
			$cv_url = isset( $answers['cv_upload'] ) ? trim( (string) $answers['cv_upload'] ) : '';
		}

		if ( '' !== $cv_url ) {
			$lines[] = '<b>' . esc_html__( 'CV adjunto', 'formulario-acelera-ai-daniel' ) . ':</b> '
				. '<a href="' . esc_url( $cv_url ) . '">' . esc_html( $cv_url ) . '</a>';
		}

		return implode( '<br>', $lines );

	}

	/**
	 * Format a single answer for the note (escaped, label-resolved).
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $question Question definition.
	 * @param  mixed $raw      Sanitized answer value.
	 * @return string Escaped HTML fragment, '' when unrenderable.
	 */
	private static function format_answer( array $question, $raw ) {

		switch ( $question['type'] ) {

			case 'single':
				return esc_html( self::option_label( $question, (string) $raw ) );

			case 'multi':
				$values = is_array( $raw ) ? $raw : array( $raw );
				$labels = array();

				foreach ( $values as $value ) {
					$labels[] = self::option_label( $question, (string) $value );
				}

				return esc_html( implode( ', ', $labels ) );

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

				return esc_html( implode( '; ', $rows ) );

			case 'scale':
				$max = isset( $question['max'] ) ? (int) $question['max'] : 5;

				return esc_html( (int) $raw . '/' . $max );

			case 'file':
				return esc_html( (string) $raw );

			default:
				// text | textarea | email | tel | date.
				if ( is_array( $raw ) ) {
					return esc_html( implode( ', ', array_map( 'strval', $raw ) ) );
				}

				return esc_html( (string) $raw );
		}

	}

	/**
	 * Resolve an option value to its Spanish label.
	 *
	 * Falls back to the raw value when the option is unknown so the note
	 * never silently loses data.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array  $question Question definition (with 'options').
	 * @param  string $value    Stored option value.
	 * @return string
	 */
	private static function option_label( array $question, $value ) {

		if ( ! empty( $question['options'] ) ) {
			foreach ( $question['options'] as $option ) {
				if ( (string) $option['value'] === (string) $value ) {
					return (string) $option['label'];
				}
			}
		}

		return (string) $value;

	}

	/* ---------------------------------------------------------------------
	 * HTTP layer
	 * ------------------------------------------------------------------- */

	/**
	 * Perform an authenticated request against the Clientify API.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string     $method HTTP method (GET | POST).
	 * @param  string     $path   Path starting with '/', may carry a query string.
	 * @param  array|null $body   Optional. JSON-encoded request body.
	 * @return array|WP_Error Decoded JSON body (array() when empty) or WP_Error.
	 */
	private function request( $method, $path, $body = null ) {

		if ( ! $this->has_api_key() ) {
			return new WP_Error(
				'acelera_clientify_no_key',
				__( 'No hay API key de Clientify configurada.', 'formulario-acelera-ai-daniel' )
			);
		}

		/**
		 * Filters the Clientify Authorization header value.
		 *
		 * Default format is `Token {api_key}` per the Clientify v2 docs;
		 * this filter exists so the format can be corrected without a
		 * code change if Clientify ever expects a different scheme.
		 *
		 * @since 1.0.0
		 *
		 * @param string $auth_header Full Authorization header value.
		 */
		$auth_header = apply_filters( 'acelera_clientify_auth_header', 'Token ' . $this->api_key );

		$args = array(
			'method'  => $method,
			'timeout' => self::TIMEOUT,
			'headers' => array(
				'Authorization' => $auth_header,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = ( 'POST' === $method )
			? wp_remote_post( self::BASE_URL . $path, $args )
			: wp_remote_get( self::BASE_URL . $path, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'acelera_clientify_http_error',
				sprintf(
					/* translators: %s: transport error message. */
					__( 'Error de conexión con Clientify: %s', 'formulario-acelera-ai-daniel' ),
					$response->get_error_message()
				)
			);
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = (string) wp_remote_retrieve_body( $response );
		$decoded  = json_decode( $raw_body, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'acelera_clientify_api_' . $code,
				sprintf(
					/* translators: 1: HTTP status code, 2: response body excerpt. */
					__( 'Clientify respondió %1$d: %2$s', 'formulario-acelera-ai-daniel' ),
					$code,
					substr( $raw_body, 0, 300 )
				),
				array( 'status' => $code )
			);
		}

		return is_array( $decoded ) ? $decoded : array();

	}

}
