function main() {
	var shortdesc = mw.config.get( 'wgShortDesc' ),
		tagline;

	if ( shortdesc ) {
		tagline = document.getElementById( 'siteSub' );
		// Wikipedia uses shortdescription class
		// Added for gadgets and extension compatibility
		tagline.classList.add( 'ext-shortdesc', 'shortdescription' );
		tagline.innerHTML = mw.html.escape( shortdesc );
	}
}

main();
