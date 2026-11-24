/**
 * UniInbox for Contact Form 7 - settings screen: tabs, provider cards,
 * category and template repeaters, connection tests and unsaved-changes bar.
 */
( function () {
	'use strict';

	var form = document.getElementById( 'uicf7-settings-form' );
	var api = window.uicf7;
	var i18n = ( window.uicf7Settings && window.uicf7Settings.i18n ) || {};

	if ( ! form || ! api ) {
		return;
	}

	/* ---------------------------------------------------------------- tabs */

	var tabs = Array.prototype.slice.call( form.querySelectorAll( '.uicf7-tab' ) );
	var panels = Array.prototype.slice.call( form.querySelectorAll( '.uicf7-panel' ) );
	var current = 'ai';

	var activate = function ( name, focusTab ) {
		if ( ! panels.some( function ( p ) {
			return p.getAttribute( 'data-panel' ) === name;
		} ) ) {
			name = 'ai';
		}
		current = name;
		tabs.forEach( function ( tab ) {
			var on = tab.getAttribute( 'data-tab' ) === name;
			tab.classList.toggle( 'is-active', on );
			tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			tab.tabIndex = on ? 0 : -1;
			if ( on && focusTab ) {
				tab.focus();
			}
		} );
		panels.forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-panel' ) !== name;
		} );
		if ( window.history && window.history.replaceState ) {
			window.history.replaceState( null, '', '#' + name );
		}
	};

	tabs.forEach( function ( tab, index ) {
		tab.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			activate( tab.getAttribute( 'data-tab' ) );
		} );
		tab.addEventListener( 'keydown', function ( event ) {
			var next = null;
			if ( 'ArrowDown' === event.key || 'ArrowRight' === event.key ) {
				next = tabs[ ( index + 1 ) % tabs.length ];
			} else if ( 'ArrowUp' === event.key || 'ArrowLeft' === event.key ) {
				next = tabs[ ( index - 1 + tabs.length ) % tabs.length ];
			}
			if ( next ) {
				event.preventDefault();
				activate( next.getAttribute( 'data-tab' ), true );
			}
		} );
	} );

	form.querySelectorAll( '[data-goto]' ).forEach( function ( link ) {
		link.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			activate( link.getAttribute( 'data-goto' ) );
			window.scrollTo( { top: 0, behavior: 'smooth' } );
		} );
	} );

	var initial = ( window.location.hash || '' ).replace( '#', '' );
	if ( ! initial ) {
		try {
			initial = window.sessionStorage.getItem( 'uicf7Tab' ) || '';
		} catch ( e ) {}
	}
	activate( initial || 'ai' );

	/* ----------------------------------------------------- unsaved changes */

	var bar = form.querySelector( '.uicf7-savebar' );
	var status = bar ? bar.querySelector( '.uicf7-savebar__status' ) : null;
	var dirty = false;

	var markDirty = function () {
		dirty = true;
		if ( bar ) {
			bar.classList.add( 'is-dirty' );
			status.textContent = i18n.unsaved || '';
		}
	};

	form.addEventListener( 'input', markDirty );
	form.addEventListener( 'change', markDirty );
	form.addEventListener( 'submit', function () {
		dirty = false;
		try {
			window.sessionStorage.setItem( 'uicf7Tab', current );
		} catch ( e ) {}
	} );
	window.addEventListener( 'beforeunload', function ( event ) {
		if ( dirty ) {
			event.preventDefault();
			event.returnValue = '';
		}
	} );

	/* ----------------------------------------------------- provider cards */

	var showProvider = function () {
		var checked = form.querySelector( 'input[name$="[provider]"]:checked' );
		var value = checked ? checked.value : '';
		form.querySelectorAll( '.uicf7-provider-config' ).forEach( function ( box ) {
			box.hidden = box.getAttribute( 'data-provider' ) !== value;
		} );
	};
	form.querySelectorAll( 'input[name$="[provider]"]' ).forEach( function ( radio ) {
		radio.addEventListener( 'change', showProvider );
	} );
	showProvider();

	var aiToggle = document.getElementById( 'uicf7-ai-enabled' );
	var dimAi = function () {
		form.querySelectorAll( '[data-needs-ai]' ).forEach( function ( section ) {
			section.classList.toggle( 'is-dim', !! aiToggle && ! aiToggle.checked );
		} );
	};
	if ( aiToggle ) {
		aiToggle.addEventListener( 'change', dimAi );
		dimAi();
	}

	/* ---------------------------------------------------------- repeaters */

	form.querySelectorAll( '[data-add]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var name = btn.getAttribute( 'data-add' );
			var repeater = form.querySelector( '[data-repeater="' + name + '"]' );
			var template = document.getElementById( 'uicf7-tpl-' + name );
			if ( ! repeater || ! template ) {
				return;
			}
			var max = parseInt( repeater.getAttribute( 'data-max' ) || '0', 10 );
			if ( max && repeater.querySelectorAll( '[data-row]' ).length >= max ) {
				api.toast( i18n.maxItems, true );
				return;
			}
			var index = parseInt( repeater.getAttribute( 'data-next' ) || '0', 10 );
			repeater.setAttribute( 'data-next', String( index + 1 ) );

			var holder = document.createElement( 'div' );
			holder.innerHTML = template.innerHTML.split( '__INDEX__' ).join( String( index ) ).trim();
			var row = holder.firstElementChild;
			row.classList.add( 'is-new' );
			repeater.appendChild( row );

			var first = row.querySelector( 'input[type="text"]' );
			if ( first ) {
				first.focus();
			}
			markDirty();
		} );
	} );

	form.addEventListener( 'click', function ( event ) {
		var remove = event.target.closest( '[data-remove]' );
		if ( ! remove ) {
			return;
		}
		var row = remove.closest( '[data-row]' );
		if ( ! row ) {
			return;
		}
		var filled = Array.prototype.some.call( row.querySelectorAll( 'input[type="text"], textarea' ), function ( input ) {
			return '' !== input.value.trim();
		} );
		if ( filled && ! window.confirm( i18n.removeIt || '' ) ) {
			return;
		}
		row.remove();
		markDirty();
	} );

	/* --------------------------------------------------------------- tests */

	var value = function ( selector ) {
		var el = form.querySelector( selector );
		return el ? el.value : '';
	};

	form.querySelectorAll( '[data-test]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var result = btn.parentNode.querySelector( '.uicf7-test__result' );
			var action;
			var data;

			if ( 'ai' === btn.getAttribute( 'data-test' ) ) {
				var checked = form.querySelector( 'input[name$="[provider]"]:checked' );
				var provider = checked ? checked.value : '';
				action = 'test_ai';
				data = {
					provider: provider,
					key: value( '[data-key-for="' + provider + '"]' ),
					model: value( '[data-model-for="' + provider + '"]' )
				};
			} else {
				action = 'test_alert';
				data = {
					email: value( '[data-alert="email"]' ),
					slack: value( '[data-alert="slack"]' ),
					token: value( '[data-alert="token"]' ),
					chat: value( '[data-alert="chat"]' )
				};
			}

			btn.classList.add( 'is-busy' );
			result.className = 'uicf7-test__result';
			result.textContent = i18n.testing || '';

			api.post( action, data )
				.then( function ( res ) {
					result.classList.add( 'is-ok' );
					result.textContent = res.message;
				} )
				.catch( function ( err ) {
					result.classList.add( 'is-error' );
					result.textContent = err.message;
				} )
				.finally( function () {
					btn.classList.remove( 'is-busy' );
				} );
		} );
	} );
}() );
