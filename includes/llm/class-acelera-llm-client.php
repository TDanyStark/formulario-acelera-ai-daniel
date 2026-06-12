<?php

/**
 * Multi-provider LLM client for the module feedback (Fase 6.1).
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
 * Thin HTTP client over the Anthropic Messages API and the OpenAI Chat
 * Completions API.
 *
 * The active driver is decided by the `llm_provider` setting
 * ('claude' | 'chatgpt'). The `llm_model` setting applies to whichever
 * provider is active; when it is empty each provider falls back to its
 * own default constant (model names verified against the official docs,
 * June 2026).
 *
 * Error contract: every failure path returns a WP_Error (never throws,
 * never echoes). Error data may contain a short response-body excerpt
 * for logging, but NEVER the API key.
 *
 * Sanitization is intentionally NOT done here: the raw model text is
 * returned as-is and Acelera_Module_Feedback (6.5) converts/escapes it
 * before caching or printing.
 *
 * @since      1.0.0
 * @package    Formulario_Acelera_Ai_Daniel
 * @subpackage Formulario_Acelera_Ai_Daniel/includes/llm
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Acelera_LLM_Client {

	/**
	 * Default Anthropic model when the `llm_model` setting is empty.
	 *
	 * Other currently valid options: claude-haiku-4-5, claude-opus-4-6.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const DEFAULT_MODEL_CLAUDE = 'claude-sonnet-4-6';

	/**
	 * Default OpenAI model when the `llm_model` setting is empty.
	 *
	 * Other currently valid options: gpt-4.1-mini, gpt-4o-mini.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const DEFAULT_MODEL_OPENAI = 'gpt-5';

	/**
	 * HTTP timeout for the LLM request, in seconds.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const TIMEOUT = 30;

	/**
	 * Maximum tokens requested from the model.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const MAX_TOKENS = 1024;

	/**
	 * Generate a completion with the active provider.
	 *
	 * @since  1.0.0
	 * @param  string $system_prompt System prompt (admin-configured, with
	 *                               placeholders already replaced).
	 * @param  string $user_content  Deterministic context block sent as the
	 *                               user message.
	 * @return string|WP_Error Raw model text on success.
	 */
	public static function generate( $system_prompt, $user_content ) {

		$provider = (string) Acelera_Settings::get( 'llm_provider', 'claude' );

		if ( 'chatgpt' === $provider ) {
			return self::generate_openai( (string) $system_prompt, (string) $user_content );
		}

		return self::generate_anthropic( (string) $system_prompt, (string) $user_content );

	}

	/**
	 * Resolve the model identifier for a provider.
	 *
	 * Pure helper (CLI-testable): a non-empty configured value wins,
	 * otherwise the per-provider default constant applies.
	 *
	 * @since  1.0.0
	 * @param  string $provider   'claude' | 'chatgpt'.
	 * @param  string $configured Value of the `llm_model` setting.
	 * @return string Model identifier.
	 */
	public static function resolve_model( $provider, $configured = '' ) {

		$configured = trim( (string) $configured );

		if ( '' !== $configured ) {
			return $configured;
		}

		return ( 'chatgpt' === $provider ) ? self::DEFAULT_MODEL_OPENAI : self::DEFAULT_MODEL_CLAUDE;

	}

	/**
	 * Model that the active provider would use right now.
	 *
	 * Exposed so the feedback layer can store provider/model metadata
	 * alongside the cached HTML.
	 *
	 * @since  1.0.0
	 * @return string Model identifier.
	 */
	public static function active_model() {

		return self::resolve_model(
			(string) Acelera_Settings::get( 'llm_provider', 'claude' ),
			(string) Acelera_Settings::get( 'llm_model', '' )
		);

	}

	/**
	 * Anthropic Messages API driver.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $system System prompt.
	 * @param  string $user   User content.
	 * @return string|WP_Error
	 */
	private static function generate_anthropic( $system, $user ) {

		$api_key = (string) Acelera_Settings::get( 'anthropic_api_key', '' );

		if ( '' === $api_key ) {
			return new WP_Error( 'no_api_key', 'Anthropic API key is not configured.' );
		}

		$model = self::resolve_model( 'claude', (string) Acelera_Settings::get( 'llm_model', '' ) );

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $model,
						'max_tokens' => self::MAX_TOKENS,
						'system'     => $system,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => $user,
							),
						),
					)
				),
			)
		);

		$data = self::parse_response( $response, 'anthropic' );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Expected shape: { content: [ { type: 'text', text: '...' } ] }.
		if ( empty( $data['content'][0]['text'] ) || ! is_string( $data['content'][0]['text'] ) ) {
			return new WP_Error(
				'acelera_llm_empty_content',
				'Anthropic response did not contain text content.',
				array( 'excerpt' => self::excerpt( wp_remote_retrieve_body( $response ) ) )
			);
		}

		return $data['content'][0]['text'];

	}

	/**
	 * OpenAI Chat Completions API driver.
	 *
	 * Uses `max_completion_tokens`: current models (gpt-5, o-series)
	 * reject the legacy `max_tokens` parameter, while every model still
	 * served on /v1/chat/completions accepts the new name. If a very old
	 * custom model ever errors on it, set the limit from the prompt
	 * instead (the parameter is an upper bound, not a requirement).
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $system System prompt.
	 * @param  string $user   User content.
	 * @return string|WP_Error
	 */
	private static function generate_openai( $system, $user ) {

		$api_key = (string) Acelera_Settings::get( 'openai_api_key', '' );

		if ( '' === $api_key ) {
			return new WP_Error( 'no_api_key', 'OpenAI API key is not configured.' );
		}

		$model = self::resolve_model( 'chatgpt', (string) Acelera_Settings::get( 'llm_model', '' ) );

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'                 => $model,
						'max_completion_tokens' => self::MAX_TOKENS,
						'messages'              => array(
							array(
								'role'    => 'system',
								'content' => $system,
							),
							array(
								'role'    => 'user',
								'content' => $user,
							),
						),
					)
				),
			)
		);

		$data = self::parse_response( $response, 'openai' );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Expected shape: { choices: [ { message: { content: '...' } } ] }.
		if ( empty( $data['choices'][0]['message']['content'] ) || ! is_string( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error(
				'acelera_llm_empty_content',
				'OpenAI response did not contain message content.',
				array( 'excerpt' => self::excerpt( wp_remote_retrieve_body( $response ) ) )
			);
		}

		return $data['choices'][0]['message']['content'];

	}

	/**
	 * Shared transport/HTTP/JSON validation for both drivers.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array|WP_Error $response wp_remote_post() return value.
	 * @param  string         $provider Provider slug for error messages.
	 * @return array|WP_Error Decoded JSON body on success.
	 */
	private static function parse_response( $response, $provider ) {

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'acelera_llm_http_error',
				sprintf( '%s request failed: %s', $provider, $response->get_error_message() )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'acelera_llm_bad_status',
				sprintf( '%s returned HTTP %d.', $provider, $code ),
				array( 'excerpt' => self::excerpt( $body ) )
			);
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'acelera_llm_bad_json',
				sprintf( '%s returned malformed JSON.', $provider ),
				array( 'excerpt' => self::excerpt( $body ) )
			);
		}

		return $data;

	}

	/**
	 * Short, log-safe excerpt of a response body.
	 *
	 * Bodies come from the provider, so they never contain our API key;
	 * truncating keeps logs readable either way.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $body Raw response body.
	 * @return string At most 300 characters.
	 */
	private static function excerpt( $body ) {

		$body = trim( preg_replace( '/\s+/', ' ', (string) $body ) );

		return ( strlen( $body ) > 300 ) ? substr( $body, 0, 300 ) . '…' : $body;

	}

}
