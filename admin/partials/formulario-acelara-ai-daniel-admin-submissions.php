<?php

/**
 * "Sumisiones" admin list (Fase 5.3).
 *
 * Minimal escaped table with the Clientify sync status per submission and
 * a "Reenviar" action for failed/skipped rows. Expects $submissions
 * (row objects), $page, $per_page, $total and $total_pages from
 * Formulario_Acelara_Ai_Daniel_Admin::render_submissions_page().
 *
 * @link       https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$acelera_status_labels = array(
	'pending' => __( 'Pendiente', 'formulario-acelara-ai-daniel' ),
	'sent'    => __( 'Enviado', 'formulario-acelara-ai-daniel' ),
	'error'   => __( 'Error', 'formulario-acelara-ai-daniel' ),
	'skipped' => __( 'Omitido', 'formulario-acelara-ai-daniel' ),
);
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p>
		<?php
		printf(
			/* translators: %d: total number of submissions. */
			esc_html__( '%d sumisiones en total. Estado de sincronización con Clientify por fila.', 'formulario-acelara-ai-daniel' ),
			(int) $total
		);
		?>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'ID', 'formulario-acelara-ai-daniel' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Usuario', 'formulario-acelara-ai-daniel' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Fecha', 'formulario-acelara-ai-daniel' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Estado del formulario', 'formulario-acelara-ai-daniel' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Clientify', 'formulario-acelara-ai-daniel' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Contacto', 'formulario-acelara-ai-daniel' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Último error', 'formulario-acelara-ai-daniel' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Acciones', 'formulario-acelara-ai-daniel' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( array() === $submissions ) : ?>
				<tr>
					<td colspan="8"><?php esc_html_e( 'No hay sumisiones todavía.', 'formulario-acelara-ai-daniel' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $submissions as $submission ) : ?>
					<?php
					$acelera_user       = get_userdata( (int) $submission->user_id );
					$acelera_user_name  = $acelera_user ? $acelera_user->display_name : sprintf( '#%d', (int) $submission->user_id );
					$acelera_cf_status  = (string) $submission->clientify_status;
					$acelera_contact_id = (int) $submission->clientify_contact_id;
					$acelera_last_error = get_transient( Acelera_Clientify_Dispatcher::ERROR_TRANSIENT_PREFIX . (int) $submission->id );
					$acelera_resendable = in_array( $acelera_cf_status, array( 'error', 'skipped' ), true );
					?>
					<tr>
						<td><?php echo (int) $submission->id; ?></td>
						<td><?php echo esc_html( $acelera_user_name ); ?></td>
						<td><?php echo esc_html( (string) $submission->created_at ); ?></td>
						<td><?php echo esc_html( (string) $submission->status ); ?></td>
						<td>
							<?php
							echo esc_html(
								isset( $acelera_status_labels[ $acelera_cf_status ] )
									? $acelera_status_labels[ $acelera_cf_status ]
									: ( '' !== $acelera_cf_status ? $acelera_cf_status : '—' )
							);
							?>
						</td>
						<td>
							<?php if ( $acelera_contact_id > 0 && 'sent' === $acelera_cf_status ) : ?>
								<a href="<?php echo esc_url( 'https://app.clientify.com/contacts/' . $acelera_contact_id ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo (int) $acelera_contact_id; ?>
								</a>
							<?php elseif ( $acelera_contact_id > 0 ) : ?>
								<?php echo (int) $acelera_contact_id; ?>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
						<td><?php echo $acelera_last_error ? esc_html( (string) $acelera_last_error ) : '&mdash;'; ?></td>
						<td>
							<?php if ( $acelera_resendable ) : ?>
								<button type="button" class="button button-small acelera-clientify-resend" data-submission-id="<?php echo (int) $submission->id; ?>">
									<?php esc_html_e( 'Reenviar', 'formulario-acelara-ai-daniel' ); ?>
								</button>
								<span class="acelera-resend-result" aria-live="polite"></span>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					(string) paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => (int) $page,
							'total'   => (int) $total_pages,
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
