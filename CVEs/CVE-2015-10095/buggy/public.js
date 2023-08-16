jQuery(document).ready(function ( ) {
	"use strict";
	//Append the inline content to the body
	jQuery(woo_popup['popup_content']).appendTo('body');

	//Open that content on load with prettyPhoto
	jQuery.prettyPhoto.open('#woopopup');

});
