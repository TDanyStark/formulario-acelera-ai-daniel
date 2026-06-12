<?php

/**
 * Rule-based scoring engine for the ACELERA diagnostic form.
 *
 * Pure PHP (no WordPress functions) so it can be unit-tested from the CLI.
 * Implements the spec annex ("Lógica del motor") + plan section 4.5.
 *
 * @link       https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes/form
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Scores the 5 ACELERA routes (0–100) and produces the personalized
 * module order plus special-rule flags.
 *
 * Route → module mapping (matches Acelera_Course_Map):
 * migratoria=m1, empresa=m2, profesional=m3, softlanding=m4, inversion=m5.
 *
 * Special rules:
 * - `bloqueador_migratorio`: P2.4 = 'no' AND P2.5 = 'operar_yo_mismo'
 *   → M1 goes first regardless of scores.
 * - `revision_asesor`: 'no_seguro' on P2.4 or P2.5.
 * - `diagnostico_abierto`: P1.1 includes 'no_claro' → P1.1 direct weights
 *   are NOT applied (order by indirect signals only).
 * - Ties broken by natural course order m1 < m2 < m3 < m4 < m5.
 *
 * @since      1.0.0
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes/form
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Acelera_Scoring {

	/**
	 * Single tunable weights table (plan 4.5).
	 *
	 * Adjust point values here — the signal logic that decides WHEN each
	 * weight applies lives in score() with a comment per signal.
	 *
	 * @since 1.0.0
	 * @var   array<string, array<string, int>>
	 */
	const WEIGHTS = array(
		'migratoria'  => array(
			'objetivo_p1_1' => 40, // P1.1 incluye 'migratorio'.
			'sin_estatus'   => 20, // P2.2 = 'sin_estatus'.
			'bloqueador'    => 30, // P2.4 = 'no' Y P2.5 = 'operar_yo_mismo'.
			'urgencia_alta' => 10, // P1.2 = 'ya_en_eeuu' o 'en_3_6_meses'.
			'sin_abogado'   => 10, // P2.6 = 'no' o 'tuve_ya_no'.
		),
		'empresa'     => array(
			'objetivo_p1_1'  => 40, // P1.1 incluye 'negocio'.
			'situacion_p5_2' => 25, // P5.2 = 'dueno_negocio' o 'idea_negocio'.
			'etapa_avanzada' => 10, // P6B.1 = 'vendo_constante' o 'replicar_eeuu'.
			'facturacion'    => 10, // P6B.3 = '1k_5k', '5k_20k' o 'mas_20k'.
			'dependencia'    => 5,  // P6B.5 = 'funciona_sin_mi' (madurez operativa).
		),
		'profesional' => array(
			'objetivo_p1_1'    => 40, // P1.1 incluye 'empleo'.
			'situacion_p5_2'   => 25, // P5.2 = 'empleado' o 'independiente'.
			'ingles_bajo'      => 10, // P4.6 = 'basico' (señal de necesidad).
			'networking_debil' => 10, // P6A.2 = 'algo' o 'no_se_empezar'.
			'linkedin_debil'   => 10, // P6A.4 = 'si_desactualizado' o 'no_tengo'.
			'cv_subido'        => 5,  // cv_upload presente (URL no vacía).
		),
		'softlanding' => array(
			'objetivo_p1_1'       => 40, // P1.1 incluye 'familia'.
			'con_hijos'           => 25, // P3.1 = 'pareja_hijos' o 'hijos_sin_pareja'.
			'con_pareja'          => 10, // P3.1 = 'con_pareja' (solo si no aplicó con_hijos).
			'colegio_no_decidido' => 10, // P3.3 incluye 'no_decidido'.
			'sin_ciudad'          => 10, // P4.1 = 'necesito_ayuda'.
			'sin_red'             => 10, // P4.4 = 'sin_red'.
		),
		'inversion'   => array(
			'objetivo_p1_1'       => 40, // P1.1 incluye 'invertir'.
			'vive_inversiones'    => 25, // P5.2 = 'inversiones'.
			'capital_alto'        => 20, // P5.3 = '150k_500k' o 'mas_500k'.
			'horizonte_inmediato' => 10, // P6C.3 = 'inmediato'.
		),
	);

	/**
	 * Route key → module key, in natural course order.
	 *
	 * @since 1.0.0
	 * @var   array<string, string>
	 */
	const ROUTE_TO_MODULE = array(
		'migratoria'  => 'm1',
		'empresa'     => 'm2',
		'profesional' => 'm3',
		'softlanding' => 'm4',
		'inversion'   => 'm5',
	);

	/**
	 * Score the 5 routes and build the personalized module order.
	 *
	 * @since  1.0.0
	 * @param  array $answers Sanitized answers keyed by question ID
	 *                        (option value slugs from Acelera_Questions).
	 * @return array {
	 *     @type array  $scores       0–100 per route, keys: migratoria,
	 *                                empresa, profesional, softlanding, inversion.
	 *     @type string $module_order CSV, e.g. 'm2,m1,m4,m3,m5'.
	 *     @type array  $flags        bloqueador_migratorio, revision_asesor,
	 *                                diagnostico_abierto (bool each).
	 * }
	 */
	public static function score( array $answers ): array {
		$w = self::WEIGHTS;

		// ---- Special-rule flags -------------------------------------------------
		$diagnostico_abierto  = self::answer_contains( $answers, 'p1_1', 'no_claro' );
		$bloqueador           = self::answer_is( $answers, 'p2_4', array( 'no' ) )
			&& self::answer_is( $answers, 'p2_5', array( 'operar_yo_mismo' ) );
		$revision_asesor      = self::answer_is( $answers, 'p2_4', array( 'no_seguro' ) )
			|| self::answer_is( $answers, 'p2_5', array( 'no_seguro' ) );

		// With "no lo tengo claro" the direct P1.1 weights are skipped for
		// EVERY route: order comes from indirect signals only.
		$apply_p1_1 = ! $diagnostico_abierto;

		$scores = array(
			'migratoria'  => 0,
			'empresa'     => 0,
			'profesional' => 0,
			'softlanding' => 0,
			'inversion'   => 0,
		);

		// ---- Migratoria (M1) ----------------------------------------------------
		if ( $apply_p1_1 && self::answer_contains( $answers, 'p1_1', 'migratorio' ) ) {
			$scores['migratoria'] += $w['migratoria']['objetivo_p1_1'];
		}
		if ( self::answer_is( $answers, 'p2_2', array( 'sin_estatus' ) ) ) {
			$scores['migratoria'] += $w['migratoria']['sin_estatus'];
		}
		if ( $bloqueador ) {
			$scores['migratoria'] += $w['migratoria']['bloqueador'];
		}
		if ( self::answer_is( $answers, 'p1_2', array( 'ya_en_eeuu', 'en_3_6_meses' ) ) ) {
			$scores['migratoria'] += $w['migratoria']['urgencia_alta'];
		}
		if ( self::answer_is( $answers, 'p2_6', array( 'no', 'tuve_ya_no' ) ) ) {
			$scores['migratoria'] += $w['migratoria']['sin_abogado'];
		}

		// ---- Empresa (M2) -------------------------------------------------------
		if ( $apply_p1_1 && self::answer_contains( $answers, 'p1_1', 'negocio' ) ) {
			$scores['empresa'] += $w['empresa']['objetivo_p1_1'];
		}
		if ( self::answer_is( $answers, 'p5_2', array( 'dueno_negocio', 'idea_negocio' ) ) ) {
			$scores['empresa'] += $w['empresa']['situacion_p5_2'];
		}
		if ( self::answer_is( $answers, 'p6b_1', array( 'vendo_constante', 'replicar_eeuu' ) ) ) {
			$scores['empresa'] += $w['empresa']['etapa_avanzada'];
		}
		if ( self::answer_is( $answers, 'p6b_3', array( '1k_5k', '5k_20k', 'mas_20k' ) ) ) {
			$scores['empresa'] += $w['empresa']['facturacion'];
		}
		if ( self::answer_is( $answers, 'p6b_5', array( 'funciona_sin_mi' ) ) ) {
			$scores['empresa'] += $w['empresa']['dependencia'];
		}

		// ---- Profesional (M3) ---------------------------------------------------
		if ( $apply_p1_1 && self::answer_contains( $answers, 'p1_1', 'empleo' ) ) {
			$scores['profesional'] += $w['profesional']['objetivo_p1_1'];
		}
		if ( self::answer_is( $answers, 'p5_2', array( 'empleado', 'independiente' ) ) ) {
			$scores['profesional'] += $w['profesional']['situacion_p5_2'];
		}
		if ( self::answer_is( $answers, 'p4_6', array( 'basico' ) ) ) {
			$scores['profesional'] += $w['profesional']['ingles_bajo'];
		}
		if ( self::answer_is( $answers, 'p6a_2', array( 'algo', 'no_se_empezar' ) ) ) {
			$scores['profesional'] += $w['profesional']['networking_debil'];
		}
		if ( self::answer_is( $answers, 'p6a_4', array( 'si_desactualizado', 'no_tengo' ) ) ) {
			$scores['profesional'] += $w['profesional']['linkedin_debil'];
		}
		if ( ! empty( $answers['cv_upload'] ) && is_string( $answers['cv_upload'] ) ) {
			$scores['profesional'] += $w['profesional']['cv_subido'];
		}

		// ---- Softlanding (M4) ---------------------------------------------------
		if ( $apply_p1_1 && self::answer_contains( $answers, 'p1_1', 'familia' ) ) {
			$scores['softlanding'] += $w['softlanding']['objetivo_p1_1'];
		}
		if ( self::answer_is( $answers, 'p3_1', array( 'pareja_hijos', 'hijos_sin_pareja' ) ) ) {
			$scores['softlanding'] += $w['softlanding']['con_hijos'];
		} elseif ( self::answer_is( $answers, 'p3_1', array( 'con_pareja' ) ) ) {
			$scores['softlanding'] += $w['softlanding']['con_pareja'];
		}
		if ( self::answer_contains( $answers, 'p3_3', 'no_decidido' ) ) {
			$scores['softlanding'] += $w['softlanding']['colegio_no_decidido'];
		}
		if ( self::answer_is( $answers, 'p4_1', array( 'necesito_ayuda' ) ) ) {
			$scores['softlanding'] += $w['softlanding']['sin_ciudad'];
		}
		if ( self::answer_is( $answers, 'p4_4', array( 'sin_red' ) ) ) {
			$scores['softlanding'] += $w['softlanding']['sin_red'];
		}

		// ---- Inversión (M5) -----------------------------------------------------
		if ( $apply_p1_1 && self::answer_contains( $answers, 'p1_1', 'invertir' ) ) {
			$scores['inversion'] += $w['inversion']['objetivo_p1_1'];
		}
		if ( self::answer_is( $answers, 'p5_2', array( 'inversiones' ) ) ) {
			$scores['inversion'] += $w['inversion']['vive_inversiones'];
		}
		if ( self::answer_is( $answers, 'p5_3', array( '150k_500k', 'mas_500k' ) ) ) {
			$scores['inversion'] += $w['inversion']['capital_alto'];
		}
		if ( self::answer_is( $answers, 'p6c_3', array( 'inmediato' ) ) ) {
			$scores['inversion'] += $w['inversion']['horizonte_inmediato'];
		}

		// Cap every route at 100.
		foreach ( $scores as $route => $points ) {
			$scores[ $route ] = (int) min( 100, max( 0, $points ) );
		}

		// ---- Module order -------------------------------------------------------
		$module_scores = array();

		foreach ( self::ROUTE_TO_MODULE as $route => $module ) {
			$module_scores[ $module ] = $scores[ $route ];
		}

		$modules = array_keys( $module_scores );

		// Sort by score desc; ties broken by natural order m1 < m2 < … < m5
		// (module keys are 'm1'..'m5' so strcmp is a safe natural tiebreak).
		usort(
			$modules,
			function ( $a, $b ) use ( $module_scores ) {
				if ( $module_scores[ $a ] === $module_scores[ $b ] ) {
					return strcmp( $a, $b );
				}

				return ( $module_scores[ $a ] > $module_scores[ $b ] ) ? -1 : 1;
			}
		);

		// Bloqueador migratorio: M1 goes first regardless of scores.
		if ( $bloqueador ) {
			$modules = array_values( array_diff( $modules, array( 'm1' ) ) );
			array_unshift( $modules, 'm1' );
		}

		return array(
			'scores'       => $scores,
			'module_order' => implode( ',', $modules ),
			'flags'        => array(
				'bloqueador_migratorio' => $bloqueador,
				'revision_asesor'       => $revision_asesor,
				'diagnostico_abierto'   => $diagnostico_abierto,
			),
		);
	}

	/**
	 * Whether a scalar answer equals one of the given option values.
	 *
	 * @since  1.0.0
	 * @param  array    $answers Answers keyed by question ID.
	 * @param  string   $id      Question ID.
	 * @param  string[] $values  Accepted option values.
	 * @return bool
	 */
	private static function answer_is( array $answers, $id, array $values ): bool {
		if ( ! isset( $answers[ $id ] ) || is_array( $answers[ $id ] ) ) {
			return false;
		}

		return in_array( (string) $answers[ $id ], $values, true );
	}

	/**
	 * Whether a multi answer (array) contains the given option value.
	 *
	 * @since  1.0.0
	 * @param  array  $answers Answers keyed by question ID.
	 * @param  string $id      Question ID.
	 * @param  string $value   Option value to look for.
	 * @return bool
	 */
	private static function answer_contains( array $answers, $id, $value ): bool {
		if ( ! isset( $answers[ $id ] ) ) {
			return false;
		}

		$answer = is_array( $answers[ $id ] ) ? $answers[ $id ] : array( $answers[ $id ] );

		return in_array( $value, array_map( 'strval', $answer ), true );
	}

}
