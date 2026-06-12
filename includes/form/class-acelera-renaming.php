<?php

/**
 * Per-user module renumbering and sidebar reordering (Fase 4.6).
 *
 * @link       https://danielamado.com
 * @since      1.0.0
 *
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes/form
 */

/**
 * Renumbers and reorders the ACELERA modules per user.
 *
 * Persistence: two user_meta keys written when a submission completes —
 * `acelera_module_order` (array of module keys, e.g. ['m5','m2',...]) and
 * `acelera_module_labels` (map module key → renumbered label, e.g.
 * 'm5' => 'Módulo 1. Inversión / Patrimonio'). The thematic name is kept;
 * only the number changes.
 *
 * Decision (Part B): {@see Acelera_Renaming::save_user_order()} is called
 * DIRECTLY from the /submit REST flow (Acelera_Rest) instead of listening
 * to the `acelera_form_completed` action. The action stays a clean
 * extension point for Fase 5 (Clientify) while the renumbering — a hard
 * requirement of the submit response — runs deterministically before the
 * response payload is built.
 *
 * Display surfaces:
 * - `acelera_section_title` filter (Fase 3 section.php override) →
 *   replaces sidebar/course section headings with the renumbered label.
 * - `the_title` filter → defensively renumbers lesson titles that start
 *   with a "Módulo X" prefix, only for lessons mapped in
 *   Acelera_Course_Map (implicit ACELERA-course guard, cheap early bail).
 * - {@see Acelera_Renaming::reorder_lesson_rows()} → consumed by the
 *   Fase 3 rows.php override to render sidebar sections in the user's
 *   personalized order.
 *
 * Reorder rule (documented per plan 4.6): Bienvenida ALWAYS first, then
 * any lesson NOT mapped to a module (unknown/unmapped sections) keeping
 * their original relative order, then the five modules in the user's
 * `module_order`. Modules missing from a corrupt order are appended in
 * natural course order. Without an active order the natural order is
 * returned untouched. The accordion keys by section/module identity (not
 * by position), so it stays order-independent.
 *
 * @since      1.0.0
 * @package    Formulario_Acelara_Ai_Daniel
 * @subpackage Formulario_Acelara_Ai_Daniel/includes/form
 * @author     Daniel Amado <daniel.amadove@gmail.com>
 */
class Acelera_Renaming {

	/**
	 * User meta key holding the personalized module order (array).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_ORDER = 'acelera_module_order';

	/**
	 * User meta key holding the renumbered labels map (module key → label).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_LABELS = 'acelera_module_labels';

	/* ---------------------------------------------------------------------
	 * Persistence
	 * ------------------------------------------------------------------- */

	/**
	 * Persist the personalized order + renumbered labels for a user.
	 *
	 * Called directly from the /submit REST flow (see class docblock for
	 * the rationale).
	 *
	 * @since  1.0.0
	 * @param  int          $user_id      User ID.
	 * @param  string|array $module_order CSV ('m2,m1,...') or array of keys.
	 * @return void
	 */
	public static function save_user_order( $user_id, $module_order ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return;
		}

		$order = self::sanitize_order( $module_order );

		update_user_meta( $user_id, self::META_ORDER, $order );
		update_user_meta( $user_id, self::META_LABELS, self::build_labels( $order ) );
	}

	/**
	 * Delete the renumbering meta for a user (reset flow).
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return void
	 */
	public static function clear_user( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return;
		}

		delete_user_meta( $user_id, self::META_ORDER );
		delete_user_meta( $user_id, self::META_LABELS );
	}

	/**
	 * Personalized module order for a user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return string[] Module keys in personalized order, or array() when
	 *                  the user has no active renumbering.
	 */
	public static function get_user_order( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return array();
		}

		$order = get_user_meta( $user_id, self::META_ORDER, true );

		if ( ! is_array( $order ) || array() === $order ) {
			return array();
		}

		return self::sanitize_order( $order );
	}

	/**
	 * Renumbered labels map for a user.
	 *
	 * Falls back to rebuilding from the stored order when the labels meta
	 * is missing (defensive self-heal).
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return array<string, string> module key → 'Módulo {n}. {label}', or
	 *                               array() when no renumbering applies.
	 */
	public static function get_user_labels( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return array();
		}

		$labels = get_user_meta( $user_id, self::META_LABELS, true );

		if ( is_array( $labels ) && array() !== $labels ) {
			return $labels;
		}

		$order = self::get_user_order( $user_id );

		if ( array() === $order ) {
			return array();
		}

		return self::build_labels( $order );
	}

	/* ---------------------------------------------------------------------
	 * Pure helpers (CLI-testable, no WP functions)
	 * ------------------------------------------------------------------- */

	/**
	 * Normalize a module order into a complete, valid list of keys.
	 *
	 * Accepts a CSV string or an array. Unknown keys and duplicates are
	 * dropped; modules missing from the input are appended in natural
	 * course order (m1 < m2 < … < m5) so the result always covers the
	 * five modules.
	 *
	 * @since  1.0.0
	 * @param  string|array $module_order CSV or array of module keys.
	 * @return string[] Sanitized order covering every module exactly once.
	 */
	public static function sanitize_order( $module_order ) {
		$valid = array_keys( Acelera_Course_Map::modules() );

		if ( is_string( $module_order ) ) {
			$module_order = explode( ',', $module_order );
		}

		if ( ! is_array( $module_order ) ) {
			$module_order = array();
		}

		$order = array();

		foreach ( $module_order as $key ) {
			$key = trim( (string) $key );

			if ( in_array( $key, $valid, true ) && ! in_array( $key, $order, true ) ) {
				$order[] = $key;
			}
		}

		// Defensive: append any missing module in natural order.
		foreach ( $valid as $key ) {
			if ( ! in_array( $key, $order, true ) ) {
				$order[] = $key;
			}
		}

		return $order;
	}

	/**
	 * Build the renumbered labels map from an ordered list of module keys.
	 *
	 * Position 1 in the order becomes "Módulo 1. {thematic label}", etc.
	 *
	 * @since  1.0.0
	 * @param  string[] $order Module keys in personalized order.
	 * @return array<string, string> module key → renumbered label.
	 */
	public static function build_labels( array $order ) {
		$map    = Acelera_Course_Map::modules();
		$labels = array();
		$number = 1;

		foreach ( $order as $key ) {
			if ( ! isset( $map[ $key ] ) ) {
				continue;
			}

			$labels[ $key ] = sprintf( 'Módulo %d. %s', $number, $map[ $key ]['label'] );
			$number++;
		}

		return $labels;
	}

	/**
	 * Reorder the sidebar lesson rows per the user's module order.
	 *
	 * Consumed by the Fase 3 rows.php override BEFORE its render loop.
	 * Buckets every lesson by Acelera_Course_Map::module_for_lesson() and
	 * rebuilds the list as: welcome lessons (original order) → unmapped
	 * lessons (original order) → module buckets in the given order. Inside
	 * each bucket the original relative order is preserved, so each
	 * section heading (attached to the module's first lesson) still
	 * renders first within its group.
	 *
	 * @since  1.0.0
	 * @param  array    $lessons LD lesson rows (each ['post' => WP_Post, ...]).
	 * @param  string[] $order   Personalized module order; array() = no-op.
	 * @return array Reordered lesson rows.
	 */
	public static function reorder_lesson_rows( array $lessons, array $order ) {
		if ( array() === $order ) {
			return $lessons;
		}

		$buckets = array(
			'welcome'   => array(),
			'_unmapped' => array(),
		);

		foreach ( $order as $key ) {
			$buckets[ $key ] = array();
		}

		foreach ( $lessons as $lesson ) {
			$lesson_id = self::resolve_lesson_id( $lesson );
			$module    = $lesson_id ? Acelera_Course_Map::module_for_lesson( $lesson_id ) : null;

			if ( 'welcome' === $module ) {
				$buckets['welcome'][] = $lesson;
			} elseif ( null !== $module && isset( $buckets[ $module ] ) ) {
				$buckets[ $module ][] = $lesson;
			} else {
				$buckets['_unmapped'][] = $lesson;
			}
		}

		$reordered = array_merge( $buckets['welcome'], $buckets['_unmapped'] );

		foreach ( $order as $key ) {
			$reordered = array_merge( $reordered, $buckets[ $key ] );
		}

		return $reordered;
	}

	/**
	 * Extract the lesson post ID from an LD lesson row shape.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  mixed $lesson LD lesson array ({post: WP_Post}), object or ID.
	 * @return int Lesson post ID, or 0 when unresolvable.
	 */
	private static function resolve_lesson_id( $lesson ) {
		if ( is_array( $lesson ) && isset( $lesson['post'] ) && is_object( $lesson['post'] ) && isset( $lesson['post']->ID ) ) {
			return (int) $lesson['post']->ID;
		}

		if ( is_object( $lesson ) && isset( $lesson->ID ) ) {
			return (int) $lesson->ID;
		}

		if ( is_numeric( $lesson ) ) {
			return (int) $lesson;
		}

		return 0;
	}

	/**
	 * Build the renumbered module items for a given order.
	 *
	 * Shared by the shortcode result screen and the /result + /submit REST
	 * payloads. Each item: key, number, label (renumbered), url (permalink
	 * of the module's first lesson).
	 *
	 * @since  1.0.0
	 * @param  string|array $module_order CSV or array of module keys.
	 * @return array<int, array{key:string, number:int, label:string, url:string}>
	 */
	public static function module_items( $module_order ) {
		$map   = Acelera_Course_Map::modules();
		$order = self::sanitize_order( $module_order );

		$items  = array();
		$number = 1;

		foreach ( $order as $key ) {
			$url = get_permalink( $map[ $key ]['first_lesson'] );

			$items[] = array(
				'key'    => $key,
				'number' => $number,
				'label'  => sprintf( 'Módulo %d. %s', $number, $map[ $key ]['label'] ),
				'url'    => $url ? $url : '',
			);

			$number++;
		}

		return $items;
	}

	/* ---------------------------------------------------------------------
	 * Display filters (registered through the plugin loader)
	 * ------------------------------------------------------------------- */

	/**
	 * `acelera_section_title` filter — renumber sidebar section headings.
	 *
	 * Fired by the Fase 3 section.php override. The section's module is
	 * resolved from its FIRST lesson via module_for_lesson() (never from
	 * the title), so renaming/reordering cannot break the identity.
	 *
	 * @since  1.0.0
	 * @param  string $title   Raw section title.
	 * @param  object $section Section object {order, ID, post_title, type, steps}.
	 * @return string Renumbered label for the current user, or the original.
	 */
	public function filter_section_title( $title, $section = null ) {
		if ( ! is_user_logged_in() || ! is_object( $section ) || empty( $section->steps ) || ! is_array( $section->steps ) ) {
			return $title;
		}

		$steps  = array_map( 'intval', $section->steps );
		$module = Acelera_Course_Map::module_for_lesson( $steps[0] );

		if ( null === $module || 'welcome' === $module ) {
			return $title;
		}

		$labels = self::get_user_labels( get_current_user_id() );

		return isset( $labels[ $module ] ) ? $labels[ $module ] : $title;
	}

	/**
	 * `the_title` filter — defensively renumber "Módulo X" lesson titles.
	 *
	 * Cheap early bail: the regex runs first, then the lesson must belong
	 * to the ACELERA course map (module_for_lesson() acts as the implicit
	 * course-16242 guard — IDs are unique to that course) and the user
	 * must have an active personalized order.
	 *
	 * NOTE: pending real-site validation (plan 4.6) — whether lesson
	 * titles actually carry a "Módulo X" prefix on production. If they
	 * never do, this filter simply never matches and is a no-op.
	 *
	 * @since  1.0.0
	 * @param  string $title   Post title.
	 * @param  int    $post_id Post ID (second filter argument).
	 * @return string
	 */
	public function filter_the_title( $title, $post_id = 0 ) {
		if ( ! is_string( $title ) || ! preg_match( '/^M[óo]dulo\s+\d+/u', $title ) ) {
			return $title;
		}

		if ( ! is_user_logged_in() || ! $post_id ) {
			return $title;
		}

		$module = Acelera_Course_Map::module_for_lesson( (int) $post_id );

		if ( null === $module || 'welcome' === $module ) {
			return $title;
		}

		$order    = self::get_user_order( get_current_user_id() );
		$position = array_search( $module, $order, true );

		if ( array() === $order || false === $position ) {
			return $title;
		}

		return preg_replace( '/^M[óo]dulo\s+\d+/u', sprintf( 'Módulo %d', (int) $position + 1 ), $title, 1 );
	}

}
