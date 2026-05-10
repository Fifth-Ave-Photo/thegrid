/**
 * Grid Index Live Deck — interactive hero deck.
 *
 * Click a signal in the right stack and the hero swaps in place
 * with a smooth fade/translate. Optional autoplay, pause-on-hover,
 * keyboard navigation (← →), and touch swipe.
 *
 * No framework. No deps. Lightweight and progressive — if JS fails,
 * the first hero card and the signal links remain fully usable.
 */
( function () {
	'use strict';

	var decks = document.querySelectorAll( '.gi-deck' );
	if ( ! decks.length ) return;

	decks.forEach( function ( deck ) {
		init( deck );
	} );

	function init( deck ) {
		var stage    = deck.querySelector( '.gi-deck__stage' );
		var slides   = deck.querySelectorAll( '.gi-deck__slide' );
		var signals  = deck.querySelectorAll( '.gi-deck__signal' );
		var prevBtn  = deck.querySelector( '[data-deck-prev]' );
		var nextBtn  = deck.querySelector( '[data-deck-next]' );
		var counter  = deck.querySelector( '.gi-deck__counter' );

		if ( ! stage || slides.length < 2 ) return;

		var total      = slides.length;
		var current    = 0;
		var autoplay   = deck.dataset.autoplay === '1';
		var rotation   = parseInt( deck.dataset.rotation, 10 ) || 7000;
		var timer      = null;
		var paused     = false;

		// Preload images for non-active slides (next first) so swap is instant.
		preload( 1 );

		function go( index ) {
			index = ( ( index % total ) + total ) % total;
			if ( index === current ) return;
			var prev = slides[ current ];
			var next = slides[ index ];
			prev.classList.remove( 'is-active' );
			prev.classList.add( 'is-leaving' );
			next.classList.add( 'is-active' );
			window.setTimeout( function () {
				prev.classList.remove( 'is-leaving' );
			}, 420 );
			signals.forEach( function ( s, i ) {
				s.classList.toggle( 'is-active', i === index );
				s.setAttribute( 'aria-current', i === index ? 'true' : 'false' );
			} );
			current = index;
			updateCounter();
			preload( 1 );
		}

		function next() { go( current + 1 ); }
		function prev() { go( current - 1 ); }

		function updateCounter() {
			if ( ! counter ) return;
			counter.textContent = pad( current + 1 ) + ' / ' + pad( total );
		}
		function pad( n ) { return n < 10 ? '0' + n : '' + n; }

		function preload( offset ) {
			var idx  = ( current + offset ) % total;
			var img  = slides[ idx ] && slides[ idx ].querySelector( 'img[data-src]' );
			if ( img && ! img.src ) img.src = img.dataset.src;
		}

		function startAutoplay() {
			if ( ! autoplay || paused ) return;
			stopAutoplay();
			timer = window.setInterval( next, rotation );
		}
		function stopAutoplay() {
			if ( timer ) { window.clearInterval( timer ); timer = null; }
		}

		// Signal clicks — always swap the active slide (no navigation).
		// "Open post" CTA on the hero handles internal navigation.
		signals.forEach( function ( s, i ) {
			s.addEventListener( 'click', function ( e ) {
				if ( e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1 ) return;
				e.preventDefault();
				go( i );
				stopAutoplay();
				startAutoplay();
			} );
			s.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					go( i );
				}
			} );
		} );

		// Prev/Next
		if ( prevBtn ) prevBtn.addEventListener( 'click', function () { prev(); stopAutoplay(); startAutoplay(); } );
		if ( nextBtn ) nextBtn.addEventListener( 'click', function () { next(); stopAutoplay(); startAutoplay(); } );

		// Keyboard (when focus is inside the deck)
		deck.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowRight' ) { e.preventDefault(); next(); }
			if ( e.key === 'ArrowLeft' )  { e.preventDefault(); prev(); }
		} );

		// Swipe (touch)
		var startX = 0, dx = 0, swiping = false;
		stage.addEventListener( 'touchstart', function ( e ) {
			swiping = true; startX = e.touches[ 0 ].clientX; dx = 0;
		}, { passive: true } );
		stage.addEventListener( 'touchmove', function ( e ) {
			if ( ! swiping ) return;
			dx = e.touches[ 0 ].clientX - startX;
		}, { passive: true } );
		stage.addEventListener( 'touchend', function () {
			if ( ! swiping ) return;
			swiping = false;
			if ( Math.abs( dx ) > 40 ) ( dx < 0 ? next() : prev() );
		} );

		// Pause-on-hover
		deck.addEventListener( 'mouseenter', function () { paused = true; stopAutoplay(); } );
		deck.addEventListener( 'mouseleave', function () { paused = false; startAutoplay(); } );

		// Reduced motion → disable autoplay.
		if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
			autoplay = false;
		}

		updateCounter();
		startAutoplay();
	}
} )();
