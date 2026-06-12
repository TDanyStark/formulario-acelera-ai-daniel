<?php
/**
 * HTML template for the ACELERA result email.
 *
 * Table-based layout with inline styles only (email client compatibility).
 * Rendered by Acelera_Email::render_template() with these variables:
 *
 * @var string $greeting_name Recipient name (P0.1 answer).
 * @var array  $modules       Ordered items: [ 'number' => int,
 *                            'label' => 'Módulo 1. …', 'url' => string ].
 *
 * @link       https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelera_Ai_Daniel
 * @subpackage Formulario_Acelera_Ai_Daniel/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$greeting_name = isset( $greeting_name ) ? (string) $greeting_name : '';
$modules       = isset( $modules ) && is_array( $modules ) ? $modules : array();
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( 'Tu resultado del diagnóstico ACELERA' ); ?></title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f4; padding:24px 0;">
		<tr>
			<td align="center">
				<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:94%; background-color:#ffffff; border-radius:8px; overflow:hidden; font-family:Arial, Helvetica, sans-serif;">
					<!-- Header -->
					<tr>
						<td style="background-color:#1a1a2e; padding:28px 32px; text-align:center;">
							<span style="color:#ffffff; font-size:22px; font-weight:bold; letter-spacing:1px;">ACELERA</span>
						</td>
					</tr>
					<!-- Greeting -->
					<tr>
						<td style="padding:32px 32px 8px 32px;">
							<p style="margin:0 0 16px 0; color:#1a1a2e; font-size:18px; font-weight:bold;">
								<?php if ( '' !== $greeting_name ) : ?>
									¡Hola, <?php echo esc_html( $greeting_name ); ?>!
								<?php else : ?>
									¡Hola!
								<?php endif; ?>
							</p>
							<p style="margin:0 0 8px 0; color:#444444; font-size:15px; line-height:1.6;">
								Gracias por completar tu diagnóstico. Con base en tus respuestas, este es el camino recomendado para ti dentro del programa.
							</p>
						</td>
					</tr>
					<!-- Title -->
					<tr>
						<td style="padding:16px 32px 8px 32px;">
							<h1 style="margin:0; color:#1a1a2e; font-size:20px; line-height:1.4;">Tu orden de ejecución del curso</h1>
						</td>
					</tr>
					<!-- Modules -->
					<tr>
						<td style="padding:8px 32px 24px 32px;">
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
								<?php foreach ( $modules as $module ) : ?>
								<tr>
									<td style="padding:8px 0;">
										<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f7f7fb; border:1px solid #e4e4ef; border-radius:6px;">
											<tr>
												<td style="padding:16px 20px;">
													<p style="margin:0 0 12px 0; color:#1a1a2e; font-size:16px; font-weight:bold;">
														<?php echo esc_html( $module['label'] ); ?>
													</p>
													<?php if ( ! empty( $module['url'] ) ) : ?>
													<a href="<?php echo esc_url( $module['url'] ); ?>" style="display:inline-block; background-color:#e94560; color:#ffffff; text-decoration:none; font-size:14px; font-weight:bold; padding:10px 22px; border-radius:4px;">
														Empezar este módulo
													</a>
													<?php endif; ?>
												</td>
											</tr>
										</table>
									</td>
								</tr>
								<?php endforeach; ?>
							</table>
						</td>
					</tr>
					<!-- Footer -->
					<tr>
						<td style="background-color:#f7f7fb; padding:20px 32px; text-align:center; border-top:1px solid #e4e4ef;">
							<p style="margin:0; color:#888888; font-size:12px; line-height:1.6;">
								Recibes este correo porque completaste el Formulario de Diagnóstico del programa ACELERA.<br>
								Cafecito con Cata
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
