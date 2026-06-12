(function( $ ) {
	'use strict';

	/**
	 * Admin behavior for the Curso Acelera pages (Fase 5.3).
	 *
	 * - "Probar conexión" button on the Clientify settings tab.
	 * - "Reenviar" buttons on the Sumisiones list.
	 *
	 * Depends on the `aceleraAdmin` object localized by
	 * Formulario_Acelara_Ai_Daniel_Admin::enqueue_scripts()
	 * ({ ajaxUrl, nonce, i18n }).
	 */

	$( function() {

		if ( 'undefined' === typeof window.aceleraAdmin ) {
			return;
		}

		var settings = window.aceleraAdmin;

		function renderResult( $target, ok, message ) {
			$target
				.text( message )
				.css( 'color', ok ? '#008a20' : '#d63638' );
		}

		// --- Clientify: test connection -----------------------------------.
		$( document ).on( 'click', '#acelera-clientify-test', function() {
			var $button = $( this );
			var $result = $( '#acelera-clientify-test-result' );

			$button.prop( 'disabled', true );
			renderResult( $result, true, settings.i18n.testing );

			$.post( settings.ajaxUrl, {
				action: 'acelera_clientify_test',
				nonce: settings.nonce
			} )
				.done( function( response ) {
					var ok = !! ( response && response.success );
					var message = ( response && response.data && response.data.message )
						? response.data.message
						: settings.i18n.genericKo;

					renderResult( $result, ok, message );
				} )
				.fail( function() {
					renderResult( $result, false, settings.i18n.genericKo );
				} )
				.always( function() {
					$button.prop( 'disabled', false );
				} );
		} );

		// --- Clientify: resend a failed/skipped submission ------------------.
		$( document ).on( 'click', '.acelera-clientify-resend', function() {
			var $button = $( this );
			var $result = $button.siblings( '.acelera-resend-result' );
			var submissionId = $button.data( 'submission-id' );

			$button.prop( 'disabled', true );
			renderResult( $result, true, settings.i18n.resending );

			$.post( settings.ajaxUrl, {
				action: 'acelera_clientify_resend',
				nonce: settings.nonce,
				submission_id: submissionId
			} )
				.done( function( response ) {
					var ok = !! ( response && response.success );
					var message = ( response && response.data && response.data.message )
						? response.data.message
						: settings.i18n.genericKo;

					renderResult( $result, ok, message );

					if ( ! ok ) {
						$button.prop( 'disabled', false );
					}
				} )
				.fail( function() {
					renderResult( $result, false, settings.i18n.genericKo );
					$button.prop( 'disabled', false );
				} );
		} );

	} );

})( jQuery );
