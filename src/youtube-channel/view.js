/**
 * YouTube Channel Videos, front end. The server renders the posters and the
 * video list; nothing loads from YouTube until a visitor presses play. Then
 * the poster is replaced by a youtube-nocookie.com player that continues with
 * the following videos in the list.
 */

const mobile = () => window.matchMedia( '(max-width: 768px)' ).matches;

function playerUrl( id, ids ) {
	const start = ids.indexOf( id );
	const rest = ids.slice( start + 1 ).concat( ids.slice( 0, Math.max( start, 0 ) ) );
	const params = new URLSearchParams( { autoplay: '1', playsinline: '1', rel: '0' } );
	if ( rest.length ) {
		params.set( 'playlist', rest.join( ',' ) );
	}
	return `https://www.youtube-nocookie.com/embed/${ id }?${ params }`;
}

/** Dates as "prieš 3 dienas" / "3 days ago" in the page language; the server prints a plain date. */
function relativeDates( root ) {
	if ( ! window.Intl || ! Intl.RelativeTimeFormat ) {
		return;
	}
	const format = new Intl.RelativeTimeFormat( document.documentElement.lang || undefined, { numeric: 'auto' } );
	const units = [ [ 'year', 365 ], [ 'month', 30 ], [ 'week', 7 ], [ 'day', 1 ] ];
	root.querySelectorAll( 'time[datetime]' ).forEach( ( time ) => {
		const days = Math.floor( ( Date.now() - Date.parse( time.dateTime ) ) / 86400000 );
		if ( ! ( days >= 0 ) ) {
			return;
		}
		const [ unit, size ] = units.find( ( [ , length ] ) => days >= length ) || [ 'day', 1 ];
		time.title = time.textContent;
		time.textContent = format.format( -Math.floor( days / size ), unit );
	} );
}

/**
 * Some mobile browsers (iOS Safari especially) return to the top of the page
 * when the player leaves fullscreen. Remember the position while the player
 * is in view and restore it if the page jumps back near the top.
 */
function keepScrollAfterFullscreen( player ) {
	let saved = null;
	let away = false;
	const near = () => {
		const rect = player.getBoundingClientRect();
		return rect.bottom > -120 && rect.top < window.innerHeight + 120;
	};
	const remember = () => {
		if ( mobile() && near() ) {
			saved = window.scrollY;
		}
	};
	const restore = () => {
		if ( saved === null || ! mobile() ) {
			return;
		}
		const target = saved;
		[ 0, 80, 250, 600 ].forEach( ( delay ) => setTimeout( () => {
			if ( window.scrollY < 80 && target > 120 ) {
				window.scrollTo( 0, target );
			}
		}, delay ) );
	};
	const leave = () => {
		if ( mobile() && near() ) {
			away = true;
			remember();
		}
	};
	const back = () => {
		if ( away ) {
			away = false;
			restore();
		}
	};
	window.addEventListener( 'scroll', () => away || remember(), { passive: true } );
	const change = () => ( document.fullscreenElement || document.webkitFullscreenElement ? leave() : back() );
	document.addEventListener( 'fullscreenchange', change );
	document.addEventListener( 'webkitfullscreenchange', change );
	// Native iOS video fullscreen is not reported as fullscreenElement.
	window.addEventListener( 'blur', leave );
	window.addEventListener( 'focus', back );
	document.addEventListener( 'visibilitychange', () => ( document.hidden ? leave() : back() ) );
	remember();
}

function setUp( root ) {
	const player = root.querySelector( '.alps-ytc__player' );
	const track = root.querySelector( '.alps-ytc__track' );
	const cards = [ ...root.querySelectorAll( '.alps-ytc__card' ) ];
	const ids = cards.map( ( card ) => card.dataset.video );
	const nowTitle = root.querySelector( '.alps-ytc__now-title' );
	const watch = root.querySelector( '.alps-ytc__watch' );
	let playing = '';

	function mark( id ) {
		cards.forEach( ( card ) => {
			const active = card.dataset.video === id;
			card.classList.toggle( 'is-active', active );
			if ( active ) {
				card.setAttribute( 'aria-current', 'true' );
			} else {
				card.removeAttribute( 'aria-current' );
			}
		} );
		const card = cards.find( ( item ) => item.dataset.video === id );
		if ( card && nowTitle ) {
			nowTitle.textContent = card.dataset.title;
		}
		if ( watch ) {
			watch.href = `https://www.youtube.com/watch?v=${ encodeURIComponent( id ) }`;
		}
	}

	function play( id ) {
		if ( ! /^[A-Za-z0-9_-]{11}$/.test( id ) || id === playing ) {
			return;
		}
		const first = ! playing;
		playing = id;
		const frame = document.createElement( 'iframe' );
		frame.src = playerUrl( id, ids.length ? ids : [ id ] );
		frame.title = root.dataset.iframeTitle || 'YouTube';
		frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
		frame.allowFullscreen = true;
		// Without a referrer YouTube refuses to play (error 153).
		frame.referrerPolicy = 'strict-origin-when-cross-origin';
		player.replaceChildren( frame );
		mark( id );
		if ( first ) {
			keepScrollAfterFullscreen( player );
		}
	}

	root.addEventListener( 'click', ( event ) => {
		const target = event.target.closest( '.alps-ytc__poster, .alps-ytc__card' );
		if ( ! target || ! root.contains( target ) ) {
			return;
		}
		play( target.dataset.video );
		if ( target.classList.contains( 'alps-ytc__card' ) ) {
			const rect = player.getBoundingClientRect();
			if ( rect.top < 0 || rect.bottom > window.innerHeight ) {
				player.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		}
	} );

	relativeDates( root );

	const nav = root.querySelector( '.alps-ytc__nav' );
	if ( ! track || ! nav ) {
		return;
	}
	const [ prev, next ] = nav.querySelectorAll( '.alps-ytc__arrow' );
	const count = nav.querySelector( '.alps-ytc__count' );
	let frame = 0;

	function update() {
		frame = 0;
		const overflow = track.scrollWidth - track.clientWidth > 4;
		nav.hidden = ! overflow;
		if ( ! overflow ) {
			return;
		}
		const max = track.scrollWidth - track.clientWidth;
		// scrollLeft is negative in right-to-left layouts.
		const left = Math.abs( track.scrollLeft );
		prev.disabled = left <= 4;
		next.disabled = left >= max - 4;
		const width = cards[ 0 ].parentElement.getBoundingClientRect().width || 1;
		const gap = parseFloat( getComputedStyle( track ).columnGap ) || 0;
		const first = Math.round( left / ( width + gap ) );
		const shown = Math.max( 1, Math.round( ( track.clientWidth + gap ) / ( width + gap ) ) );
		count.textContent = `${ first + 1 }–${ Math.min( first + shown, cards.length ) } / ${ cards.length }`;
	}
	const schedule = () => {
		frame = frame || window.requestAnimationFrame( update );
	};

	nav.addEventListener( 'click', ( event ) => {
		const arrow = event.target.closest( '.alps-ytc__arrow' );
		if ( arrow ) {
			const direction = getComputedStyle( track ).direction === 'rtl' ? -1 : 1;
			track.scrollBy( { left: Number( arrow.dataset.step ) * direction * track.clientWidth * 0.9, behavior: 'smooth' } );
		}
	} );
	track.addEventListener( 'scroll', schedule, { passive: true } );
	window.addEventListener( 'resize', schedule );
	update();
}

function init() {
	document.querySelectorAll( '[data-alps-ytc]:not([data-ready])' ).forEach( ( root ) => {
		root.dataset.ready = '';
		setUp( root );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init, { once: true } );
} else {
	init();
}
