<?php

/**
 * Provide a admin area view for the plugin
 *
 * Renders the "Curso Acelera" settings page with its tab navigation.
 * Expects $tabs (array slug => label) and $active_tab (string) from
 * Formulario_Acelera_Ai_Daniel_Admin::render_settings_page().
 *
 * @link       https://https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelera_Ai_Daniel
 * @subpackage Formulario_Acelera_Ai_Daniel/admin/partials
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

	<?php if ( 'ayuda' !== $active_tab ) : ?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'acelera_settings' );
			do_settings_sections( 'acelera-settings-' . $active_tab );
			submit_button();
			?>
		</form>
	<?php endif; ?>

	<?php if ( 'ayuda' === $active_tab ) : ?>
		<h2><?php esc_html_e( 'Shortcodes disponibles', 'formulario-acelera-ai-daniel' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Copia el shortcode y pégalo en la página o clase donde quieras mostrar el contenido.', 'formulario-acelera-ai-daniel' ); ?>
		</p>

		<table class="widefat striped" style="max-width:820px;margin-top:12px;">
			<thead>
				<tr>
					<th style="width:220px;"><?php esc_html_e( 'Shortcode', 'formulario-acelera-ai-daniel' ); ?></th>
					<th><?php esc_html_e( 'Descripción', 'formulario-acelera-ai-daniel' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<code class="acelera-shortcode-code">[acelera_form]</code>
						<button type="button" class="button button-small acelera-copy-shortcode" data-shortcode="[acelera_form]">
							<?php esc_html_e( 'Copiar', 'formulario-acelera-ai-daniel' ); ?>
						</button>
					</td>
					<td>
						<?php esc_html_e( 'Formulario de diagnóstico ACELERA. Muestra el formulario por pasos al alumno; una vez enviado, muestra automáticamente la pantalla de resultado con su orden personalizado de módulos. Colócalo en una página accesible solo para alumnos logueados.', 'formulario-acelera-ai-daniel' ); ?>
					</td>
				</tr>
				<tr>
					<td>
						<code class="acelera-shortcode-code">[acelera_feedback module="m1"]</code>
						<button type="button" class="button button-small acelera-copy-shortcode" data-shortcode='[acelera_feedback module="m1"]'>
							<?php esc_html_e( 'Copiar', 'formulario-acelera-ai-daniel' ); ?>
						</button>
					</td>
					<td>
						<?php esc_html_e( 'Feedback personalizado generado con IA (LLM). Tú eliges el módulo con el atributo module y dónde colocarlo: pega el shortcode en la clase/lección que quieras. El módulo NO se detecta automáticamente: el feedback corresponde al valor que indiques en module ("m1" a "m5").', 'formulario-acelera-ai-daniel' ); ?>
						<br><br>
						<?php esc_html_e( 'Shortcode por módulo (clic para copiar):', 'formulario-acelera-ai-daniel' ); ?>
						<span class="acelera-feedback-module-codes">
							<?php foreach ( Acelera_Course_Map::modules() as $module_key => $module_def ) : ?>
								<?php $fb_code = sprintf( '[acelera_feedback module="%s"]', $module_key ); ?>
								<button type="button" class="button button-small acelera-copy-shortcode" data-shortcode='<?php echo esc_attr( $fb_code ); ?>' title="<?php echo esc_attr( $module_def['label'] ); ?>">
									<?php echo esc_html( strtoupper( $module_key ) ); ?>
								</button>
							<?php endforeach; ?>
						</span>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="description" style="margin-top:16px;">
			<?php esc_html_e( 'Nota: ambos shortcodes requieren que el alumno haya iniciado sesión.', 'formulario-acelera-ai-daniel' ); ?>
		</p>

		<script>
		( function () {
			document.querySelectorAll( '.acelera-copy-shortcode' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var code = btn.getAttribute( 'data-shortcode' );
					var done = function () {
						var original = btn.textContent;
						btn.textContent = <?php echo wp_json_encode( __( '¡Copiado!', 'formulario-acelera-ai-daniel' ) ); ?>;
						setTimeout( function () { btn.textContent = original; }, 1500 );
					};
					if ( navigator.clipboard && navigator.clipboard.writeText ) {
						navigator.clipboard.writeText( code ).then( done, done );
					} else {
						var ta = document.createElement( 'textarea' );
						ta.value = code;
						document.body.appendChild( ta );
						ta.select();
						try { document.execCommand( 'copy' ); } catch ( e ) {}
						document.body.removeChild( ta );
						done();
					}
				} );
			} );
		}() );
		</script>
	<?php endif; ?>

	<?php if ( 'clientify' === $active_tab ) : ?>
		<hr>
		<p>
			<button type="button" class="button" id="acelera-clientify-test">
				<?php esc_html_e( 'Probar conexión', 'formulario-acelera-ai-daniel' ); ?>
			</button>
			<span id="acelera-clientify-test-result" aria-live="polite"></span>
		</p>
	<?php endif; ?>

	<?php if ( 'llm' === $active_tab ) : ?>
		<hr>
		<h2><?php esc_html_e( 'Regenerar feedback (soporte)', 'formulario-acelera-ai-daniel' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Borra el feedback cacheado de un alumno; se volverá a generar con el LLM en su próxima visita a una clase con el shortcode.', 'formulario-acelera-ai-daniel' ); ?>
		</p>
		<p>
			<label for="acelera-llm-user"><?php esc_html_e( 'Usuario (ID o email)', 'formulario-acelera-ai-daniel' ); ?></label>
			<input type="text" id="acelera-llm-user" class="regular-text" />

			<label for="acelera-llm-module"><?php esc_html_e( 'Módulo', 'formulario-acelera-ai-daniel' ); ?></label>
			<select id="acelera-llm-module">
				<option value="todos"><?php esc_html_e( 'Todos los módulos', 'formulario-acelera-ai-daniel' ); ?></option>
				<?php foreach ( Acelera_Course_Map::modules() as $module_key => $module_def ) : ?>
					<option value="<?php echo esc_attr( $module_key ); ?>">
						<?php echo esc_html( sprintf( '%s — %s', strtoupper( $module_key ), $module_def['label'] ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="button" class="button" id="acelera-llm-regenerate">
				<?php esc_html_e( 'Regenerar feedback', 'formulario-acelera-ai-daniel' ); ?>
			</button>
			<span id="acelera-llm-regenerate-result" aria-live="polite"></span>
		</p>
	<?php endif; ?>
</div>
