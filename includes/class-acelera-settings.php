<?php

/**
 * Global access helper for the plugin settings.
 *
 * @link       https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes
 */

/**
 * Read access to the serialized `acelera_settings` option.
 *
 * Saved values are merged with sensible defaults so callers never have to
 * worry about missing keys.
 *
 * @since      1.0.0
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Acelera_Settings {

	/**
	 * Name of the single serialized option.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const OPTION_NAME = 'acelera_settings';

	/**
	 * Default values for every setting field.
	 *
	 * @since  1.0.0
	 * @return array<string, string>
	 */
	public static function defaults() {
		return array(
			// Clientify (Fase 5).
			'clientify_api_key' => '',
			'clientify_owner'   => 'info@cafecitoconcata.com',
			'clientify_tags'    => '',
			// LLM (Fase 6). llm_model intentionally defaults to '' —
			// empty means "per-provider default" resolved by
			// Acelera_LLM_Client::resolve_model() (claude-sonnet-4-6 for
			// Claude, gpt-5 for OpenAI), so switching provider never
			// leaves a stale model name from the other vendor.
			'llm_provider'      => 'claude',
			'anthropic_api_key' => '',
			'openai_api_key'    => '',
			'llm_model'         => '',
			// Prompts (Fase 6).
			'prompt_m1'         => '',
			'prompt_m2'         => '',
			'prompt_m3'         => '',
			'prompt_m4'         => '',
			'prompt_m5'         => '',
			// Email (Fase 4).
			'email_subject'     => 'Tu resultado del diagnóstico ACELERA',
			'email_from_name'   => 'Cafecito con Cata',
		);
	}

	/**
	 * Get every setting merged with defaults.
	 *
	 * @since  1.0.0
	 * @return array<string, string>
	 */
	public static function all() {
		$saved = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get a single setting value.
	 *
	 * Resolution order: saved non-empty value, plugin default, $default arg.
	 *
	 * @since  1.0.0
	 * @param  string $key     Setting key, e.g. 'clientify_api_key'.
	 * @param  mixed  $default Optional. Fallback when no value exists.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$saved = get_option( self::OPTION_NAME, array() );

		if ( is_array( $saved ) && isset( $saved[ $key ] ) && '' !== $saved[ $key ] ) {
			return $saved[ $key ];
		}

		$defaults = self::defaults();

		if ( isset( $defaults[ $key ] ) && '' !== $defaults[ $key ] ) {
			return $defaults[ $key ];
		}

		return $default;
	}

}
