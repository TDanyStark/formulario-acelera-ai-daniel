/**
 * ACELERA — sidebar section accordion (Fase 3).
 *
 * Vanilla JS, no dependencies. Works on the buttons rendered by the
 * section.php template override (.acelera-section-toggle) and the
 * containers rendered by the rows.php override (.acelera-section-body).
 *
 * Behavior:
 * - Click (or Enter/Space, native to <button>) toggles the section.
 * - State persisted per section in sessionStorage so it survives
 *   navigation between lessons of the same browsing session.
 * - The section containing the current lesson always starts open,
 *   winning over a stored 'closed' state (server renders it open too;
 *   localized as aceleraAccordion.currentSectionId).
 *
 * Localized data (aceleraAccordion): { courseId, currentSectionId }.
 *
 * @since 1.0.0
 * @package Formulario_Acelara_Ai_Daniel
 */

( function() {
	'use strict';

	function init() {
		var settings         = window.aceleraAccordion || {};
		var courseId         = parseInt( settings.courseId, 10 ) || 0;
		var currentSectionId = String( settings.currentSectionId || '' );
		var toggles          = document.querySelectorAll( '.acelera-section-toggle' );

		if ( ! toggles.length ) {
			return;
		}

		function storageKey( sectionId ) {
			return 'acelera_accordion_' + courseId + '_' + sectionId;
		}

		function readState( sectionId ) {
			try {
				return window.sessionStorage.getItem( storageKey( sectionId ) );
			} catch ( e ) {
				return null;
			}
		}

		function writeState( sectionId, open ) {
			try {
				window.sessionStorage.setItem( storageKey( sectionId ), open ? 'open' : 'closed' );
			} catch ( e ) {
				// sessionStorage unavailable — state just will not persist.
			}
		}

		function applyState( toggle, body, open ) {
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

			var heading = toggle.closest ? toggle.closest( '.acelera-accordion-heading' ) : null;

			if ( heading ) {
				heading.classList.toggle( 'acelera-open', open );
			}

			if ( body ) {
				body.classList.toggle( 'acelera-open', open );
			}
		}

		Array.prototype.forEach.call( toggles, function( toggle ) {
			var sectionId = toggle.getAttribute( 'data-acelera-section' ) || '';
			var body      = document.getElementById( toggle.getAttribute( 'aria-controls' ) || '' );

			// Server-rendered default (open when it holds the current lesson).
			var open = 'true' === toggle.getAttribute( 'aria-expanded' );

			// Stored state from earlier navigation overrides the default…
			var stored = readState( sectionId );

			if ( 'open' === stored ) {
				open = true;
			} else if ( 'closed' === stored ) {
				open = false;
			}

			// …but the current lesson's section always starts open.
			if ( currentSectionId && sectionId === currentSectionId ) {
				open = true;
			}

			applyState( toggle, body, open );

			toggle.addEventListener( 'click', function() {
				var isOpen = 'true' === toggle.getAttribute( 'aria-expanded' );

				applyState( toggle, body, ! isOpen );
				writeState( sectionId, ! isOpen );
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
