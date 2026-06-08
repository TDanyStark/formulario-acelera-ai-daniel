<?php
/**
 * Clase principal del plugin.
 *
 * Orquesta la carga de dependencias, hooks de admin y front-end,
 * la integración con LearnDash y la conexión con Clientify (CRM).
 *
 * @package FormularioAcelaraAiDaniel
 */

// Si este archivo se llama directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class FAAD_Plugin
 */
class FAAD_Plugin {

	/**
	 * Versión del plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Slug / text domain del plugin.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->version = defined( 'FAAD_VERSION' ) ? FAAD_VERSION : '1.0.0';
		$this->slug    = defined( 'FAAD_SLUG' ) ? FAAD_SLUG : 'formulario-acelara-ai-daniel';
	}

	/**
	 * Punto de entrada: registra hooks y arranca el plugin.
	 */
	public function run() {
		$this->define_hooks();
	}

	/**
	 * Registra los hooks principales de WordPress.
	 */
	private function define_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// TODO: registrar shortcode del formulario.
		// TODO: registrar integración con LearnDash (curso / módulos como rutas).
		// TODO: registrar conexión con Clientify (CRM).
	}

	/**
	 * Carga el text domain para traducciones.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			$this->slug,
			false,
			dirname( plugin_basename( FAAD_PLUGIN_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Encola los assets del front-end.
	 */
	public function enqueue_public_assets() {
		wp_enqueue_style(
			$this->slug . '-public',
			FAAD_PLUGIN_URL . 'public/css/faad-public.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			$this->slug . '-public',
			FAAD_PLUGIN_URL . 'public/js/faad-public.js',
			array( 'jquery' ),
			$this->version,
			true
		);
	}

	/**
	 * Encola los assets del panel de administración.
	 *
	 * @param string $hook Hook de la página actual del admin.
	 */
	public function enqueue_admin_assets( $hook ) {
		// TODO: encolar assets solo en las pantallas del plugin.
	}
}
