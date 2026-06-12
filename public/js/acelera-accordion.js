/**
 * ACELERA — sidebar section accordion (Fase 3) + BuddyBoss adapter.
 *
 * Vanilla JS, no dependencies. Two code paths, auto-detected on init:
 *
 * 1. LD30 template overrides — works on the buttons rendered by the
 *    section.php override (.acelera-section-toggle) and the containers
 *    rendered by the rows.php override (.acelera-section-body).
 *    Behaviour unchanged from Fase 3.
 *
 * 2. BuddyBoss theme sidebar (.lms-lessions-list ol.bb-lessons-list) —
 *    the BuddyBoss theme replaces the LD30 focus-mode sidebar with its
 *    own template, so the PHP template overrides never render there.
 *    This adapter rebuilds the same UX client-side (progressive
 *    enhancement): renumbers section headings, reorders section groups
 *    per the user's module order, wraps collapsible modules in
 *    accordion bodies and neutralizes gate-locked lesson links. Data
 *    comes from `aceleraSidebar` (localized in PHP):
 *    { modules: [{key,label,collapsible}], lessonModules: {path:key},
 *      lockedPaths: [path], currentPath, strings }.
 *    Lessons are matched by permalink PATHNAME (host-agnostic,
 *    trailing-slash normalized). Defensive: any unexpected structure
 *    aborts leaving the BuddyBoss DOM untouched.
 *
 * Shared behaviour (both paths):
 * - Click (or Enter/Space, native to <button>) toggles the section.
 * - State persisted per section in sessionStorage under
 *   acelera_accordion_{courseId}_{sectionId}.
 * - The section containing the current lesson always starts open,
 *   winning over a stored 'closed' state.
 *
 * Pure helpers (normalizePath, groupSections, orderGroups) are exported
 * via module.exports for plain-node unit testing.
 *
 * @since 1.0.0
 * @package Formulario_Acelara_Ai_Daniel
 */

( function() {
	'use strict';

	/* ------------------------------------------------------------------
	 * Pure helpers (no DOM, no globals — testable under plain node).
	 * ---------------------------------------------------------------- */

	/**
	 * Normalize a URL or path into a comparable pathname.
	 *
	 * Strips scheme/host (absolute and protocol-relative URLs), drops
	 * query string and fragment, guarantees leading and trailing slash.
	 *
	 * @param {string} href URL, pathname or empty value.
	 * @return {string} Normalized pathname ('' when unresolvable).
	 */
	function normalizePath( href ) {
		if ( ! href ) {
			return '';
		}

		var path  = String( href );
		var match = path.match( /^[a-z][a-z0-9+.-]*:\/\/[^\/]*(\/.*)?$/i );

		if ( match ) {
			path = match[1] || '/';
		} else if ( 0 === path.indexOf( '//' ) ) {
			var slash = path.indexOf( '/', 2 );
			path = ( -1 === slash ) ? '/' : path.slice( slash );
		}

		path = path.split( '#' )[0].split( '?' )[0];

		if ( '' === path ) {
			return '';
		}

		if ( '/' !== path.charAt( 0 ) ) {
			path = '/' + path;
		}

		if ( '/' !== path.charAt( path.length - 1 ) ) {
			path += '/';
		}

		return path;
	}

	/**
	 * Group a flat ordered list of sidebar items into sections.
	 *
	 * An item with a non-null sectionId starts a new section (it carries
	 * the heading). Items before the first heading form an implicit
	 * leading section (sectionId null). The section module is the module
	 * of its first mapped lesson.
	 *
	 * @param {Array<{sectionId: ?string, module: ?string}>} items Ordered item descriptors.
	 * @return {Array<{sectionId: ?string, module: ?string, indices: number[]}>}
	 */
	function groupSections( items ) {
		var groups  = [];
		var current = null;

		items.forEach( function( item, index ) {
			if ( null !== item.sectionId || null === current ) {
				current = { sectionId: item.sectionId, module: null, indices: [] };
				groups.push( current );
			}

			current.indices.push( index );

			if ( null === current.module && item.module ) {
				current.module = item.module;
			}
		} );

		return groups;
	}

	/**
	 * Order section groups: non-module groups first (welcome / unmapped,
	 * keeping their original relative order), then module groups per the
	 * given module key order (original relative order inside each key).
	 *
	 * @param {Array<{module: ?string}>} groups     Section groups (any shape with .module).
	 * @param {string[]}                 moduleKeys Module keys in display order (e.g. ['m1','m2',...]).
	 * @return {Array} Reordered groups (same objects, new array).
	 */
	function orderGroups( groups, moduleKeys ) {
		var ordered = [];

		groups.forEach( function( group ) {
			if ( ! group.module || -1 === moduleKeys.indexOf( group.module ) ) {
				ordered.push( group );
			}
		} );

		moduleKeys.forEach( function( key ) {
			groups.forEach( function( group ) {
				if ( group.module === key ) {
					ordered.push( group );
				}
			} );
		} );

		return ordered;
	}

	/* Export pure helpers for plain-node tests. */
	if ( 'undefined' !== typeof module && module.exports ) {
		module.exports = {
			normalizePath: normalizePath,
			groupSections: groupSections,
			orderGroups:   orderGroups
		};
	}

	/* Outside a browser (plain node test run) there is nothing else to do. */
	if ( 'undefined' === typeof document ) {
		return;
	}

	/* ------------------------------------------------------------------
	 * Shared accordion state helpers (sessionStorage + ARIA).
	 * ---------------------------------------------------------------- */

	function courseId() {
		var settings = window.aceleraAccordion || {};
		return parseInt( settings.courseId, 10 ) || 0;
	}

	function storageKey( sectionId ) {
		return 'acelera_accordion_' + courseId() + '_' + sectionId;
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

	/**
	 * Apply the initial state and wire the click handler of one toggle.
	 *
	 * @param {Element}  toggle    The .acelera-section-toggle button.
	 * @param {?Element} body      The collapsible container.
	 * @param {string}   sectionId Storage identity of the section.
	 * @param {boolean}  open      Initial open state.
	 */
	function bindToggle( toggle, body, sectionId, open ) {
		applyState( toggle, body, open );

		toggle.addEventListener( 'click', function() {
			var isOpen = 'true' === toggle.getAttribute( 'aria-expanded' );

			applyState( toggle, body, ! isOpen );
			writeState( sectionId, ! isOpen );
		} );
	}

	/* ------------------------------------------------------------------
	 * Path 1 — LD30 template overrides (Fase 3, unchanged behaviour).
	 * ---------------------------------------------------------------- */

	function initLd30( toggles ) {
		var settings         = window.aceleraAccordion || {};
		var currentSectionId = String( settings.currentSectionId || '' );

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

			bindToggle( toggle, body, sectionId, open );
		} );
	}

	/* ------------------------------------------------------------------
	 * Path 2 — BuddyBoss sidebar adapter.
	 * ---------------------------------------------------------------- */

	var CHEVRON_SVG = '<svg width="12" height="12" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><path d="M3 6l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

	/**
	 * Build the descriptor of one BuddyBoss sidebar <li>.
	 *
	 * @param {Element} li           The li.lms-lesson-item element.
	 * @param {Object}  pathToModule Normalized path → module key map.
	 * @return {Object} { li, headingDiv, sectionId, module, anchor, path }
	 */
	function describeBbItem( li, pathToModule ) {
		var headingDiv = li.querySelector( '.ld-item-list-section-heading' );
		var sectionId  = null;

		if ( headingDiv ) {
			var idMatch = String( headingDiv.className || '' ).match( /ld-item-section-heading-(\d+)/ );
			sectionId   = idMatch ? idMatch[1] : '';
		}

		var anchor = li.querySelector( 'a[href]' );
		var path   = anchor ? normalizePath( anchor.getAttribute( 'href' ) ) : '';

		return {
			li:         li,
			headingDiv: headingDiv,
			sectionId:  sectionId,
			module:     ( path && Object.prototype.hasOwnProperty.call( pathToModule, path ) ) ? pathToModule[ path ] : null,
			anchor:     anchor,
			path:       path
		};
	}

	/**
	 * Neutralize a gate-locked lesson row (UX layer; the server-side
	 * template_redirect remains the real protection).
	 */
	function lockBbItem( item, tooltip ) {
		item.li.classList.add( 'acelera-locked' );

		item.anchor.setAttribute( 'href', '#' );
		item.anchor.setAttribute( 'aria-disabled', 'true' );

		if ( tooltip ) {
			item.anchor.setAttribute( 'title', tooltip );
		}

		item.anchor.addEventListener( 'click', function( e ) {
			e.preventDefault();
			e.stopPropagation();
		} );
	}

	/**
	 * Turn a BuddyBoss section heading into an accordion toggle button,
	 * reusing the Fase 3 classes/markup so the existing CSS applies.
	 *
	 * @param {Element} headingDiv .ld-item-list-section-heading element.
	 * @param {string}  sectionKey Storage identity of the section.
	 * @param {string}  bodyId     id of the collapsible container.
	 * @param {Object}  strings    Localized strings.
	 * @return {?Element} The toggle button, or null when the heading is malformed.
	 */
	function buildBbToggle( headingDiv, sectionKey, bodyId, strings ) {
		var heading = headingDiv.querySelector( '.ld-lesson-section-heading' );

		if ( ! heading ) {
			return null;
		}

		var label  = heading.textContent.replace( /\s+/g, ' ' ).trim();
		var button = document.createElement( 'button' );

		button.type      = 'button';
		button.className = 'acelera-section-toggle';
		button.setAttribute( 'aria-expanded', 'false' );
		button.setAttribute( 'aria-controls', bodyId );
		button.setAttribute( 'data-acelera-section', sectionKey );

		if ( strings.toggleSection ) {
			button.setAttribute( 'title', strings.toggleSection );
		}

		var title = document.createElement( 'span' );
		title.className   = 'acelera-section-title';
		title.textContent = label;

		var chevron = document.createElement( 'span' );
		chevron.className = 'acelera-chevron acelera-chevron-svg';
		chevron.setAttribute( 'aria-hidden', 'true' );
		chevron.innerHTML = CHEVRON_SVG;

		button.appendChild( title );
		button.appendChild( chevron );

		heading.textContent = '';
		heading.appendChild( button );
		headingDiv.classList.add( 'acelera-accordion-heading' );

		return button;
	}

	/**
	 * BuddyBoss adapter body. Throws on unexpected structure BEFORE any
	 * DOM mutation, so the caller's try/catch leaves the sidebar intact.
	 *
	 * @param {Element} list The ol.bb-lessons-list element.
	 */
	function runBbAdapter( list ) {
		var sidebar = window.aceleraSidebar || {};
		var modules = ( sidebar.modules && sidebar.modules.length ) ? sidebar.modules : [];
		var strings = sidebar.strings || {};

		if ( ! modules.length ) {
			return; // No localized data — nothing to enhance.
		}

		/* --- Read phase (no mutations). --- */

		var pathToModule = {};
		var rawMap       = sidebar.lessonModules || {};

		Object.keys( rawMap ).forEach( function( key ) {
			var normalized = normalizePath( key );

			if ( normalized ) {
				pathToModule[ normalized ] = rawMap[ key ];
			}
		} );

		var lockedSet = {};

		( sidebar.lockedPaths || [] ).forEach( function( path ) {
			var normalized = normalizePath( path );

			if ( normalized ) {
				lockedSet[ normalized ] = true;
			}
		} );

		var currentPath = normalizePath( sidebar.currentPath || '' );

		var lis  = [];
		var node = list.firstElementChild;

		while ( node ) {
			if ( 'LI' !== node.tagName ) {
				throw new Error( 'unexpected non-LI child in bb-lessons-list' );
			}

			lis.push( node );
			node = node.nextElementSibling;
		}

		if ( ! lis.length ) {
			return;
		}

		var items  = lis.map( function( li ) {
			return describeBbItem( li, pathToModule );
		} );

		var groups       = groupSections( items );
		var hasHeadings  = groups.some( function( group ) {
			return null !== group.sectionId;
		} );
		var modulesByKey = {};
		var moduleKeys   = [];

		modules.forEach( function( mod ) {
			if ( mod && mod.key ) {
				modulesByKey[ mod.key ] = mod;
				moduleKeys.push( mod.key );
			}
		} );

		/* --- Mutate phase. --- */

		// 5. Gate locks (independent from the accordion restructuring).
		items.forEach( function( item ) {
			if ( item.path && lockedSet[ item.path ] && item.anchor ) {
				lockBbItem( item, strings.tooltip || '' );
			}
		} );

		// Without section headings there is nothing to renumber/reorder/collapse.
		if ( ! hasHeadings ) {
			return;
		}

		// 2. Renumber section headings with the user's display labels.
		groups.forEach( function( group ) {
			var first = items[ group.indices[0] ];

			if ( ! first.headingDiv || ! group.module ) {
				return;
			}

			var mod = modulesByKey[ group.module ];

			if ( ! mod || ! mod.label ) {
				return;
			}

			var heading = first.headingDiv.querySelector( '.ld-lesson-section-heading' );

			if ( heading ) {
				heading.textContent = mod.label;
			}
		} );

		// 3 + 4. Rebuild the OL: groups in display order; collapsible
		// modules get an extracted heading li + a collapsible body li.
		var ordered  = orderGroups( groups, moduleKeys );
		var fragment = document.createDocumentFragment();

		ordered.forEach( function( group ) {
			var first       = items[ group.indices[0] ];
			var mod         = group.module ? modulesByKey[ group.module ] : null;
			var collapsible = !! ( mod && mod.collapsible && first.headingDiv );

			if ( ! collapsible ) {
				group.indices.forEach( function( index ) {
					fragment.appendChild( items[ index ].li );
				} );
				return;
			}

			var sectionKey = group.sectionId || ( 'bb-' + group.module );
			var bodyId     = 'acelera-section-body-' + sectionKey;

			// Heading li: heading div moved out of the first lesson li.
			var headingLi       = document.createElement( 'li' );
			headingLi.className = 'acelera-bb-section';
			headingLi.appendChild( first.headingDiv );

			var toggle = buildBbToggle( first.headingDiv, sectionKey, bodyId, strings );

			if ( ! toggle ) {
				// Malformed heading: keep the group as-is (heading div is
				// re-appended inside its original li untouched).
				first.li.insertBefore( first.headingDiv, first.li.firstChild );
				group.indices.forEach( function( index ) {
					fragment.appendChild( items[ index ].li );
				} );
				return;
			}

			// Body li: nested ol holding the group's lesson lis.
			var bodyLi       = document.createElement( 'li' );
			bodyLi.className = 'acelera-bb-section-body acelera-section-body';
			bodyLi.id        = bodyId;

			var inner       = document.createElement( 'ol' );
			inner.className = 'acelera-section-body-inner';

			var hasCurrent = false;

			group.indices.forEach( function( index ) {
				var item = items[ index ];

				if ( item.li.classList.contains( 'current' ) || ( currentPath && item.path === currentPath ) ) {
					hasCurrent = true;
				}

				inner.appendChild( item.li );
			} );

			bodyLi.appendChild( inner );
			fragment.appendChild( headingLi );
			fragment.appendChild( bodyLi );

			// Initial state: closed by default, stored state wins, the
			// current lesson's section always starts open.
			var open   = hasCurrent;
			var stored = readState( sectionKey );

			if ( 'open' === stored ) {
				open = true;
			} else if ( 'closed' === stored ) {
				open = false;
			}

			if ( hasCurrent ) {
				open = true;
			}

			bindToggle( toggle, bodyLi, sectionKey, open );
		} );

		list.appendChild( fragment );
	}

	function initBuddyBoss() {
		var list = document.querySelector( '.lms-lessions-list ol.bb-lessons-list' );

		if ( ! list || list.hasAttribute( 'data-acelera-bb' ) ) {
			return;
		}

		// Idempotency guard — never run the adapter twice on the same list.
		list.setAttribute( 'data-acelera-bb', '1' );

		try {
			runBbAdapter( list );
		} catch ( e ) {
			// Defensive: never break the BuddyBoss sidebar.
			if ( window.console && window.console.warn ) {
				window.console.warn( '[acelera] BuddyBoss sidebar adapter aborted:', e );
			}
		}
	}

	/* ------------------------------------------------------------------
	 * Bootstrap.
	 * ---------------------------------------------------------------- */

	function init() {
		var ld30Toggles = document.querySelectorAll( '.acelera-section-toggle' );

		if ( ld30Toggles.length ) {
			initLd30( ld30Toggles );
			return;
		}

		initBuddyBoss();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
