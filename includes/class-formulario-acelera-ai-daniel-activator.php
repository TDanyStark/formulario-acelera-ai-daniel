<?php

/**
 * Fired during plugin activation
 *
 * @link       https://https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelera_Ai_Daniel
 * @subpackage Formulario_Acelera_Ai_Daniel/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Formulario_Acelera_Ai_Daniel
 * @subpackage Formulario_Acelera_Ai_Daniel/includes
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Formulario_Acelera_Ai_Daniel_Activator {

	/**
	 * Database schema version, stored in the `acelera_db_version` option
	 * for future migrations.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Run activation tasks.
	 *
	 * Creates the form submissions table and stores the DB schema version.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

		self::create_submissions_table();

		update_option( 'acelera_db_version', self::DB_VERSION );

	}

	/**
	 * Create (or upgrade) the `{$wpdb->prefix}acelera_form_submissions` table.
	 *
	 * Uses dbDelta(), so the statement follows its formatting rules: one
	 * field per line, two spaces after "PRIMARY KEY", and KEY syntax with
	 * the index name.
	 *
	 * @since    1.0.0
	 */
	private static function create_submissions_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'acelera_form_submissions';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id BIGINT UNSIGNED NOT NULL,
	answers LONGTEXT NOT NULL,
	scores LONGTEXT NULL,
	module_order VARCHAR(50) NULL,
	flags LONGTEXT NULL,
	cv_url VARCHAR(500) NULL,
	clientify_contact_id BIGINT NULL,
	clientify_status VARCHAR(20) NULL,
	status VARCHAR(20) NOT NULL DEFAULT 'completed',
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY  (id),
	KEY user_id (user_id),
	KEY status (status)
) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );
	}

}
