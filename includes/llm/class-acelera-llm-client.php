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
	 * Shorter HTTP timeout for the model-listing requests, in seconds.
	 *
	 * The settings screen blocks on this call, so keep it tight.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const LIST_TIMEOUT = 8;

	/**
	 * Transient key prefix for the cached per-provider model list.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const MODELS_TRANSIENT_PREFIX = 'acelera_llm_models_';

	/**
	 * Lifetime of the cached model list, in seconds (12 hours).
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const MODELS_TTL = 43200;

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
	 * List available models for a provider (cached 12h).
	 *
	 * Queries the provider's list-models endpoint and returns an
	 * ordered map of model id => human label. Results are cached in a
	 * transient; on any failure (no key, HTTP error, malformed body)
	 * an empty array is returned so callers can fall back to a static
	 * list. The empty result is NOT cached, so a transient outage is
	 * retried on the next request.
	 *
	 * @since  1.0.0
	 * @param  string $provider 'claude' | 'chatgpt'.
	 * @param  bool   $force    Bypass the cache and re-query the API.
	 * @return array<string,string> Model id => label (may be empty).
	 */
	public static function fetch_models( $provider, $force = false ) {

		$provider  = ( 'chatgpt' === $provider ) ? 'chatgpt' : 'claude';
		$transient = self::MODELS_TRANSIENT_PREFIX . $provider;

		if ( ! $force ) {
			$cached = get_transient( $transient );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$models = ( 'chatgpt' === $provider )
			? self::list_openai_models()
			: self::list_anthropic_models();

		// Only cache non-empty results so transient failures are retried.
		if ( ! empty( $models ) ) {
			set_transient( $transient, $models, self::MODELS_TTL );
		}

		return $models;

	}

	/**
	 * Drop the cached model lists for both providers.
	 *
	 * Called when the API keys change so a new key re-queries the API
	 * instead of serving the previous (possibly empty) cached result.
	 *
	 * @since 1.0.0
	 */
	public static function flush_models_cache() {

		delete_transient( self::MODELS_TRANSIENT_PREFIX . 'claude' );
		delete_transient( self::MODELS_TRANSIENT_PREFIX . 'chatgpt' );

	}

	/**
	 * Query the Anthropic /v1/models endpoint.
	 *
	 * Response shape: { data: [ { id, display_name }, ... ] }.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array<string,string> Model id => label (empty on failure).
	 */
	private static function list_anthropic_models() {

		$api_key = (string) Acelera_Settings::get( 'anthropic_api_key', '' );

		if ( '' === $api_key ) {
			return array();
		}

		$response = wp_remote_get(
			'https://api.anthropic.com/v1/models?limit=1000',
			array(
				'timeout' => self::LIST_TIMEOUT,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				),
			)
		);

		$data = self::parse_response( $response, 'anthropic' );

		if ( is_wp_error( $data ) || empty( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return array();
		}

		$models = array();

		foreach ( $data['data'] as $entry ) {
			if ( empty( $entry['id'] ) || ! is_string( $entry['id'] ) ) {
				continue;
			}

			$id    = $entry['id'];
			$label = ( ! empty( $entry['display_name'] ) && is_string( $entry['display_name'] ) )
				? $entry['display_name']
				: $id;

			$models[ $id ] = $label;
		}

		return $models;

	}

	/**
	 * Query the OpenAI /v1/models endpoint.
	 *
	 * The endpoint returns every model (embeddings, audio, image, …),
	 * so the list is filtered to chat-capable gpt-* / o-series families
	 * and sorted with the newest-looking ids first.
	 *
	 * Response shape: { data: [ { id }, ... ] }.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array<string,string> Model id => label (empty on failure).
	 */
	private static function list_openai_models() {

		$api_key = (string) Acelera_Settings::get( 'openai_api_key', '' );

		if ( '' === $api_key ) {
			return array();
		}

		$response = wp_remote_get(
			'https://api.openai.com/v1/models',
			array(
				'timeout' => self::LIST_TIMEOUT,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
			)
		);

		$data = self::parse_response( $response, 'openai' );

		if ( is_wp_error( $data ) || empty( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return array();
		}

		$ids = array();

		foreach ( $data['data'] as $entry ) {
			if ( empty( $entry['id'] ) || ! is_string( $entry['id'] ) ) {
				continue;
			}

			if ( self::is_openai_chat_model( $entry['id'] ) ) {
				$ids[] = $entry['id'];
			}
		}

		sort( $ids );

		$models = array();
		foreach ( $ids as $id ) {
			$models[ $id ] = $id; // OpenAI ids have no separate display name.
		}

		return $models;

	}

	/**
	 * Whether an OpenAI model id is a chat-capable gpt/o-series model.
	 *
	 * Excludes embeddings, audio (whisper/tts), image (dall-e),
	 * moderation, realtime, search/transcribe variants and dated
	 * snapshot duplicates that only add noise to the dropdown.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $id Model id, e.g. 'gpt-4o-mini'.
	 * @return bool
	 */
	private static function is_openai_chat_model( $id ) {

		$id = strtolower( $id );

		// Chat families only.
		$is_family = ( 0 === strpos( $id, 'gpt-' ) )
			|| (bool) preg_match( '/^o\d/', $id ); // o1, o3, o4, …

		if ( ! $is_family ) {
			return false;
		}

		// Drop non-chat / specialized variants.
		$blocked = array( 'embedding', 'whisper', 'tts', 'audio', 'realtime', 'image', 'dall-e', 'moderation', 'transcribe', 'search', 'instruct' );

		foreach ( $blocked as $needle ) {
			if ( false !== strpos( $id, $needle ) ) {
				return false;
			}
		}

		return true;

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
