/**
 * UniInbox for Contact Form 7 - shared admin helpers.
 */
( function () {
	'use strict';

	var cfg = window.uicf7Admin || {};
	var i18n = cfg.i18n || {};
	var toastTimer = null;

	var api = {
		/**
		 * POST to admin-ajax. Resolves with response data, rejects with an Error.
		 */
		post: function ( action, data ) {
			var body = new FormData();
			body.append( 'action', 'uicf7_' + action );
			body.append( 'nonce', cfg.nonce || '' );
			Object.keys( data || {} ).forEach( function ( key ) {
				body.append( key, data[ key ] );
			} );

			return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( response ) {
					return response.json().catch( function () {
						return { success: false, data: { message: i18n.error } };
					} );
				} )
				.then( function ( json ) {
					if ( ! json || ! json.success ) {
						throw new Error( json && json.data && json.data.message ? json.data.message : i18n.error );
					}
					return json.data;
				} );
		},

		/**
		 * Small notification in the corner.
		 */
		toast: function ( text, isError ) {
			var el = document.querySelector( '.uicf7-toast' );
			if ( ! el ) {
				el = document.createElement( 'div' );
				el.className = 'uicf7-toast';
				el.setAttribute( 'role', 'status' );
				el.setAttribute( 'aria-live', 'polite' );
				document.body.appendChild( el );
			}
			el.textContent = text;
			el.classList.toggle( 'is-error', !! isError );
			window.requestAnimationFrame( function () {
				el.classList.add( 'is-visible' );
			} );
			window.clearTimeout( toastTimer );
			toastTimer = window.setTimeout( function () {
				el.classList.remove( 'is-visible' );
			}, isError ? 6000 : 3500 );
		},

		escapeHtml: function ( text ) {
			return String( text )
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				.replace( /"/g, '&quot;' )
				.replace( /'/g, '&#039;' );
		}
	};

	window.uicf7 = api;

	// Confirm destructive links and buttons.
	document.addEventListener( 'click', function ( event ) {
		var el = event.target.closest ? event.target.closest( '.uicf7-confirm, .uicf7-confirm-submit' ) : null;
		if ( ! el ) {
			return;
		}
		var text = el.getAttribute( 'data-confirm' ) || i18n.confirmDelete || '';
		if ( text && ! window.confirm( text ) ) {
			event.preventDefault();
			event.stopPropagation();
		}
	} );

	// Star toggles (inbox rows and the message screen).
	document.addEventListener( 'click', function ( event ) {
		var btn = event.target.closest ? event.target.closest( '.uicf7-star' ) : null;
		if ( ! btn ) {
			return;
		}
		event.preventDefault();
		btn.classList.add( 'is-busy' );
		api.post( 'star', { id: btn.getAttribute( 'data-id' ) } )
			.then( function ( data ) {
				var on = !! data.starred;
				btn.classList.toggle( 'is-on', on );
				btn.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				var icon = btn.querySelector( '.dashicons' );
				if ( icon ) {
					icon.classList.toggle( 'dashicons-star-filled', on );
					icon.classList.toggle( 'dashicons-star-empty', ! on );
				}
			} )
			.catch( function ( err ) {
				api.toast( err.message, true );
			} )
			.finally( function () {
				btn.classList.remove( 'is-busy' );
			} );
	} );

	// Close dropdown menus on outside click or Escape.
	document.addEventListener( 'click', function ( event ) {
		document.querySelectorAll( '.uicf7-menu[open]' ).forEach( function ( menu ) {
			if ( ! menu.contains( event.target ) ) {
				menu.removeAttribute( 'open' );
			}
		} );
	} );
	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' !== event.key ) {
			return;
		}
		document.querySelectorAll( '.uicf7-menu[open]' ).forEach( function ( menu ) {
			menu.removeAttribute( 'open' );
			var summary = menu.querySelector( 'summary' );
			if ( summary ) {
				summary.focus();
			}
		} );
	} );
}() );
