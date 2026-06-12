<?php

/**
 * Course map for the ACELERA course.
 *
 * Single source of truth for every course/lesson ID used by the plugin.
 * No other file may hardcode LearnDash IDs.
 *
 * @link       https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes/config
 */

/**
 * Hardcoded map of the ACELERA course structure.
 *
 * IDs verified against orden_curso.md. Lesson IDs are NOT contiguous per
 * module (e.g. 16254 belongs to M2 but sits between M1 IDs), so every
 * module declares an explicit list of lesson IDs instead of ranges.
 *
 * @since      1.0.0
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes/config
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Acelera_Course_Map {

	/**
	 * LearnDash course ID for "1. PROGRAMA ACELERA".
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const COURSE_ID = 16242;

	/**
	 * Welcome section lessons (gate — never reordered).
	 *
	 * Onboarding, Comunidad, Acuerdo estudiantil, Formulario de Diagnóstico.
	 *
	 * @since 1.0.0
	 * @var   int[]
	 */
	const WELCOME_LESSONS = array( 16243, 16244, 16245, 16246 );

	/**
	 * Lesson that hosts the diagnostic form shortcode.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const FORM_LESSON_ID = 16246;

	/**
	 * Module definitions keyed 'm1'..'m5'.
	 *
	 * Each module contains:
	 * - label        (string) Human readable module name.
	 * - route        (string) Form route slug associated with the module.
	 * - first_lesson (int)    First lesson ID of the module.
	 * - last_lesson  (int)    Last lesson ID of the module.
	 * - lessons      (int[])  Explicit list of every lesson ID in the module.
	 *
	 * @since  1.0.0
	 * @return array<string, array{label: string, route: string, first_lesson: int, last_lesson: int, lessons: int[]}>
	 */
	public static function modules() {
		return array(
			'm1' => array(
				'label'        => 'Decisión de Emigrar',
				'route'        => 'migratoria',
				'first_lesson' => 16247,
				'last_lesson'  => 16258,
				'lessons'      => array( 16247, 16248, 16249, 16250, 16251, 16252, 16253, 16256, 16257, 16258 ),
			),
			'm2' => array(
				'label'        => 'Empresa y Emprendimiento',
				'route'        => 'empresa',
				'first_lesson' => 16254,
				'last_lesson'  => 16276,
				'lessons'      => array( 16254, 16259, 16260, 16261, 16262, 16263, 16264, 16265, 16266, 16267, 16268, 16269, 16270, 16271, 16272, 16273, 16274, 16275, 16276 ),
			),
			'm3' => array(
				'label'        => 'Profesional',
				'route'        => 'profesional',
				'first_lesson' => 16255,
				'last_lesson'  => 16282,
				'lessons'      => array( 16255, 16277, 16278, 16279, 16280, 16281, 16282 ),
			),
			'm4' => array(
				'label'        => 'Reubicación y Softlanding',
				'route'        => 'softlanding',
				'first_lesson' => 16283,
				'last_lesson'  => 16297,
				'lessons'      => array( 16283, 16284, 16285, 16286, 16287, 16288, 16289, 16290, 16291, 16292, 16293, 16294, 16295, 16296, 16297 ),
			),
			'm5' => array(
				'label'        => 'Inversión / Patrimonio',
				'route'        => 'inversion',
				'first_lesson' => 16298,
				'last_lesson'  => 16302,
				'lessons'      => array( 16298, 16299, 16300, 16301, 16302 ),
			),
		);
	}

	/**
	 * Resolve the module key a given lesson belongs to.
	 *
	 * @since  1.0.0
	 * @param  int $lesson_id Lesson post ID.
	 * @return string|null 'm1'..'m5' for module lessons, 'welcome' for the
	 *                     welcome section, or null when the lesson does not
	 *                     belong to the ACELERA course map.
	 */
	public static function module_for_lesson( $lesson_id ) {
		$lesson_id = (int) $lesson_id;

		if ( in_array( $lesson_id, self::WELCOME_LESSONS, true ) ) {
			return 'welcome';
		}

		foreach ( self::modules() as $key => $module ) {
			if ( in_array( $lesson_id, $module['lessons'], true ) ) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * Flat list of every lesson ID across modules M1–M5.
	 *
	 * Welcome lessons are NOT included.
	 *
	 * @since  1.0.0
	 * @return int[]
	 */
	public static function all_module_lessons() {
		$lessons = array();

		foreach ( self::modules() as $module ) {
			$lessons = array_merge( $lessons, $module['lessons'] );
		}

		return $lessons;
	}

}
