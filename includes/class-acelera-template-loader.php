<?php

/**
 * LearnDash template override loader for the ACELERA course.
 *
 * @link       https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes
 */

/**
 * Redirects LD30 navigation widget templates to plugin overrides (Fase 3).
 *
 * Hooked to the `learndash_template` filter (apply_filters in
 * sfwd-lms/includes/class-ld-lms.php:5071, signature
 * ($filepath, $name, $args, $echo, $return_file_path)). Every template
 * rendered through learndash_get_template_part() /
 * SFWD_LMS::get_template() passes through that filter, including the
 * focus-mode sidebar chain:
 * focus/sidebar.php:163 -> widgets/navigation/rows.php -> lesson-row.php
 * -> section.php.
 *
 * Overrides apply ONLY when the rendered context belongs to the ACELERA
 * course (Acelera_Course_Map::COURSE_ID); every other course keeps the
 * original templates (theme override or LD default).
 *
 * Also hosts the accordion grouping helpers consumed by the overridden
 * templates: which module a section belongs to (always resolved from the
 * section's FIRST lesson via Acelera_Course_Map::module_for_lesson(),
 * never from the title, so Fase 4 renumbering/reordering cannot break it),
 * whether it collapses, and whether it starts open.
 *
 * @since      1.0.0
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Acelera_Template_Loader {

	/**
	 * LD template names (as passed to the `learndash_template` filter)
	 * that this plugin overrides.
	 *
	 * Each name maps 1:1 to a file under public/templates/ld30/.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string[]
	 */
	private $overridable_templates = array(
		'widgets/navigation/rows.php',
		'widgets/navigation/section.php',
		'widgets/navigation/lesson-row.php',
	);

	/**
	 * Filter callback for `learndash_template`.
	 *
	 * Registered with priority 999 so it runs AFTER any theme
	 * `learndash/` folder override has been resolved into $filepath —
	 * inside the ACELERA course the plugin always wins (plan risk note).
	 *
	 * @since    1.0.0
	 * @param    string     $filepath         Template file path resolved by LD.
	 * @param    string     $name             Template name (e.g. 'widgets/navigation/rows.php').
	 * @param    array|null $args             Template data.
	 * @param    bool|null  $echo             Whether the template output is echoed.
	 * @param    bool       $return_file_path Whether only the file path is returned.
	 * @return   string Template file path.
	 */
	public function filter_template( $filepath, $name, $args = null, $echo = false, $return_file_path = false ) {

		if ( ! in_array( (string) $name, $this->overridable_templates, true ) ) {
			return $filepath;
		}

		if ( ! $this->is_acelera_course( $args ) ) {
			return $filepath;
		}

		$override = plugin_dir_path( dirname( __FILE__ ) ) . 'public/templates/ld30/' . $name;

		if ( file_exists( $override ) ) {
			return $override;
		}

		return $filepath;

	}

	/**
	 * Whether the template being rendered belongs to the ACELERA course.
	 *
	 * Prefers the `course_id` passed in the template $args (always present
	 * in the navigation widget chain, and the only reliable source during
	 * LD AJAX pagination); falls back to learndash_get_course_id() for the
	 * current request. If neither resolves to the ACELERA course, the
	 * original template is kept.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array|null $args Template data passed by LearnDash.
	 * @return   bool
	 */
	private function is_acelera_course( $args ) {

		$course_id = 0;

		if ( is_array( $args ) && isset( $args['course_id'] ) ) {
			$course_id = (int) $args['course_id'];
		}

		if ( 0 === $course_id && function_exists( 'learndash_get_course_id' ) ) {
			$course_id = (int) learndash_get_course_id();
		}

		return Acelera_Course_Map::COURSE_ID === $course_id;

	}

	/**
	 * Accordion metadata for one sidebar section.
	 *
	 * $section is an entry of learndash_30_get_course_sections()
	 * (sfwd-lms/themes/ld30/includes/helpers.php:2426): an object with
	 * {order, ID, post_title, type, steps[]}, keyed in that array by its
	 * first step (lesson) ID.
	 *
	 * Rules (plan 3.2):
	 * - Module resolved from the FIRST lesson of the section via
	 *   Acelera_Course_Map::module_for_lesson() — independent from both
	 *   the section title and the visual order (Fase 4 safe).
	 * - 'welcome' + 'm1' + unmapped sections: never collapsible, always
	 *   rendered expanded with LD's stock DOM.
	 * - 'm2'..'m5': collapsible, closed by default, auto-open when the
	 *   section contains the current lesson.
	 *
	 * @since    1.0.0
	 * @param    object $section   Section object from learndash_30_get_course_sections().
	 * @param    int    $course_id Course post ID.
	 * @return   array{module: string|null, collapsible: bool, open: bool, container_id: string}
	 */
	public static function section_accordion_meta( $section, $course_id = 0 ) {

		$meta = array(
			'module'       => null,
			'collapsible'  => false,
			'open'         => true,
			'container_id' => '',
		);

		if ( ! is_object( $section ) || empty( $section->ID ) ) {
			return $meta;
		}

		$meta['container_id'] = 'acelera-section-body-' . $section->ID;

		$steps = ( isset( $section->steps ) && is_array( $section->steps ) ) ? array_map( 'intval', $section->steps ) : array();

		if ( empty( $steps ) ) {
			return $meta;
		}

		$meta['module']      = Acelera_Course_Map::module_for_lesson( $steps[0] );
		$meta['collapsible'] = ! in_array( $meta['module'], array( null, 'welcome', 'm1' ), true );

		if ( ! $meta['collapsible'] ) {
			return $meta;
		}

		$current_lesson_id = self::current_lesson_id( $course_id );

		$meta['open'] = ( $current_lesson_id > 0 && in_array( $current_lesson_id, $steps, true ) );

		return $meta;

	}

	/**
	 * Resolve the lesson governing the current request.
	 *
	 * Mirrors the resolution used by LD30's own
	 * widgets/navigation/lesson-row.php: the lesson itself on lesson
	 * singulars, the parent lesson on topic/quiz singulars.
	 *
	 * @since    1.0.0
	 * @param    int $course_id Course post ID (used to resolve parent steps).
	 * @return   int Lesson post ID, or 0 when none applies.
	 */
	public static function current_lesson_id( $course_id = 0 ) {

		global $post;

		if ( ! isset( $post ) || ! is_object( $post ) || ! isset( $post->post_type ) ) {
			return 0;
		}

		if ( 'sfwd-lessons' === $post->post_type ) {
			return (int) $post->ID;
		}

		if ( in_array( $post->post_type, array( 'sfwd-topic', 'sfwd-quiz' ), true ) && function_exists( 'learndash_course_get_single_parent_step' ) ) {

			if ( 0 === (int) $course_id && function_exists( 'learndash_get_course_id' ) ) {
				$course_id = (int) learndash_get_course_id( $post->ID );
			}

			return (int) learndash_course_get_single_parent_step( $course_id, $post->ID, 'sfwd-lessons' );
		}

		return 0;

	}

	/**
	 * ID of the section that contains the current lesson.
	 *
	 * Used to localize `currentSectionId` for acelera-accordion.js so the
	 * auto-open of the current section wins over a stored 'closed' state.
	 *
	 * @since    1.0.0
	 * @param    int $course_id Course post ID.
	 * @return   string Section ID as string, or '' when none applies.
	 */
	public static function current_section_id( $course_id ) {

		if ( ! function_exists( 'learndash_30_get_course_sections' ) ) {
			return '';
		}

		$lesson_id = self::current_lesson_id( $course_id );

		if ( 0 === $lesson_id ) {
			return '';
		}

		$sections = learndash_30_get_course_sections( $course_id );

		if ( empty( $sections ) || ! is_array( $sections ) ) {
			return '';
		}

		foreach ( $sections as $section ) {

			if ( ! is_object( $section ) || ! isset( $section->steps ) || ! is_array( $section->steps ) ) {
				continue;
			}

			if ( in_array( $lesson_id, array_map( 'intval', $section->steps ), true ) ) {
				return (string) $section->ID;
			}
		}

		return '';

	}

}
