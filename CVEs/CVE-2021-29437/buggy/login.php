<?php

namespace MediaWiki\Extension\ScratchOAuth2\Common;

require_once __DIR__ . "/consts.php";
require_once __DIR__ . "/db.php";

class SOA2Login {
	/**
	 * Generate the Scratch verification code.
	 * @param string $username the username to generate the code for
	 */
	public static function gen_code( $session ) {
		if (!$session->exists( 'soa2_scratch_code' )) {
			$code = chunk_split(hash('sha256', random_bytes(32)), 5, ':');
			$code = substr($code, 0, strlen($code) - 1); // chop off last colon
			$session->set( 'soa2_scratch_code', $code );
			$session->save();
		}
		return $session->get( 'soa2_scratch_code' );
	}
	/**
	 * Get the data needed to complete a login.
	 * @param string $username the username to get the codes for
	 */
	public static function codes( string $username ) {
		global $wgRequest;
		// get user data from API
		$user = json_decode(file_get_contents(sprintf(
			SOA2_USERS_API, urlencode($username))), true);
		if (!$user) return null;
		// save user data
		$username = $user['username'];
		SOA2DB::saveUser( $user['id'], $user['username'] );
		// actually do the code generation
		$session = $wgRequest->getSession();
		$session->persist();
		$csrf = $session->getToken()->toString();
		// Step 15
		$code = self::gen_code( $session ); // Step 15
		return [ 'username' => $username, 'csrf' => $csrf, 'code' => $code ];
	}
	/**
	 * Did the user comment this code?
	 * @param string $code the code
	 * @param string $username the user
	 */
	public static function commented( string $username, string $code ) {
		$username = strtolower($username);
		// Step 20, 21
		$comments = file_get_contents(sprintf(SOA2_COMMENTS_API, $username, rand()));
		$matches = [];
		preg_match_all(SOA2_COMMENTS_REGEX, $comments, $matches, PREG_PATTERN_ORDER);
		for ($i = 0; $i < count($matches[0]); ++$i) {
			if (strtolower($matches[1][$i]) !== $username) continue;
			if (hash_equals($code, $matches[2][$i])) return true; // Step 22
		}
		return false;
	}
	/**
	 * Log in a user
	 * @param string $username the username to login
	 * @param string $csrf the CSRF token submitted
	 * @return boolean whether the login was successful
	 */
	public static function login( string $username, string $csrf ) {
		global $wgRequest;
		$session = $wgRequest->getSession();
		$session->persist();
		$token = $session->getToken();
		if (!$session->getToken()->match($csrf, SOA2_CODE_EXPIRY)) return false;
		$code = self::gen_code( $session ); // Step 19
		if (!self::commented( $username, $code )) return false;
		// the login's successful, set the user ID
		$user = SOA2DB::getUserByName( $username );
		$session->set( 'soa2_user_id', $user->user_id ); // Step 23
		$session->remove( 'soa2_scratch_code' );
		$session->save();
		return true;
	}
}