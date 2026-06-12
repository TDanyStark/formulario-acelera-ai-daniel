<?php
/**
 * ACELERA override — LearnDash LD30 course navigation widget rows.
 *
 * Based on sfwd-lms/themes/ld30/templates/widgets/navigation/rows.php
 * (LD 5.1.4). Loaded only inside the ACELERA course via
 * Acelera_Template_Loader (filter `learndash_template`).
 *
 * Changes vs original:
 * - Section headings are rendered HERE (before the first lesson of each
 *   section) instead of inside lesson-row.php, so collapsible sections can
 *   wrap their lesson rows in a real container. lesson-row.php receives an
 *   empty 'sections' array to suppress its internal heading rendering.
 * - Collapsible sections (modules 2-5 per Acelera_Template_Loader meta)
 *   wrap their rows in .acelera-section-body > .acelera-section-body-inner
 *   (grid-template-rows collapse technique, see acelera-accordion.css).
 *   Non-collapsible sections (Bienvenida, Módulo 1, unmapped) keep LD's
 *   stock sibling DOM untouched.
 * - Fase 4: lesson rows are reordered per the current user's personalized
 *   module order (Acelera_Renaming::reorder_lesson_rows). Rule: Bienvenida
 *   always first, unmapped lessons next (original relative order), then
 *   the modules in the user's order. No active submission → natural order.
 *   Section headings stay attached to each module's first lesson, and the
 *   accordion keys by section ID/module (order-independent).
 *
 * @since 1.0.0
 *
 * @package Formulario_Acelera_Ai_Daniel\Templates\LD30
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $lessons ) ) :

	// ACELERA (Fase 4): render the sections in the user's personalized
	// module order. No-op while the user has no active submission.
	if ( class_exists( 'Acelera_Renaming' ) && is_user_logged_in() ) {
		$acelera_user_order = Acelera_Renaming::get_user_order( get_current_user_id() );

		if ( array() !== $acelera_user_order ) {
			$lessons = Acelera_Renaming::reorder_lesson_rows( $lessons, $acelera_user_order );
		}
	}

	$sections = learndash_30_get_course_sections( $course_id );
	$i        = 0;

	// ACELERA: whether a collapsible section container is currently open.
	$acelera_group_open = false;

	foreach ( $lessons as $course_lesson ) :

		$all_topics = learndash_topic_dots( $course_lesson['post']->ID, false, 'array' );

		/** This filter is documented in themes/ld30/includes/helpers.php */
		$topic_pager_args = apply_filters(
			'ld30_ajax_topic_pager_args',
			array(
				'course_id' => $course_id,
				'lesson_id' => $course_lesson['post']->ID,
			)
		);

		$lesson_topics = learndash_process_lesson_topics_pager( $all_topics, $topic_pager_args );

		// ACELERA: a new section starts on this lesson — render its heading
		// here and, when collapsible, open a wrapper around its rows.
		if ( isset( $sections[ $course_lesson['post']->ID ] ) ) :

			if ( $acelera_group_open ) :
				echo '</div></div> <!--/.acelera-section-body-->';
				$acelera_group_open = false;
			endif;

			$acelera_section = $sections[ $course_lesson['post']->ID ];
			$acelera_meta    = Acelera_Template_Loader::section_accordion_meta( $acelera_section, $course_id );

			learndash_get_template_part(
				'widgets/navigation/section.php',
				array(
					'section'      => $acelera_section,
					'course_id'    => $course_id,
					'user_id'      => $user_id,
					'acelera_meta' => $acelera_meta,
				),
				true
			);

			if ( ! empty( $acelera_meta['collapsible'] ) ) :
				printf(
					'<div id="%1$s" class="acelera-section-body%2$s" data-acelera-section="%3$s"><div class="acelera-section-body-inner">',
					esc_attr( $acelera_meta['container_id'] ),
					! empty( $acelera_meta['open'] ) ? ' acelera-open' : '',
					esc_attr( $acelera_section->ID )
				);
				$acelera_group_open = true;
			endif;

		endif;

		learndash_get_template_part(
			'widgets/navigation/lesson-row.php',
			array(
				'count'           => $i,
				'sections'        => array(), // ACELERA: headings already rendered above.
				'lesson'          => $course_lesson,
				'course_id'       => $course_id,
				'user_id'         => $user_id,
				'lesson_topics'   => $lesson_topics,
				'widget_instance' => $widget_instance,
				'has_access'      => $has_access,
			),
			true
		);

		$i++;
	endforeach;

	// ACELERA: close the last collapsible section container.
	if ( $acelera_group_open ) :
		echo '</div></div> <!--/.acelera-section-body-->';
		$acelera_group_open = false;
	endif;

endif;

/**
 * Should we show quizzes in the course navigation based on pagination?
 */
$show_course_quizzes = true;

if ( isset( $course_pager_results['pager'] ) && ! empty( $course_pager_results['pager'] ) ) {
	$show_course_quizzes = ( absint( $course_pager_results['pager']['paged'] ) === absint( $course_pager_results['pager']['total_pages'] ) ? true : false );
}

if ( isset( $widget_instance['show_course_quizzes'] ) && true !== (bool) $widget_instance['show_course_quizzes'] ) {
	$show_course_quizzes = false;
}

if ( true == $show_course_quizzes ) :
	$course_quiz_list = learndash_get_course_quiz_list( $course_id, get_current_user_id() );

	if ( ! empty( $course_quiz_list ) ) :
		foreach ( $course_quiz_list as $quiz ) :

			learndash_get_template_part(
				'widgets/navigation/quiz-row.php',
				array(
					'quiz'      => $quiz,
					'user_id'   => $user_id,
					'course_id' => $course_id,
					'context'   => 'course',
				),
				true
			);

		endforeach;
	endif;

endif;

if ( isset( $course_pager_results['pager'] ) ) :
	learndash_get_template_part(
		'modules/pagination.php',
		array(
			'pager_results' => $course_pager_results['pager'],
			'pager_context' => 'course_lessons',
			'course_id'     => $course_id,
		),
		true
	);
endif;
