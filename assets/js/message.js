/**
 * UniInbox for Contact Form 7 - message screen: rich-text reply editor,
 * templates, AI assist, attachments, CC/BCC and the internal-note composer.
 *
 * The editor progressively enhances a plain <textarea>: without JavaScript
 * the textarea still works. The server cleans all HTML with wp_kses.
 */
( function () {
	'use strict';

	var cfg = window.uicf7Message || {};
	var i18n = cfg.i18n || {};
	var api = window.uicf7;
	var form = document.getElementById( 'uicf7-reply-form' );
	var source = document.getElementById( 'uicf7-reply-html' );
	var toolbar = document.querySelector( '.uicf7-toolbar' );
	var wrap = document.querySelector( '.uicf7-editor-wrap' );
	var linkbar = document.querySelector( '.uicf7-linkbar' );
	var linkInput = document.getElementById( 'uicf7-link-url' );
	var editor = null;
	var savedRange = null;
	var dirty = false;

	if ( ! api || ! form ) {
		return;
	}

	/* ------------------------------------------------------------ helpers */

	var textToHtml = function ( text ) {
		return String( text )
			.replace( /\r\n?/g, '\n' )
			.split( /\n{2,}/ )
			.map( function ( para ) {
				return para.trim() ? '<p>' + api.escapeHtml( para.trim() ).replace( /\n/g, '<br>' ) + '</p>' : '';
			} )
			.join( '' );
	};

	var isEmpty = function () {
		return ! editor || '' === editor.textContent.replace( / /g, ' ' ).trim();
	};

	var saveSelection = function () {
		var sel = window.getSelection();
		if ( sel && sel.rangeCount && editor && editor.contains( sel.anchorNode ) ) {
			savedRange = sel.getRangeAt( 0 ).cloneRange();
		}
	};

	var placeCaretAtEnd = function () {
		var range = document.createRange();
		range.selectNodeContents( editor );
		range.collapse( false );
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange( range );
	};

	var restoreSelection = function () {
		editor.focus();
		if ( ! savedRange || ! editor.contains( savedRange.startContainer ) ) {
			placeCaretAtEnd();
			return;
		}
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange( savedRange );
	};

	var insertHtml = function ( html ) {
		if ( isEmpty() ) {
			editor.innerHTML = html;
			editor.focus();
			placeCaretAtEnd();
		} else {
			restoreSelection();
			document.execCommand( 'insertHTML', false, html );
		}
		dirty = true;
	};

	var updateState = function () {
		if ( ! toolbar ) {
			return;
		}
		[ 'bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList' ].forEach( function ( cmd ) {
			var btn = toolbar.querySelector( '[data-cmd="' + cmd + '"]' );
			if ( btn ) {
				var on = false;
				try {
					on = document.queryCommandState( cmd );
				} catch ( e ) {}
				btn.classList.toggle( 'is-active', !! on );
				btn.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			}
		} );
	};

	var closeMenus = function () {
		document.querySelectorAll( '.uicf7-menu[open]' ).forEach( function ( menu ) {
			menu.removeAttribute( 'open' );
		} );
	};

	/* ------------------------------------------------------------- editor */

	if ( source && toolbar && wrap ) {
		editor = document.createElement( 'div' );
		editor.className = 'uicf7-editor';
		editor.contentEditable = 'true';
		editor.setAttribute( 'role', 'textbox' );
		editor.setAttribute( 'aria-multiline', 'true' );
		editor.setAttribute( 'aria-label', i18n.body || '' );
		editor.setAttribute( 'data-placeholder', i18n.placeholder || '' );
		editor.innerHTML = source.value; // Already cleaned on the server.
		if ( isEmpty() ) {
			editor.innerHTML = '';
		}

		source.hidden = true;
		source.parentNode.insertBefore( editor, source.nextSibling );
		toolbar.hidden = false;

		try {
			document.execCommand( 'defaultParagraphSeparator', false, 'p' );
		} catch ( e ) {}

		editor.addEventListener( 'input', function () {
			dirty = true;
			if ( isEmpty() && ! editor.querySelector( 'li, blockquote' ) ) {
				editor.innerHTML = '';
			}
		} );
		editor.addEventListener( 'keyup', function () {
			saveSelection();
			updateState();
		} );
		editor.addEventListener( 'mouseup', function () {
			saveSelection();
			updateState();
		} );
		editor.addEventListener( 'blur', saveSelection );
		document.addEventListener( 'selectionchange', function () {
			if ( document.activeElement === editor ) {
				saveSelection();
				updateState();
			}
		} );

		// Paste as clean text so styles from other apps do not leak in.
		editor.addEventListener( 'paste', function ( event ) {
			var data = event.clipboardData || window.clipboardData;
			if ( ! data ) {
				return;
			}
			var text = data.getData( 'text/plain' );
			event.preventDefault();
			if ( -1 === text.indexOf( '\n' ) ) {
				document.execCommand( 'insertText', false, text );
			} else {
				document.execCommand( 'insertHTML', false, textToHtml( text ) );
			}
		} );

		editor.addEventListener( 'keydown', function ( event ) {
			if ( ( event.ctrlKey || event.metaKey ) && 'k' === event.key.toLowerCase() ) {
				event.preventDefault();
				openLink();
			}
		} );

		// Keep the text selection when clicking toolbar buttons.
		toolbar.addEventListener( 'mousedown', function ( event ) {
			if ( event.target.closest( '[data-cmd]' ) ) {
				event.preventDefault();
			}
		} );

		toolbar.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '[data-cmd]' );
			if ( ! btn ) {
				return;
			}
			event.preventDefault();
			var cmd = btn.getAttribute( 'data-cmd' );
			if ( 'link' === cmd ) {
				openLink();
				return;
			}
			restoreSelection();
			if ( 'quote' === cmd ) {
				var current = String( document.queryCommandValue( 'formatBlock' ) || '' ).toLowerCase();
				document.execCommand( 'formatBlock', false, 'blockquote' === current ? '<p>' : '<blockquote>' );
			} else if ( 'removeFormat' === cmd ) {
				document.execCommand( 'removeFormat' );
				document.execCommand( 'unlink' );
				var block = String( document.queryCommandValue( 'formatBlock' ) || '' ).toLowerCase();
				if ( 'blockquote' === block ) {
					document.execCommand( 'formatBlock', false, '<p>' );
				}
			} else {
				document.execCommand( cmd, false, null );
			}
			dirty = true;
			saveSelection();
			updateState();
		} );
	}

	/* -------------------------------------------------------------- links */

	var currentAnchor = function () {
		var node = savedRange ? savedRange.commonAncestorContainer : null;
		while ( node && node !== editor ) {
			if ( 1 === node.nodeType && 'A' === node.tagName ) {
				return node;
			}
			node = node.parentNode;
		}
		return null;
	};

	var openLink = function () {
		if ( ! linkbar || ! editor ) {
			return;
		}
		saveSelection();
		var anchor = currentAnchor();
		linkInput.value = anchor ? anchor.getAttribute( 'href' ) : '';
		linkbar.hidden = false;
		linkInput.focus();
	};

	var closeLink = function () {
		linkbar.hidden = true;
		restoreSelection();
	};

	var applyLink = function () {
		var url = linkInput.value.trim();
		if ( url && ! /^(https?:\/\/|mailto:)/i.test( url ) ) {
			if ( /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( url ) ) {
				url = 'mailto:' + url;
			} else if ( /^[\w-]+(\.[\w-]+)+(\/\S*)?$/.test( url ) ) {
				url = 'https://' + url;
			} else {
				api.toast( i18n.badLink, true );
				linkInput.focus();
				return;
			}
		}
		linkbar.hidden = true;
		restoreSelection();
		if ( ! url ) {
			document.execCommand( 'unlink' );
		} else if ( savedRange && savedRange.collapsed && ! currentAnchor() ) {
			var label = url.replace( /^mailto:/i, '' );
			document.execCommand( 'insertHTML', false, '<a href="' + api.escapeHtml( url ) + '">' + api.escapeHtml( label ) + '</a>&nbsp;' );
		} else {
			document.execCommand( 'createLink', false, url );
		}
		dirty = true;
	};

	if ( linkbar ) {
		linkbar.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '[data-link]' );
			if ( ! btn ) {
				return;
			}
			if ( 'apply' === btn.getAttribute( 'data-link' ) ) {
				applyLink();
			} else {
				closeLink();
			}
		} );
		linkInput.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				applyLink();
			} else if ( 'Escape' === event.key ) {
				event.preventDefault();
				closeLink();
			}
		} );
	}

	/* ----------------------------------------------------------- templates */

	document.querySelectorAll( '[data-template]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var id = btn.getAttribute( 'data-template' );
			var tpl = ( cfg.templates || [] ).filter( function ( t ) {
				return t.id === id;
			} )[ 0 ];
			closeMenus();
			if ( ! tpl ) {
				return;
			}
			if ( editor ) {
				insertHtml( tpl.html );
			} else if ( source ) {
				source.value += ( source.value ? '\n\n' : '' ) + tpl.html;
			}
		} );
	} );

	/* ----------------------------------------------------------- AI assist */

	document.querySelectorAll( '[data-assist]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			closeMenus();
			if ( ! editor ) {
				return;
			}
			var mode = btn.getAttribute( 'data-assist' );
			wrap.classList.add( 'is-busy' );
			wrap.setAttribute( 'data-busy', i18n.writing || '' );
			editor.classList.add( 'is-busy' );
			api.post( 'assist', { id: cfg.id, mode: mode, text: editor.innerText || '' } )
				.then( function ( data ) {
					editor.innerHTML = data.html;
					dirty = true;
					editor.focus();
					placeCaretAtEnd();
				} )
				.catch( function ( err ) {
					api.toast( err.message, true );
				} )
				.finally( function () {
					wrap.classList.remove( 'is-busy' );
					editor.classList.remove( 'is-busy' );
				} );
		} );
	} );

	/* ------------------------------------------------------------ CC / BCC */

	document.querySelectorAll( '[data-reveal]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var row = document.getElementById( btn.getAttribute( 'data-reveal' ) );
			if ( ! row ) {
				return;
			}
			row.hidden = false;
			btn.hidden = true;
			var input = row.querySelector( 'input' );
			if ( input ) {
				input.focus();
			}
		} );
	} );

	/* --------------------------------------------------------- attachments */

	var attachBtn = form.querySelector( '[data-attach]' );
	var list = form.querySelector( '[data-files]' );
	var idsInput = form.querySelector( 'input[name="attachment_ids"]' );
	var files = [];
	var frame = null;

	var renderFiles = function () {
		list.innerHTML = '';
		files.forEach( function ( file ) {
			var li = document.createElement( 'li' );
			li.className = 'uicf7-file';
			li.innerHTML = '<span class="dashicons dashicons-paperclip" aria-hidden="true"></span>';

			var name = document.createElement( 'span' );
			name.textContent = file.name;
			li.appendChild( name );

			if ( file.size ) {
				var size = document.createElement( 'span' );
				size.className = 'uicf7-muted';
				size.textContent = file.size;
				li.appendChild( size );
			}

			var remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'uicf7-file__remove';
			remove.setAttribute( 'aria-label', ( i18n.remove || '' ) + ' ' + file.name );
			remove.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
			remove.addEventListener( 'click', function () {
				files = files.filter( function ( f ) {
					return f.id !== file.id;
				} );
				renderFiles();
			} );
			li.appendChild( remove );
			list.appendChild( li );
		} );
		idsInput.value = files.map( function ( f ) {
			return f.id;
		} ).join( ',' );
	};

	if ( attachBtn && list && idsInput ) {
		attachBtn.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			if ( ! frame ) {
				frame = window.wp.media( {
					title: i18n.attachTitle,
					button: { text: i18n.attachBtn },
					multiple: true
				} );
				frame.on( 'select', function () {
					var limitHit = false;
					frame.state().get( 'selection' ).toJSON().forEach( function ( item ) {
						if ( files.some( function ( f ) {
							return f.id === item.id;
						} ) ) {
							return;
						}
						if ( files.length >= ( cfg.maxAttachments || 5 ) ) {
							limitHit = true;
							return;
						}
						files.push( {
							id: item.id,
							name: item.filename || item.title || String( item.id ),
							size: item.filesizeHumanReadable || ''
						} );
					} );
					if ( limitHit ) {
						api.toast( i18n.maxFiles, true );
					}
					renderFiles();
					dirty = true;
				} );
			}
			frame.open();
		} );
	}

	/* ------------------------------------------------------- composer tabs */

	var noteForm = document.getElementById( 'uicf7-note-form' );
	document.querySelectorAll( '.uicf7-segmented [data-mode]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var note = 'note' === btn.getAttribute( 'data-mode' );
			document.querySelectorAll( '.uicf7-segmented [data-mode]' ).forEach( function ( other ) {
				var on = other === btn;
				other.classList.toggle( 'is-active', on );
				other.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
			form.hidden = note;
			if ( noteForm ) {
				noteForm.hidden = ! note;
				if ( note ) {
					noteForm.querySelector( 'textarea' ).focus();
				}
			}
		} );
	} );

	/* -------------------------------------------------------------- submit */

	form.addEventListener( 'submit', function ( event ) {
		if ( editor ) {
			source.value = isEmpty() ? '' : editor.innerHTML;
		}
		var submitter = event.submitter;
		if ( submitter && 'send_reply' === submitter.value ) {
			if ( editor ? isEmpty() : '' === source.value.trim() ) {
				event.preventDefault();
				api.toast( i18n.empty, true );
				return;
			}
			if ( ! window.confirm( i18n.sendConfirm ) ) {
				event.preventDefault();
				return;
			}
		}
		dirty = false;
	} );

	form.addEventListener( 'input', function ( event ) {
		if ( event.target !== editor ) {
			dirty = true;
		}
	} );

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( dirty && ! isEmpty() ) {
			event.preventDefault();
			event.returnValue = i18n.leave || '';
		}
	} );
}() );
