(function( $ ) {
	'use strict';

	/**
	 * Admin behavior for the Curso Acelera pages (Fase 5.3 / 6.4).
	 *
	 * - "Probar conexión" button on the Clientify settings tab.
	 * - "Reenviar" buttons on the Sumisiones list.
	 * - "Regenerar feedback" support tool on the LLM settings tab.
	 *
	 * Depends on the `aceleraAdmin` object localized by
	 * Formulario_Acelera_Ai_Daniel_Admin::enqueue_scripts()
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

		// --- LLM: filter the model <select> by the selected provider -------.
		function filterLlmModels() {
			var provider = $( '#llm_provider' ).val();
			var $model = $( '.acelera-llm-model' );

			if ( ! provider || ! $model.length ) {
				return;
			}

			$model.find( 'optgroup' ).each( function() {
				var $group = $( this );
				var match = $group.data( 'provider' ) === provider;
				$group.prop( 'disabled', ! match ).toggle( match );
			} );

			$model.find( 'option[data-provider]' ).each( function() {
				var $option = $( this );
				var match = $option.data( 'provider' ) === provider;
				$option.prop( 'disabled', ! match ).toggle( match );
			} );

			// If the current selection belongs to another provider, fall back
			// to the empty "provider default" option.
			var $selected = $model.find( 'option:selected' );
			if ( $selected.length && $selected.data( 'provider' ) && $selected.data( 'provider' ) !== provider ) {
				$model.val( '' );
			}
		}

		$( document ).on( 'change', '#llm_provider', filterLlmModels );
		filterLlmModels();

		// --- LLM: clear a user's cached module feedback (Fase 6.4) ---------.
		$( document ).on( 'click', '#acelera-llm-regenerate', function() {
			var $button = $( this );
			var $result = $( '#acelera-llm-regenerate-result' );
			var user = $.trim( $( '#acelera-llm-user' ).val() || '' );
			var module = $( '#acelera-llm-module' ).val() || 'todos';

			$button.prop( 'disabled', true );
			renderResult( $result, true, settings.i18n.regenerating );

			$.post( settings.ajaxUrl, {
				action: 'acelera_llm_regenerate',
				nonce: settings.llmNonce,
				user: user,
				module: module
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

	} );

})( jQuery );
