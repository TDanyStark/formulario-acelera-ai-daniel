<?php

/**
 * Provide a admin area view for the plugin
 *
 * Renders the "Curso Acelera" settings page with its tab navigation.
 * Expects $tabs (array slug => label) and $active_tab (string) from
 * Formulario_Acelara_Ai_Daniel_Admin::render_settings_page().
 *
 * @link       https://https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors(); ?>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_slug => $tab_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'acelera-settings', 'tab' => $tab_slug ), admin_url( 'admin.php' ) ) ); ?>"
				class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'acelera_settings' );
		do_settings_sections( 'acelera-settings-' . $active_tab );
		submit_button();
		?>
	</form>

	<?php if ( 'clientify' === $active_tab ) : ?>
		<hr>
		<p>
			<button type="button" class="button" id="acelera-clientify-test">
				<?php esc_html_e( 'Probar conexión', 'formulario-acelara-ai-daniel' ); ?>
			</button>
			<span id="acelera-clientify-test-result" aria-live="polite"></span>
		</p>
	<?php endif; ?>
</div>
