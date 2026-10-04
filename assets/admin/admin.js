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
	var preview = { open: false, device: 'desktop', all: true, lang: '' };
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

	function slideStatus( s ) {
		if ( ! s.enabled ) { return 'disabled'; }
		if ( ! s.image_url ) { return 'noimage'; }
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

	function applyState( res ) {
		var newOpen = openId;
		state.slides = res.slides;
		state.settings = res.settings;
		state.advanced = res.advanced || state.advanced;
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
		h += '<button type="button" class="button' + ( preview.open ? ' is-on' : '' ) + '" data-action="toggle-preview"><span class="dashicons dashicons-visibility"></span> ' + esc( I.preview ) + '</button>';
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
		h += '<div class="wonom-preview-stage is-' + preview.device + '"><iframe id="wonom-preview-frame" src="' + esc( previewSrc() ) + '" title="' + esc( I.preview ) + '"></iframe></div>';
		h += '</div>';
		return h;
	}
	function previewSrc() {
		var u = CFG.previewUrl + ( preview.all ? '&all=1' : '' ) + ( preview.lang ? '&lang=' + encodeURIComponent( preview.lang ) : '' ) + '&t=' + Date.now();
		return u;
	}
	function refreshPreview() {
		var f = document.getElementById( 'wonom-preview-frame' );
		if ( f ) { f.src = previewSrc(); }
	}

	function renderCard( s, i ) {
		var st = slideStatus( s );
		var open = openId === s.id;
		var thumb = s.thumb || s.image_url;
		var h = '<div class="wonom-slide-card' + ( open ? ' is-open' : '' ) + ' type-' + s.type + ' st-' + st + '" data-id="' + esc( s.id ) + '">';
		h += '<div class="wonom-slide-head">';
		h += '<span class="wonom-drag" title="' + esc( I.dragToReorder ) + '"><span class="dashicons dashicons-menu"></span></span>';
		h += '<span class="wonom-order">' + ( i + 1 ) + '</span>';
		h += '<div class="wonom-thumb" data-action="open">' + ( thumb ? '<img src="' + esc( thumb ) + '" alt="">' : '<span class="dashicons dashicons-format-image"></span>' ) + '</div>';
		h += '<div class="wonom-slide-meta" data-action="open">';
		h += '<div class="wonom-slide-title"><strong class="js-name">' + esc( s.name || s.heading || I.untitled ) + '</strong>';
		if ( s.type === 'campaign' ) { h += ' <span class="wonom-tag wonom-tag--campaign">' + esc( I.typeCampaign ) + '</span>'; }
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
			return '<textarea rows="2" data-field="' + key + '"' + ( lang ? ' data-lang="' + esc( lang ) + '"' : '' ) + ' placeholder="' + esc( ph ) + '">' + esc( val ) + '</textarea>';
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

	function renderEditor( s ) {
		var h = '<div class="wonom-editor">';

		/* Images */
		h += '<section class="wonom-sec"><h3><span class="dashicons dashicons-format-image"></span> ' + esc( I.secImages ) + '</h3>';
		h += '<div class="wonom-grid wonom-grid--2">';
		h += imageBox( s, 'image', I.desktopImage, I.recommended );
		h += imageBox( s, 'mobile_image', I.mobileImage, I.mobileHint );
		h += '</div>';
		h += '<p class="wonom-hint">' + esc( I.focalHint ) + '</p>';
		h += '</section>';

		/* Content with language tabs */
		h += '<section class="wonom-sec"><h3><span class="dashicons dashicons-editor-textcolor"></span> ' + esc( I.secContent ) + '</h3>';
		h += field( I.name, input( s, 'name' ), esc( I.nameHint ) );
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
		h += '<div class="wonom-grid wonom-grid--2">';
		h += field( I.eyebrow, input( s, 'eyebrow', { lang: L } ) );
		h += field( I.badge, input( s, 'badge', { lang: L } ) );
		h += '</div>';
		h += field( I.heading, input( s, 'heading', { lang: L, attrs: ' class="wonom-big"' } ) );
		h += field( I.text, input( s, 'text', { lang: L, textarea: true } ) );
		h += '<div class="wonom-grid wonom-grid--2">';
		h += field( I.buttonText, input( s, 'button_text', { lang: L } ) );
		h += field( I.buttonUrl, input( s, 'button_url', { lang: L, type: 'url', placeholder: L ? ( s.button_url || CFG.homeUrl ) : CFG.homeUrl } ) );
		h += field( I.button2Text, input( s, 'button2_text', { lang: L } ) );
		h += field( I.button2Url, input( s, 'button2_url', { lang: L, type: 'url' } ) );
		h += '</div>';
		h += field( I.alt, input( s, 'alt', { lang: L } ) );
		if ( L ) { h += '<p class="wonom-hint">' + esc( I.fallbackHint ) + '</p>'; }
		h += '</section>';

		/* Design */
		h += '<section class="wonom-sec"><h3><span class="dashicons dashicons-art"></span> ' + esc( I.secDesign ) + '</h3>';
		h += '<div class="wonom-grid wonom-grid--2">';
		h += field( I.align, segmented( 'align', s.align, [ [ 'left', I.left, 'editor-alignleft' ], [ 'center', I.center, 'editor-aligncenter' ], [ 'right', I.right, 'editor-alignright' ] ] ) );
		h += field( I.valign, segmented( 'valign', s.valign, [ [ 'top', I.top, 'arrow-up-alt' ], [ 'middle', I.middle, 'minus' ], [ 'bottom', I.bottom, 'arrow-down-alt' ] ] ) );
		h += '</div>';
		h += '<div class="wonom-grid wonom-grid--3">';
		h += field( I.textColor, color( s, 'text_color' ) );
		h += field( I.buttonBg, color( s, 'button_bg' ) );
		h += field( I.buttonColor, color( s, 'button_color' ) );
		h += '</div>';
		h += '<div class="wonom-grid wonom-grid--2">';
		h += field( I.overlay, '<span class="wonom-range"><input type="range" min="0" max="90" step="5" data-field="overlay" value="' + ( s.overlay | 0 ) + '"><output>' + ( s.overlay | 0 ) + '%</output></span>', esc( I.overlayHint ) );
		h += field( I.overlayColor, color( s, 'overlay_color' ) );
		h += '</div>';
		h += '</section>';

		/* Mobile */
		h += '<section class="wonom-sec"><h3><span class="dashicons dashicons-smartphone"></span> ' + esc( I.secMobile ) + '</h3>';
		h += '<p class="wonom-hint">' + esc( I.mobileIntro ) + '</p>';
		h += '<div class="wonom-grid wonom-grid--2">';
		h += field( I.align, segmented( 'mobile_align', s.mobile_align, [ [ 'left', I.left, 'editor-alignleft' ], [ 'center', I.center, 'editor-aligncenter' ], [ 'right', I.right, 'editor-alignright' ] ], true ) );
		h += field( I.valign, segmented( 'mobile_valign', s.mobile_valign, [ [ 'top', I.top, 'arrow-up-alt' ], [ 'middle', I.middle, 'minus' ], [ 'bottom', I.bottom, 'arrow-down-alt' ] ], true ) );
		h += '</div>';
		h += '<label class="wonom-check"><input type="checkbox" data-field="mobile_hide_text"' + ( s.mobile_hide_text ? ' checked' : '' ) + '> ' + esc( I.hideTextMobile ) + '</label>';
		h += '</section>';

		/* Schedule */
		h += '<section class="wonom-sec wonom-sec--schedule"><h3><span class="dashicons dashicons-calendar-alt"></span> ' + esc( I.secSchedule ) + '</h3>';
		h += '<p class="wonom-hint">' + esc( I.scheduleIntro ) + ' ' + esc( sprintf( I.scheduleTz, state.timezone || '' ) ) + '</p>';
		h += '<div class="wonom-grid wonom-grid--2">';
		h += dateField( s, 'start', I.start, [ [ 'now', I.presetNow ], [ 'tomorrow', I.presetTomorrow ], [ 'monday', I.presetMonday ] ] );
		h += dateField( s, 'end', I.end, [ [ 'p7', I.preset7 ], [ 'p14', I.preset14 ], [ 'eom', I.presetEom ] ] );
		h += '</div>';
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

	function afterSlidesRender() {
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
		if ( open && open.dataset.scrollTo ) { open.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
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
			toggleRow( 'show_arrows', I.showArrows ) + toggleRow( 'show_dots', I.showDots ) + toggleRow( 'show_progress', I.showProgress ) );

		h += group( I.gLayout, 'align-wide',
			'<div class="wonom-grid wonom-grid--2">' + ratioField( 'ratio_desktop', I.ratioDesktop ) + ratioField( 'ratio_mobile', I.ratioMobile ) + '</div>' +
			'<p class="wonom-hint">' + esc( I.ratioHint ) + '</p>' +
			'<div class="wonom-grid wonom-grid--3">' +
			field( I.maxWidth, sInput( 'max_width', 'number', ' min="0" max="4000" step="10"' ) ) +
			field( I.breakpoint, sInput( 'mobile_breakpoint', 'number', ' min="320" max="1400" step="1"' ), esc( I.breakpointHint ) ) +
			field( I.contentWidth, sInput( 'content_max_width', 'number', ' min="200" max="2000" step="10"' ) ) +
			field( I.paddingMobile, sInput( 'padding_mobile', 'number', ' min="0" max="100"' ) ) +
			'</div>' );

		h += group( I.gTypography, 'editor-textcolor',
			'<div class="wonom-grid wonom-grid--2">' +
			field( I.headingSize, sInput( 'heading_size', 'number', ' min="12" max="160"' ) ) +
			field( I.headingSizeMobile, sInput( 'heading_size_mobile', 'number', ' min="12" max="100"' ) ) +
			field( I.textSize, sInput( 'text_size', 'number', ' min="10" max="60"' ) ) +
			field( I.textSizeMobile, sInput( 'text_size_mobile', 'number', ' min="10" max="40"' ) ) +
			field( I.fontFamily, sInput( 'font_family', 'text', ' placeholder="inherit"' ), esc( I.fontHint ) ) +
			field( I.buttonRadius, sInput( 'button_radius', 'number', ' min="0" max="100"' ) ) +
			field( I.headingTag, sSelect( 'heading_tag', [ [ 'h2', 'H2' ], [ 'h1', 'H1' ], [ 'h3', 'H3' ], [ 'p', 'P' ] ] ), esc( I.headingTagHint ) ) +
			'</div>' );

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
				if ( e.target.closest( 'input, label' ) ) { return; }
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
			case 'toggle-preview': preview.open = ! preview.open; render(); break;
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
		var n = card.querySelector( '.js-name' ); if ( n ) { n.textContent = s.name || s.heading || I.untitled; }
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
			overlay: 20, overlay_color: '#000000', badge: '', start: '', end: '',
			focal_x: 50, focal_y: 50, mobile_focal_x: 50, mobile_focal_y: 50,
			mobile_align: '', mobile_valign: '', mobile_hide_text: false, i18n: {}
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
