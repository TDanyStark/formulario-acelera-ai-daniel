/**
 * ACELERA diagnostic form — step-by-step frontend (Fase 4.3).
 *
 * Vanilla JS, no jQuery, mobile-first. One question per screen with
 * Atrás / Omitir / Siguiente, live conditional engine (faithful port of
 * Acelera_Questions::evaluate_condition — ops: eq, in, gte, not_only,
 * any/all nesting), progress over currently-visible questions, autosave
 * in localStorage, CV upload to /upload-cv and final POST to /submit.
 *
 * Decision (documented): after a successful submit AND after a confirmed
 * reset the page is reloaded (location.reload()). The PHP shortcode then
 * renders the authoritative state (result screen / fresh form) and the
 * sidebar picks up the renumbered/reordered sections in the same paint.
 *
 * Expects the `aceleraForm` object localized by Acelera_Form_Shortcode:
 * { restUrl, nonce, userId, state, result, questions, prefill, strings }.
 *
 * @package Formulario_Acelera_Ai_Daniel
 * @since   1.0.0
 */

( function () {
	'use strict';

	var cfg = window.aceleraForm || null;

	/* -------------------------------------------------------------------
	 * Bootstrap
	 * ----------------------------------------------------------------- */

	function onReady( fn ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', fn );
		} else {
			fn();
		}
	}

	onReady( function () {
		var root = document.getElementById( 'acelera-form' );

		if ( ! root || ! cfg ) {
			return;
		}

		if ( 'result' === root.getAttribute( 'data-state' ) ) {
			initResultScreen( root );
			return;
		}

		initStepForm( root );
	} );

	/* -------------------------------------------------------------------
	 * REST helper
	 * ----------------------------------------------------------------- */

	function api( path, options ) {
		options = options || {};
		options.headers = options.headers || {};
		options.headers['X-WP-Nonce'] = cfg.nonce;
		options.credentials = 'same-origin';

		return fetch( cfg.restUrl + path, options ).then( function ( response ) {
			return response.json().then( function ( data ) {
				return { status: response.status, ok: response.ok, data: data };
			} );
		} );
	}

	/* -------------------------------------------------------------------
	 * Conditional engine — faithful port of PHP evaluate_condition().
	 * Ported ops: eq (scalar equality; array answer → contains value),
	 * in (scalar → membership; array answer → non-empty intersection),
	 * gte (numeric >=; unanswered/array/non-numeric → false),
	 * not_only (false ONLY when answer is exactly one element === value),
	 * plus nestable compound {any:[...]}/{all:[...]}.
	 * ----------------------------------------------------------------- */

	function evalCondition( cond, answers ) {
		if ( null === cond || undefined === cond ) {
			return true;
		}

		if ( Array.isArray( cond ) && 0 === cond.length ) {
			return true; // PHP: array() === $cond → visible.
		}

		if ( cond.any && Array.isArray( cond.any ) ) {
			return cond.any.some( function ( sub ) {
				return evalCondition( sub, answers );
			} );
		}

		if ( cond.all && Array.isArray( cond.all ) ) {
			return cond.all.every( function ( sub ) {
				return evalCondition( sub, answers );
			} );
		}

		if ( ! cond.question || ! cond.op ) {
			return false;
		}

		var value  = ( 'value' in cond ) ? cond.value : null;
		var answer = Object.prototype.hasOwnProperty.call( answers, cond.question ) ? answers[ cond.question ] : null;

		switch ( cond.op ) {
			case 'eq':
				if ( Array.isArray( answer ) ) {
					return -1 !== answer.indexOf( value );
				}
				return null !== answer && String( answer ) === String( value );

			case 'in':
				var haystack = Array.isArray( value ) ? value : [ value ];
				if ( Array.isArray( answer ) ) {
					return answer.some( function ( a ) {
						return -1 !== haystack.indexOf( a );
					} );
				}
				return null !== answer && -1 !== haystack.map( String ).indexOf( String( answer ) );

			case 'gte':
				if ( null === answer || Array.isArray( answer ) || '' === answer || isNaN( Number( answer ) ) ) {
					return false;
				}
				return Number( answer ) >= Number( value );

			case 'not_only':
				if ( Array.isArray( answer ) && 1 === answer.length ) {
					return String( answer[0] ) !== String( value );
				}
				return true;
		}

		return false;
	}

	function visibleQuestions( answers ) {
		return cfg.questions.filter( function ( q ) {
			return evalCondition( q.show_if, answers );
		} );
	}

	/**
	 * Collapse the currently-visible questions into "steps". Consecutive
	 * questions sharing the same `group` are merged into a single step so
	 * they render together on one screen (inputs side by side) and are
	 * validated/advanced/counted as one. Ungrouped questions become their
	 * own single-question step.
	 *
	 * @param  {Array} answers Answers keyed by question id.
	 * @return {Array<Array>} Array of steps; each step is an array of 1+ questions.
	 */
	function visibleSteps( answers ) {
		var questions = visibleQuestions( answers );
		var steps     = [];
		var i         = 0;

		while ( i < questions.length ) {
			var q = questions[ i ];

			if ( q.group ) {
				var bucket = [ q ];
				var j      = i + 1;
				while ( j < questions.length && questions[ j ].group === q.group ) {
					bucket.push( questions[ j ] );
					j++;
				}
				steps.push( bucket );
				i = j;
			} else {
				steps.push( [ q ] );
				i++;
			}
		}

		return steps;
	}

	/* -------------------------------------------------------------------
	 * Small utils
	 * ----------------------------------------------------------------- */

	function isEmpty( v ) {
		if ( null === v || undefined === v ) {
			return true;
		}
		if ( 'string' === typeof v && '' === v.trim() ) {
			return true;
		}
		if ( Array.isArray( v ) && 0 === v.length ) {
			return true;
		}
		return false;
	}

	function sprintf1( template, value ) {
		return template.replace( /%(1\$)?d/, String( value ) );
	}

	function progressText( current, total ) {
		return cfg.strings.progressOf.replace( '%1$d', String( current ) ).replace( '%2$d', String( total ) );
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== text && null !== text ) {
			node.textContent = text;
		}
		return node;
	}

	function storageKey() {
		return 'aceleraForm:' + String( cfg.userId );
	}

	function saveProgress( state ) {
		try {
			window.localStorage.setItem( storageKey(), JSON.stringify( { answers: state.answers, idx: state.idx, v: 1 } ) );
		} catch ( e ) { /* storage unavailable — autosave silently off */ }
	}

	function loadProgress() {
		try {
			var raw = window.localStorage.getItem( storageKey() );
			if ( ! raw ) {
				return null;
			}
			var data = JSON.parse( raw );
			if ( data && data.answers && 'object' === typeof data.answers ) {
				return data;
			}
		} catch ( e ) { /* corrupt JSON → start clean */ }
		return null;
	}

	function clearProgress() {
		try {
			window.localStorage.removeItem( storageKey() );
		} catch ( e ) { /* noop */ }
	}

	/* -------------------------------------------------------------------
	 * Per-type validation (mirror of the server rules)
	 * ----------------------------------------------------------------- */

	function validateAnswer( q, raw ) {
		if ( isEmpty( raw ) ) {
			return q.required ? { ok: false, message: cfg.strings.required } : { ok: true };
		}

		switch ( q.type ) {
			case 'email':
				if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( String( raw ).trim() ) ) {
					return { ok: false, message: cfg.strings.invalidEmail };
				}
				return { ok: true };

			case 'tel':
				var tel    = String( raw ).trim();
				var digits = tel.replace( /[^0-9]/g, '' );
				if ( ! /^\+?[0-9 ()\-\.]{7,20}$/.test( tel ) || digits.length < 7 ) {
					return { ok: false, message: cfg.strings.invalidTel };
				}
				return { ok: true };

			case 'date':
				var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec( String( raw ) );
				if ( ! m ) {
					return { ok: false, message: cfg.strings.invalidDate };
				}
				var d = new Date( Number( m[1] ), Number( m[2] ) - 1, Number( m[3] ) );
				if ( d.getFullYear() !== Number( m[1] ) || d.getMonth() !== Number( m[2] ) - 1 || d.getDate() !== Number( m[3] ) ) {
					return { ok: false, message: cfg.strings.invalidDate };
				}
				return { ok: true };

			case 'multi':
				if ( q.max && Array.isArray( raw ) && raw.length > q.max ) {
					return { ok: false, message: sprintf1( cfg.strings.maxOptions, q.max ) };
				}
				return { ok: true };

			case 'scale':
				var min = q.min || 1;
				var max = q.max || 5;
				var n   = Number( raw );
				if ( isNaN( n ) || n < min || n > max ) {
					return { ok: false, message: cfg.strings.required };
				}
				return { ok: true };

			case 'repeater':
				if ( ! Array.isArray( raw ) ) {
					return { ok: false, message: cfg.strings.invalidRows };
				}
				for ( var i = 0; i < raw.length; i++ ) {
					var row = raw[ i ] || {};
					var age = Number( row.edad );
					if ( isEmpty( row.nombre ) || '' === String( row.edad || '' ).trim() || isNaN( age ) || age < 0 || age > 99 || ( 'si' !== row.estudia && 'no' !== row.estudia ) ) {
						return { ok: false, message: cfg.strings.invalidRows };
					}
				}
				return { ok: true };
		}

		return { ok: true };
	}

	/* -------------------------------------------------------------------
	 * Step form
	 * ----------------------------------------------------------------- */

	function initStepForm( root ) {
		var app = root.querySelector( '.acelera-form-app' );

		if ( ! app ) {
			return;
		}

		var state = {
			answers: {},
			idx: 0,
			uploading: false,
			submitting: false,
		};

		// Restore autosaved progress (pre-submit only).
		var saved = loadProgress();

		if ( saved ) {
			state.answers = saved.answers || {};
			state.idx     = 'number' === typeof saved.idx ? saved.idx : 0;
		}

		// Prefill name/email from the WP user when not already answered.
		cfg.questions.forEach( function ( q ) {
			if ( q.prefill && isEmpty( state.answers[ q.id ] ) && cfg.prefill && ! isEmpty( cfg.prefill[ q.id ] ) ) {
				state.answers[ q.id ] = cfg.prefill[ q.id ];
			}
		} );

		// Keyboard: Enter advances when valid. Native behavior is kept in
		// textareas and inside repeater inputs.
		root.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' !== event.key || state.submitting ) {
				return;
			}
			if ( event.target && ( 'TEXTAREA' === event.target.tagName || event.target.closest( '.acelera-form-repeater' ) ) ) {
				return;
			}
			var nextBtn = root.querySelector( '.acelera-form-next' );
			if ( nextBtn && ! nextBtn.disabled ) {
				event.preventDefault();
				nextBtn.click();
			}
		} );

		render();

		/* ---------------- render cycle ---------------- */

		function currentSteps() {
			return visibleSteps( state.answers );
		}

		// A step is "skippable" only when every question in it is optional
		// or explicitly skippable (grouped name fields are required → not skippable).
		function stepIsSkippable( step ) {
			return step.every( function ( q ) {
				return ! q.required || q.skippable;
			} );
		}

		// Validate every question of a step; returns the first failure or ok.
		function validateStep( step ) {
			for ( var i = 0; i < step.length; i++ ) {
				var check = validateAnswer( step[ i ], state.answers[ step[ i ].id ] );
				if ( ! check.ok ) {
					return check;
				}
			}
			return { ok: true };
		}

		function render( errorMessage ) {
			var steps = currentSteps();

			if ( state.idx < 0 ) {
				state.idx = 0;
			}
			if ( state.idx >= steps.length ) {
				state.idx = steps.length - 1;
			}

			var step = steps[ state.idx ];

			app.innerHTML = '';

			if ( ! step ) {
				return;
			}

			var isGroup = step.length > 1;
			var primary = step[ 0 ];

			// Progress bar (% over currently-visible steps).
			var pct      = Math.round( ( state.idx / steps.length ) * 100 );
			var progress = el( 'div', 'acelera-form-progress' );
			var bar      = el( 'div', 'acelera-form-progress-track' );
			var fill     = el( 'div', 'acelera-form-progress-fill' );
			fill.style.width = pct + '%';
			bar.appendChild( fill );
			progress.appendChild( bar );
			progress.appendChild( el( 'span', 'acelera-form-progress-text', progressText( state.idx + 1, steps.length ) ) );
			app.appendChild( progress );

			// Question card.
			var card = el( 'div', 'acelera-form-card' );

			if ( isGroup ) {
				// Inline group (e.g. Nombre + Apellido side by side). The
				// card heading is hidden per-question; instead each input
				// carries its own short label above it.
				var groupWrap = el( 'div', 'acelera-form-group' );

				step.forEach( function ( q ) {
					var fieldWrap = el( 'div', 'acelera-form-group-field' );
					var fieldLbl  = el( 'label', 'acelera-form-group-label', q.label );
					fieldLbl.id    = 'acelera-q-' + q.id;
					fieldLbl.htmlFor = 'acelera-input-' + q.id;
					fieldWrap.appendChild( fieldLbl );

					var fieldInput = el( 'div', 'acelera-form-input' );
					renderInput( q, fieldInput );
					fieldWrap.appendChild( fieldInput );

					groupWrap.appendChild( fieldWrap );
				} );

				card.appendChild( groupWrap );
			} else {
				var label = el( 'h3', 'acelera-form-label', primary.label );
				label.id  = 'acelera-q-' + primary.id;
				card.appendChild( label );

				if ( primary.help ) {
					card.appendChild( el( 'p', 'acelera-form-help', primary.help ) );
				}

				var inputWrap = el( 'div', 'acelera-form-input' );
				renderInput( primary, inputWrap );
				card.appendChild( inputWrap );
			}

			var error = el( 'p', 'acelera-form-error' );
			error.setAttribute( 'role', 'alert' );
			if ( errorMessage ) {
				error.textContent = errorMessage;
				error.classList.add( 'is-visible' );
			}
			card.appendChild( error );

			app.appendChild( card );

			// Navigation.
			var nav = el( 'div', 'acelera-form-nav' );

			var back = el( 'button', 'acelera-form-btn acelera-form-btn--ghost acelera-form-back', cfg.strings.back );
			back.type     = 'button';
			back.disabled = 0 === state.idx;
			back.addEventListener( 'click', function () {
				if ( state.idx > 0 ) {
					state.idx--;
					saveProgress( state );
					render();
				}
			} );
			nav.appendChild( back );

			var spacer = el( 'div', 'acelera-form-nav-spacer' );
			nav.appendChild( spacer );

			if ( stepIsSkippable( step ) ) {
				var skip = el( 'button', 'acelera-form-btn acelera-form-btn--ghost acelera-form-skip', cfg.strings.skip );
				skip.type = 'button';
				skip.addEventListener( 'click', function () {
					step.forEach( function ( q ) {
						delete state.answers[ q.id ];
					} );
					advance( step );
				} );
				nav.appendChild( skip );
			}

			var isLast = state.idx === steps.length - 1;
			var next   = el( 'button', 'acelera-form-btn acelera-form-btn--primary acelera-form-next', isLast ? cfg.strings.send : cfg.strings.next );
			next.type  = 'button';
			next.addEventListener( 'click', function () {
				var check = validateStep( step );
				if ( ! check.ok ) {
					showError( check.message );
					return;
				}
				advance( step );
			} );
			nav.appendChild( next );

			app.appendChild( nav );

			refreshNextState( step );
			focusFirstField();
		}

		function showError( message ) {
			var error = app.querySelector( '.acelera-form-error' );
			if ( error ) {
				error.textContent = message || cfg.strings.errorGeneric;
				error.classList.add( 'is-visible' );
			}
		}

		function clearError() {
			var error = app.querySelector( '.acelera-form-error' );
			if ( error ) {
				error.textContent = '';
				error.classList.remove( 'is-visible' );
			}
		}

		// Resolve the step currently on screen (array of 1+ questions).
		function activeStep() {
			var steps = currentSteps();
			return steps[ state.idx ] || steps[ steps.length - 1 ] || null;
		}

		function refreshNextState( step ) {
			var next = app.querySelector( '.acelera-form-next' );
			if ( ! next ) {
				return;
			}

			// Allow callers (e.g. setAnswer) to omit the step.
			if ( ! step ) {
				step = activeStep();
			}
			if ( ! step ) {
				return;
			}

			if ( state.uploading ) {
				next.disabled = true;
				return;
			}

			// Disabled when ANY question in the step is invalid. Required
			// questions must be answered; optional ones only need to be
			// valid when filled.
			next.disabled = step.some( function ( q ) {
				var raw = state.answers[ q.id ];
				if ( q.required ) {
					return isEmpty( raw ) || ! validateAnswer( q, raw ).ok;
				}
				return ! isEmpty( raw ) && ! validateAnswer( q, raw ).ok;
			} );
		}

		function focusFirstField() {
			var field = app.querySelector( 'input, textarea, select, button.acelera-form-option' );
			if ( field ) {
				field.focus();
			}
		}

		function advance( step ) {
			clearError();

			var primaryId = step[ 0 ].id;
			var steps     = currentSteps();
			var pos        = -1;

			for ( var i = 0; i < steps.length; i++ ) {
				if ( steps[ i ][ 0 ].id === primaryId ) {
					pos = i;
					break;
				}
			}

			if ( -1 === pos ) {
				pos = Math.min( state.idx, steps.length - 1 );
			}

			if ( pos >= steps.length - 1 ) {
				doSubmit();
				return;
			}

			state.idx = pos + 1;
			saveProgress( state );
			render();
		}

		function setAnswer( q, value ) {
			if ( isEmpty( value ) ) {
				delete state.answers[ q.id ];
			} else {
				state.answers[ q.id ] = value;
			}
			saveProgress( state );
			clearError();
			refreshNextState();
		}

		/* ---------------- input renderers ---------------- */

		function renderInput( q, wrap ) {
			switch ( q.type ) {
				case 'text':
				case 'email':
				case 'tel':
				case 'date':
					renderTextInput( q, wrap );
					break;
				case 'textarea':
					renderTextarea( q, wrap );
					break;
				case 'single':
					renderSingle( q, wrap );
					break;
				case 'multi':
					renderMulti( q, wrap );
					break;
				case 'scale':
					renderScale( q, wrap );
					break;
				case 'repeater':
					renderRepeater( q, wrap );
					break;
				case 'file':
					renderFile( q, wrap );
					break;
			}
		}

		function renderTextInput( q, wrap ) {
			var input = document.createElement( 'input' );
			input.type      = q.type;
			input.className = 'acelera-form-field';
			input.id        = 'acelera-input-' + q.id;
			input.value     = state.answers[ q.id ] || '';
			input.setAttribute( 'aria-labelledby', 'acelera-q-' + q.id );

			if ( 'tel' === q.type && window.intlTelInput ) {
				renderTelInput( q, input, wrap );
				return;
			}

			input.addEventListener( 'input', function () {
				setAnswer( q, input.value );
			} );
			wrap.appendChild( input );
		}

		// Phone input enhanced with intl-tel-input (country flag dropdown).
		// On each render the DOM is recreated, so we initialize a fresh
		// instance here and keep it in a render-local closure (no leaks).
		function renderTelInput( q, input, wrap ) {
			wrap.appendChild( input );

			var iti = window.intlTelInput( input, {
				initialCountry: 'co',
				preferredCountries: [ 'co', 'us', 'mx', 've', 'ar', 'es' ],
				separateDialCode: true,
				utilsScript: cfg.intlUtilsUrl || '',
			} );

			// Apply prefill (e.g. "+573042465482") so the library detects
			// the country (+57 → Colombia) and shows only the national part.
			var prefill = state.answers[ q.id ];
			if ( ! isEmpty( prefill ) ) {
				try {
					iti.setNumber( String( prefill ) );
				} catch ( e ) {
					input.value = String( prefill );
				}
			}

			function syncAnswer() {
				var value = '';
				try {
					value = iti.getNumber(); // E.164, e.g. +573042465482.
				} catch ( e ) {
					value = '';
				}
				// utils.js may still be loading → getNumber() empty; fall
				// back to the raw input so the answer is never lost.
				if ( isEmpty( value ) ) {
					value = input.value;
				}
				setAnswer( q, value );
			}

			input.addEventListener( 'input', syncAnswer );
			input.addEventListener( 'countrychange', syncAnswer );
			// utils.js loads async; capture the formatted number once ready.
			input.addEventListener( 'change', syncAnswer );
		}

		function renderTextarea( q, wrap ) {
			var input = document.createElement( 'textarea' );
			input.className = 'acelera-form-field acelera-form-field--textarea';
			input.rows      = 4;
			input.value     = state.answers[ q.id ] || '';
			input.setAttribute( 'aria-labelledby', 'acelera-q-' + q.id );
			input.addEventListener( 'input', function () {
				setAnswer( q, input.value );
			} );
			wrap.appendChild( input );
		}

		function renderSingle( q, wrap ) {
			var list = el( 'div', 'acelera-form-options' );
			list.setAttribute( 'role', 'radiogroup' );
			list.setAttribute( 'aria-labelledby', 'acelera-q-' + q.id );

			( q.options || [] ).forEach( function ( option ) {
				var item = el( 'label', 'acelera-form-option' );
				var radio = document.createElement( 'input' );
				radio.type    = 'radio';
				radio.name    = q.id;
				radio.value   = option.value;
				radio.checked = state.answers[ q.id ] === option.value;
				radio.addEventListener( 'change', function () {
					setAnswer( q, option.value );
					list.querySelectorAll( '.acelera-form-option' ).forEach( function ( node ) {
						node.classList.toggle( 'is-selected', node === item );
					} );
				} );
				if ( radio.checked ) {
					item.classList.add( 'is-selected' );
				}
				item.appendChild( radio );
				item.appendChild( el( 'span', 'acelera-form-option-label', option.label ) );
				list.appendChild( item );
			} );

			wrap.appendChild( list );
		}

		function renderMulti( q, wrap ) {
			var list = el( 'div', 'acelera-form-options' );
			list.setAttribute( 'role', 'group' );
			list.setAttribute( 'aria-labelledby', 'acelera-q-' + q.id );

			function selected() {
				return Array.isArray( state.answers[ q.id ] ) ? state.answers[ q.id ].slice() : [];
			}

			function enforceMax() {
				if ( ! q.max ) {
					return;
				}
				var atMax = selected().length >= q.max;
				list.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( box ) {
					box.disabled = atMax && ! box.checked;
					box.closest( '.acelera-form-option' ).classList.toggle( 'is-disabled', box.disabled );
				} );
			}

			( q.options || [] ).forEach( function ( option ) {
				var item = el( 'label', 'acelera-form-option' );
				var box  = document.createElement( 'input' );
				box.type    = 'checkbox';
				box.value   = option.value;
				box.checked = -1 !== selected().indexOf( option.value );
				box.addEventListener( 'change', function () {
					var values = selected();
					var at     = values.indexOf( option.value );
					if ( box.checked && -1 === at ) {
						values.push( option.value );
					} else if ( ! box.checked && -1 !== at ) {
						values.splice( at, 1 );
					}
					setAnswer( q, values );
					item.classList.toggle( 'is-selected', box.checked );
					enforceMax();
				} );
				if ( box.checked ) {
					item.classList.add( 'is-selected' );
				}
				item.appendChild( box );
				item.appendChild( el( 'span', 'acelera-form-option-label', option.label ) );
				list.appendChild( item );
			} );

			wrap.appendChild( list );
			enforceMax();

			if ( q.max ) {
				wrap.appendChild( el( 'p', 'acelera-form-hint', sprintf1( cfg.strings.maxOptions, q.max ) ) );
			}
		}

		function renderScale( q, wrap ) {
			var min  = q.min || 1;
			var max  = q.max || 5;
			var list = el( 'div', 'acelera-form-scale' );
			list.setAttribute( 'role', 'radiogroup' );
			list.setAttribute( 'aria-labelledby', 'acelera-q-' + q.id );

			for ( var value = min; value <= max; value++ ) {
				( function ( v ) {
					var pill = el( 'label', 'acelera-form-scale-pill' );
					var radio = document.createElement( 'input' );
					radio.type    = 'radio';
					radio.name    = q.id;
					radio.value   = String( v );
					radio.checked = Number( state.answers[ q.id ] ) === v;
					radio.addEventListener( 'change', function () {
						setAnswer( q, v );
						list.querySelectorAll( '.acelera-form-scale-pill' ).forEach( function ( node ) {
							node.classList.toggle( 'is-selected', node === pill );
						} );
					} );
					if ( radio.checked ) {
						pill.classList.add( 'is-selected' );
					}
					pill.appendChild( radio );
					pill.appendChild( el( 'span', null, String( v ) ) );
					list.appendChild( pill );
				}( value ) );
			}

			wrap.appendChild( list );

			var captions = el( 'div', 'acelera-form-scale-captions' );
			captions.appendChild( el( 'span', null, cfg.strings.scaleLow ) );
			captions.appendChild( el( 'span', null, cfg.strings.scaleHigh ) );
			wrap.appendChild( captions );
		}

		function renderRepeater( q, wrap ) {
			var container = el( 'div', 'acelera-form-repeater' );

			function rows() {
				return Array.isArray( state.answers[ q.id ] ) ? state.answers[ q.id ] : [];
			}

			function setRows( next ) {
				setAnswer( q, next );
			}

			function paint() {
				container.innerHTML = '';

				rows().forEach( function ( row, index ) {
					var box   = el( 'div', 'acelera-form-repeater-row' );
					var title = el( 'div', 'acelera-form-repeater-row-head' );
					title.appendChild( el( 'strong', null, cfg.strings.childLabel + ' ' + ( index + 1 ) ) );

					var remove = el( 'button', 'acelera-form-repeater-remove', cfg.strings.removeChild );
					remove.type = 'button';
					remove.addEventListener( 'click', function () {
						var next = rows().slice();
						next.splice( index, 1 );
						setRows( next );
						paint();
					} );
					title.appendChild( remove );
					box.appendChild( title );

					( q.subfields || [] ).forEach( function ( sub ) {
						var field = el( 'div', 'acelera-form-repeater-field' );
						field.appendChild( el( 'label', 'acelera-form-repeater-label', sub.label ) );

						if ( 'single' === sub.type ) {
							var group = el( 'div', 'acelera-form-repeater-radios' );
							( sub.options || [] ).forEach( function ( option ) {
								var lbl   = el( 'label', 'acelera-form-repeater-radio' );
								var radio = document.createElement( 'input' );
								radio.type    = 'radio';
								radio.name    = q.id + '-' + index + '-' + sub.key;
								radio.value   = option.value;
								radio.checked = row[ sub.key ] === option.value;
								radio.addEventListener( 'change', function () {
									var next = rows().slice();
									next[ index ] = Object.assign( {}, next[ index ], {} );
									next[ index ][ sub.key ] = option.value;
									setRows( next );
								} );
								lbl.appendChild( radio );
								lbl.appendChild( el( 'span', null, option.label ) );
								group.appendChild( lbl );
							} );
							field.appendChild( group );
						} else {
							var input = document.createElement( 'input' );
							input.type      = 'number' === sub.type ? 'number' : 'text';
							input.className = 'acelera-form-field acelera-form-field--small';
							if ( 'number' === sub.type ) {
								input.min = String( sub.min || 0 );
								input.max = String( sub.max || 99 );
							}
							input.value = undefined !== row[ sub.key ] && null !== row[ sub.key ] ? String( row[ sub.key ] ) : '';
							input.addEventListener( 'input', function () {
								var next = rows().slice();
								next[ index ] = Object.assign( {}, next[ index ], {} );
								next[ index ][ sub.key ] = input.value;
								setRows( next );
							} );
							field.appendChild( input );
						}

						box.appendChild( field );
					} );

					container.appendChild( box );
				} );

				var add = el( 'button', 'acelera-form-btn acelera-form-btn--ghost acelera-form-repeater-add', cfg.strings.addChild );
				add.type = 'button';
				add.addEventListener( 'click', function () {
					var next = rows().slice();
					next.push( { nombre: '', edad: '', estudia: '' } );
					setRows( next );
					paint();
				} );
				container.appendChild( add );
			}

			paint();
			wrap.appendChild( container );
		}

		function renderFile( q, wrap ) {
			var box     = el( 'div', 'acelera-form-file' );
			var status  = el( 'p', 'acelera-form-file-status' );
			var current = state.answers[ q.id ];

			function paintUploaded( url ) {
				box.innerHTML = '';
				var name = String( url ).split( '/' ).pop();
				box.appendChild( el( 'p', 'acelera-form-file-name', cfg.strings.uploaded + ' ' + name ) );

				var remove = el( 'button', 'acelera-form-btn acelera-form-btn--ghost', cfg.strings.removeFile );
				remove.type = 'button';
				remove.addEventListener( 'click', function () {
					setAnswer( q, null );
					paintPicker();
				} );
				box.appendChild( remove );
			}

			function paintPicker() {
				box.innerHTML = '';

				var input = document.createElement( 'input' );
				input.type      = 'file';
				input.className = 'acelera-form-file-input';
				input.accept    = '.' + ( q.accept || [ 'pdf', 'doc', 'docx' ] ).join( ',.' );
				input.addEventListener( 'change', function () {
					var file = input.files && input.files[0];
					if ( ! file ) {
						return;
					}

					var ext     = file.name.split( '.' ).pop().toLowerCase();
					var allowed = q.accept || [ 'pdf', 'doc', 'docx' ];

					if ( -1 === allowed.indexOf( ext ) ) {
						status.textContent = cfg.strings.fileBadType;
						return;
					}

					var maxBytes = ( q.max_size_mb || 10 ) * 1024 * 1024;
					if ( file.size > maxBytes ) {
						status.textContent = cfg.strings.fileTooBig;
						return;
					}

					status.textContent = cfg.strings.uploading;
					state.uploading    = true;
					refreshNextState();

					var body = new FormData();
					body.append( 'file', file );

					api( 'upload-cv', { method: 'POST', body: body } ).then( function ( response ) {
						state.uploading = false;
						if ( response.ok && response.data && response.data.url ) {
							status.textContent = '';
							setAnswer( q, response.data.url );
							paintUploaded( response.data.url );
						} else {
							status.textContent = ( response.data && response.data.message ) ? response.data.message : cfg.strings.uploadError;
							refreshNextState();
						}
					} ).catch( function () {
						state.uploading    = false;
						status.textContent = cfg.strings.uploadError;
						refreshNextState();
					} );
				} );

				box.appendChild( input );
			}

			if ( current ) {
				paintUploaded( current );
			} else {
				paintPicker();
			}

			wrap.appendChild( box );
			wrap.appendChild( status );
		}

		/* ---------------- submit ---------------- */

		function prunedAnswers() {
			var out = {};

			visibleQuestions( state.answers ).forEach( function ( q ) {
				var value = state.answers[ q.id ];
				if ( ! isEmpty( value ) ) {
					out[ q.id ] = value;
				}
			} );

			return out;
		}

		function doSubmit() {
			if ( state.submitting ) {
				return;
			}

			state.submitting = true;

			var next = app.querySelector( '.acelera-form-next' );
			if ( next ) {
				next.disabled    = true;
				next.textContent = cfg.strings.sending;
			}

			api( 'submit', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( { answers: prunedAnswers() } ),
			} ).then( function ( response ) {
				if ( response.ok && response.data && response.data.ok ) {
					clearProgress();
					app.innerHTML = '';
					app.appendChild( el( 'p', 'acelera-form-success', cfg.strings.successTitle ) );
					// Decision: reload — PHP renders the result state and the
					// sidebar refreshes with the renumbered/reordered modules.
					window.location.reload();
					return;
				}

				// Double-submit race: an active submission already exists on the
				// server (409). Treat it like success — the result is already
				// there, so reload and let PHP render the result screen.
				if ( 409 === response.status && response.data && 'acelera_already_submitted' === response.data.code ) {
					clearProgress();
					window.location.reload();
					return;
				}

				state.submitting = false;

				if ( 400 === response.status && response.data && response.data.errors ) {
					jumpToFirstError( response.data.errors );
					return;
				}

				render( ( response.data && response.data.message ) ? response.data.message : cfg.strings.errorGeneric );
			} ).catch( function () {
				state.submitting = false;
				render( cfg.strings.errorGeneric );
			} );
		}

		function jumpToFirstError( errors ) {
			var steps = currentSteps();

			// state.idx is a STEP index; find the first step that contains
			// any question with a server-reported error.
			for ( var i = 0; i < steps.length; i++ ) {
				for ( var j = 0; j < steps[ i ].length; j++ ) {
					var qid = steps[ i ][ j ].id;
					if ( Object.prototype.hasOwnProperty.call( errors, qid ) ) {
						state.idx = i;
						render( errors[ qid ] );
						return;
					}
				}
			}

			// Error on a non-visible question (shouldn't happen): generic.
			render( cfg.strings.errorGeneric );
		}
	}

	/* -------------------------------------------------------------------
	 * Result screen (state 3): reset modal wiring
	 * ----------------------------------------------------------------- */

	function initResultScreen( root ) {
		var modal   = root.querySelector( '.acelera-form-modal' );
		var open    = root.querySelector( '.acelera-form-reset-open' );
		var confirm = root.querySelector( '.acelera-form-reset-confirm' );

		if ( ! modal || ! open || ! confirm ) {
			return;
		}

		function show() {
			modal.hidden = false;
			confirm.focus();
		}

		function hide() {
			modal.hidden = true;
			open.focus();
		}

		open.addEventListener( 'click', show );

		modal.querySelectorAll( '[data-acelera-modal-close]' ).forEach( function ( node ) {
			node.addEventListener( 'click', hide );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! modal.hidden ) {
				hide();
			}
		} );

		confirm.addEventListener( 'click', function () {
			confirm.disabled    = true;
			confirm.textContent = cfg.strings.resetting;

			api( 'reset', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( { confirm: true } ),
			} ).then( function ( response ) {
				if ( response.ok && response.data && response.data.ok ) {
					clearProgress();
					// Decision: reload — PHP re-renders the fresh form and the
					// sidebar returns to the natural module order.
					window.location.reload();
					return;
				}
				confirm.disabled    = false;
				confirm.textContent = cfg.strings.errorGeneric;
			} ).catch( function () {
				confirm.disabled    = false;
				confirm.textContent = cfg.strings.errorGeneric;
			} );
		} );
	}

}() );
