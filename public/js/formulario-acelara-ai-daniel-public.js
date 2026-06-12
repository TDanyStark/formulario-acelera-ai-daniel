(function( $ ) {
	'use strict';

	/**
	 * Welcome gate (Fase 2) — front-end signaling.
	 *
	 * The server already adds the `acelera-locked` class to locked lesson
	 * rows (filters `learndash_lesson_row_class` and
	 * `learndash-nav-widget-lesson-class`) and enforces access with a
	 * template_redirect. This layer only improves UX: it neutralizes the
	 * row links (href="#"), adds the tooltip, and — as a fallback for any
	 * LD template that bypasses those filters — marks rows whose
	 * `ld-lesson-item-{ID}` class matches a locked lesson ID localized in
	 * `aceleraGate.lockedLessons`.
	 */
	$( function() {

		if ( typeof aceleraGate === 'undefined' || ! aceleraGate.lockedLessons || ! aceleraGate.lockedLessons.length ) {
			return;
		}

		// Fallback: tag rows by the ld-lesson-item-{ID} class LD prints in
		// course listings, in case a template missed the PHP filters.
		$.each( aceleraGate.lockedLessons, function( i, lessonId ) {
			$( '.ld-lesson-item-' + lessonId ).addClass( 'acelera-locked' );
		} );

		// Neutralize navigation for every locked row.
		$( '.acelera-locked' ).each( function() {
			$( this ).find( 'a' )
				.attr( 'href', '#' )
				.attr( 'title', aceleraGate.tooltip )
				.attr( 'aria-disabled', 'true' )
				.on( 'click', function( e ) {
					e.preventDefault();
					e.stopPropagation();
				} );
		} );

	} );

})( jQuery );
