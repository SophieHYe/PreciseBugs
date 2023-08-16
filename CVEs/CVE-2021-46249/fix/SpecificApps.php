<?php
namespace MediaWiki\Extension\ScratchOAuth2\Api;

require_once dirname(__DIR__) . "/common/consts.php";
require_once dirname(__DIR__) . "/common/apps.php";

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\Extension\ScratchOAuth2\Common\SOA2Apps;

/**
 * Handle client-specific routes
 * GET/PATCH/DELETE /soa2/v0/applications/{client_id}
 */
class SpecificApps extends SimpleHandler {
	public function run( int $client_id ) {
		$owner_id = SOA2Apps::userID();
		if (!$owner_id) return $this->getResponseFactory()->createHttpError(401);
		$request = $this->getRequest();
		switch ( $request->getMethod() ) {
			case 'GET':
				return $this->get( $client_id, $owner_id );
			case 'PATCH':
				return $this->patch( $client_id, $owner_id );
			case 'DELETE':
				return $this->delete( $client_id, $owner_id );
		}
	}
	private function http400() {
		return $this->getResponseFactory()->createHttpError(400);
	}
	private function get( int $client_id, int $owner_id ) {
		$app = SOA2Apps::application( $client_id, $owner_id );
		if (!$app) return $this->getResponseFactory()->createHttpError(404);
		return $this->getResponseFactory()->createJson($app);
	}
	private function patch( int $client_id, int $owner_id ) {
		$data = $this->getRequest()->getBody()->getContents();
		$data = json_decode($data, true);
		if (!$data) return $this->http400();
		// Users may not modify flags, thus 403
		if (array_key_exists('flags', $data)) return $this->getResponseFactory()->createHttpError(403);
		if (
			array_key_exists('reset_secret', $data)
			&& !is_bool($data['reset_secret'])
		) return $this->http400();
		if (
			array_key_exists('app_name', $data)
			&& !SOA2Apps::appNameValid($data['app_name'])
		) return $this->http400();
		if (
			array_key_exists('redirect_uris', $data)
			&& !SOA2Apps::redirectURIsValid($data['redirect_uris'])
		) return $this->http400();
		$app = SOA2Apps::update( $client_id, $owner_id, $data );
		if (!$app) return $this->getResponseFactory()->createHttpError(404);
		return $this->getResponseFactory()->createJson($app);
	}
	private function delete( int $client_id, int $owner_id ) {
		if (SOA2Apps::delete( $client_id, $owner_id )) {
			return $this->getResponseFactory()->createNoContent();
		} else {
			return $this->getResponseFactory()->createHttpError(404);
		}
	}
	public function getParamSettings() {
		return [
			'client_id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}