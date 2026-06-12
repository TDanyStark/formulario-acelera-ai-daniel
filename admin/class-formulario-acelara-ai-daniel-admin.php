<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Registers the "Curso Acelera" settings page (Settings API, single
 * serialized option `acelera_settings` with tabs) and enqueues admin
 * assets only on that page.
 *
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/admin
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Formulario_Acelara_Ai_Daniel_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Hook suffix of the plugin settings page, set by add_settings_menu().
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $settings_page_hook    Hook suffix returned by add_menu_page().
	 */
	private $settings_page_hook = '';

	/**
	 * Hook suffix of the submissions list page, set by add_settings_menu().
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $submissions_page_hook    Hook suffix returned by add_submenu_page().
	 */
	private $submissions_page_hook = '';

	/**
	 * Nonce action shared by the Clientify admin AJAX endpoints.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const CLIENTIFY_NONCE_ACTION = 'acelera_clientify_admin';

	/**
	 * Setting keys that hold API secrets and must never be printed in full.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string[]
	 */
	private $api_key_fields = array( 'clientify_api_key', 'anthropic_api_key', 'openai_api_key' );

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * Only enqueued on the plugin settings page.
	 *
	 * @since    1.0.0
	 * @param    string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_styles( $hook_suffix ) {

		if ( ! in_array( $hook_suffix, array( $this->settings_page_hook, $this->submissions_page_hook ), true ) ) {
			return;
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/formulario-acelara-ai-daniel-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * Only enqueued on the plugin settings page.
	 *
	 * @since    1.0.0
	 * @param    string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_scripts( $hook_suffix ) {

		if ( ! in_array( $hook_suffix, array( $this->settings_page_hook, $this->submissions_page_hook ), true ) ) {
			return;
		}

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/formulario-acelara-ai-daniel-admin.js', array( 'jquery' ), $this->version, false );

		wp_localize_script(
			$this->plugin_name,
			'aceleraAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::CLIENTIFY_NONCE_ACTION ),
				'i18n'    => array(
					'testing'   => __( 'Probando conexión…', 'formulario-acelara-ai-daniel' ),
					'resending' => __( 'Reprogramando…', 'formulario-acelara-ai-daniel' ),
					'genericKo' => __( 'Error inesperado. Revisa la consola del navegador.', 'formulario-acelara-ai-daniel' ),
				),
			)
		);

	}

	/**
	 * Register the "Curso Acelera" top-level menu page.
	 *
	 * @since    1.0.0
	 */
	public function add_settings_menu() {

		$this->settings_page_hook = add_menu_page(
			__( 'Acelera', 'formulario-acelara-ai-daniel' ),
			__( 'Curso Acelera', 'formulario-acelara-ai-daniel' ),
			'manage_options',
			'acelera-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-welcome-learn-more',
			58
		);

		// Mirror the default WP behavior: a submenu entry for the parent page.
		add_submenu_page(
			'acelera-settings',
			__( 'Ajustes', 'formulario-acelara-ai-daniel' ),
			__( 'Ajustes', 'formulario-acelara-ai-daniel' ),
			'manage_options',
			'acelera-settings'
		);

		// Submissions status list (Fase 5.3).
		$this->submissions_page_hook = add_submenu_page(
			'acelera-settings',
			__( 'Sumisiones', 'formulario-acelara-ai-daniel' ),
			__( 'Sumisiones', 'formulario-acelara-ai-daniel' ),
			'manage_options',
			'acelera-submissions',
			array( $this, 'render_submissions_page' )
		);

	}

	/**
	 * Register the `acelera_settings` option, sections and fields.
	 *
	 * One section per tab; each tab uses its own Settings API "page" slug
	 * (acelera-settings-{tab}) so only the active tab is rendered.
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {

		register_setting(
			'acelera_settings',
			Acelera_Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// --- Tab: Clientify ---------------------------------------------.
		add_settings_section(
			'acelera_section_clientify',
			__( 'Integración con Clientify', 'formulario-acelara-ai-daniel' ),
			'__return_false',
			'acelera-settings-clientify'
		);

		$this->add_field( 'clientify_api_key', __( 'API Key de Clientify', 'formulario-acelara-ai-daniel' ), 'clientify', 'api_key' );
		$this->add_field( 'clientify_owner', __( 'Owner (email)', 'formulario-acelara-ai-daniel' ), 'clientify', 'text', array(
			'description' => __( 'Email del propietario asignado a los contactos creados.', 'formulario-acelara-ai-daniel' ),
		) );
		$this->add_field( 'clientify_tags', __( 'Tags', 'formulario-acelara-ai-daniel' ), 'clientify', 'text', array(
			'description' => __( 'Tags separados por comas que se aplican a los contactos.', 'formulario-acelara-ai-daniel' ),
		) );

		// --- Tab: LLM -----------------------------------------------------.
		add_settings_section(
			'acelera_section_llm',
			__( 'Proveedor LLM (feedback por módulo)', 'formulario-acelara-ai-daniel' ),
			'__return_false',
			'acelera-settings-llm'
		);

		$this->add_field( 'llm_provider', __( 'Proveedor', 'formulario-acelara-ai-daniel' ), 'llm', 'select', array(
			'options' => array(
				'claude'  => 'Claude (Anthropic)',
				'chatgpt' => 'ChatGPT (OpenAI)',
			),
		) );
		$this->add_field( 'anthropic_api_key', __( 'API Key de Anthropic', 'formulario-acelara-ai-daniel' ), 'llm', 'api_key' );
		$this->add_field( 'openai_api_key', __( 'API Key de OpenAI', 'formulario-acelara-ai-daniel' ), 'llm', 'api_key' );
		$this->add_field( 'llm_model', __( 'Modelo', 'formulario-acelara-ai-daniel' ), 'llm', 'text', array(
			'description' => __( 'Identificador del modelo, p. ej. claude-sonnet-4-20250514 o gpt-4o.', 'formulario-acelara-ai-daniel' ),
		) );

		// --- Tab: Prompts ---------------------------------------------------.
		add_settings_section(
			'acelera_section_prompts',
			__( 'Prompts por módulo', 'formulario-acelara-ai-daniel' ),
			'__return_false',
			'acelera-settings-prompts'
		);

		foreach ( Acelera_Course_Map::modules() as $key => $module ) {
			$this->add_field(
				'prompt_' . $key,
				sprintf( '%s — %s', strtoupper( $key ), $module['label'] ),
				'prompts',
				'textarea'
			);
		}

		// --- Tab: Email -----------------------------------------------------.
		add_settings_section(
			'acelera_section_email',
			__( 'Email de resultado', 'formulario-acelara-ai-daniel' ),
			'__return_false',
			'acelera-settings-email'
		);

		$this->add_field( 'email_subject', __( 'Asunto', 'formulario-acelara-ai-daniel' ), 'email', 'text' );
		$this->add_field( 'email_from_name', __( 'Nombre del remitente', 'formulario-acelara-ai-daniel' ), 'email', 'text' );

	}

	/**
	 * Sanitize the submitted settings.
	 *
	 * Each tab submits only its own fields, so the result is merged over
	 * the previously stored option. API key fields keep their stored value
	 * when the submitted value is the masked placeholder.
	 *
	 * @since    1.0.0
	 * @param    array $input Raw submitted values.
	 * @return   array Sanitized full settings array.
	 */
	public function sanitize_settings( $input ) {

		$existing = get_option( Acelera_Settings::OPTION_NAME, array() );

		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		if ( ! is_array( $input ) ) {
			return $existing;
		}

		$output = $existing;

		// API keys: keep the stored value when the masked placeholder comes back.
		foreach ( $this->api_key_fields as $key ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			$value = trim( (string) $input[ $key ] );

			if ( false !== strpos( $value, "\u{2022}" ) ) {
				continue; // Masked placeholder submitted: keep stored value.
			}

			$output[ $key ] = sanitize_text_field( $value );
		}

		if ( isset( $input['clientify_owner'] ) ) {
			$output['clientify_owner'] = sanitize_email( $input['clientify_owner'] );
		}

		if ( isset( $input['clientify_tags'] ) ) {
			$tags = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', (string) $input['clientify_tags'] ) ) ) );

			$output['clientify_tags'] = implode( ', ', $tags );
		}

		if ( isset( $input['llm_provider'] ) ) {
			$output['llm_provider'] = in_array( $input['llm_provider'], array( 'claude', 'chatgpt' ), true ) ? $input['llm_provider'] : 'claude';
		}

		if ( isset( $input['llm_model'] ) ) {
			$output['llm_model'] = sanitize_text_field( $input['llm_model'] );
		}

		foreach ( array_keys( Acelera_Course_Map::modules() ) as $module_key ) {
			$prompt_key = 'prompt_' . $module_key;

			if ( isset( $input[ $prompt_key ] ) ) {
				$output[ $prompt_key ] = sanitize_textarea_field( $input[ $prompt_key ] );
			}
		}

		if ( isset( $input['email_subject'] ) ) {
			$output['email_subject'] = sanitize_text_field( $input['email_subject'] );
		}

		if ( isset( $input['email_from_name'] ) ) {
			$output['email_from_name'] = sanitize_text_field( $input['email_from_name'] );
		}

		return $output;

	}

	/**
	 * Render the settings page (delegates to the admin partial).
	 *
	 * @since    1.0.0
	 */
	public function render_settings_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = array(
			'clientify' => __( 'Clientify', 'formulario-acelara-ai-daniel' ),
			'llm'       => __( 'LLM', 'formulario-acelara-ai-daniel' ),
			'prompts'   => __( 'Prompts', 'formulario-acelara-ai-daniel' ),
			'email'     => __( 'Email', 'formulario-acelara-ai-daniel' ),
		);

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'clientify'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = 'clientify';
		}

		include plugin_dir_path( __FILE__ ) . 'partials/formulario-acelara-ai-daniel-admin-display.php';

	}

	/**
	 * Render a settings field.
	 *
	 * Generic callback for every field registered via add_field().
	 *
	 * @since    1.0.0
	 * @param    array $args Field arguments (key, type, options, description).
	 */
	public function render_field( $args ) {

		$key   = $args['key'];
		$type  = $args['type'];
		$value = Acelera_Settings::get( $key );
		$name  = Acelera_Settings::OPTION_NAME . '[' . $key . ']';

		switch ( $type ) {

			case 'api_key':
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" autocomplete="off" />',
					esc_attr( $key ),
					esc_attr( $name ),
					esc_attr( $this->mask_api_key( $value ) )
				);
				echo '<p class="description">' . esc_html__( 'Se muestran solo los últimos 4 caracteres. Pegá una clave nueva para reemplazarla.', 'formulario-acelara-ai-daniel' ) . '</p>';
				break;

			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $key ), esc_attr( $name ) );
				foreach ( $args['options'] as $option_value => $option_label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $option_value ),
						selected( $value, $option_value, false ),
						esc_html( $option_label )
					);
				}
				echo '</select>';
				break;

			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="6" class="large-text code">%3$s</textarea>',
					esc_attr( $key ),
					esc_attr( $name ),
					esc_textarea( $value )
				);
				break;

			case 'text':
			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $key ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;
		}

		if ( ! empty( $args['description'] ) && 'api_key' !== $type ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}

	}

	/**
	 * Mask an API key, leaving only the last 4 characters visible.
	 *
	 * @since    1.0.0
	 * @param    string $value Stored API key.
	 * @return   string Masked representation, e.g. "••••1234". Empty when no key stored.
	 */
	private function mask_api_key( $value ) {

		$value = (string) $value;

		if ( '' === $value ) {
			return '';
		}

		$visible = strlen( $value ) > 4 ? substr( $value, -4 ) : '';

		return str_repeat( "\u{2022}", 4 ) . $visible;

	}

	/**
	 * Render the "Sumisiones" list page (delegates to the admin partial).
	 *
	 * @since    1.0.0
	 */
	public function render_submissions_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$per_page = 20;
		$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$repo        = new Acelera_Submissions_Repo();
		$submissions = $repo->get_page( $per_page, $page );
		$total       = $repo->count_all();
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );

		include plugin_dir_path( __FILE__ ) . 'partials/formulario-acelara-ai-daniel-admin-submissions.php';

	}

	/**
	 * AJAX `acelera_clientify_test` — validate the stored API key.
	 *
	 * @since    1.0.0
	 */
	public function ajax_clientify_test() {

		check_ajax_referer( self::CLIENTIFY_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No tienes permisos suficientes.', 'formulario-acelara-ai-daniel' ) ),
				403
			);
		}

		$client = new Acelera_Clientify();
		$result = $client->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array( 'message' => __( 'Conexión correcta con Clientify.', 'formulario-acelara-ai-daniel' ) )
		);

	}

	/**
	 * AJAX `acelera_clientify_resend` — reschedule a failed/skipped send.
	 *
	 * Resets the row to 'pending' (attempt 1) and clears the stored error.
	 *
	 * @since    1.0.0
	 */
	public function ajax_clientify_resend() {

		check_ajax_referer( self::CLIENTIFY_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No tienes permisos suficientes.', 'formulario-acelara-ai-daniel' ) ),
				403
			);
		}

		$submission_id = isset( $_POST['submission_id'] ) ? (int) $_POST['submission_id'] : 0;

		$repo = new Acelera_Submissions_Repo();
		$row  = $submission_id > 0 ? $repo->get_by_id( $submission_id ) : null;

		if ( ! $row ) {
			wp_send_json_error(
				array( 'message' => __( 'La sumisión no existe.', 'formulario-acelara-ai-daniel' ) )
			);
		}

		if ( 'sent' === (string) $row->clientify_status ) {
			wp_send_json_error(
				array( 'message' => __( 'Esta sumisión ya fue enviada a Clientify.', 'formulario-acelara-ai-daniel' ) )
			);
		}

		delete_transient( Acelera_Clientify_Dispatcher::ERROR_TRANSIENT_PREFIX . $submission_id );

		$repo->update_clientify( $submission_id, (int) $row->clientify_contact_id ? (int) $row->clientify_contact_id : null, 'pending' );

		Acelera_Clientify_Dispatcher::schedule( $submission_id, 1, 0 );

		wp_send_json_success(
			array( 'message' => __( 'Reenvío programado.', 'formulario-acelara-ai-daniel' ) )
		);

	}

	/**
	 * Helper to register a settings field on a tab.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $key   Setting key.
	 * @param    string $label Field label.
	 * @param    string $tab   Tab slug (clientify | llm | prompts | email).
	 * @param    string $type  Field type (text | api_key | select | textarea).
	 * @param    array  $extra Optional. Extra args (options, description).
	 */
	private function add_field( $key, $label, $tab, $type, $extra = array() ) {

		add_settings_field(
			'acelera_field_' . $key,
			$label,
			array( $this, 'render_field' ),
			'acelera-settings-' . $tab,
			'acelera_section_' . $tab,
			array_merge(
				array(
					'key'       => $key,
					'type'      => $type,
					'label_for' => $key,
				),
				$extra
			)
		);

	}

}
