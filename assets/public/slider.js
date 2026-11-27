/*! Wonom Slider – dependency-free front-end script. */
( function () {
	'use strict';

	// Real hover-capable pointer (mouse/trackpad). Touch devices emit synthetic mouseenter on tap,
	// which would otherwise pause autoplay forever.
	var HOVER = window.matchMedia && window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;

	/* ---------- clock for timed slides ----------
	 * Slides with a start or an end are in the page ahead of time; the browser shows and hides them
	 * by the clock, so a cached page needs no purge at the moment of change. The device clock is
	 * used; when a change is near it is compared once per session with the Date header of a static
	 * file (no PHP involved) and corrected if it is off by two minutes or more. */
	var skew = 0;            // seconds to add to the device clock
	var clockAsked = false;
	var timedSliders = [];

	function nowSec( floor ) {
		// Never earlier than the moment the page was built on the server.
		return Math.max( Date.now() / 1000 + skew, floor || 0 );
	}

	function setSkew( s ) {
		if ( s === skew ) { return; }
		skew = s;
		timedSliders.forEach( function ( sl ) { sl.applyTiming(); sl.watch(); } );
	}

	function checkClock( url ) {
		if ( clockAsked || ! url || ! window.fetch ) { return; }
		clockAsked = true;
		try {
			var saved = JSON.parse( window.sessionStorage.getItem( 'wonomSliderClock' ) || 'null' );
			if ( saved && typeof saved.s === 'number' ) { setSkew( saved.s ); return; }
		} catch ( e ) { /* storage blocked */ }
		var t0 = Date.now();
		window.fetch( url, { method: 'HEAD', cache: 'no-store', credentials: 'omit' } ).then( function ( res ) {
			var server = Date.parse( res.headers.get( 'date' ) || '' );
			if ( ! server ) { return; }
			var s = Math.round( ( server - ( t0 + Date.now() ) / 2 ) / 1000 );
			if ( Math.abs( s ) < 120 ) { s = 0; }
			try { window.sessionStorage.setItem( 'wonomSliderClock', JSON.stringify( { s: s } ) ); } catch ( e ) { /* storage blocked */ }
			setSkew( s );
		} ).catch( function () { /* offline or another origin: keep the device clock */ } );
	}

	/**
	 * Resolve third-party lazy-load placeholders (data-src / data-srcset / data-lazy-src…) on a slide.
	 * Themes and optimisers rewrite <img> tags; if their script is absent or late (editor preview,
	 * delayed JS), the slide would stay blank. Harmless when the attributes are not present.
	 * `eager` also lifts loading="lazy": a slide that is still hidden would otherwise not fetch.
	 */
	function hydrate( slide, eager ) {
		if ( ! slide ) { return; }
		[].forEach.call( slide.querySelectorAll( 'img' ), function ( img ) {
			var src = img.getAttribute( 'data-wonom-src' ) || img.getAttribute( 'data-src' ) || img.getAttribute( 'data-lazy-src' ) || img.getAttribute( 'data-original' );
			var set = img.getAttribute( 'data-wonom-srcset' ) || img.getAttribute( 'data-srcset' ) || img.getAttribute( 'data-lazy-srcset' );
			if ( eager ) { img.loading = 'eager'; }
			if ( src && ( ! img.getAttribute( 'src' ) || /lazy|placeholder|blank|data:image\/(svg|gif)/i.test( img.getAttribute( 'src' ) ) ) ) {
				img.setAttribute( 'src', src );
				img.removeAttribute( 'data-wonom-src' ); img.removeAttribute( 'data-src' ); img.removeAttribute( 'data-lazy-src' ); img.removeAttribute( 'data-original' );
			}
			if ( set && ! img.getAttribute( 'srcset' ) ) {
				img.setAttribute( 'srcset', set );
				img.removeAttribute( 'data-wonom-srcset' ); img.removeAttribute( 'data-srcset' ); img.removeAttribute( 'data-lazy-srcset' );
			}
			img.classList.remove( 'wd-lazy-fade', 'lazyload', 'lazy' );
			img.classList.add( 'wd-lazy-loaded' );
		} );
		[].forEach.call( slide.querySelectorAll( 'source[data-wonom-srcset], source[data-srcset]' ), function ( s ) {
			s.setAttribute( 'srcset', s.getAttribute( 'data-wonom-srcset' ) || s.getAttribute( 'data-srcset' ) );
			s.removeAttribute( 'data-wonom-srcset' ); s.removeAttribute( 'data-srcset' );
		} );
	}

	/**
	 * An upcoming slide arrives sealed: an empty placeholder with its timing and the markup as
	 * base64 (so crawlers do not read a future offer as a current one). Decode it and put the real
	 * slide in its place; returns the element that is in the page now.
	 */
	function unseal( el ) {
		var data = el && el.getAttribute( 'data-ws-seal' );
		if ( ! data || ! el.parentNode ) { return el; }
		try {
			var s = window.atob( data );
			try {
				s = new window.TextDecoder().decode( Uint8Array.from( s, function ( c ) { return c.charCodeAt( 0 ); } ) );
			} catch ( e ) {
				s = decodeURIComponent( escape( s ) );
			}
			var tpl = document.createElement( 'template' );
			tpl.innerHTML = s;
			var real = tpl.content.firstElementChild;
			if ( ! real ) { return el; }
			el.parentNode.replaceChild( real, el );
			return real;
		} catch ( e ) {
			return el;
		}
	}

	function Slider( root ) {
		if ( root.__wonom ) {
			return root.__wonom;
		}
		root.__wonom = this;

		var cfg = {};
		try {
			cfg = JSON.parse( root.getAttribute( 'data-wonom-slider' ) || '{}' );
		} catch ( e ) {
			cfg = {};
		}

		this.root = root;
		this.cfg = {
			autoplay: !! cfg.autoplay,
			interval: Math.max( 1000, parseInt( cfg.interval, 10 ) || 6000 ),
			speed: Math.max( 100, parseInt( cfg.speed, 10 ) || 800 ),
			transition: cfg.transition === 'slide' ? 'slide' : 'fade',
			loop: cfg.loop !== false,
			pauseOnHover: cfg.pauseOnHover !== false,
			progress: !! cfg.progress
		};

		// `all` = every slide in the markup, `slides` = the ones shown right now (timed slides come and go).
		this.all = [].slice.call( root.querySelectorAll( '.wonom-slide' ) );
		this.slides = this.all.filter( function ( el ) { return ! el.hasAttribute( 'hidden' ); } );
		this.floor = parseInt( root.getAttribute( 'data-ws-now' ), 10 ) || 0;
		this.timed = this.all.map( function ( el ) {
			return {
				el: el,
				start: parseInt( el.getAttribute( 'data-ws-start' ), 10 ) || 0,
				end: parseInt( el.getAttribute( 'data-ws-end' ), 10 ) || 0,   // exclusive
				group: el.getAttribute( 'data-ws-group' ) || ''
			};
		} );
		this.hasTiming = this.timed.some( function ( t ) { return t.start || t.end; } );
		this.dotsWrap = root.querySelector( '.wonom-slider__dots' );
		this.dots = [].slice.call( root.querySelectorAll( '.wonom-slider__dot' ) );
		this.progress = root.querySelector( '.wonom-slider__progress' );
		this.track = root.querySelector( '.wonom-slider__track' );
		this.index = 0;
		this.timer = null;
		this.clockTimer = null;
		this.paused = false;
		this.animating = false;
		this.count = this.slides.length;

		this.sizeTrack();

		if ( ! this.hasTiming ) {
			hydrate( this.slides[ 0 ] );
			if ( this.count < 2 ) {
				root.classList.add( 'is-single' );
				return;
			}
			this.bind();
			this.go( 0, 0, true );
		} else {
			if ( this.all.length > 1 ) { this.bind(); }
			timedSliders.push( this );
			this.applyTiming( true );
			this.watch();
			this.bindClock();
		}

		// Slides 2…n ship their images deferred (data-src). Fetch the second slide once the
		// browser is idle so the first transition is never blank; the rest follow one by one.
		var second = this.slides[ 1 ];
		( window.requestIdleCallback || function ( f ) { setTimeout( f, 1500 ); } )( function () { hydrate( second ); } );

		this.play();
	}

	/* ---------- timed slides ---------- */

	/**
	 * Show the slides whose time it is and hide the rest. Returns true when the visible set changed.
	 */
	Slider.prototype.applyTiming = function ( init ) {
		if ( ! this.hasTiming ) { return false; }
		var self = this, root = this.root, n = nowSec( this.floor ), best = {}, vis = [];

		this.timed.forEach( function ( t ) {
			t.on = ( ! t.start || n >= t.start ) && ( ! t.end || n < t.end );
			// One slide of a group at a time (automatic campaign slide): the latest start wins.
			if ( t.on && t.group && ( ! best[ t.group ] || t.start > best[ t.group ].start ) ) { best[ t.group ] = t; }
		} );
		this.timed.forEach( function ( t ) {
			if ( t.on && t.group && best[ t.group ] !== t ) { t.on = false; }
			if ( t.on ) { vis.push( self.open( t ) ); }
		} );

		var same = vis.length === this.slides.length && vis.every( function ( el, i ) { return el === self.slides[ i ]; } );
		if ( same && ! init ) { return false; }

		var active = this.slides[ this.index ];
		this.all.forEach( function ( el ) {
			if ( vis.indexOf( el ) < 0 ) {
				el.setAttribute( 'hidden', '' );
				el.classList.remove( 'is-active', 'is-prev', 'is-next' );
				el.setAttribute( 'aria-hidden', 'true' );
			} else {
				el.removeAttribute( 'hidden' );
			}
		} );
		this.slides = vis;
		this.count = vis.length;

		if ( ! this.count ) {
			root.setAttribute( 'hidden', '' );
			clearInterval( this.timer );
			return true;
		}
		root.removeAttribute( 'hidden' );
		root.classList.toggle( 'is-single', this.count < 2 );
		vis.forEach( function ( el, i ) { el.setAttribute( 'aria-label', ( i + 1 ) + ' / ' + vis.length ); } );
		this.buildDots();

		// The slider takes its shape from the first slide that has an image.
		var ratio = '';
		vis.some( function ( el ) { ratio = el.getAttribute( 'data-ws-ratio' ) || ''; return !! ratio; } );
		if ( ratio ) {
			var p = ratio.split( '|' );
			root.style.setProperty( '--ws-ratio', p[ 0 ] );
			root.style.setProperty( '--ws-ratio-mobile', p[ 1 ] || p[ 0 ] );
		}
		if ( this.resize ) { this.resize(); }

		var idx = init ? 0 : vis.indexOf( active );
		this.animating = false;
		this.go( idx < 0 ? 0 : idx, 0, true );
		if ( ! init ) { this.play(); }
		return true;
	};

	/** Decode a sealed timed slide (see unseal) and keep the lists pointing at the real element. */
	Slider.prototype.open = function ( t ) {
		var old = t.el, el = unseal( old );
		if ( el !== old ) {
			t.el = el;
			var i = this.all.indexOf( old );
			if ( i >= 0 ) { this.all[ i ] = el; }
		}
		return el;
	};

	/** One dot per visible slide. */
	Slider.prototype.buildDots = function () {
		var wrap = this.dotsWrap;
		if ( ! wrap ) { return; }
		if ( wrap.children.length !== this.count ) {
			var label = wrap.getAttribute( 'data-label' ) || '%d';
			wrap.textContent = '';
			for ( var i = 0; i < this.count; i++ ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'wonom-slider__dot';
				b.setAttribute( 'data-index', i );
				b.setAttribute( 'role', 'tab' );
				b.setAttribute( 'aria-selected', 'false' );
				b.setAttribute( 'aria-label', label.replace( '%d', i + 1 ) );
				wrap.appendChild( b );
			}
		}
		this.dots = [].slice.call( wrap.querySelectorAll( '.wonom-slider__dot' ) );
	};

	/**
	 * Wake up at the next start or end (and two minutes before a start, to fetch its images).
	 * A timer, never a request to the server.
	 */
	Slider.prototype.watch = function () {
		var self = this;
		clearTimeout( this.clockTimer );
		if ( ! this.hasTiming ) { return; }
		var n = nowSec( this.floor ), next = 0;
		this.timed.forEach( function ( t ) {
			[ t.start ? t.start - 120 : 0, t.start, t.end ].forEach( function ( p ) {
				if ( p > n && ( ! next || p < next ) ) { next = p; }
			} );
			if ( t.start > n && t.start - n <= 125 ) { hydrate( self.open( t ), true ); }   // decode + fetch images 2 min ahead
		} );
		if ( ! next ) { return; }
		this.clockTimer = setTimeout( function () {
			self.applyTiming();
			self.watch();
		}, Math.min( ( next - n ) * 1000 + 250, 3600000 ) );
	};

	Slider.prototype.bindClock = function () {
		var self = this, n = nowSec( this.floor ), near = false;
		// Timers are throttled in background tabs and stop while the device sleeps.
		document.addEventListener( 'visibilitychange', function () {
			if ( ! document.hidden ) { self.applyTiming(); self.watch(); }
		} );
		window.addEventListener( 'pageshow', function ( e ) {
			if ( e.persisted ) { self.applyTiming(); self.watch(); }
		} );
		// Ask for the real time only when it matters: a change since the page was built or within two days.
		this.timed.forEach( function ( t ) {
			[ t.start, t.end ].forEach( function ( p ) {
				if ( p && p > self.floor - 60 && p < n + 172800 ) { near = true; }
			} );
		} );
		if ( near ) { checkClock( this.root.getAttribute( 'data-ws-clock' ) ); }
	};

	Slider.prototype.bind = function () {
		var self = this, root = this.root;

		[].forEach.call( root.querySelectorAll( '.wonom-slider__arrow' ), function ( btn ) {
			btn.addEventListener( 'click', function () {
				self.step( parseInt( btn.getAttribute( 'data-dir' ), 10 ) || 1, true );
			} );
		} );

		// Delegated: the dots are rebuilt when a timed slide appears or disappears.
		if ( this.dotsWrap ) {
			this.dotsWrap.addEventListener( 'click', function ( e ) {
				var dot = e.target && e.target.closest ? e.target.closest( '.wonom-slider__dot' ) : null;
				var i = dot ? self.dots.indexOf( dot ) : -1;
				if ( i >= 0 ) { self.go( i, i > self.index ? 1 : -1, false, true ); }
			} );
		}

		if ( this.cfg.pauseOnHover && HOVER ) {
			root.addEventListener( 'mouseenter', function () { self.pause(); } );
			root.addEventListener( 'mouseleave', function () { self.resume(); } );
		}
		// Pause only for keyboard focus (accessibility); taps and clicks also move focus but must not stop autoplay.
		var pointerFocus = false;
		root.addEventListener( 'pointerdown', function () { pointerFocus = true; }, true );
		root.addEventListener( 'touchstart', function () { pointerFocus = true; }, { capture: true, passive: true } );
		root.addEventListener( 'keydown', function () { pointerFocus = false; }, true );
		root.addEventListener( 'focusin', function () {
			if ( ! pointerFocus ) { self.pause(); }
			pointerFocus = false;
		} );
		root.addEventListener( 'focusout', function ( e ) {
			if ( ! root.contains( e.relatedTarget ) ) {
				self.resume();
			}
		} );

		root.setAttribute( 'tabindex', root.getAttribute( 'tabindex' ) || '0' );
		root.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowLeft' ) { e.preventDefault(); self.step( -1, true ); }
			if ( e.key === 'ArrowRight' ) { e.preventDefault(); self.step( 1, true ); }
		} );

		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) { self.pause(); } else { self.resume(); }
		} );

		// Pause while off-screen (saves CPU, keeps slides in sync when the user scrolls back).
		if ( 'IntersectionObserver' in window ) {
			var io = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( en ) {
					if ( en.isIntersecting ) { self.resume(); } else { self.pause(); }
				} );
			}, { threshold: 0.2 } );
			io.observe( root );
		}

		// Touch / pointer swipe.
		var startX = 0, startY = 0, dx = 0, dragging = false, pointerId = null;
		var track = root.querySelector( '.wonom-slider__track' );

		function onDown( e ) {
			if ( e.pointerType === 'mouse' && e.button !== 0 ) { return; }
			if ( e.target.closest( 'a, button' ) && e.pointerType === 'mouse' ) { return; }
			pointerId = e.pointerId;
			startX = e.clientX; startY = e.clientY; dx = 0; dragging = false;
			root.classList.add( 'is-touch' );
		}
		function onMove( e ) {
			if ( pointerId === null || e.pointerId !== pointerId ) { return; }
			dx = e.clientX - startX;
			var dy = e.clientY - startY;
			if ( ! dragging && Math.abs( dx ) > 10 && Math.abs( dx ) > Math.abs( dy ) ) {
				dragging = true;
				self.pause();
				root.classList.add( 'is-dragging' );
			}
			if ( dragging ) {
				e.preventDefault();
			}
		}
		function onUp( e ) {
			if ( pointerId === null || e.pointerId !== pointerId ) { return; }
			pointerId = null;
			root.classList.remove( 'is-dragging' );
			if ( dragging ) {
				var threshold = Math.min( 80, root.clientWidth * 0.15 );
				if ( dx < -threshold ) { self.step( 1, true ); }
				else if ( dx > threshold ) { self.step( -1, true ); }
				// Swallow the click that follows a swipe so links are not followed.
				var swallow = function ( ev ) { ev.preventDefault(); ev.stopPropagation(); root.removeEventListener( 'click', swallow, true ); };
				root.addEventListener( 'click', swallow, true );
				setTimeout( function () { root.removeEventListener( 'click', swallow, true ); }, 300 );
			}
			dragging = false;
			self.resume();
		}
		if ( window.PointerEvent ) {
			track.addEventListener( 'pointerdown', onDown );
			track.addEventListener( 'pointermove', onMove, { passive: false } );
			track.addEventListener( 'pointerup', onUp );
			track.addEventListener( 'pointercancel', onUp );
		} else {
			track.addEventListener( 'touchstart', function ( e ) { var t = e.touches[ 0 ]; onDown( { pointerId: 1, pointerType: 'touch', clientX: t.clientX, clientY: t.clientY, target: e.target } ); }, { passive: true } );
			track.addEventListener( 'touchmove', function ( e ) { var t = e.touches[ 0 ]; onMove( { pointerId: 1, clientX: t.clientX, clientY: t.clientY, preventDefault: function () {} } ); }, { passive: true } );
			track.addEventListener( 'touchend', function () { onUp( { pointerId: 1 } ); } );
		}
	};

	/**
	 * Give the track an explicit pixel height derived from the active aspect ratio.
	 * In column-direction flex parents (e.g. Elementor containers on mobile) a purely
	 * aspect-ratio based height does not contribute to the parent's size and the next
	 * section would overlap the slider.
	 */
	Slider.prototype.sizeTrack = function () {
		var self = this, track = this.track;
		if ( ! track ) { return; }
		function apply() {
			var w = self.root.clientWidth;
			if ( ! w ) { return; }
			var cs = getComputedStyle( track );
			var m = /([\d.]+)\s*\/\s*([\d.]+)/.exec( cs.aspectRatio || '' );
			if ( ! m ) { return; }
			var h = w * parseFloat( m[ 2 ] ) / parseFloat( m[ 1 ] );
			var min = parseFloat( cs.minHeight ) || 0;
			var max = parseFloat( cs.maxHeight );
			if ( min ) { h = Math.max( h, min ); }
			if ( max && cs.maxHeight !== 'none' ) { h = Math.min( h, max ); }
			var px = Math.round( h * 100 ) / 100 + 'px';
			if ( track.style.height !== px ) { track.style.height = px; }
		}
		apply();
		this.resize = apply; // the shape changes when a timed slide becomes the first one
		if ( 'ResizeObserver' in window ) {
			// React to width changes only, and outside the observer callback, to avoid
			// "ResizeObserver loop" warnings (setting the height resizes the observed box).
			var lastW = self.root.clientWidth;
			new ResizeObserver( function () {
				var w = self.root.clientWidth;
				if ( w === lastW ) { return; }
				lastW = w;
				requestAnimationFrame( apply );
			} ).observe( this.root );
		} else {
			window.addEventListener( 'resize', apply );
		}
		window.addEventListener( 'orientationchange', function () { setTimeout( apply, 50 ); } );
	};

	Slider.prototype.step = function ( dir, user ) {
		if ( this.count < 2 ) { return; }
		var next = this.index + dir;
		if ( next >= this.count ) {
			if ( ! this.cfg.loop ) { return; }
			next = 0;
		}
		if ( next < 0 ) {
			if ( ! this.cfg.loop ) { return; }
			next = this.count - 1;
		}
		this.go( next, dir, false, user );
	};

	Slider.prototype.go = function ( index, dir, instant, user ) {
		var self = this;
		if ( index === this.index && ! instant ) { return; }
		if ( this.animating && ! instant ) { return; }

		var prev = this.index;
		this.index = index;

		this.slides.forEach( function ( s, i ) {
			s.classList.remove( 'is-prev', 'is-next' );
			if ( i === index ) {
				s.classList.add( 'is-active' );
				s.removeAttribute( 'aria-hidden' );
			} else {
				s.classList.remove( 'is-active' );
				s.setAttribute( 'aria-hidden', 'true' );
				if ( self.cfg.transition === 'slide' ) {
					if ( i === prev ) {
						s.classList.add( dir >= 0 ? 'is-prev' : 'is-next' );
					} else {
						s.classList.add( i < index ? 'is-prev' : 'is-next' );
					}
				}
			}
		} );

		this.dots.forEach( function ( d, i ) {
			d.classList.toggle( 'is-active', i === index );
			d.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
		} );

		// Eagerly fetch the next image so the transition never shows a blank.
		var upcoming = this.slides[ ( index + 1 ) % this.count ];
		var img = upcoming && upcoming.querySelector( 'img[loading="lazy"]' );
		if ( img ) { img.loading = 'eager'; }
		hydrate( this.slides[ index ] );
		if ( upcoming ) { hydrate( upcoming ); }

		if ( ! instant ) {
			this.animating = true;
			setTimeout( function () { self.animating = false; }, this.cfg.speed );
		}

		if ( user ) {
			this.restart();
		} else {
			this.runProgress();
		}

		var ev;
		try {
			ev = new CustomEvent( 'wonom-slider:change', { detail: { index: index, slide: this.slides[ index ] } } );
			this.root.dispatchEvent( ev );
		} catch ( e ) { /* IE */ }
	};

	Slider.prototype.runProgress = function () {
		if ( ! this.progress ) { return; }
		var bar = this.progress;
		bar.classList.remove( 'is-running' );
		// Force reflow to restart the CSS animation.
		void bar.offsetWidth; // eslint-disable-line no-void
		if ( this.cfg.autoplay && ! this.paused ) {
			bar.classList.add( 'is-running' );
		}
	};

	Slider.prototype.play = function () {
		var self = this;
		clearInterval( this.timer );
		if ( ! this.cfg.autoplay || this.count < 2 ) { return; }
		this.timer = setInterval( function () {
			if ( ! self.paused ) {
				self.step( 1, false );
			}
		}, this.cfg.interval );
		this.runProgress();
	};

	Slider.prototype.restart = function () {
		if ( ! this.cfg.autoplay ) { return; }
		this.play();
	};

	Slider.prototype.pause = function () {
		this.paused = true;
		this.root.classList.add( 'is-paused' );
	};

	Slider.prototype.resume = function () {
		if ( ! this.paused ) { return; }
		this.paused = false;
		this.root.classList.remove( 'is-paused' );
		this.restart();
	};

	function initAll( scope ) {
		[].forEach.call( ( scope || document ).querySelectorAll( '.wonom-slider' ), function ( el ) {
			new Slider( el ); // eslint-disable-line no-new
		} );
	}

	window.WonomSlider = { init: initAll, Slider: Slider };

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () { initAll(); } );
	} else {
		initAll();
	}

	// Elementor editor / preview: re-init when the widget is (re)rendered.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/wonom_slider.default', function ( $scope ) {
				var el = $scope && $scope[ 0 ] ? $scope[ 0 ] : $scope;
				initAll( el );
			} );
		}
	} );
}() );
