( function() {
	'use strict';

	/**
	 * Loader for the [acelera_feedback module="mX"] containers (Fase 6.2).
	 *
	 * For every .acelera-feedback container it calls
	 * GET acelera/v1/feedback/{module} with the wp_rest nonce:
	 * - { html }    → inject (server-side wp_kses_post sanitized) + reveal.
	 * - { hide }    → remove the container entirely (silent fallback).
	 * - { pending } → another request holds the generation lock; retry
	 *                 ONCE after retryDelayMs, then give up and hide.
	 *
	 * Depends on the `aceleraFeedback` object localized by
	 * Acelera_Module_Feedback::enqueue_assets()
	 * ({ restUrl, nonce, retryDelayMs, strings }).
	 */

	if ( 'undefined' === typeof window.aceleraFeedback ) {
		return;
	}

	var cfg = window.aceleraFeedback;

	function removeContainer( container ) {
		if ( container && container.parentNode ) {
			container.parentNode.removeChild( container );
		}
	}

	function reveal( container, html ) {
		var content = container.querySelector( '.acelera-feedback-content' );

		if ( ! content ) {
			removeContainer( container );
			return;
		}

		// Server-sanitized HTML (wp_kses_post before caching).
		content.innerHTML = html;
		content.hidden = false;

		container.classList.add( 'acelera-feedback--ready' );

		var skeleton = container.querySelector( '.acelera-feedback-skeleton' );
		var loading = container.querySelector( '.acelera-feedback-loading' );

		if ( skeleton ) {
			skeleton.parentNode.removeChild( skeleton );
		}

		if ( loading ) {
			loading.parentNode.removeChild( loading );
		}
	}

	function load( container, isRetry ) {
		var module = container.getAttribute( 'data-module' );

		if ( ! module ) {
			removeContainer( container );
			return;
		}

		window.fetch( cfg.restUrl + 'feedback/' + encodeURIComponent( module ), {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': cfg.nonce
			}
		} )
			.then( function( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}

				return response.json();
			} )
			.then( function( data ) {
				if ( data && data.html ) {
					reveal( container, data.html );
				} else if ( data && data.pending && ! isRetry ) {
					// Single retry: by then the concurrent request should
					// have populated the cache (lock TTL is 60s).
					window.setTimeout( function() {
						load( container, true );
					}, cfg.retryDelayMs || 5000 );
				} else {
					removeContainer( container );
				}
			} )
			.catch( function() {
				// Network/parse errors: the lesson must never break.
				removeContainer( container );
			} );
	}

	function init() {
		var containers = document.querySelectorAll( '.acelera-feedback[data-module]' );

		Array.prototype.forEach.call( containers, function( container ) {
			load( container, false );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
