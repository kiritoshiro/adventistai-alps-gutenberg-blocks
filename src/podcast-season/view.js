/**
 * Podcast Season front end. The block has one <audio preload="none"> player;
 * each episode is a link to its audio file, which this script plays in that
 * player instead of opening the file. Nothing is downloaded before a visitor
 * presses play.
 */
const players = [];

function setup( section ) {
	const audio = section.querySelector( '.alps-pod__audio' );
	const now = section.querySelector( '.alps-pod__now' );
	const episodes = Array.from( section.querySelectorAll( '.alps-pod__episode' ) );
	if ( ! audio || ! episodes.length ) {
		return;
	}
	players.push( audio );
	let current = Math.max( 0, episodes.findIndex( ( link ) => link.classList.contains( 'is-active' ) ) );
	const heading = section.querySelector( '.alps-pod__title' );
	const cover = section.querySelector( '.alps-pod__cover' );

	// The hidden "Play:" before each title says "Pause:" on the episode that is playing.
	function announce( playing ) {
		episodes.forEach( ( link, index ) => {
			const action = link.querySelector( '.alps-pod__action' );
			if ( action ) {
				action.textContent = ( playing && index === current ? section.dataset.pause : section.dataset.play ) || action.textContent;
			}
		} );
	}

	function describe() {
		if ( ! ( 'mediaSession' in navigator ) || typeof window.MediaMetadata !== 'function' ) {
			return;
		}
		navigator.mediaSession.metadata = new window.MediaMetadata( {
			title: episodes[ current ].dataset.title || '',
			album: heading ? heading.textContent : '',
			artwork: cover ? [ { src: cover.currentSrc || cover.src, sizes: '240x240' } ] : [],
		} );
		navigator.mediaSession.setActionHandler( 'previoustrack', current > 0 ? () => select( current - 1, true ) : null );
		navigator.mediaSession.setActionHandler( 'nexttrack', current < episodes.length - 1 ? () => select( current + 1, true ) : null );
	}

	function select( index, play ) {
		const link = episodes[ index ];
		if ( ! link ) {
			return;
		}
		announce( false );
		episodes[ current ].classList.remove( 'is-active' );
		episodes[ current ].removeAttribute( 'aria-current' );
		link.classList.add( 'is-active' );
		link.setAttribute( 'aria-current', 'true' );
		current = index;
		if ( audio.src !== link.href ) {
			audio.src = link.href;
		}
		if ( now ) {
			now.textContent = link.dataset.title || '';
		}
		const more = link.closest( 'details' );
		if ( more && ! more.open ) {
			more.open = true;
		}
		if ( play ) {
			const started = audio.play();
			if ( started && started.catch ) {
				started.catch( () => {} );
			}
		}
	}

	section.addEventListener( 'click', ( event ) => {
		const link = event.target.closest && event.target.closest( '.alps-pod__episode' );
		// Modified clicks still open or download the file.
		if ( ! link || ! section.contains( link ) || event.button || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey ) {
			return;
		}
		event.preventDefault();
		const index = episodes.indexOf( link );
		if ( index === current && ! audio.paused ) {
			audio.pause();
			return;
		}
		select( index, true );
	} );

	audio.addEventListener( 'play', () => {
		// One podcast at a time on the page.
		players.forEach( ( other ) => other !== audio && other.pause() );
		section.classList.add( 'is-playing' );
		announce( true );
		describe();
	} );
	audio.addEventListener( 'pause', () => {
		section.classList.remove( 'is-playing' );
		announce( false );
	} );
	audio.addEventListener( 'ended', () => {
		section.classList.remove( 'is-playing' );
		announce( false );
		if ( 'autoNext' in section.dataset && current < episodes.length - 1 ) {
			select( current + 1, true );
		}
	} );
}

function start() {
	document.querySelectorAll( '[data-alps-pod]' ).forEach( setup );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
