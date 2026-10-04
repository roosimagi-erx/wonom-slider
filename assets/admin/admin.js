/* Wonom Slider – admin editor. Vanilla JS + jQuery UI sortable, talks to the REST API. */
( function ( $ ) {
	'use strict';

	var CFG = window.WONOM_SLIDER || {};
	var I = CFG.i18n || {};
	var state = CFG.state || { slides: [], settings: {}, advanced: {}, languages: [] };
	var app = document.getElementById( 'wonom-slider-app' );
	if ( ! app ) { return; }

	var dirty = { slides: false, settings: false, advanced: false };
	var openId = null;
	var langTab = {}; // slideId -> language slug
	var tab = ( location.hash || '#slides' ).replace( '#', '' );
	if ( [ 'slides', 'calendar', 'settings' ].indexOf( tab ) < 0 ) { tab = 'slides'; }
	var preview = { open: true, device: 'desktop', all: true, lang: '' };
	try { preview.open = window.localStorage.getItem( 'wonom_slider_preview' ) !== '0'; } catch ( e ) { /* private mode */ }
	var calView = null;
	var saveBtn = document.getElementById( 'wonom-save' );
	var dirtyEl = document.getElementById( 'wonom-dirty' );

	/* ------------------------------------------------------------------ utils */

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function sprintf( str ) {
		var args = [].slice.call( arguments, 1 ), i = 0;
		return String( str ).replace( /%(\d+\$)?[sd]/g, function ( m, n ) {
			var idx = n ? parseInt( n, 10 ) - 1 : i++;
			return args[ idx ] == null ? '' : args[ idx ];
		} );
	}
	function pad( n ) { return ( n < 10 ? '0' : '' ) + n; }
	function uid() { return 's' + Math.random().toString( 16 ).slice( 2, 10 ); }
	function clone( o ) { return JSON.parse( JSON.stringify( o ) ); }
	function findSlide( id ) {
		for ( var i = 0; i < state.slides.length; i++ ) { if ( state.slides[ i ].id === id ) { return state.slides[ i ]; } }
		return null;
	}
	function defaultLang() { return state.languages && state.languages.length ? state.languages[ 0 ].slug : ''; }
	function langName( slug ) {
		for ( var i = 0; i < state.languages.length; i++ ) { if ( state.languages[ i ].slug === slug ) { return state.languages[ i ].name; } }
		return slug.toUpperCase();
	}

	// ISO "YYYY-MM-DDTHH:MM" <-> parts <-> display "dd.mm.yyyy hh:mm"
	function parseIso( v ) {
		var m = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/.exec( v || '' );
		return m ? { y: +m[ 1 ], m: +m[ 2 ], d: +m[ 3 ], h: +m[ 4 ], mi: +m[ 5 ] } : null;
	}
	function toIso( p ) { return p.y + '-' + pad( p.m ) + '-' + pad( p.d ) + 'T' + pad( p.h ) + ':' + pad( p.mi ); }
	function toDisplay( iso ) { var p = parseIso( iso ); return p ? pad( p.d ) + '.' + pad( p.m ) + '.' + p.y + ' ' + pad( p.h ) + ':' + pad( p.mi ) : ''; }
	function parseDisplay( txt ) {
		txt = String( txt || '' ).trim();
		if ( ! txt ) { return null; }
		var m = /^(\d{1,2})[./-](\d{1,2})[./-](\d{4})(?:[\s,]+(\d{1,2})[:.](\d{1,2}))?$/.exec( txt );
		if ( m ) { return { y: +m[ 3 ], m: +m[ 2 ], d: +m[ 1 ], h: +( m[ 4 ] || 0 ), mi: +( m[ 5 ] || 0 ) }; }
		return parseIso( txt );
	}
	function isoToDate( iso ) { var p = parseIso( iso ); return p ? new Date( p.y, p.m - 1, p.d, p.h, p.mi ) : null; }
	function dateToIso( d ) { return toIso( { y: d.getFullYear(), m: d.getMonth() + 1, d: d.getDate(), h: d.getHours(), mi: d.getMinutes() } ); }
	function siteNow() {
		// state.now is the site-local time at page load; advance it by elapsed time.
		var base = isoToDate( state.now ) || new Date();
		return new Date( base.getTime() + ( Date.now() - loadedAt ) );
	}
	var loadedAt = Date.now();

	function hasContent( s ) {
		if ( s.bg_mode === 'collage' && Array.isArray( s.collage ) && s.collage.length ) { return true; }
		return !! ( s.image_url || s.heading || s.text || s.eyebrow || s.button_text );
	}
	function slideStatus( s ) {
		if ( ! s.enabled ) { return 'disabled'; }
		if ( ! hasContent( s ) ) { return 'noimage'; }
		var now = siteNow();
		var st = isoToDate( s.start ), en = isoToDate( s.end );
		if ( st && st > now ) { return 'scheduled'; }
		if ( en && en < now ) { return 'expired'; }
		return 'active';
	}
	function statusLabel( st ) {
		return { active: I.stActive, scheduled: I.stScheduled, expired: I.stExpired, disabled: I.stDisabled, noimage: I.stNoimage }[ st ] || st;
	}
	function scheduleSummary( s ) {
		var st = isoToDate( s.start ), en = isoToDate( s.end ), now = siteNow();
		if ( ! st && ! en ) { return I.always; }
		if ( st && en && en < st ) { return I.scheduleInvalid; }
		if ( en && en < now ) { return sprintf( I.endedAt, toDisplay( s.end ) ); }
		if ( st && st > now ) { return en ? sprintf( I.startsEnds, toDisplay( s.start ), toDisplay( s.end ) ) : sprintf( I.startsAt, toDisplay( s.start ) ); }
		return en ? sprintf( I.liveUntil, toDisplay( s.end ) ) : I.liveNow;
	}
	function durationText( s ) {
		var st = isoToDate( s.start ), en = isoToDate( s.end );
		if ( ! st || ! en || en <= st ) { return ''; }
		var hrs = Math.round( ( en - st ) / 36e5 );
		return sprintf( I.duration, hrs >= 48 ? sprintf( I.days, Math.round( hrs / 24 ) ) : sprintf( I.hours, hrs ) );
	}

	function api( path, method, body ) {
		return window.wp.apiFetch( {
			url: CFG.rest + path,
			method: method || 'GET',
			data: body,
			headers: { 'X-WP-Nonce': CFG.nonce }
		} );
	}

	function toast( msg, type ) {
		var box = document.getElementById( 'wonom-toasts' );
		if ( ! box ) { return; }
		var t = document.createElement( 'div' );
		t.className = 'wonom-toast is-' + ( type || 'info' );
		t.textContent = msg;
		box.appendChild( t );
		requestAnimationFrame( function () { t.classList.add( 'is-in' ); } );
		setTimeout( function () { t.classList.remove( 'is-in' ); setTimeout( function () { t.remove(); }, 300 ); }, 3200 );
	}

	function markDirty( what ) {
		dirty[ what ] = true;
		saveBtn.disabled = false;
		dirtyEl.hidden = false;
	}
	function isDirty() { return dirty.slides || dirty.settings || dirty.advanced; }
	function clearDirty() {
		dirty = { slides: false, settings: false, advanced: false };
		saveBtn.disabled = true;
		dirtyEl.hidden = true;
	}

	/* ------------------------------------------------------------------ save */

	function save() {
		if ( ! isDirty() ) { return Promise.resolve(); }
		saveBtn.disabled = true;
		saveBtn.textContent = I.saving;
		var chain = Promise.resolve();
		if ( dirty.slides ) {
			chain = chain.then( function () { return api( '/slides', 'POST', { slides: state.slides } ); } );
		}
		if ( dirty.settings ) {
			chain = chain.then( function () { return api( '/settings', 'POST', { settings: state.settings } ); } );
		}
		if ( dirty.advanced ) {
			chain = chain.then( function () { return api( '/advanced', 'POST', { advanced: state.advanced } ); } );
		}
		return chain.then( function ( res ) {
			if ( res && res.slides ) { applyState( res ); }
			clearDirty();
			saveBtn.textContent = I.save;
			toast( I.allSaved, 'ok' );
			render();
			refreshPreview();
		} ).catch( function ( err ) {
			saveBtn.disabled = false;
			saveBtn.textContent = I.save;
			toast( I.saveFailed + ( err && err.message ? ': ' + err.message : '' ), 'err' );
		} );
	}

	// PHP encodes empty associative arrays as [] – make sure map-like fields are real objects.
	function normalizeSlides( slides ) {
		( slides || [] ).forEach( function ( s ) {
			[ 'i18n', 'typo' ].forEach( function ( k ) {
				if ( ! s[ k ] || Array.isArray( s[ k ] ) || typeof s[ k ] !== 'object' ) { s[ k ] = {}; }
			} );
			if ( ! Array.isArray( s.collage ) ) { s.collage = s.collage && typeof s.collage === 'object' ? Object.keys( s.collage ).map( function ( k ) { return s.collage[ k ]; } ) : []; }
			if ( s.i18n ) { Object.keys( s.i18n ).forEach( function ( l ) { if ( ! s.i18n[ l ] || Array.isArray( s.i18n[ l ] ) ) { s.i18n[ l ] = {}; } } ); }
		} );
		return slides;
	}
	normalizeSlides( state.slides );

	function applyState( res ) {
		var newOpen = openId;
		state.slides = normalizeSlides( res.slides );
		state.settings = res.settings;
		state.advanced = res.advanced || state.advanced;
		state.cache = res.cache || state.cache;
		state.campaigns = res.campaigns !== undefined ? res.campaigns : state.campaigns;
		state.fonts = res.fonts || state.fonts;
		state.languages = res.languages || state.languages;
		state.now = res.now || state.now;
		loadedAt = Date.now();
		openId = newOpen && findSlide( newOpen ) ? newOpen : null;
	}

	saveBtn.addEventListener( 'click', save );
	document.addEventListener( 'keydown', function ( e ) {
		if ( ( e.ctrlKey || e.metaKey ) && e.key.toLowerCase() === 's' ) { e.preventDefault(); save(); }
	} );
	window.addEventListener( 'beforeunload', function ( e ) {
		if ( isDirty() ) { e.preventDefault(); e.returnValue = I.leaveConfirm; return I.leaveConfirm; }
	} );

	/* ------------------------------------------------------------------ tabs */

	var nav = document.getElementById( 'wonom-nav' );
	nav.addEventListener( 'click', function ( e ) {
		var a = e.target.closest( 'a[data-tab]' );
		if ( ! a ) { return; }
		e.preventDefault();
		tab = a.getAttribute( 'data-tab' );
		history.replaceState( null, '', '#' + tab );
		render();
	} );
	function syncNav() {
		[].forEach.call( nav.querySelectorAll( 'a' ), function ( a ) { a.classList.toggle( 'is-active', a.getAttribute( 'data-tab' ) === tab ); } );
	}

	/* ------------------------------------------------------------------ render */

	function render() {
		syncNav();
		if ( tab === 'calendar' ) { app.innerHTML = renderCalendar(); }
		else if ( tab === 'settings' ) { app.innerHTML = renderSettings(); afterSettingsRender(); }
		else { app.innerHTML = renderSlides(); afterSlidesRender(); }
	}

	/* ---------- slides tab ---------- */

	function renderSlides() {
		var h = '';
		h += '<div class="wonom-toolbar">';
		h += '<button type="button" class="button button-primary" data-action="add" data-type="regular">+ ' + esc( I.addSlide ) + '</button>';
		h += '<button type="button" class="button" data-action="add" data-type="campaign">+ ' + esc( I.addCampaign ) + '</button>';
		h += '<span class="wonom-spacer"></span>';
		h += '<button type="button" class="button' + ( preview.open ? ' is-on' : '' ) + '" data-action="toggle-preview"><span class="dashicons dashicons-' + ( preview.open ? 'hidden' : 'visibility' ) + '"></span> ' + esc( preview.open ? I.hidePreview : I.preview ) + '</button>';
		h += '</div>';

		if ( preview.open ) { h += renderPreview(); }

		if ( ! state.slides.length ) {
			h += '<div class="wonom-empty"><span class="dashicons dashicons-images-alt2"></span><p>' + esc( I.noSlides ) + '</p></div>';
		} else {
			h += '<div class="wonom-list" id="wonom-list">';
			state.slides.forEach( function ( s, i ) { h += renderCard( s, i ); } );
			h += '</div>';
		}
		return h;
	}

	function renderPreview() {
		var h = '<div class="wonom-card wonom-preview">';
		h += '<div class="wonom-preview-tools">';
		h += '<div class="wonom-switch" role="group">';
		[ [ 'desktop', I.desktop, 'desktop' ], [ 'tablet', I.tablet, 'tablet' ], [ 'mobile', I.mobile, 'smartphone' ] ].forEach( function ( d ) {
			h += '<button type="button" data-action="preview-device" data-device="' + d[ 0 ] + '" aria-pressed="' + ( preview.device === d[ 0 ] ) + '"><span class="dashicons dashicons-' + d[ 2 ] + '"></span> ' + esc( d[ 1 ] ) + '</button>';
		} );
		h += '</div>';
		if ( state.languages.length > 1 ) {
			h += '<div class="wonom-switch" role="group">';
			state.languages.forEach( function ( l ) {
				var on = ( preview.lang || defaultLang() ) === l.slug;
				h += '<button type="button" data-action="preview-lang" data-lang="' + esc( l.slug ) + '" aria-pressed="' + on + '">' + esc( l.name ) + '</button>';
			} );
			h += '</div>';
		}
		h += '<label class="wonom-check"><input type="checkbox" data-action="preview-all"' + ( preview.all ? ' checked' : '' ) + '> ' + esc( I.previewAll ) + '</label>';
		h += '<span class="wonom-spacer"></span>';
		h += '<span class="wonom-hint">' + esc( I.previewHint ) + '</span>';
		h += '<button type="button" class="button" data-action="preview-refresh"><span class="dashicons dashicons-update"></span> ' + esc( I.refresh ) + '</button>';
		h += '</div>';
		h += '<div class="wonom-preview-stage is-' + preview.device + '"><iframe id="wonom-preview-frame" title="' + esc( I.preview ) + '"></iframe></div>';
		h += '</div>';
		return h;
	}
	// The preview is fetched through REST and shown via srcdoc, so page optimisers
	// (lazy-load placeholders, delayed JavaScript) cannot interfere with it.
	var previewReq = 0;
	function refreshPreview() {
		var f = document.getElementById( 'wonom-preview-frame' );
		if ( ! f ) { return; }
		var req = ++previewReq;
		api( '/preview?all=' + ( preview.all ? 1 : 0 ) + ( preview.lang ? '&lang=' + encodeURIComponent( preview.lang ) : '' ) + '&t=' + Date.now() ).then( function ( res ) {
			if ( req !== previewReq || ! document.getElementById( 'wonom-preview-frame' ) ) { return; }
			var body = res.html || '<p style="font:14px/1.5 system-ui,sans-serif;color:#555;padding:40px;text-align:center">' + esc( I.stageEmpty ) + '</p>';
			f.srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
				+ '<link rel="stylesheet" href="' + esc( res.css ) + '">' + ( res.fonts ? '<link rel="stylesheet" href="' + esc( res.fonts ) + '">' : '' )
				+ '<style>html,body{margin:0;padding:0;background:#f3f4f6}' + ( res.custom_css || '' ) + '</style></head><body>' + body
				+ '<script src="' + esc( res.js ) + '"><\/script></body></html>';
		} ).catch( function ( e ) { toast( e && e.message ? e.message : 'Preview error', 'err' ); } );
	}

	function renderCard( s, i ) {
		var st = slideStatus( s );
		var open = openId === s.id;
		var thumb = s.thumb || s.image_url;
		if ( ! thumb && s.bg_mode === 'collage' && Array.isArray( s.collage ) && s.collage[ 0 ] ) { thumb = s.collage[ 0 ].thumb || s.collage[ 0 ].url; }
		var h = '<div class="wonom-slide-card' + ( open ? ' is-open' : '' ) + ' type-' + s.type + ' st-' + st + '" data-id="' + esc( s.id ) + '">';
		h += '<div class="wonom-slide-head">';
		h += '<span class="wonom-drag" title="' + esc( I.dragToReorder ) + '"><span class="dashicons dashicons-menu"></span></span>';
		h += '<span class="wonom-order">' + ( i + 1 ) + '</span>';
		var thumbInner;
		if ( s.preview ) {
			// Exact front-end markup, scaled down (480px virtual width → 160px).
			thumbInner = '<div class="wonom-thumb__scale">' + s.preview + '</div>';
		} else if ( thumb ) {
			thumbInner = '<img src="' + esc( thumb ) + '" alt="">';
		} else if ( hasContent( s ) ) {
			thumbInner = '<span class="wonom-thumb-text" style="background:' + esc( s.bg_color || '#1d2433' ) + ';color:' + esc( s.text_color || '#fff' ) + '">' + esc( ( s.heading || s.text || s.eyebrow || 'Aa' ).slice( 0, 14 ) ) + '</span>';
		} else {
			thumbInner = '<span class="dashicons dashicons-format-image"></span>';
		}
		h += '<div class="wonom-thumb' + ( s.preview ? ' wonom-thumb--live' : '' ) + '" data-action="open">' + thumbInner + '</div>';
		h += '<div class="wonom-slide-meta" data-action="open">';
		h += '<div class="wonom-slide-title">';
		if ( open ) {
			h += '<input type="text" class="wonom-name-inline js-name" data-field="name" value="' + esc( s.name ) + '" placeholder="' + esc( s.heading || I.untitled ) + '" title="' + esc( I.nameHint ) + '">';
		} else {
			h += '<strong class="js-name">' + esc( s.name || s.heading || I.untitled ) + '</strong>';
		}
		if ( s.type === 'campaign' ) { h += ' <span class="wonom-tag wonom-tag--campaign">' + esc( I.typeCampaign ) + '</span>'; }
		if ( s.source === 'campaign' && s.campaign_id ) { h += ' <span class="wonom-tag wonom-tag--linked" title="' + esc( sprintf( I.linkedFromCampaign, s.campaign_title || s.campaign_id ) ) + '"><span class="dashicons dashicons-admin-links"></span> ' + esc( I.gCampaigns ) + '</span>'; }
		h += '</div>';
		h += '<div class="wonom-slide-sub"><span class="wonom-pill wonom-pill--' + st + ' js-status">' + esc( statusLabel( st ) ) + '</span> <span class="js-sched">' + esc( scheduleSummary( s ) ) + '</span></div>';
		h += '</div>';
		h += '<div class="wonom-slide-actions">';
		h += '<label class="wonom-toggle" title="' + esc( I.enabled ) + '"><input type="checkbox" data-field="enabled"' + ( s.enabled ? ' checked' : '' ) + '><span></span></label>';
		h += '<button type="button" class="wonom-icon" data-action="move" data-dir="-1" title="' + esc( I.moveUp ) + '"' + ( i === 0 ? ' disabled' : '' ) + '><span class="dashicons dashicons-arrow-up-alt2"></span></button>';
		h += '<button type="button" class="wonom-icon" data-action="move" data-dir="1" title="' + esc( I.moveDown ) + '"' + ( i === state.slides.length - 1 ? ' disabled' : '' ) + '><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
		h += '<button type="button" class="wonom-icon" data-action="duplicate" title="' + esc( I.duplicate ) + '"><span class="dashicons dashicons-admin-page"></span></button>';
		h += '<button type="button" class="wonom-icon is-danger" data-action="delete" title="' + esc( I.delete ) + '"><span class="dashicons dashicons-trash"></span></button>';
		h += '<button type="button" class="button' + ( open ? '' : ' button-primary' ) + '" data-action="open">' + esc( open ? I.close : I.edit ) + '</button>';
		h += '</div>';
		h += '</div>';
		if ( open ) { h += renderEditor( s ); }
		h += '</div>';
		return h;
	}

	function field( label, inner, hint, cls ) {
		return '<div class="wonom-field' + ( cls ? ' ' + cls : '' ) + '"><label>' + esc( label ) + '</label>' + inner + ( hint ? '<span class="wonom-hint">' + hint + '</span>' : '' ) + '</div>';
	}
	function input( s, key, opts ) {
		opts = opts || {};
		var lang = opts.lang || '';
		var val = lang ? ( ( s.i18n && s.i18n[ lang ] && s.i18n[ lang ][ key ] ) || '' ) : ( s[ key ] == null ? '' : s[ key ] );
		var ph = opts.placeholder != null ? opts.placeholder : ( lang ? ( s[ key ] || '' ) : '' );
		if ( opts.textarea ) {
			return '<textarea rows="2" data-field="' + key + '"' + ( lang ? ' data-lang="' + esc( lang ) + '"' : '' ) + ' placeholder="' + esc( ph ) + '"' + ( opts.attrs || '' ) + '>' + esc( val ) + '</textarea>';
		}
		return '<input type="' + ( opts.type || 'text' ) + '" data-field="' + key + '"' + ( lang ? ' data-lang="' + esc( lang ) + '"' : '' ) + ' value="' + esc( val ) + '" placeholder="' + esc( ph ) + '"' + ( opts.attrs || '' ) + '>';
	}
	function color( s, key ) {
		return '<span class="wonom-color"><input type="color" data-field="' + key + '" value="' + esc( s[ key ] || '#000000' ) + '"><input type="text" class="wonom-color-hex" data-field="' + key + '" value="' + esc( s[ key ] || '' ) + '" maxlength="7"></span>';
	}
	function segmented( key, value, options, allowEmpty ) {
		var h = '<div class="wonom-switch wonom-switch--seg" role="group">';
		if ( allowEmpty ) {
			h += '<button type="button" data-seg="' + key + '" data-value="" aria-pressed="' + ( ! value ) + '">' + esc( I.sameAsDesktop ) + '</button>';
		}
		options.forEach( function ( o ) {
			h += '<button type="button" data-seg="' + key + '" data-value="' + o[ 0 ] + '" aria-pressed="' + ( value === o[ 0 ] ) + '"' + ( o[ 2 ] ? ' title="' + esc( o[ 1 ] ) + '"' : '' ) + '>' + ( o[ 2 ] ? '<span class="dashicons dashicons-' + o[ 2 ] + '"></span>' : esc( o[ 1 ] ) ) + '</button>';
		} );
		return h + '</div>';
	}
	function imageBox( s, base, label, hint ) {
		var url = s[ base + '_url' ];
		var thumb = base === 'image' ? ( s.thumb || url ) : ( s.mobile_thumb || url );
		var fx = base === 'image' ? s.focal_x : s.mobile_focal_x;
		var fy = base === 'image' ? s.focal_y : s.mobile_focal_y;
		var h = '<div class="wonom-imgbox" data-base="' + base + '">';
		h += '<label>' + esc( label ) + '</label>';
		if ( url ) {
			h += '<div class="wonom-imgwrap" data-action="focal" title="' + esc( I.focalHint ) + '"><img src="' + esc( thumb ) + '" alt=""><span class="wonom-focal" style="left:' + fx + '%;top:' + fy + '%"></span></div>';
			h += '<div class="wonom-imgmeta">' + ( s[ base + '_width' ] ? esc( sprintf( I.imageSize, s[ base + '_width' ], s[ base + '_height' ] ) ) : '' ) + '</div>';
			h += '<div class="wonom-imgactions"><button type="button" class="button" data-action="pick-image">' + esc( I.replace ) + '</button> <button type="button" class="button-link is-danger" data-action="remove-image">' + esc( I.remove ) + '</button></div>';
		} else {
			h += '<button type="button" class="wonom-imgpick" data-action="pick-image"><span class="dashicons dashicons-plus-alt2"></span>' + esc( I.chooseImage ) + '</button>';
		}
		if ( hint ) { h += '<span class="wonom-hint">' + esc( hint ) + '</span>'; }
		h += '</div>';
		return h;
	}

	function numInput( s, key, min, max, step ) {
		return '<input type="number" data-field="' + key + '" value="' + esc( s[ key ] ) + '" min="' + min + '" max="' + max + '" step="' + ( step || 0.5 ) + '">';
	}

	function renderEditor( s ) {
		var h = '<div class="wonom-editor">';

		/* Stage – live preview with draggable text block */
		h += '<section class="wonom-sec wonom-sec--stage"><h3><span class="dashicons dashicons-welcome-view-site"></span> ' + esc( I.stageTitle ) + '</h3>';
		h += '<div class="wonom-stage-tools"><div class="wonom-switch" role="group">';
		[ [ 'desktop', I.desktop, 'desktop' ], [ 'mobile', I.mobile, 'smartphone' ] ].forEach( function ( d ) {
			h += '<button type="button" data-action="stage-device" data-device="' + d[ 0 ] + '" aria-pressed="' + ( stage.device === d[ 0 ] ) + '"><span class="dashicons dashicons-' + d[ 2 ] + '"></span> ' + esc( d[ 1 ] ) + '</button>';
		} );
		h += '</div><span class="wonom-hint">' + esc( I.stageHint ) + '</span></div>';
		h += '<div class="wonom-stage is-' + stage.device + '" data-stage><div class="wonom-stage-empty">' + esc( I.stageLoading ) + '</div></div>';
		h += '</section>';

		/* Images */
		h += '<section class="wonom-sec"><h3><span class="dashicons dashicons-format-image"></span> ' + esc( I.secImages ) + '</h3>';
		h += field( I.bgMode, segmented( 'bg_mode', s.bg_mode || 'image', [ [ 'image', I.bgImage ], [ 'collage', I.bgCollage ] ] ) );
		if ( s.bg_mode === 'collage' ) {
			var col = Array.isArray( s.collage ) ? s.collage : [];
			h += '<div class="wonom-grid wonom-grid--4 wonom-collage-slots">';
			for ( var ci = 0; ci < 4; ci++ ) {
				var it = col[ ci ];
				h += '<div class="wonom-imgbox wonom-cslot" data-slot="' + ci + '"><label>' + esc( sprintf( I.collageSlot, ci + 1 ) ) + '</label>';
				if ( it ) {
					h += '<div class="wonom-imgwrap is-portrait" data-action="focal-collage" title="' + esc( I.focalHint ) + '"><img src="' + esc( it.thumb || it.url ) + '" alt=""><span class="wonom-focal" style="left:' + ( it.focal_x || 50 ) + '%;top:' + ( it.focal_y || 50 ) + '%"></span></div>';
					h += '<div class="wonom-imgmeta">' + ( it.width ? esc( sprintf( I.imageSize, it.width, it.height ) ) : '' ) + '</div>';
					h += '<div class="wonom-imgactions"><button type="button" class="button" data-action="pick-collage">' + esc( I.replace ) + '</button> <button type="button" class="button-link is-danger" data-action="remove-collage">' + esc( I.remove ) + '</button></div>';
				} else {
					h += '<button type="button" class="wonom-imgpick" data-action="pick-collage"><span class="dashicons dashicons-plus-alt2"></span>' + esc( ci === col.length ? I.collageAdd : I.chooseImage ) + '</button>';
				}
				h += '</div>';
			}
			h += '</div>';
			h += '<p class="wonom-hint">' + esc( I.collageHint ) + '</p>';
			h += '<div class="wonom-grid wonom-grid--3">';
			h += field( I.seam, '<select data-field="collage_seam">' + [ [ 'hard', I.seamHard ], [ 'fade', I.seamFade ], [ 'blur', I.seamBlur ] ].map( function ( o ) { return '<option value="' + o[ 0 ] + '"' + ( s.collage_seam === o[ 0 ] ? ' selected' : '' ) + '>' + esc( o[ 1 ] ) + '</option>'; } ).join( '' ) + '</select>' );
			h += field( I.collageGap, numInput( s, 'collage_gap', 0, 60, 1 ) );
			h += field( I.collageMobile, '<select data-field="collage_mobile">' + [ [ 'all', I.mAll ], [ 'first2', I.mFirst2 ], [ 'first1', I.mFirst1 ] ].map( function ( o ) { return '<option value="' + o[ 0 ] + '"' + ( s.collage_mobile === o[ 0 ] ? ' selected' : '' ) + '>' + esc( o[ 1 ] ) + '</option>'; } ).join( '' ) + '</select>' );
			h += '</div>';
		} else {
			h += '<div class="wonom-grid wonom-grid--2">';
			h += imageBox( s, 'image', I.desktopImage, I.recommended );
			h += imageBox( s, 'mobile_image', I.mobileImage, I.mobileHint );
			h += '</div>';
			h += '<p class="wonom-hint">' + esc( I.focalHint ) + '</p>';
		}
		h += '<div class="wonom-grid wonom-grid--2">' + field( I.frameWidth, numInput( s, 'frame_width', 0, 80, 1 ) ) + field( I.frameRadius, numInput( s, 'frame_radius', 0, 80, 1 ), esc( I.frameHint ) ) + '</div>';
		h += '</section>';

		/* Content with language tabs */
		h += '<section class="wonom-sec"><h3><span class="dashicons dashicons-editor-textcolor"></span> ' + esc( I.secContent ) + '</h3>';
		var cur = langTab[ s.id ] || defaultLang();
		if ( state.languages.length > 1 ) {
			h += '<div class="wonom-langbar"><div class="wonom-switch" role="group">';
			state.languages.forEach( function ( l ) {
				var filled = l.default || ( s.i18n && s.i18n[ l.slug ] && Object.keys( s.i18n[ l.slug ] ).length );
				h += '<button type="button" data-action="lang" data-lang="' + esc( l.slug ) + '" aria-pressed="' + ( cur === l.slug ) + '">' + esc( l.name ) + ( l.default ? ' <small>(' + esc( I.defaultLang ) + ')</small>' : '' ) + ( filled ? '' : ' <i class="wonom-dot"></i>' ) + '</button>';
			} );
			h += '</div><span class="wonom-spacer"></span>';
			if ( cur !== defaultLang() ) {
				h += '<button type="button" class="button" data-action="translate" data-lang="' + esc( cur ) + '"><span class="dashicons dashicons-translation"></span> ' + esc( sprintf( I.translateFrom, langName( defaultLang() ) ) ) + '</button>';
			}
			h += '</div>';
		}
		var L = cur === defaultLang() ? '' : cur;

		/* Content source: own texts or a Kampaaniariba campaign (locks texts, link and schedule). */
		var linked = s.source === 'campaign' && s.campaign_id > 0;
		if ( state.campaigns ) {
			h += field( I.source, segmented( 'source', s.source || 'own', [ [ 'own', I.sourceOwn ], [ 'campaign', I.sourceCampaign ] ] ) );
			if ( s.source === 'campaign' ) {
				if ( ! state.campaigns.length ) {
					h += '<p class="wonom-hint wonom-warn">' + esc( I.campaignNone ) + '</p>';
				} else {
					var opts = '<option value="0">' + esc( I.campaignPick ) + '</option>';
					state.campaigns.forEach( function ( c ) {
						var stLabel = { live: I.cLive, upcoming: I.cUpcoming, ended: I.cEnded, off: I.cOff }[ c.status ] || c.status;
						opts += '<option value="' + c.id + '"' + ( ( s.campaign_id | 0 ) === c.id ? ' selected' : '' ) + '>' + esc( c.title ) + ' (' + esc( stLabel ) + ( c.start ? ', ' + esc( toDisplay( c.start ) ) : '' ) + ')</option>';
					} );
					h += field( I.campaignSelect, '<select data-campaign>' + opts + '</select>' );
				}
				if ( linked ) {
					h += '<p class="wonom-hint">' + esc( I.campaignLocked ) + ( s.campaign_edit_url ? ' <a href="' + esc( s.campaign_edit_url ) + '" target="_blank" rel="noopener">' + esc( I.editCampaign ) + ' ↗</a>' : '' ) + '</p>';
					if ( s.campaign_missing ) { h += '<p class="wonom-hint wonom-bad">' + esc( I.campaignMissing ) + '</p>'; }
				}
			}
		}
		var lock = linked ? ' disabled' : '';

		/* Two columns: content on the left, the look of that same row on the right. */
		var T = s.typo || {}, G = state.settings;
		function typoFont( key, label ) {
			var v = T[ key ] == null ? '' : T[ key ], isCustom = String( v ).indexOf( 'custom:' ) === 0;
			var h2 = '<div class="wonom-field"><label>' + esc( label ) + '</label><select data-typo="' + key + '">';
			h2 += '<option value=""' + ( v === '' ? ' selected' : '' ) + '>' + esc( I.asSettings ) + '</option>';
			h2 += '<option value="inherit"' + ( v === 'inherit' ? ' selected' : '' ) + '>' + esc( I.fontInherit ) + '</option>';
			( state.fonts || [] ).forEach( function ( f ) { h2 += '<option value="' + esc( f ) + '"' + ( v === f ? ' selected' : '' ) + ' style="font-family:\'' + esc( f ) + '\'">' + esc( f ) + '</option>'; } );
			h2 += '<option value="custom"' + ( isCustom ? ' selected' : '' ) + '>' + esc( I.fontCustom ) + '</option></select>';
			h2 += '<input type="text" data-typo-custom="' + key + '" value="' + esc( isCustom ? v.slice( 7 ) : '' ) + '" placeholder="' + esc( I.fontCustomPh ) + '"' + ( isCustom ? '' : ' hidden' ) + ' style="margin-top:6px"></div>';
			return h2;
		}
		function typoNum( key, label, min, max, globalVal ) {
			return field( label, '<input type="number" data-typo="' + key + '" value="' + esc( T[ key ] == null ? '' : T[ key ] ) + '" min="' + min + '" max="' + max + '" placeholder="' + esc( globalVal ) + '">' );
		}
		function typoSelect( key, label, options ) {
			var v = T[ key ] == null ? '' : String( T[ key ] );
			var h2 = '<select data-typo="' + key + '"><option value="">' + esc( I.asSettings ) + '</option>';
			options.forEach( function ( o ) { h2 += '<option value="' + esc( o[ 0 ] ) + '"' + ( v === String( o[ 0 ] ) ? ' selected' : '' ) + '>' + esc( o[ 1 ] ) + '</option>'; } );
			return field( label, h2 + '</select>' );
		}
		function pair( left, right ) {
			return '<div class="wonom-pair"><div class="wonom-pair__main">' + left + '</div><div class="wonom-pair__look">' + right + '</div></div>';
		}
		function look() { return '<div class="wonom-look">' + [].slice.call( arguments ).join( '' ) + '</div>'; }
		function lookHint( t ) { return '<p class="wonom-hint wonom-look-hint">' + esc( t ) + '</p>'; }

		h += '<div class="wonom-pairs">';
		h += '<div class="wonom-pair wonom-pair--head"><div>' + esc( I.colContent ) + '</div><div>' + esc( I.colTypo ) + '</div></div>';

		h += pair(
			field( I.badge, input( s, 'badge', { lang: L, attrs: lock } ) ),
			lookHint( I.badgeTypoHint )
		);
		h += pair(
			field( I.eyebrow, input( s, 'eyebrow', { lang: L, attrs: lock } ) ),
			look( typoNum( 'eyebrow_size', I.tSize, 8, 40, 14 ), typoNum( 'eyebrow_size_mobile', I.tSizeMobile, 8, 30, 12 ) ) + lookHint( I.eyebrowTypoHint )
		);
		h += pair(
			field( I.heading, input( s, 'heading', { lang: L, attrs: ' class="wonom-big"' + lock } ) ),
			look(
				typoFont( 'font_heading', I.tFont ),
				typoNum( 'heading_size', I.tSize, 12, 160, G.heading_size ),
				typoNum( 'heading_size_mobile', I.tSizeMobile, 12, 100, G.heading_size_mobile ),
				typoSelect( 'heading_weight', I.tWeight, [ [ 300, '300' ], [ 400, '400' ], [ 500, '500' ], [ 600, '600' ], [ 700, '700' ], [ 800, '800' ] ] ),
				typoSelect( 'heading_uppercase', I.tUpper, [ [ 1, I.yes ], [ 0, I.no ] ] ),
				typoNum( 'heading_spacing', I.tSpacing, -10, 60, G.heading_spacing ),
				field( I.tColor, color( s, 'text_color' ) ),
				typoNum( 'gap', I.tGapBelow, 0, 80, 14 )
			) + lookHint( I.tTextColorHint + ' ' + I.gapHint )
		);
		h += pair(
			field( I.text, input( s, 'text', { lang: L, textarea: true, attrs: lock } ) ),
			look( typoFont( 'font_text', I.tFont ), typoNum( 'text_size', I.tSize, 10, 60, G.text_size ), typoNum( 'text_size_mobile', I.tSizeMobile, 10, 40, G.text_size_mobile ) )
		);
		h += pair(
			field( I.buttonText, input( s, 'button_text', { lang: L } ) ) + field( I.buttonUrl, input( s, 'button_url', { lang: L, type: 'url', placeholder: L ? ( s.button_url || CFG.homeUrl ) : CFG.homeUrl, attrs: lock } ) ),
			look( field( I.tBg, color( s, 'button_bg' ) ), field( I.tColor, color( s, 'button_color' ) ), typoNum( 'button_radius', I.tRadius, 0, 100, G.button_radius ), typoNum( 'gap_button', I.tGapAbove, 0, 100, 22 ) )
		);
		h += pair(
			field( I.button2Text, input( s, 'button2_text', { lang: L } ) ) + field( I.button2Url, input( s, 'button2_url', { lang: L, type: 'url' } ) ),
			lookHint( I.button2TypoHint )
		);
		h += pair( field( I.alt, input( s, 'alt', { lang: L } ) ), '' );
		h += '</div>';
		if ( L ) { h += '<p class="wonom-hint">' + esc( I.fallbackHint ) + '</p>'; }
		h += '<p class="wonom-hint">' + esc( I.typoIntro ) + ' ' + esc( I.fontsHint ) + ' <button type="button" class="button-link" data-action="typo-reset">' + esc( I.typoReset ) + '</button></p>';
		h += '</section>';

		/* Design */
		h += '<section class="wonom-sec"><h3><span class="dashicons dashicons-art"></span> ' + esc( I.secDesign ) + '</h3>';
		h += '<div class="wonom-grid wonom-grid--2">';
		h += field( I.align, segmented( 'align', s.align, [ [ 'left', I.left, 'editor-alignleft' ], [ 'center', I.center, 'editor-aligncenter' ], [ 'right', I.right, 'editor-alignright' ] ] ) );
		h += field( I.valign, segmented( 'valign', s.valign, [ [ 'top', I.top, 'arrow-up-alt' ], [ 'middle', I.middle, 'minus' ], [ 'bottom', I.bottom, 'arrow-down-alt' ] ] ) );
		h += '</div>';
		h += '<div class="wonom-grid wonom-grid--3">';
		h += field( I.overlay, '<span class="wonom-range"><input type="range" min="0" max="90" step="5" data-field="overlay" value="' + ( s.overlay | 0 ) + '"><output>' + ( s.overlay | 0 ) + '%</output></span>', esc( I.overlayHint ) );
		h += field( I.overlayColor, color( s, 'overlay_color' ) );
		h += field( I.bgColor, color( s, 'bg_color' ), esc( I.bgHint ) );
		h += '</div>';
		h += field( I.posMode, segmented( 'pos_mode', s.pos_mode, [ [ 'grid', I.posGrid ], [ 'free', I.posFree ] ] ), esc( I.posHint ) );
		h += '<div class="wonom-grid wonom-grid--3 wonom-posrow js-pos-free" data-for="desktop"' + ( s.pos_mode === 'free' ? '' : ' hidden' ) + '>';
		h += field( I.posX, numInput( s, 'pos_x', 0, 100 ) ) + field( I.posY, numInput( s, 'pos_y', 0, 100 ) ) + field( I.posW, numInput( s, 'pos_w', 10, 100 ) );
		h += '</div>';
		h += '</section>';

		/* Mobile */
		h += '<section class="wonom-sec"><h3><span class="dashicons dashicons-smartphone"></span> ' + esc( I.secMobile ) + '</h3>';
		h += '<p class="wonom-hint">' + esc( I.mobileIntro ) + '</p>';
		h += '<div class="wonom-grid wonom-grid--2">';
		h += field( I.align, segmented( 'mobile_align', s.mobile_align, [ [ 'left', I.left, 'editor-alignleft' ], [ 'center', I.center, 'editor-aligncenter' ], [ 'right', I.right, 'editor-alignright' ] ], true ) );
		h += field( I.valign, segmented( 'mobile_valign', s.mobile_valign, [ [ 'top', I.top, 'arrow-up-alt' ], [ 'middle', I.middle, 'minus' ], [ 'bottom', I.bottom, 'arrow-down-alt' ] ], true ) );
		h += '</div>';
		h += field( I.posMode, segmented( 'mobile_pos_mode', s.mobile_pos_mode, [ [ 'grid', I.posGrid ], [ 'free', I.posFree ] ], true ) );
		h += '<div class="wonom-grid wonom-grid--3 wonom-posrow js-pos-free" data-for="mobile"' + ( s.mobile_pos_mode === 'free' ? '' : ' hidden' ) + '>';
		h += field( I.posX, numInput( s, 'mobile_pos_x', 0, 100 ) ) + field( I.posY, numInput( s, 'mobile_pos_y', 0, 100 ) ) + field( I.posW, numInput( s, 'mobile_pos_w', 10, 100 ) );
		h += '</div>';
		h += '<label class="wonom-check"><input type="checkbox" data-field="mobile_hide_text"' + ( s.mobile_hide_text ? ' checked' : '' ) + '> ' + esc( I.hideTextMobile ) + '</label>';
		h += '</section>';

		/* Schedule */
		h += '<section class="wonom-sec wonom-sec--schedule"><h3><span class="dashicons dashicons-calendar-alt"></span> ' + esc( I.secSchedule ) + '</h3>';
		if ( linked ) {
			h += '<p class="wonom-hint">' + esc( sprintf( I.linkedFromCampaign, s.campaign_title || s.campaign_id ) ) + ' – ' + esc( I.start ) + ': <strong>' + esc( s.start ? toDisplay( s.start ) : I.noStart ) + '</strong>, ' + esc( I.end ) + ': <strong>' + esc( s.end ? toDisplay( s.end ) : I.noEnd ) + '</strong>' + ( s.campaign_edit_url ? ' · <a href="' + esc( s.campaign_edit_url ) + '" target="_blank" rel="noopener">' + esc( I.editCampaign ) + ' ↗</a>' : '' ) + '</p>';
		} else {
			h += '<p class="wonom-hint">' + esc( I.scheduleIntro ) + ' ' + esc( sprintf( I.scheduleTz, state.timezone || '' ) ) + '</p>';
			h += '<div class="wonom-grid wonom-grid--2">';
			h += dateField( s, 'start', I.start, [ [ 'now', I.presetNow ], [ 'tomorrow', I.presetTomorrow ], [ 'monday', I.presetMonday ] ] );
			h += dateField( s, 'end', I.end, [ [ 'p7', I.preset7 ], [ 'p14', I.preset14 ], [ 'eom', I.presetEom ] ] );
			h += '</div>';
		}
		var invalid = isoToDate( s.start ) && isoToDate( s.end ) && isoToDate( s.end ) < isoToDate( s.start );
		h += '<div class="wonom-schedstatus' + ( invalid ? ' is-bad' : '' ) + '"><span class="wonom-pill wonom-pill--' + slideStatus( s ) + '">' + esc( statusLabel( slideStatus( s ) ) ) + '</span> <span class="js-sched2">' + esc( scheduleSummary( s ) ) + '</span> <span class="wonom-muted js-dur">' + esc( durationText( s ) ) + '</span></div>';
		h += '</section>';

		/* Advanced */
		h += '<section class="wonom-sec"><h3><span class="dashicons dashicons-admin-generic"></span> ' + esc( I.secAdvanced ) + '</h3>';
		h += field( I.typeLabel, segmented( 'type', s.type, [ [ 'regular', I.typeRegular ], [ 'campaign', I.typeCampaign ] ] ), esc( I.typeHint ) );
		h += '<label class="wonom-check"><input type="checkbox" data-field="link_whole_slide"' + ( s.link_whole_slide ? ' checked' : '' ) + '> ' + esc( I.linkWhole ) + '</label>';
		h += '<label class="wonom-check"><input type="checkbox" data-field="button_new_tab"' + ( s.button_new_tab ? ' checked' : '' ) + '> ' + esc( I.newTab ) + '</label>';
		h += '</section>';

		h += '<div class="wonom-editor-foot"><button type="button" class="button" data-action="open">' + esc( I.close ) + '</button><button type="button" class="button button-primary" data-action="save">' + esc( I.save ) + '</button></div>';
		h += '</div>';
		return h;
	}

	function dateField( s, key, label, presets ) {
		var h = '<div class="wonom-field"><label>' + esc( label ) + '</label>';
		h += '<span class="wonom-dt"><input type="text" class="wonom-date" data-date="' + key + '" value="' + esc( toDisplay( s[ key ] ) ) + '" placeholder="pp.kk.aaaa hh:mm" autocomplete="off">';
		h += '<button type="button" class="wonom-dtbtn" data-action="cal" data-date="' + key + '" aria-label="' + esc( I.openCalendar ) + '"><span class="dashicons dashicons-calendar-alt"></span></button></span>';
		h += '<span class="wonom-presets">';
		presets.forEach( function ( p ) { h += '<button type="button" class="button-link" data-action="preset" data-date="' + key + '" data-preset="' + p[ 0 ] + '">' + esc( p[ 1 ] ) + '</button>'; } );
		h += '<button type="button" class="button-link" data-action="preset" data-date="' + key + '" data-preset="clear">' + esc( I.clear ) + '</button>';
		h += '</span></div>';
		return h;
	}

	// Same-origin preview iframe: size it to the slider so no empty area remains below.
	function fitPreview() {
		var f = document.getElementById( 'wonom-preview-frame' );
		if ( ! f ) { return; }
		try {
			var d = f.contentDocument, sl = d && d.querySelector( '.wonom-slider' );
			if ( sl ) {
				f.style.height = Math.max( 120, Math.ceil( sl.getBoundingClientRect().height ) ) + 'px';
				[].forEach.call( d.images, function ( img ) { if ( ! img.complete ) { img.addEventListener( 'load', fitPreview ); } } );
			}
		} catch ( e ) { /* cross-origin – keep default height */ }
	}

	// Live miniatures: fit the scaled slide vertically and keep image requests small.
	function fitThumbs() {
		[].forEach.call( app.querySelectorAll( '.wonom-thumb--live' ), function ( box ) {
			var sc = box.querySelector( '.wonom-thumb__scale' ), sl = sc && sc.querySelector( '.wonom-slider' );
			if ( ! sl ) { return; }
			[].forEach.call( sc.querySelectorAll( 'img' ), function ( img ) {
				// Resolve theme lazy-load placeholders (no theme JS runs in the admin).
				var ds = img.getAttribute( 'data-src' ), dss = img.getAttribute( 'data-srcset' );
				if ( ds ) { img.setAttribute( 'src', ds ); img.removeAttribute( 'data-src' ); }
				if ( dss ) { img.setAttribute( 'srcset', dss ); img.removeAttribute( 'data-srcset' ); }
				img.classList.remove( 'wd-lazy-fade' );
				img.setAttribute( 'sizes', '480px' ); img.loading = 'lazy';
			} );
			[].forEach.call( sc.querySelectorAll( 'source[data-srcset]' ), function ( so ) { so.setAttribute( 'srcset', so.getAttribute( 'data-srcset' ) ); so.removeAttribute( 'data-srcset' ); } );
			var scale = 160 / 480;
			sc.style.transform = 'scale(' + scale + ')';
			// offsetHeight is the untransformed layout height of the 480px-wide slide.
			box.style.height = Math.max( 44, Math.min( 90, Math.round( sl.offsetHeight * scale ) ) ) + 'px';
		} );
	}

	function afterSlidesRender() {
		fitThumbs();
		var pf = document.getElementById( 'wonom-preview-frame' );
		if ( pf ) { pf.addEventListener( 'load', fitPreview ); refreshPreview(); }
		var list = document.getElementById( 'wonom-list' );
		if ( list && $.fn.sortable ) {
			$( list ).sortable( {
				handle: '.wonom-drag',
				axis: 'y',
				placeholder: 'wonom-slide-card wonom-placeholder',
				tolerance: 'pointer',
				update: function () {
					var ids = $( list ).children().map( function () { return this.getAttribute( 'data-id' ); } ).get();
					state.slides.sort( function ( a, b ) { return ids.indexOf( a.id ) - ids.indexOf( b.id ); } );
					markDirty( 'slides' );
					render();
				}
			} );
		}
		var open = app.querySelector( '.wonom-slide-card.is-open' );
		if ( open ) { mountStage( findSlide( open.getAttribute( 'data-id' ) ) ); }
	}

	/* ---------- stage: live preview rendered by the server, text block draggable ---------- */

	var stage = { device: 'desktop', iframe: null, slideId: null, timer: null, req: 0 };

	function mountStage( s ) {
		var host = app.querySelector( '[data-stage]' );
		if ( ! host || ! s ) { stage.iframe = null; stage.slideId = null; return; }
		stage.slideId = s.id;
		host.className = 'wonom-stage is-' + stage.device;
		renderStage( s );
	}

	function scheduleStage( s, delay ) {
		clearTimeout( stage.timer );
		stage.timer = setTimeout( function () { renderStage( s ); }, delay || 400 );
	}

	function renderStage( s ) {
		var host = app.querySelector( '[data-stage]' );
		if ( ! host || stage.slideId !== s.id ) { return; }
		var req = ++stage.req;
		api( '/render', 'POST', { slide: s, settings: state.settings, lang: langTab[ s.id ] || defaultLang() } ).then( function ( res ) {
			if ( req !== stage.req || stage.slideId !== s.id ) { return; }
			if ( ! res.html ) {
				host.innerHTML = '<div class="wonom-stage-empty">' + esc( I.stageEmpty ) + '</div>';
				stage.iframe = null;
				return;
			}
			var doc = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
				+ '<link rel="stylesheet" href="' + esc( res.css ) + '">'
				+ ( res.fonts ? '<link rel="stylesheet" href="' + esc( res.fonts ) + '">' : '' )
				+ '<style>html,body{margin:0;background:#eceff4;overflow:hidden}'
				+ '.wonom-slide__badge,.wonom-slide__eyebrow,.wonom-slide__heading,.wonom-slide__text,.wonom-slide__actions{animation:none!important;opacity:1!important;transform:none!important;transition:none!important}'
				+ '.wonom-slide__inner{outline:1px dashed rgba(255,255,255,.75);outline-offset:8px;cursor:move;user-select:none;-webkit-user-select:none}'
				+ '.wonom-slide__inner:hover,.wonom-slide__inner:focus{outline:2px solid #2563eb;outline-offset:8px}'
				+ '.wonom-slide__inner a{pointer-events:none}'
				+ '.wonom-stage-handle{position:absolute;right:-18px;top:50%;width:14px;height:34px;margin-top:-17px;border-radius:5px;background:#2563eb;box-shadow:0 1px 4px rgba(0,0,0,.3);cursor:ew-resize}'
				+ '.wonom-stage-guide{position:absolute;background:rgba(37,99,235,.85);pointer-events:none;display:none;z-index:9}'
				+ '.wonom-stage-guide.v{left:50%;top:0;bottom:0;width:1px}.wonom-stage-guide.h{top:50%;left:0;right:0;height:1px}'
				+ '.wonom-stage-guide.is-on{display:block}'
				+ ( res.custom_css || '' ) + '</style></head><body>' + res.html + '</body></html>';

			var iframe = document.createElement( 'iframe' );
			iframe.className = 'wonom-stage-frame';
			iframe.setAttribute( 'title', I.preview );
			host.innerHTML = '';
			host.appendChild( iframe );
			stage.iframe = iframe;
			iframe.addEventListener( 'load', function () {
				fitStage( iframe );
				attachStage( iframe.contentDocument, s );
				[].forEach.call( iframe.contentDocument.images, function ( img ) { img.addEventListener( 'load', function () { fitStage( iframe ); } ); } );
			} );
			iframe.srcdoc = doc;
		} ).catch( function ( e ) {
			host.innerHTML = '<div class="wonom-stage-empty">' + esc( e && e.message ? e.message : 'Error' ) + '</div>';
		} );
	}

	function fitStage( iframe ) {
		try {
			var d = iframe.contentDocument;
			var sl = d.querySelector( '.wonom-slider' );
			iframe.style.height = ( sl ? sl.getBoundingClientRect().height : d.body.scrollHeight ) + 'px';
		} catch ( e ) { /* ignore */ }
	}

	// Which position fields apply on the current stage device.
	function posKeys() {
		return stage.device === 'mobile' ? { mode: 'mobile_pos_mode', x: 'mobile_pos_x', y: 'mobile_pos_y', w: 'mobile_pos_w', vx: '--ws-mx', vy: '--ws-my', vw: '--ws-mw', cls: 'm-pos-' } : { mode: 'pos_mode', x: 'pos_x', y: 'pos_y', w: 'pos_w', vx: '--ws-x', vy: '--ws-y', vw: '--ws-w', cls: 'pos-' };
	}

	function applyFree( s, slideEl, x, y, w ) {
		var k = posKeys();
		x = Math.round( x * 10 ) / 10; y = Math.round( y * 10 ) / 10; w = Math.round( w * 10 ) / 10;
		s[ k.mode ] = 'free'; s[ k.x ] = x; s[ k.y ] = y; s[ k.w ] = w;
		slideEl.classList.remove( k.cls + 'grid' ); slideEl.classList.add( k.cls + 'free' );
		slideEl.style.setProperty( k.vx, x + '%' ); slideEl.style.setProperty( k.vy, y + '%' ); slideEl.style.setProperty( k.vw, w + '%' );
		if ( stage.device === 'desktop' && ! s.mobile_pos_mode ) {
			// Mobile inherits desktop while it has no own mode.
			slideEl.classList.remove( 'm-pos-grid' ); slideEl.classList.add( 'm-pos-free' );
			slideEl.style.setProperty( '--ws-mx', x + '%' ); slideEl.style.setProperty( '--ws-my', y + '%' ); slideEl.style.setProperty( '--ws-mw', w + '%' );
		}
	}

	function syncPosControls( s ) {
		var card = app.querySelector( '.wonom-slide-card.is-open' );
		if ( ! card ) { return; }
		[ 'pos_x', 'pos_y', 'pos_w', 'mobile_pos_x', 'mobile_pos_y', 'mobile_pos_w' ].forEach( function ( k ) {
			var inp = card.querySelector( '[data-field="' + k + '"]' );
			if ( inp && document.activeElement !== inp ) { inp.value = s[ k ]; }
		} );
		[ 'pos_mode', 'mobile_pos_mode' ].forEach( function ( k ) {
			[].forEach.call( card.querySelectorAll( '[data-seg="' + k + '"]' ), function ( b ) { b.setAttribute( 'aria-pressed', String( b.getAttribute( 'data-value' ) === ( s[ k ] || '' ) ) ); } );
		} );
		var dRow = card.querySelector( '.js-pos-free[data-for="desktop"]' ), mRow = card.querySelector( '.js-pos-free[data-for="mobile"]' );
		if ( dRow ) { dRow.hidden = s.pos_mode !== 'free'; }
		if ( mRow ) { mRow.hidden = s.mobile_pos_mode !== 'free'; }
	}

	function attachStage( doc, s ) {
		var slideEl = doc.querySelector( '.wonom-slide' ), inner = doc.querySelector( '.wonom-slide__inner' );
		if ( ! slideEl || ! inner ) { return; }
		inner.setAttribute( 'tabindex', '0' );
		var handle = doc.createElement( 'div' ); handle.className = 'wonom-stage-handle'; inner.appendChild( handle );
		var gv = doc.createElement( 'div' ); gv.className = 'wonom-stage-guide v'; slideEl.appendChild( gv );
		var gh = doc.createElement( 'div' ); gh.className = 'wonom-stage-guide h'; slideEl.appendChild( gh );

		function current() {
			var r = slideEl.getBoundingClientRect(), ir = inner.getBoundingClientRect();
			return { r: r, cx: ( ir.left + ir.width / 2 - r.left ) / r.width * 100, cy: ( ir.top + ir.height / 2 - r.top ) / r.height * 100, w: ir.width / r.width * 100 };
		}
		function commit() {
			markDirty( 'slides' );
			syncPosControls( s );
		}
		function snap( v, guide ) {
			var on = Math.abs( v - 50 ) < 1.5;
			guide.classList.toggle( 'is-on', on );
			return on ? 50 : Math.max( 0, Math.min( 100, v ) );
		}

		var drag = null;
		inner.addEventListener( 'pointerdown', function ( e ) {
			if ( e.button !== 0 ) { return; }
			var c = current();
			var isHandle = e.target === handle;
			if ( ( posKeys().mode === 'pos_mode' ? s.pos_mode : ( s.mobile_pos_mode || s.pos_mode ) ) !== 'free' || ( stage.device === 'mobile' && ! s.mobile_pos_mode ) ) {
				applyFree( s, slideEl, c.cx, c.cy, c.w );
			}
			drag = { handle: isHandle, r: c.r, cx: c.cx, cy: c.cy, w: c.w, offX: e.clientX - ( c.r.left + c.cx / 100 * c.r.width ), offY: e.clientY - ( c.r.top + c.cy / 100 * c.r.height ) };
			inner.setPointerCapture( e.pointerId );
			e.preventDefault();
		} );
		inner.addEventListener( 'pointermove', function ( e ) {
			if ( ! drag ) { return; }
			if ( drag.handle ) {
				var cxPx = drag.r.left + drag.cx / 100 * drag.r.width;
				var w = Math.max( 10, Math.min( 100, Math.abs( e.clientX - cxPx ) * 2 / drag.r.width * 100 ) );
				applyFree( s, slideEl, drag.cx, drag.cy, w );
			} else {
				var nx = snap( ( e.clientX - drag.offX - drag.r.left ) / drag.r.width * 100, gv );
				var ny = snap( ( e.clientY - drag.offY - drag.r.top ) / drag.r.height * 100, gh );
				applyFree( s, slideEl, nx, ny, drag.w );
			}
		} );
		function end() {
			if ( ! drag ) { return; }
			drag = null;
			gv.classList.remove( 'is-on' ); gh.classList.remove( 'is-on' );
			commit();
		}
		inner.addEventListener( 'pointerup', end );
		inner.addEventListener( 'pointercancel', end );
		inner.addEventListener( 'keydown', function ( e ) {
			var dx = { ArrowLeft: -1, ArrowRight: 1 }[ e.key ] || 0, dy = { ArrowUp: -1, ArrowDown: 1 }[ e.key ] || 0;
			if ( ! dx && ! dy ) { return; }
			e.preventDefault();
			var c = current(), step = e.shiftKey ? 5 : 1, k = posKeys();
			var x = s[ k.mode ] === 'free' ? parseFloat( s[ k.x ] ) : c.cx, y = s[ k.mode ] === 'free' ? parseFloat( s[ k.y ] ) : c.cy, w = s[ k.mode ] === 'free' ? parseFloat( s[ k.w ] ) : c.w;
			applyFree( s, slideEl, Math.max( 0, Math.min( 100, x + dx * step ) ), Math.max( 0, Math.min( 100, y + dy * step ) ), w );
			commit();
		} );
	}

	/**
	 * Per-slide typography → CSS custom property on the stage's slide element.
	 */
	var TYPO_VARS = { font_heading: '--ws-font-h', font_text: '--ws-font-t', heading_size: '--ws-h-size', heading_size_mobile: '--ws-h-size-mobile', text_size: '--ws-t-size', text_size_mobile: '--ws-t-size-mobile', heading_weight: '--ws-h-weight', heading_uppercase: '--ws-h-transform', heading_spacing: '--ws-h-spacing', gap: '--ws-gap', gap_button: '--ws-gap-btn', button_radius: '--ws-btn-radius', eyebrow_size: '--ws-e-size', eyebrow_size_mobile: '--ws-e-size-mobile' };
	var SERIF = [ 'Playfair Display', 'Cormorant Garamond', 'DM Serif Display' ];
	function fontCss( v ) {
		if ( ! v || v === 'inherit' ) { return 'inherit'; }
		if ( v.indexOf( 'custom:' ) === 0 ) { return v.slice( 7 ) || 'inherit'; }
		return '"' + v + '", ' + ( SERIF.indexOf( v ) >= 0 ? 'Georgia, serif' : 'system-ui, sans-serif' );
	}
	function stageTypo( s, key ) {
		if ( ! stage.iframe || stage.slideId !== s.id ) { return; }
		var doc; try { doc = stage.iframe.contentDocument; } catch ( e ) { return; }
		var el = doc && doc.querySelector( '.wonom-slide' );
		if ( ! el || ! TYPO_VARS[ key ] ) { return; }
		var v = s.typo ? s.typo[ key ] : undefined;
		if ( v == null || v === '' ) { el.style.removeProperty( TYPO_VARS[ key ] ); }
		else if ( /^font_/.test( key ) ) { el.style.setProperty( TYPO_VARS[ key ], fontCss( v ) ); }
		else if ( key === 'heading_uppercase' ) { el.style.setProperty( TYPO_VARS[ key ], String( v ) === '1' || v === true ? 'uppercase' : 'none' ); }
		else if ( key === 'heading_spacing' ) { el.style.setProperty( TYPO_VARS[ key ], ( parseInt( v, 10 ) / 100 ) + 'em' ); }
		else if ( key === 'heading_weight' ) { el.style.setProperty( TYPO_VARS[ key ], parseInt( v, 10 ) ); }
		else { el.style.setProperty( TYPO_VARS[ key ], parseInt( v, 10 ) + 'px' ); }
		if ( /^font_/.test( key ) ) { scheduleStage( s, 900 ); } // server adds the web-font stylesheet
		fitStage( stage.iframe );
	}

	/**
	 * Reflect a field change on the stage without a server round-trip where possible,
	 * then schedule a full re-render so the stage always ends up exact.
	 */
	function stageUpdate( s, key ) {
		if ( ! stage.iframe || stage.slideId !== s.id ) { return; }
		var doc; try { doc = stage.iframe.contentDocument; } catch ( e ) { return; }
		if ( ! doc ) { return; }
		var slideEl = doc.querySelector( '.wonom-slide' );
		var lang = langTab[ s.id ] || defaultLang();
		var textMap = { heading: '.wonom-slide__heading', text: '.wonom-slide__text', eyebrow: '.wonom-slide__eyebrow', badge: '.wonom-slide__badge', button_text: '.wonom-slide__button:not(.wonom-slide__button--secondary)', button2_text: '.wonom-slide__button--secondary' };
		var varMap = { text_color: '--ws-text', button_bg: '--ws-btn-bg', button_color: '--ws-btn-color', overlay_color: '--ws-overlay-color', bg_color: '--ws-bg', pos_x: '--ws-x', pos_y: '--ws-y', pos_w: '--ws-w', mobile_pos_x: '--ws-mx', mobile_pos_y: '--ws-my', mobile_pos_w: '--ws-mw' };
		if ( textMap[ key ] ) {
			var el = doc.querySelector( textMap[ key ] );
			var val = ( lang !== defaultLang() && s.i18n && s.i18n[ lang ] && s.i18n[ lang ][ key ] ) || s[ key ] || '';
			if ( el && val ) { el.textContent = val; scheduleStage( s, 1500 ); return; }
			scheduleStage( s, 600 ); return;
		}
		if ( slideEl && varMap[ key ] ) {
			var v = s[ key ];
			if ( /pos_/.test( key ) ) { v = parseFloat( v ) + '%'; }
			slideEl.style.setProperty( varMap[ key ], v );
			if ( key === 'overlay' ) { slideEl.style.setProperty( '--ws-overlay', s.overlay / 100 ); }
			scheduleStage( s, 1200 ); return;
		}
		if ( key === 'overlay' && slideEl ) { slideEl.style.setProperty( '--ws-overlay', s.overlay / 100 ); scheduleStage( s, 1200 ); return; }
		if ( ( key === 'frame_width' || key === 'frame_radius' ) && slideEl ) {
			var fw = parseInt( s.frame_width, 10 ) || 0, fr = parseInt( s.frame_radius, 10 ) || 0;
			slideEl.classList.toggle( 'has-frame', fw > 0 || fr > 0 );
			slideEl.style.setProperty( '--ws-frame-d', fw + 'px' );
			slideEl.style.setProperty( '--ws-frame-r', fr + 'px' );
			scheduleStage( s, 1500 ); return;
		}
		if ( key === 'collage_gap' && doc.querySelector( '.wonom-collage' ) ) {
			doc.querySelector( '.wonom-collage' ).style.setProperty( '--ws-cgap', ( parseInt( s.collage_gap, 10 ) || 0 ) + 'px' );
			scheduleStage( s, 1500 ); return;
		}
		scheduleStage( s, 500 );
	}

	/* ---------- calendar tab ---------- */

	function renderCalendar() {
		var now = siteNow();
		if ( ! calView ) { calView = { y: now.getFullYear(), m: now.getMonth() + 1 }; }
		var sched = state.slides.filter( function ( s ) { return s.start || s.end; } );
		var h = '<div class="wonom-card">';
		h += '<div class="wonom-cal-head"><h2>' + esc( I.months[ calView.m - 1 ] ) + ' ' + calView.y + '</h2><span class="wonom-cal-nav">';
		h += '<button type="button" class="button" data-action="cal-nav" data-dir="-1">&larr;</button> <button type="button" class="button" data-action="cal-nav" data-dir="0">' + esc( I.today ) + '</button> <button type="button" class="button" data-action="cal-nav" data-dir="1">&rarr;</button></span></div>';
		h += '<p class="wonom-hint">' + esc( I.calendarIntro ) + '</p>';
		h += '<div class="wonom-cal">';
		I.dow.forEach( function ( d ) { h += '<div class="wonom-cal-dow">' + esc( d ) + '</div>'; } );
		var first = new Date( calView.y, calView.m - 1, 1 );
		var shift = ( first.getDay() + 6 ) % 7;
		var colors = [ '#2563eb', '#d946ef', '#16a34a', '#f59e0b', '#ef4444', '#0891b2', '#7c3aed' ];
		for ( var i = 0; i < 42; i++ ) {
			var d = new Date( calView.y, calView.m - 1, 1 - shift + i );
			var dayStart = d.getTime(), dayEnd = dayStart + 864e5;
			var out = d.getMonth() + 1 !== calView.m;
			var today = d.toDateString() === now.toDateString();
			h += '<div class="wonom-cal-day' + ( out ? ' is-out' : '' ) + ( today ? ' is-today' : '' ) + '"><span class="wonom-cal-num">' + d.getDate() + '</span>';
			sched.forEach( function ( s, k ) {
				var st = isoToDate( s.start ), en = isoToDate( s.end );
				var a = st ? st.getTime() : -Infinity, b = en ? en.getTime() : Infinity;
				if ( a >= dayEnd || b < dayStart ) { return; }
				var dim = ( en && en < now ) || ! s.enabled;
				h += '<a href="#slides" class="wonom-cal-bar' + ( dim ? ' is-dim' : '' ) + ( s.type === 'campaign' ? ' is-campaign' : '' ) + '" style="--bar:' + colors[ k % colors.length ] + '" data-action="goto" data-id="' + esc( s.id ) + '" title="' + esc( s.name || s.heading || I.untitled ) + '">' + esc( s.name || s.heading || I.untitled ) + '</a>';
			} );
			h += '</div>';
		}
		h += '</div></div>';

		h += '<div class="wonom-card"><h2>' + esc( I.calendarList ) + '</h2>';
		if ( ! sched.length ) {
			h += '<p class="wonom-hint">' + esc( I.noScheduled ) + '</p>';
		} else {
			h += '<table class="widefat striped wonom-table"><thead><tr><th>' + esc( I.colSlide ) + '</th><th>' + esc( I.colStart ) + '</th><th>' + esc( I.colEnd ) + '</th><th>' + esc( I.colStatus ) + '</th></tr></thead><tbody>';
			sched.slice().sort( function ( a, b ) { return ( a.start || '' ) < ( b.start || '' ) ? -1 : 1; } ).forEach( function ( s ) {
				var st = slideStatus( s );
				h += '<tr><td><a href="#slides" data-action="goto" data-id="' + esc( s.id ) + '"><strong>' + esc( s.name || s.heading || I.untitled ) + '</strong></a>' + ( s.type === 'campaign' ? ' <span class="wonom-tag wonom-tag--campaign">' + esc( I.typeCampaign ) + '</span>' : '' ) + '</td>';
				h += '<td>' + esc( s.start ? toDisplay( s.start ) : I.noStart ) + '</td><td>' + esc( s.end ? toDisplay( s.end ) : I.noEnd ) + '</td>';
				h += '<td><span class="wonom-pill wonom-pill--' + st + '">' + esc( statusLabel( st ) ) + '</span></td></tr>';
			} );
			h += '</tbody></table>';
		}
		h += '</div>';
		return h;
	}

	/* ---------- settings tab ---------- */

	function sInput( key, type, attrs ) {
		var v = state.settings[ key ];
		if ( type === 'checkbox' ) {
			return '<label class="wonom-toggle wonom-toggle--inline"><input type="checkbox" data-setting="' + key + '"' + ( v ? ' checked' : '' ) + '><span></span></label>';
		}
		return '<input type="' + type + '" data-setting="' + key + '" value="' + esc( v == null ? '' : v ) + '"' + ( attrs || '' ) + '>';
	}
	function sSelect( key, options ) {
		var h = '<select data-setting="' + key + '">';
		options.forEach( function ( o ) { h += '<option value="' + esc( o[ 0 ] ) + '"' + ( String( state.settings[ key ] ) === String( o[ 0 ] ) ? ' selected' : '' ) + '>' + esc( o[ 1 ] ) + '</option>'; } );
		return h + '</select>';
	}
	function ratioField( key, label ) {
		var v = state.settings[ key ] || 'auto';
		var isAuto = v === 'auto';
		var h = '<div class="wonom-field"><label>' + esc( label ) + '</label><span class="wonom-inline">';
		h += '<select data-ratio-mode="' + key + '"><option value="auto"' + ( isAuto ? ' selected' : '' ) + '>' + esc( I.ratioAuto ) + '</option><option value="custom"' + ( isAuto ? '' : ' selected' ) + '>W x H</option></select>';
		h += '<input type="text" data-setting="' + key + '" value="' + esc( isAuto ? '' : v ) + '" placeholder="1920x660"' + ( isAuto ? ' hidden' : '' ) + '>';
		h += '</span></div>';
		return h;
	}
	function group( title, icon, inner ) {
		return '<div class="wonom-card wonom-settings-group"><h2><span class="dashicons dashicons-' + icon + '"></span> ' + esc( title ) + '</h2>' + inner + '</div>';
	}
	function toggleRow( key, label ) {
		return '<div class="wonom-row">' + sInput( key, 'checkbox' ) + '<span>' + esc( label ) + '</span></div>';
	}

	function renderSettings() {
		var S = state.settings, A = state.advanced || {};
		var h = '<div class="wonom-settings">';

		h += group( I.gEmbed, 'shortcode',
			'<p>' + esc( I.embedShortcode ) + '</p><p class="wonom-codeline"><code>[wonom_slider]</code><button type="button" class="button" data-action="copy" data-copy="[wonom_slider]">' + esc( I.copy ) + '</button></p>' +
			'<p>' + esc( I.embedElementor ) + '</p><p>' + esc( I.embedBlock ) + '</p>' +
			'<p>' + esc( I.embedPhp ) + '</p><p class="wonom-codeline"><code>&lt;?php if ( function_exists( \'wonom_slider\' ) ) { wonom_slider(); } ?&gt;</code><button type="button" class="button" data-action="copy" data-copy="<?php if ( function_exists( \'wonom_slider\' ) ) { wonom_slider(); } ?>">' + esc( I.copy ) + '</button></p>' );

		h += group( I.gPlayback, 'controls-play',
			toggleRow( 'autoplay', I.autoplay ) + toggleRow( 'loop', I.loop ) + toggleRow( 'pause_on_hover', I.pauseOnHover ) + toggleRow( 'ken_burns', I.kenBurns ) +
			'<div class="wonom-grid wonom-grid--3">' +
			field( I.interval, sInput( 'interval', 'number', ' min="1000" max="60000" step="500"' ) ) +
			field( I.speed, sInput( 'speed', 'number', ' min="100" max="5000" step="50"' ) ) +
			field( I.transition, sSelect( 'transition', [ [ 'fade', I.fade ], [ 'slide', I.slide ] ] ) ) +
			'</div>' );

		h += group( I.gNavigation, 'leftright',
			toggleRow( 'show_arrows', I.showArrows ) + toggleRow( 'show_dots', I.showDots ) + toggleRow( 'show_progress', I.showProgress ) +
			'<div class="wonom-grid wonom-grid--3" style="margin-top:12px">' +
			field( I.dotsPosition, sSelect( 'dots_position', [ [ 'center', I.center ], [ 'left', I.left ], [ 'right', I.right ] ] ) ) +
			field( I.dotsMobile, sSelect( 'dots_mobile', [ [ 'same', I.optSame ], [ 'left', I.left ], [ 'center', I.center ], [ 'right', I.right ], [ 'hidden', I.optHidden ] ] ) ) +
			field( I.arrowsMobile, sSelect( 'arrows_mobile', [ [ 'show', I.optShow ], [ 'hide', I.optHidden ] ] ) ) +
			'</div><p class="wonom-hint">' + esc( I.dotsHint ) + '</p>' );

		h += group( I.gLayout, 'align-wide',
			'<div class="wonom-grid wonom-grid--2">' + ratioField( 'ratio_desktop', I.ratioDesktop ) + ratioField( 'ratio_mobile', I.ratioMobile ) + '</div>' +
			'<p class="wonom-hint">' + esc( I.ratioHint ) + '</p>' +
			'<div class="wonom-grid wonom-grid--3">' +
			field( I.maxWidth, sInput( 'max_width', 'number', ' min="0" max="4000" step="10"' ) ) +
			field( I.breakpoint, sInput( 'mobile_breakpoint', 'number', ' min="320" max="1400" step="1"' ), esc( I.breakpointHint ) ) +
			field( I.contentWidth, sInput( 'content_max_width', 'number', ' min="200" max="2000" step="10"' ) ) +
			field( I.paddingMobile, sInput( 'padding_mobile', 'number', ' min="0" max="100"' ) ) +
			field( I.headingTag, sSelect( 'heading_tag', [ [ 'h2', 'H2' ], [ 'h1', 'H1' ], [ 'h3', 'H3' ], [ 'p', 'P' ] ] ), esc( I.headingTagHint ) ) +
			'</div>' );

		var C = state.cache || {};
		var cacheInner = '<div class="wonom-row"><label class="wonom-toggle wonom-toggle--inline"><input type="checkbox" data-adv="auto_purge"' + ( A.auto_purge ? ' checked' : '' ) + '><span></span></label><span>' + esc( I.autoPurge ) + '</span></div>';
		cacheInner += '<p class="wonom-hint">' + esc( C.detected && C.detected.length ? sprintf( I.cacheDetected, C.detected.join( ', ' ) ) : I.cacheNone ) + ( C.next_tick ? ' · ' + esc( sprintf( I.nextTick, C.next_tick ) ) : '' ) + '</p>';
		if ( C.wp_cron_off ) { cacheInner += '<p class="wonom-warn">' + esc( I.cronOff ) + '</p>'; }
		cacheInner += '<div class="wonom-grid wonom-grid--2">' + field( I.cfZone, '<input type="text" data-adv="cf_zone" value="' + esc( A.cf_zone || '' ) + '" autocomplete="off">' ) + field( I.cfToken, '<input type="text" data-adv="cf_token" value="' + esc( A.cf_token || '' ) + '" autocomplete="off">', esc( I.cfHint ) ) + '</div>';
		cacheInner += '<div class="wonom-row"><button type="button" class="button" data-action="purge">' + esc( I.purgeNow ) + '</button></div>';
		cacheInner += '<p class="wonom-hint">' + esc( I.delayJsHint ) + '</p><p class="wonom-codeline"><code>wonom-slider</code><button type="button" class="button" data-action="copy" data-copy="wonom-slider">' + esc( I.copy ) + '</button></p>';
		h += group( I.gCache, 'performance', cacheInner );

		if ( state.campaigns ) {
			var tplOpts = '<option value="">' + esc( I.noTemplate ) + '</option>';
			state.slides.forEach( function ( sl ) { if ( sl.source === 'campaign' ) { return; } tplOpts += '<option value="' + esc( sl.id ) + '"' + ( S.campaign_template_id === sl.id ? ' selected' : '' ) + '>' + esc( sl.name || sl.heading || sl.id ) + '</option>'; } );
			h += group( I.gCampaigns, 'megaphone', field( I.campaignTemplate, '<select data-setting="campaign_template_id">' + tplOpts + '</select>', esc( I.campaignTemplateHint ) ) );
		}

		var langInner = '';
		if ( CFG.langPlugin ) {
			langInner += '<p class="wonom-ok">' + esc( sprintf( I.languagesDetected, CFG.langPlugin ) ) + ' (' + esc( state.languages.map( function ( l ) { return l.name; } ).join( ', ' ) ) + ')</p>';
		} else {
			langInner += field( I.languagesList, sInput( 'languages', 'text', ' placeholder="et,en"' ), esc( I.languagesHint ) );
		}
		langInner += field( I.deeplKey, '<input type="text" data-adv="deepl_key" value="' + esc( A.deepl_key || '' ) + '" autocomplete="off">', esc( I.deeplHint ) );
		h += group( I.gLanguages, 'translation', langInner );

		var upd = '';
		upd += field( I.repo, '<input type="text" data-adv="update_repo" value="' + esc( A.update_repo_custom || '' ) + '" placeholder="' + esc( A.update_repo || '' ) + '">', esc( I.repoHint ) );
		upd += A.update_token_const
			? '<p class="wonom-hint">' + esc( I.tokenConst ) + '</p>'
			: field( I.token, '<input type="text" data-adv="update_token" value="' + esc( A.update_token || '' ) + '" autocomplete="off">', esc( I.tokenHint ) );
		upd += '<div class="wonom-row wonom-updrow"><button type="button" class="button" data-action="check-updates">' + esc( I.checkUpdates ) + '</button><span id="wonom-upd-status" class="wonom-muted">' + esc( sprintf( I.installed, CFG.version ) ) + '</span></div>';
		h += group( I.gUpdates, 'update', upd );

		h += group( I.gCss, 'editor-code',
			'<textarea rows="6" class="wonom-code" data-setting="custom_css" spellcheck="false">' + esc( S.custom_css || '' ) + '</textarea><span class="wonom-hint">' + esc( I.customCssHint ) + '</span>' );

		h += group( I.gData, 'database',
			'<div class="wonom-row"><label class="wonom-toggle wonom-toggle--inline"><input type="checkbox" data-adv="delete_on_uninstall"' + ( A.delete_on_uninstall ? ' checked' : '' ) + '><span></span></label><span>' + esc( I.deleteOnUninstall ) + '</span></div>' +
			'<div class="wonom-row"><button type="button" class="button" data-action="export">' + esc( I.export ) + '</button> <button type="button" class="button" data-action="import">' + esc( I.import ) + '</button><input type="file" id="wonom-import-file" accept="application/json" hidden></div>' );

		h += '</div>';
		return h;
	}

	function afterSettingsRender() {
		var f = document.getElementById( 'wonom-import-file' );
		if ( f ) {
			f.addEventListener( 'change', function () {
				var file = f.files[ 0 ];
				if ( ! file ) { return; }
				var r = new FileReader();
				r.onload = function () {
					var data;
					try { data = JSON.parse( r.result ); } catch ( e ) { toast( I.importFailed, 'err' ); return; }
					if ( ! data || data.plugin !== 'wonom-slider' || ! data.slides ) { toast( I.importFailed, 'err' ); return; }
					if ( ! window.confirm( I.importConfirm ) ) { return; }
					api( '/import', 'POST', { data: data } ).then( function ( res ) {
						applyState( res ); clearDirty(); toast( I.imported, 'ok' ); render();
					} ).catch( function ( e ) { toast( I.importFailed + ( e.message ? ': ' + e.message : '' ), 'err' ); } );
				};
				r.readAsText( file );
			} );
		}
	}

	/* ------------------------------------------------------------------ events */

	app.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-action], [data-seg]' );
		if ( ! btn || btn.disabled ) { return; }
		var card = btn.closest( '.wonom-slide-card' );
		var s = card ? findSlide( card.getAttribute( 'data-id' ) ) : null;

		if ( btn.hasAttribute( 'data-seg' ) && s ) {
			s[ btn.getAttribute( 'data-seg' ) ] = btn.getAttribute( 'data-value' );
			markDirty( 'slides' ); render(); return;
		}

		var action = btn.getAttribute( 'data-action' );
		switch ( action ) {
			case 'add': addSlide( btn.getAttribute( 'data-type' ) ); break;
			case 'open':
				if ( e.target.closest( 'input, label, .wonom-name-inline' ) ) { return; }
				openId = openId === s.id ? null : s.id; render();
				if ( openId ) { var c = app.querySelector( '.wonom-slide-card.is-open' ); if ( c ) { c.scrollIntoView( { behavior: 'smooth', block: 'start' } ); } }
				break;
			case 'save': save(); break;
			case 'move': moveSlide( s, parseInt( btn.getAttribute( 'data-dir' ), 10 ) ); break;
			case 'duplicate':
				var d = clone( s ); d.id = uid(); d.name = ( s.name || s.heading || I.untitled ) + I.copySuffix;
				state.slides.splice( state.slides.indexOf( s ) + 1, 0, d ); openId = d.id; markDirty( 'slides' ); render(); break;
			case 'delete':
				if ( window.confirm( I.confirmDelete ) ) { state.slides.splice( state.slides.indexOf( s ), 1 ); if ( openId === s.id ) { openId = null; } markDirty( 'slides' ); render(); }
				break;
			case 'pick-image': pickImage( s, btn.closest( '.wonom-imgbox' ).getAttribute( 'data-base' ) ); break;
			case 'pick-collage': pickCollage( s, parseInt( btn.closest( '.wonom-cslot' ).getAttribute( 'data-slot' ), 10 ) ); break;
			case 'remove-collage':
				s.collage.splice( parseInt( btn.closest( '.wonom-cslot' ).getAttribute( 'data-slot' ), 10 ), 1 );
				markDirty( 'slides' ); render(); break;
			case 'focal-collage':
				var cw = btn, cimg = cw.querySelector( 'img' ), cr = cimg.getBoundingClientRect();
				var slotIdx = parseInt( cw.closest( '.wonom-cslot' ).getAttribute( 'data-slot' ), 10 );
				var cfx = Math.round( Math.min( 100, Math.max( 0, ( e.clientX - cr.left ) / cr.width * 100 ) ) );
				var cfy = Math.round( Math.min( 100, Math.max( 0, ( e.clientY - cr.top ) / cr.height * 100 ) ) );
				s.collage[ slotIdx ].focal_x = cfx; s.collage[ slotIdx ].focal_y = cfy;
				var cdot = cw.querySelector( '.wonom-focal' ); cdot.style.left = cfx + '%'; cdot.style.top = cfy + '%';
				markDirty( 'slides' ); stageCollageFocal( s, slotIdx ); break;
			case 'remove-image':
				var base = btn.closest( '.wonom-imgbox' ).getAttribute( 'data-base' );
				s[ base + '_id' ] = 0; s[ base + '_url' ] = ''; s[ base + '_width' ] = 0; s[ base + '_height' ] = 0;
				if ( base === 'image' ) { s.thumb = ''; } else { s.mobile_thumb = ''; }
				markDirty( 'slides' ); render(); break;
			case 'focal':
				var wrap = btn, img = wrap.querySelector( 'img' ), r = img.getBoundingClientRect();
				var fx = Math.round( Math.min( 100, Math.max( 0, ( e.clientX - r.left ) / r.width * 100 ) ) );
				var fy = Math.round( Math.min( 100, Math.max( 0, ( e.clientY - r.top ) / r.height * 100 ) ) );
				var b2 = wrap.closest( '.wonom-imgbox' ).getAttribute( 'data-base' );
				if ( b2 === 'image' ) { s.focal_x = fx; s.focal_y = fy; } else { s.mobile_focal_x = fx; s.mobile_focal_y = fy; }
				var dot = wrap.querySelector( '.wonom-focal' ); dot.style.left = fx + '%'; dot.style.top = fy + '%';
				markDirty( 'slides' ); break;
			case 'lang': langTab[ s.id ] = btn.getAttribute( 'data-lang' ); render(); break;
			case 'translate': translateSlide( s, btn.getAttribute( 'data-lang' ), btn ); break;
			case 'cal': openCalendarPopup( s, btn.getAttribute( 'data-date' ), btn ); break;
			case 'preset': applyPreset( s, btn.getAttribute( 'data-date' ), btn.getAttribute( 'data-preset' ) ); break;
			case 'toggle-preview':
				preview.open = ! preview.open;
				try { window.localStorage.setItem( 'wonom_slider_preview', preview.open ? '1' : '0' ); } catch ( e2 ) { /* ignore */ }
				render(); break;
			case 'preview-device': preview.device = btn.getAttribute( 'data-device' ); render(); break;
			case 'preview-lang': preview.lang = btn.getAttribute( 'data-lang' ); render(); break;
			case 'preview-refresh': refreshPreview(); break;
			case 'cal-nav':
				var dir = parseInt( btn.getAttribute( 'data-dir' ), 10 );
				if ( dir === 0 ) { calView = null; } else { calView.m += dir; if ( calView.m < 1 ) { calView.m = 12; calView.y--; } if ( calView.m > 12 ) { calView.m = 1; calView.y++; } }
				render(); break;
			case 'goto': e.preventDefault(); tab = 'slides'; openId = btn.getAttribute( 'data-id' ); history.replaceState( null, '', '#slides' ); render();
				var oc = app.querySelector( '.wonom-slide-card.is-open' ); if ( oc ) { oc.scrollIntoView( { behavior: 'smooth', block: 'start' } ); } break;
			case 'copy':
				navigator.clipboard && navigator.clipboard.writeText( btn.getAttribute( 'data-copy' ) ).then( function () { toast( I.copied, 'ok' ); } ); break;
			case 'check-updates': checkUpdates( btn ); break;
			case 'stage-device': stage.device = btn.getAttribute( 'data-device' ); render(); break;
			case 'typo-reset': s.typo = {}; markDirty( 'slides' ); render(); break;
			case 'purge':
				btn.disabled = true; var pl = btn.textContent; btn.textContent = I.purging;
				api( '/purge', 'POST', {} ).then( function ( r ) {
					btn.disabled = false; btn.textContent = pl;
					toast( r.done && r.done.length ? sprintf( I.purged, r.done.join( ', ' ) ) : I.purgedNone, r.errors && r.errors.length ? 'err' : 'ok' );
					if ( r.errors && r.errors.length ) { toast( r.errors.join( ' · ' ), 'err' ); }
				} ).catch( function ( e ) { btn.disabled = false; btn.textContent = pl; toast( e.message || 'Error', 'err' ); } );
				break;
			case 'export': exportJson(); break;
			case 'import': document.getElementById( 'wonom-import-file' ).click(); break;
		}
	} );

	// Text inputs: update state live without re-rendering (keeps focus).
	app.addEventListener( 'input', function ( e ) {
		var el = e.target;
		var card = el.closest( '.wonom-slide-card' );
		if ( el.hasAttribute( 'data-field' ) && card ) {
			var s = findSlide( card.getAttribute( 'data-id' ) );
			var key = el.getAttribute( 'data-field' ), lang = el.getAttribute( 'data-lang' );
			var val = el.type === 'checkbox' ? el.checked : el.value;
			if ( el.type === 'range' ) { val = parseInt( val, 10 ); el.parentNode.querySelector( 'output' ).textContent = val + '%'; }
			if ( el.classList.contains( 'wonom-color-hex' ) ) {
				if ( ! /^#[0-9a-f]{6}$/i.test( val ) ) { return; }
				el.parentNode.querySelector( 'input[type=color]' ).value = val;
			} else if ( el.type === 'color' ) {
				el.parentNode.querySelector( '.wonom-color-hex' ).value = val;
			}
			if ( lang ) {
				s.i18n = s.i18n || {};
				s.i18n[ lang ] = s.i18n[ lang ] || {};
				if ( val === '' ) { delete s.i18n[ lang ][ key ]; } else { s.i18n[ lang ][ key ] = val; }
			} else {
				s[ key ] = val;
			}
			markDirty( 'slides' );
			updateCardHeader( card, s );
			stageUpdate( s, key );
			return;
		}
		if ( el.hasAttribute( 'data-font-custom' ) ) {
			state.settings[ el.getAttribute( 'data-font-custom' ) ] = 'custom:' + el.value;
			markDirty( 'settings' );
			return;
		}
		if ( ( el.hasAttribute( 'data-typo' ) || el.hasAttribute( 'data-typo-custom' ) ) && card ) {
			var ts = findSlide( card.getAttribute( 'data-id' ) );
			ts.typo = ts.typo || {};
			if ( el.hasAttribute( 'data-typo-custom' ) ) {
				ts.typo[ el.getAttribute( 'data-typo-custom' ) ] = 'custom:' + el.value;
				markDirty( 'slides' ); stageTypo( ts, el.getAttribute( 'data-typo-custom' ) ); return;
			}
			if ( el.tagName === 'SELECT' ) { return; } // handled on change
			var tk = el.getAttribute( 'data-typo' );
			if ( el.value === '' ) { delete ts.typo[ tk ]; } else { ts.typo[ tk ] = el.value; }
			markDirty( 'slides' ); stageTypo( ts, tk );
			return;
		}
		if ( el.hasAttribute( 'data-setting' ) ) {
			var k = el.getAttribute( 'data-setting' );
			state.settings[ k ] = el.type === 'checkbox' ? el.checked : ( el.type === 'number' ? parseInt( el.value, 10 ) || 0 : el.value );
			markDirty( 'settings' );
			return;
		}
		if ( el.hasAttribute( 'data-adv' ) ) {
			state.advanced[ el.getAttribute( 'data-adv' ) ] = el.type === 'checkbox' ? el.checked : el.value;
			markDirty( 'advanced' );
		}
	} );

	app.addEventListener( 'change', function ( e ) {
		var el = e.target;
		var card = el.closest( '.wonom-slide-card' );
		if ( el.type === 'checkbox' && el.hasAttribute( 'data-field' ) && card ) {
			var s = findSlide( card.getAttribute( 'data-id' ) );
			s[ el.getAttribute( 'data-field' ) ] = el.checked;
			markDirty( 'slides' ); render(); return;
		}
		if ( el.hasAttribute( 'data-date' ) && card ) {
			var s2 = findSlide( card.getAttribute( 'data-id' ) );
			var p = parseDisplay( el.value );
			s2[ el.getAttribute( 'data-date' ) ] = p ? toIso( p ) : '';
			markDirty( 'slides' ); render(); return;
		}
		if ( el.hasAttribute( 'data-typo' ) && el.tagName === 'SELECT' && card ) {
			var ts2 = findSlide( card.getAttribute( 'data-id' ) ), tk2 = el.getAttribute( 'data-typo' );
			ts2.typo = ts2.typo || {};
			var customIn = el.parentNode.querySelector( '[data-typo-custom]' );
			if ( el.value === 'custom' ) { if ( customIn ) { customIn.hidden = false; customIn.focus(); } ts2.typo[ tk2 ] = 'custom:' + ( customIn ? customIn.value : '' ); }
			else { if ( customIn ) { customIn.hidden = true; } if ( el.value === '' ) { delete ts2.typo[ tk2 ]; } else { ts2.typo[ tk2 ] = el.value; } }
			markDirty( 'slides' ); stageTypo( ts2, tk2 ); return;
		}
		if ( el.hasAttribute( 'data-font' ) ) {
			var fk = el.getAttribute( 'data-font' );
			var custom = el.parentNode.querySelector( '[data-font-custom]' );
			if ( el.value === 'custom' ) { custom.hidden = false; custom.focus(); state.settings[ fk ] = 'custom:' + custom.value; }
			else { custom.hidden = true; state.settings[ fk ] = el.value; }
			markDirty( 'settings' ); return;
		}
		if ( el.hasAttribute( 'data-campaign' ) && card ) {
			var cs = findSlide( card.getAttribute( 'data-id' ) ), cid = parseInt( el.value, 10 ) || 0;
			cs.campaign_id = cid; cs.source = 'campaign';
			markDirty( 'slides' );
			if ( ! cid ) { render(); return; }
			api( '/campaign/' + cid ).then( function ( f ) {
				Object.keys( f.base ).forEach( function ( k ) { cs[ k ] = f.base[ k ]; } );
				cs.i18n = cs.i18n || {};
				Object.keys( f.i18n || {} ).forEach( function ( lg ) { cs.i18n[ lg ] = Object.assign( {}, cs.i18n[ lg ] || {}, f.i18n[ lg ] ); } );
				cs.start = f.start; cs.end = f.end; cs.type = 'campaign';
				if ( ! cs.name ) { cs.name = f.title; }
				cs.campaign_title = f.title; cs.campaign_status = f.status; cs.campaign_edit_url = f.edit_url;
				render();
			} ).catch( function ( e ) { toast( e.message || 'Error', 'err' ); render(); } );
			return;
		}
		if ( el.hasAttribute( 'data-ratio-mode' ) ) {
			var key = el.getAttribute( 'data-ratio-mode' );
			var txt = el.parentNode.querySelector( 'input' );
			if ( el.value === 'auto' ) { state.settings[ key ] = 'auto'; txt.hidden = true; txt.value = ''; }
			else { txt.hidden = false; txt.focus(); state.settings[ key ] = txt.value || '1920x660'; txt.value = state.settings[ key ]; }
			markDirty( 'settings' ); return;
		}
		if ( el.hasAttribute( 'data-setting' ) && ( el.tagName === 'SELECT' || el.type === 'checkbox' ) ) {
			state.settings[ el.getAttribute( 'data-setting' ) ] = el.type === 'checkbox' ? el.checked : el.value;
			markDirty( 'settings' ); return;
		}
		if ( el.hasAttribute( 'data-adv' ) && el.type === 'checkbox' ) {
			state.advanced[ el.getAttribute( 'data-adv' ) ] = el.checked;
			markDirty( 'advanced' );
		}
		if ( el.hasAttribute( 'data-action' ) && el.getAttribute( 'data-action' ) === 'preview-all' ) {
			preview.all = el.checked; refreshPreview();
		}
	} );

	function updateCardHeader( card, s ) {
		var st = slideStatus( s );
		var n = card.querySelector( '.js-name' );
		if ( n && n.tagName !== 'INPUT' ) { n.textContent = s.name || s.heading || I.untitled; }
		else if ( n ) { n.placeholder = s.heading || I.untitled; }
		var p = card.querySelector( '.js-status' ); if ( p ) { p.className = 'wonom-pill wonom-pill--' + st + ' js-status'; p.textContent = statusLabel( st ); }
		var sc = card.querySelector( '.js-sched' ); if ( sc ) { sc.textContent = scheduleSummary( s ); }
	}

	/* ------------------------------------------------------------------ actions */

	function addSlide( type ) {
		var s = {
			id: uid(), name: '', type: type === 'campaign' ? 'campaign' : 'regular', enabled: true,
			image_id: 0, image_url: '', image_width: 0, image_height: 0,
			mobile_image_id: 0, mobile_image_url: '', mobile_image_width: 0, mobile_image_height: 0,
			alt: '', eyebrow: '', heading: '', text: '', button_text: '', button_url: '', button_new_tab: false,
			button2_text: '', button2_url: '', link_whole_slide: false,
			align: 'center', valign: 'middle', text_color: '#ffffff', button_bg: '#f28cb1', button_color: '#ffffff',
			overlay: 20, overlay_color: '#000000', bg_color: '#1d2433', badge: '', start: '', end: '',
			focal_x: 50, focal_y: 50, mobile_focal_x: 50, mobile_focal_y: 50,
			mobile_align: '', mobile_valign: '', mobile_hide_text: false,
			pos_mode: 'grid', pos_x: 50, pos_y: 50, pos_w: 60, mobile_pos_mode: '', mobile_pos_x: 50, mobile_pos_y: 50, mobile_pos_w: 90,
			bg_mode: 'image', collage: [], collage_seam: 'fade', collage_gap: 0, collage_mobile: 'first2', frame_width: 0, frame_radius: 0,
			typo: {}, i18n: {}
		};
		// Campaign slides go first (they usually should be seen first) with a default 14-day window.
		if ( s.type === 'campaign' ) {
			var now = siteNow(); now.setSeconds( 0, 0 );
			var end = new Date( now.getTime() + 14 * 864e5 ); end.setHours( 23, 59, 0, 0 );
			s.start = dateToIso( now ); s.end = dateToIso( end );
			state.slides.unshift( s );
		} else {
			state.slides.push( s );
		}
		openId = s.id;
		markDirty( 'slides' );
		render();
		var c = app.querySelector( '.wonom-slide-card.is-open' ); if ( c ) { c.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
	}

	function moveSlide( s, dir ) {
		var i = state.slides.indexOf( s ), j = i + dir;
		if ( j < 0 || j >= state.slides.length ) { return; }
		state.slides.splice( i, 1 ); state.slides.splice( j, 0, s );
		markDirty( 'slides' ); render();
	}

	var frames = {};
	function pickImage( s, base ) {
		if ( ! window.wp || ! window.wp.media ) { return; }
		var frame = frames[ base ] || ( frames[ base ] = window.wp.media( { title: I.chooseImage, library: { type: 'image' }, multiple: false, button: { text: I.chooseImage } } ) );
		frame.off( 'select' );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			s[ base + '_id' ] = a.id;
			s[ base + '_url' ] = a.url;
			s[ base + '_width' ] = a.width || 0;
			s[ base + '_height' ] = a.height || 0;
			var th = ( a.sizes && ( a.sizes.medium_large || a.sizes.medium || a.sizes.large ) ) ? ( a.sizes.medium_large || a.sizes.medium || a.sizes.large ).url : a.url;
			if ( base === 'image' ) { s.thumb = th; if ( ! s.alt && a.alt ) { s.alt = a.alt; } if ( ! s.name && a.title ) { s.name = a.title; } } else { s.mobile_thumb = th; }
			markDirty( 'slides' ); render();
		} );
		frame.open();
	}

	var collageFrame = null;
	function pickCollage( s, slot ) {
		if ( ! window.wp || ! window.wp.media ) { return; }
		if ( ! collageFrame ) {
			collageFrame = window.wp.media( { title: I.bgCollage, library: { type: 'image' }, multiple: 'add', button: { text: I.chooseImage } } );
		}
		collageFrame.off( 'select' );
		collageFrame.on( 'select', function () {
			var picked = collageFrame.state().get( 'selection' ).toJSON();
			s.collage = Array.isArray( s.collage ) ? s.collage : [];
			picked.forEach( function ( a, i ) {
				var idx = slot + i;
				if ( idx > 3 ) { return; }
				var th = ( a.sizes && ( a.sizes.medium || a.sizes.medium_large || a.sizes.large ) ) ? ( a.sizes.medium || a.sizes.medium_large || a.sizes.large ).url : a.url;
				var prev = s.collage[ idx ] || {};
				s.collage[ idx ] = { id: a.id, url: a.url, width: a.width || 0, height: a.height || 0, focal_x: prev.focal_x || 50, focal_y: prev.focal_y || 50, thumb: th };
			} );
			// No holes: compact the list.
			s.collage = s.collage.filter( Boolean );
			if ( ! s.name && picked[ 0 ] && picked[ 0 ].title ) { s.name = picked[ 0 ].title; }
			markDirty( 'slides' ); render();
		} );
		collageFrame.on( 'open', function () {
			var sel = collageFrame.state().get( 'selection' );
			sel.reset();
		} );
		collageFrame.open();
	}

	function stageCollageFocal( s, idx ) {
		if ( ! stage.iframe || stage.slideId !== s.id ) { return; }
		try {
			var items = stage.iframe.contentDocument.querySelectorAll( '.wonom-collage__item' );
			if ( items[ idx ] ) { items[ idx ].style.setProperty( '--ws-cf', s.collage[ idx ].focal_x + '% ' + s.collage[ idx ].focal_y + '%' ); }
		} catch ( e ) { /* ignore */ }
	}

	function translateSlide( s, lang, btn ) {
		var src = {};
		[ 'eyebrow', 'heading', 'text', 'button_text', 'button2_text', 'badge', 'alt' ].forEach( function ( k ) { if ( s[ k ] ) { src[ k ] = s[ k ]; } } );
		btn.disabled = true; btn.textContent = I.translating;
		api( '/translate', 'POST', { fields: src, source: defaultLang(), target: lang } ).then( function ( res ) {
			s.i18n = s.i18n || {}; s.i18n[ lang ] = s.i18n[ lang ] || {};
			Object.keys( res.fields || {} ).forEach( function ( k ) { s.i18n[ lang ][ k ] = res.fields[ k ]; } );
			// Guess a language-prefixed link for Polylang/WPML style URLs.
			[ 'button_url', 'button2_url' ].forEach( function ( k ) {
				if ( s[ k ] && ! s.i18n[ lang ][ k ] ) {
					try {
						var u = new URL( s[ k ], CFG.homeUrl );
						if ( u.origin === new URL( CFG.homeUrl ).origin && u.pathname.indexOf( '/' + lang + '/' ) !== 0 ) {
							var home = new URL( CFG.homeUrl ).pathname.replace( /\/$/, '' );
							u.pathname = home + '/' + lang + u.pathname.slice( home.length );
							s.i18n[ lang ][ k ] = u.href;
						}
					} catch ( e ) { /* ignore */ }
				}
			} );
			markDirty( 'slides' ); render(); toast( sprintf( I.translated, res.engine || 'API' ), 'ok' );
		} ).catch( function ( e ) {
			btn.disabled = false; btn.textContent = sprintf( I.translateFrom, langName( defaultLang() ) );
			toast( I.translateFailed + ( e && e.message ? ': ' + e.message : '' ), 'err' );
		} );
	}

	function applyPreset( s, key, preset ) {
		var now = siteNow(); now.setSeconds( 0, 0 );
		var d = null;
		var base = key === 'end' && isoToDate( s.start ) && isoToDate( s.start ) > now ? isoToDate( s.start ) : now;
		switch ( preset ) {
			case 'clear': d = null; break;
			case 'now': d = now; break;
			case 'tomorrow': d = new Date( now ); d.setDate( d.getDate() + 1 ); d.setHours( 0, 0, 0, 0 ); break;
			case 'monday': d = new Date( now ); d.setDate( d.getDate() + ( ( 8 - d.getDay() ) % 7 || 7 ) ); d.setHours( 0, 0, 0, 0 ); break;
			case 'p7': d = new Date( base ); d.setDate( d.getDate() + 7 ); d.setHours( 23, 59, 0, 0 ); break;
			case 'p14': d = new Date( base ); d.setDate( d.getDate() + 14 ); d.setHours( 23, 59, 0, 0 ); break;
			case 'eom': d = new Date( base.getFullYear(), base.getMonth() + 1, 0, 23, 59, 0, 0 ); break;
		}
		s[ key ] = d ? dateToIso( d ) : '';
		markDirty( 'slides' ); render();
	}

	/* ---------- calendar popup (Monday first, 24 h) ---------- */

	var popup = null;
	function closePopup() {
		if ( popup ) { popup.remove(); popup = null; document.removeEventListener( 'mousedown', outsidePopup, true ); document.removeEventListener( 'keydown', escPopup, true ); }
	}
	function outsidePopup( e ) { if ( popup && ! popup.contains( e.target ) ) { closePopup(); } }
	function escPopup( e ) { if ( e.key === 'Escape' ) { closePopup(); } }

	function openCalendarPopup( s, key, anchor ) {
		closePopup();
		var wrap = anchor.closest( '.wonom-dt' );
		var now = siteNow();
		var cur = parseIso( s[ key ] ) || { y: now.getFullYear(), m: now.getMonth() + 1, d: now.getDate(), h: key === 'end' ? 23 : 0, mi: key === 'end' ? 59 : 0 };
		var view = { y: cur.y, m: cur.m };
		popup = document.createElement( 'div' );
		popup.className = 'wonom-dtpop';
		wrap.appendChild( popup );
		document.addEventListener( 'mousedown', outsidePopup, true );
		document.addEventListener( 'keydown', escPopup, true );

		function commit() {
			s[ key ] = toIso( cur );
			wrap.querySelector( 'input' ).value = toDisplay( s[ key ] );
			markDirty( 'slides' );
		}
		function draw() {
			var h = '<div class="wonom-dtnav"><button type="button" data-nav="-1" aria-label="' + esc( I.prevMonth ) + '">‹</button><span class="wonom-dtmonth">' + esc( I.months[ view.m - 1 ] ) + ' ' + view.y + '</span><button type="button" data-nav="1" aria-label="' + esc( I.nextMonth ) + '">›</button></div>';
			h += '<div class="wonom-dtgrid">';
			I.dow.forEach( function ( d ) { h += '<span class="wonom-dtdow">' + esc( d ) + '</span>'; } );
			var first = new Date( view.y, view.m - 1, 1 );
			var shift = ( first.getDay() + 6 ) % 7;
			for ( var i = 0; i < 42; i++ ) {
				var dt = new Date( view.y, view.m - 1, 1 - shift + i );
				var y = dt.getFullYear(), mo = dt.getMonth() + 1, d = dt.getDate();
				var cls = [];
				if ( mo !== view.m ) { cls.push( 'is-out' ); }
				if ( y === now.getFullYear() && mo === now.getMonth() + 1 && d === now.getDate() ) { cls.push( 'is-today' ); }
				if ( y === cur.y && mo === cur.m && d === cur.d ) { cls.push( 'is-sel' ); }
				h += '<button type="button" class="' + cls.join( ' ' ) + '" data-day="' + y + '-' + pad( mo ) + '-' + pad( d ) + '">' + d + '</button>';
			}
			h += '</div>';
			h += '<div class="wonom-dttime"><select data-time="h" aria-label="' + esc( I.hour ) + '">';
			for ( var hh = 0; hh < 24; hh++ ) { h += '<option value="' + hh + '"' + ( hh === cur.h ? ' selected' : '' ) + '>' + pad( hh ) + '</option>'; }
			h += '</select> : <select data-time="mi" aria-label="' + esc( I.minute ) + '">';
			for ( var mm = 0; mm < 60; mm += 5 ) { h += '<option value="' + mm + '"' + ( mm === cur.mi ? ' selected' : '' ) + '>' + pad( mm ) + '</option>'; }
			if ( cur.mi % 5 ) { h += '<option value="' + cur.mi + '" selected>' + pad( cur.mi ) + '</option>'; }
			h += '</select><span class="wonom-spacer"></span><button type="button" class="button-link" data-today="1">' + esc( I.today ) + '</button><button type="button" class="button button-primary" data-done="1">' + esc( I.done ) + '</button></div>';
			popup.innerHTML = h;
		}
		popup.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( 'button' );
			if ( ! b ) { return; }
			if ( b.hasAttribute( 'data-nav' ) ) {
				view.m += parseInt( b.getAttribute( 'data-nav' ), 10 );
				if ( view.m < 1 ) { view.m = 12; view.y--; } if ( view.m > 12 ) { view.m = 1; view.y++; }
				draw(); return;
			}
			if ( b.hasAttribute( 'data-day' ) ) {
				var p = b.getAttribute( 'data-day' ).split( '-' );
				cur.y = +p[ 0 ]; cur.m = +p[ 1 ]; cur.d = +p[ 2 ]; view = { y: cur.y, m: cur.m };
				commit(); draw(); return;
			}
			if ( b.hasAttribute( 'data-today' ) ) {
				cur.y = now.getFullYear(); cur.m = now.getMonth() + 1; cur.d = now.getDate(); view = { y: cur.y, m: cur.m };
				commit(); draw(); return;
			}
			if ( b.hasAttribute( 'data-done' ) ) { commit(); closePopup(); render(); }
		} );
		popup.addEventListener( 'change', function ( e ) {
			var sel = e.target.closest( 'select[data-time]' );
			if ( ! sel ) { return; }
			cur[ sel.getAttribute( 'data-time' ) ] = parseInt( sel.value, 10 );
			commit();
		} );
		draw();
	}

	/* ---------- updates / export ---------- */

	function checkUpdates( btn ) {
		var out = document.getElementById( 'wonom-upd-status' );
		btn.disabled = true; out.textContent = I.checking;
		var go = function () { return api( '/update-status?force=1' ); };
		var p = dirty.advanced ? save().then( go ) : go();
		p.then( function ( r ) {
			btn.disabled = false;
			if ( ! r.ok ) { out.innerHTML = '<span class="wonom-bad">' + esc( I.updateFailed ) + '</span> <a href="' + esc( r.repo_url ) + '" target="_blank" rel="noopener">' + esc( r.repo ) + '</a>'; return; }
			if ( r.has_update ) {
				out.innerHTML = '<span class="wonom-warn">' + esc( sprintf( I.updateAvailable, r.latest ) ) + '</span> <a class="button button-primary button-small" href="' + esc( r.update_url ) + '">' + esc( I.updateNow ) + '</a>';
			} else {
				out.innerHTML = '<span class="wonom-ok">' + esc( sprintf( I.upToDate, r.latest ) ) + '</span>';
			}
		} ).catch( function ( e ) { btn.disabled = false; out.textContent = I.updateFailed + ( e.message ? ' (' + e.message + ')' : '' ); } );
	}

	function exportJson() {
		api( '/export' ).then( function ( data ) {
			var blob = new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
			var a = document.createElement( 'a' );
			a.href = URL.createObjectURL( blob );
			a.download = 'wonom-slider-' + new Date().toISOString().slice( 0, 10 ) + '.json';
			document.body.appendChild( a ); a.click(); a.remove();
		} );
	}

	/* ------------------------------------------------------------------ boot */

	render();
}( window.jQuery ) );
