/**
 * UniInbox for Contact Form 7 - inbox screen.
 *
 * Processes waiting messages one by one while the inbox is open and updates
 * each row in place, so analysis works even when WP-Cron is slow or disabled.
 */
( function () {
	'use strict';

	var cfg = window.uicf7Inbox || {};
	var i18n = cfg.i18n || {};
	var api = window.uicf7;
	var box = document.querySelector( '.uicf7-queue' );

	if ( box && cfg.aiReady && api ) {
		var text = box.querySelector( '.uicf7-queue__text' );
		var due = parseInt( box.getAttribute( 'data-due' ) || '0', 10 );
		var waiting = parseInt( box.getAttribute( 'data-waiting' ) || '0', 10 );
		var rounds = 0;

		var format = function ( template, count ) {
			return String( template || '' ).replace( '%d', count );
		};

		var show = function ( state, message ) {
			box.hidden = false;
			box.classList.remove( 'is-done', 'is-waiting' );
			if ( state ) {
				box.classList.add( state );
			}
			text.textContent = message;
		};

		var finish = function ( left ) {
			if ( left > 0 ) {
				show( 'is-waiting', format( i18n.waiting, left ) );
				return;
			}
			show( 'is-done', i18n.done );
			window.setTimeout( function () {
				box.hidden = true;
			}, 4000 );
		};

		var update = function ( item ) {
			var row = document.querySelector( 'tr[data-id="' + item.id + '"]' );
			if ( ! row || ! item.cells ) {
				return;
			}
			[ 'message', 'category', 'urgency' ].forEach( function ( col ) {
				var cell = row.querySelector( 'td.column-' + col );
				if ( cell && 'string' === typeof item.cells[ col ] ) {
					cell.innerHTML = item.cells[ col ];
				}
			} );
			row.classList.remove( 'is-updated' );
			void row.offsetWidth; // Restart the highlight animation.
			row.classList.add( 'is-updated' );
			if ( item.spam ) {
				row.style.opacity = '.45';
			}
		};

		var step = function () {
			rounds++;
			show( '', format( i18n.analyzing, Math.max( due, 1 ) ) );
			api.post( 'queue', {} )
				.then( function ( data ) {
					if ( data.item ) {
						update( data.item );
					}
					due = data.due;
					waiting = data.waiting;
					if ( data.item && due > 0 && rounds < 200 ) {
						window.setTimeout( step, 250 );
					} else {
						finish( waiting );
					}
				} )
				.catch( function ( err ) {
					show( 'is-waiting', err.message );
				} );
		};

		if ( due > 0 ) {
			step();
		} else if ( waiting > 0 ) {
			show( 'is-waiting', format( i18n.waiting, waiting ) );
		}
	}

	// Confirm bulk delete.
	var form = document.querySelector( '.uicf7-table-form' );
	if ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			var top = form.querySelector( 'select[name="action"]' );
			var bottom = form.querySelector( 'select[name="action2"]' );
			var submitter = event.submitter;
			var action = '';
			if ( submitter && 'doaction2' === submitter.id && bottom ) {
				action = bottom.value;
			} else if ( top ) {
				action = top.value;
			}
			var adminI18n = ( window.uicf7Admin && window.uicf7Admin.i18n ) || {};
			if ( 'delete' === action && ! window.confirm( adminI18n.confirmBulk || '' ) ) {
				event.preventDefault();
			}
		} );
	}
}() );
