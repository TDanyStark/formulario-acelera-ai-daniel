<?php
/**
 * ACELERA override — LearnDash LD30 course navigation widget section.
 *
 * Based on sfwd-lms/themes/ld30/templates/widgets/navigation/section.php
 * (LD 5.1.4). Loaded only inside the ACELERA course via
 * Acelera_Template_Loader (filter `learndash_template`).
 *
 * Changes vs original:
 * - The title passes through the `acelera_section_title` filter before
 *   printing (Fase 4 hooks it to renumber module titles per user).
 * - Collapsible sections (modules 2-5) render the title inside a real
 *   <button> (accordion toggle) with aria-expanded / aria-controls; the
 *   matching container is opened by the rows.php override.
 * - Non-collapsible sections (Bienvenida, Módulo 1, unmapped) keep LD's
 *   stock markup, title filter aside.
 *
 * All four LD section actions are preserved.
 *
 * @since 1.0.0
 *
 * @package Formulario_Acelera_Ai_Daniel\Templates\LD30
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Accordion metadata: provided by the rows.php override; computed as a
// fallback in case LD reaches this template through another code path
// (e.g. the stock lesson-row.php section block).
if ( ! isset( $acelera_meta ) || ! is_array( $acelera_meta ) ) {
	$acelera_meta = class_exists( 'Acelera_Template_Loader' )
		? Acelera_Template_Loader::section_accordion_meta( $section, $course_id )
		: array(
			'module'       => null,
			'collapsible'  => false,
			'open'         => true,
			'container_id' => '',
		);
}

/**
 * Filters the ACELERA sidebar section title before output.
 *
 * Fase 4 hooks here to renumber module titles per user
 * ("Módulo 5 …" -> "Módulo 1 …"). Output is escaped after filtering.
 *
 * @since 1.0.0
 *
 * @param string $section_title Raw section title.
 * @param object $section       Section object {order, ID, post_title, type, steps}.
 */
$acelera_section_title = apply_filters( 'acelera_section_title', $section->post_title, $section );

$acelera_heading_classes = 'ld-lesson-item-section-heading ld-lesson-item-section-heading-' . $section->ID;

if ( ! empty( $acelera_meta['collapsible'] ) ) {
	$acelera_heading_classes .= ' acelera-accordion-heading';
	$acelera_heading_classes .= ! empty( $acelera_meta['open'] ) ? ' acelera-open' : '';
}

/** This action is documented in sfwd-lms/themes/ld30/templates/widgets/navigation/section.php */
do_action( 'learndash-nav-before-section-heading', $section, $course_id, $user_id ); ?>
<div class="<?php echo esc_attr( $acelera_heading_classes ); ?>">
	<?php
	/** This action is documented in sfwd-lms/themes/ld30/templates/widgets/navigation/section.php */
	do_action( 'learndash-nav-before-inner-section-heading', $section, $course_id, $user_id );

	if ( ! empty( $acelera_meta['collapsible'] ) ) :
		?>
		<span class="ld-lesson-section-heading" role="heading" aria-level="3">
			<button
				type="button"
				class="acelera-section-toggle"
				aria-expanded="<?php echo ! empty( $acelera_meta['open'] ) ? 'true' : 'false'; ?>"
				aria-controls="<?php echo esc_attr( $acelera_meta['container_id'] ); ?>"
				data-acelera-section="<?php echo esc_attr( $section->ID ); ?>"
			>
				<span class="acelera-section-title"><?php echo esc_html( $acelera_section_title ); ?></span>
				<span class="ld-icon ld-icon-arrow-down acelera-chevron" aria-hidden="true"></span>
			</button>
		</span>
	<?php else : ?>
		<span class="ld-lesson-section-heading" role="heading" aria-level="3"><?php echo esc_html( $acelera_section_title ); ?></span>
	<?php endif; ?>
	<?php
	/** This action is documented in sfwd-lms/themes/ld30/templates/widgets/navigation/section.php */
	do_action( 'learndash-nav-after-inner-section-heading', $section, $course_id, $user_id );
	?>
</div>
<?php
/** This action is documented in sfwd-lms/themes/ld30/templates/widgets/navigation/section.php */
do_action( 'learndash-nav-after-section-heading', $section, $course_id, $user_id );
