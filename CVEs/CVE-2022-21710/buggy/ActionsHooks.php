<?php
/**
 * ShortDescription actions hooks
 *
 * @file
 * @ingroup Extensions
 * @license GPL-3.0-or-later
 */

declare( strict_types=1 );

namespace MediaWiki\Extension\ShortDescription\Hooks;

use MediaWiki\Hook\InfoActionHook;

class ActionsHooks implements InfoActionHook {

	/**
	 * InfoAction hook handler, adds the short description to the info=action page
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/InfoAction
	 *
	 * @param IContextSource $context Context, used to extract the title of the page
	 * @param array[] $pageInfo Auxillary information about the page.
	 */
	public function onInfoAction( $context, &$pageInfo ) {
		$shortdesc = HookUtils::getShortDescription( $context->getTitle() );
		if ( !$shortdesc ) {
			// The page has no short description
			return;
		}

		$pageInfo['header-basic'][] = [
			$context->msg( 'shortdescription-info-label' ),
			$shortdesc
		];
	}
}
