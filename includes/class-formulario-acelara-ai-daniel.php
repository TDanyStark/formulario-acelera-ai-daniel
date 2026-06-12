<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Formulario_Acelara_Ai_Daniel {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Formulario_Acelara_Ai_Daniel_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'FORMULARIO_ACELARA_AI_DANIEL_VERSION' ) ) {
			$this->version = FORMULARIO_ACELARA_AI_DANIEL_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'formulario-acelara-ai-daniel';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Formulario_Acelara_Ai_Daniel_Loader. Orchestrates the hooks of the plugin.
	 * - Formulario_Acelara_Ai_Daniel_i18n. Defines internationalization functionality.
	 * - Formulario_Acelara_Ai_Daniel_Admin. Defines all hooks for the admin area.
	 * - Formulario_Acelara_Ai_Daniel_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-formulario-acelara-ai-daniel-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-formulario-acelara-ai-daniel-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-formulario-acelara-ai-daniel-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-formulario-acelara-ai-daniel-public.php';

		/**
		 * Hardcoded map of the ACELERA course (course/lesson IDs, modules, routes).
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/config/class-acelera-course-map.php';

		/**
		 * Repository for the acelera_form_submissions table.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-acelera-submissions-repo.php';

		/**
		 * Global read access to the plugin settings (acelera_settings option).
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-acelera-settings.php';

		/**
		 * Welcome gate state helper (Fase 2): blocks M1–M5 lessons until
		 * the Bienvenida lessons are completed.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/gate/class-acelera-welcome-gate.php';

		$this->loader = new Formulario_Acelara_Ai_Daniel_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Formulario_Acelara_Ai_Daniel_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Formulario_Acelara_Ai_Daniel_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Formulario_Acelara_Ai_Daniel_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Settings page (menu + Settings API registration).
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_settings_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		// Guard: without LearnDash there is no course to integrate with.
		// Show an admin notice and skip every public-facing hook.
		if ( ! defined( 'LEARNDASH_VERSION' ) ) {
			$this->loader->add_action( 'admin_notices', $this, 'learndash_missing_notice' );
			return;
		}

		$plugin_public = new Formulario_Acelara_Ai_Daniel_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Welcome gate (Fase 2).
		// Layer A: hard redirect away from locked M1–M5 lessons.
		$this->loader->add_action( 'template_redirect', $plugin_public, 'gate_template_redirect' );

		// Gate notice on the redirect destination (Focus Mode + the_content fallback).
		$this->loader->add_action( 'learndash-focus-content-content-before', $plugin_public, 'gate_render_focus_notice', 10, 2 );
		$this->loader->add_filter( 'the_content', $plugin_public, 'gate_prepend_notice' );

		// Layer B: LearnDash read-step filter (visual/logical consistency).
		$this->loader->add_filter( 'learndash_can_user_read_step', $plugin_public, 'gate_can_user_read_step', 10, 3 );

		// Sidebar / course listing signaling: add .acelera-locked to rows.
		$this->loader->add_filter( 'learndash_lesson_row_class', $plugin_public, 'gate_lesson_row_class', 10, 2 );
		$this->loader->add_filter( 'learndash-nav-widget-lesson-class', $plugin_public, 'gate_lesson_row_class', 10, 2 );

		// Free-mode compatibility: immediate unlock when a lesson completes.
		$this->loader->add_action( 'learndash_lesson_completed', $plugin_public, 'gate_on_lesson_completed', 10, 1 );

	}

	/**
	 * Admin notice shown when LearnDash is not active.
	 *
	 * @since    1.0.0
	 */
	public function learndash_missing_notice() {

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Formulario Acelara AI Daniel requiere LearnDash activo. Las funciones públicas del plugin están deshabilitadas hasta que se active LearnDash.', 'formulario-acelara-ai-daniel' )
		);

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Formulario_Acelara_Ai_Daniel_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
