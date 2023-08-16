<?php

class SecurityLoginExtension extends Extension {

	public function onBeforeSecurityLogin() {

		// Determine whether a user has already authenticated, and whether a back URL exists.

		$URL = $this->owner->getRequest()->getVar('BackURL');
		if(Member::currentUserID() && $URL && Director::is_site_url($URL)) {

			// Take the user to the back URL.

			$this->owner->redirect($URL);
		}
	}

}
