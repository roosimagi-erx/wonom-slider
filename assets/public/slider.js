/*! Wonom Slider – dependency-free front-end script. */
( function () {
	'use strict';

	var REDUCED = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

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
			autoplay: !! cfg.autoplay && ! REDUCED,
			interval: Math.max( 1000, parseInt( cfg.interval, 10 ) || 6000 ),
			speed: Math.max( 100, parseInt( cfg.speed, 10 ) || 800 ),
			transition: cfg.transition === 'slide' ? 'slide' : 'fade',
			loop: cfg.loop !== false,
			pauseOnHover: cfg.pauseOnHover !== false,
			progress: !! cfg.progress
		};

		this.slides = [].slice.call( root.querySelectorAll( '.wonom-slide' ) );
		this.dots = [].slice.call( root.querySelectorAll( '.wonom-slider__dot' ) );
		this.progress = root.querySelector( '.wonom-slider__progress' );
		this.index = 0;
		this.timer = null;
		this.paused = false;
		this.animating = false;
		this.count = this.slides.length;

		if ( this.count < 2 ) {
			root.classList.add( 'is-single' );
			return;
		}

		this.bind();
		this.go( 0, 0, true );
		this.play();
	}

	Slider.prototype.bind = function () {
		var self = this, root = this.root;

		[].forEach.call( root.querySelectorAll( '.wonom-slider__arrow' ), function ( btn ) {
			btn.addEventListener( 'click', function () {
				self.step( parseInt( btn.getAttribute( 'data-dir' ), 10 ) || 1, true );
			} );
		} );

		this.dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () {
				self.go( i, i > self.index ? 1 : -1, false, true );
			} );
		} );

		if ( this.cfg.pauseOnHover ) {
			root.addEventListener( 'mouseenter', function () { self.pause(); } );
			root.addEventListener( 'mouseleave', function () { self.resume(); } );
		}
		root.addEventListener( 'focusin', function () { self.pause(); } );
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

	Slider.prototype.step = function ( dir, user ) {
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
		if ( ! this.cfg.autoplay ) { return; }
		clearInterval( this.timer );
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
