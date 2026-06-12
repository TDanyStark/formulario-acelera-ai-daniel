<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelara_Ai_Daniel
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Clear every pending Clientify cron event (any args).
wp_unschedule_hook( 'acelera_send_to_clientify' );

// Delete plugin options.
delete_option( 'acelera_settings' );
delete_option( 'acelera_db_version' );

// Delete plugin transients (Clientify last-error + LLM locks) directly:
// get/delete_transient can't enumerate by prefix, so a LIKE delete on the
// options table (value + timeout rows) is the reliable cleanup.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_acelera_clientify_err_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_acelera_clientify_err_' ) . '%',
		$wpdb->esc_like( '_transient_acelera_llm_lock_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_acelera_llm_lock_' ) . '%'
	)
);

// Delete every user meta created by the plugin (acelera_ prefix).
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( 'acelera_' ) . '%'
	)
);

// Drop the form submissions table.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acelera_form_submissions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
