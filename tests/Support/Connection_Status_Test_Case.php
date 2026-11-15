<?php
/**
 * Shared set-up for the AlgoliaConnectionStatus tests.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Support;

use Brain\Monkey\Functions;

/**
 * Stubs an administrator and fake HTTP responses.
 */
abstract class Connection_Status_Test_Case extends Test_Case {

	const WRITE_KEY  = 'write/secret+1';
	const SEARCH_KEY = 'search-secret';

	/**
	 * Complete, valid connection settings.
	 *
	 * @return array
	 */
	protected static function settings() {
		return array(
			'application_id' => 'APPID1',
			'api_key'        => self::WRITE_KEY,
			'search_api_key' => self::SEARCH_KEY,
			'index_name'     => 'products',
		);
	}

	/**
	 * Set up stubs for an administrator.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'current_user_can' )->justReturn( true );
	}

	/**
	 * Stub the HTTP helpers to read a fake response array.
	 *
	 * @param int    $code    HTTP status code.
	 * @param string $body    Raw response body.
	 * @param string $message HTTP reason phrase.
	 * @return void
	 */
	protected function stub_http_response( $code, $body, $message = 'Reason' ) {
		Functions\expect( 'wp_remote_post' )->once()->andReturn(
			array(
				'code'    => $code,
				'body'    => $body,
				'message' => $message,
			)
		);
		$this->stub_response_readers();
	}

	/**
	 * Stub the wp_remote_retrieve_* helpers to read the fake response array.
	 *
	 * @return void
	 */
	protected function stub_response_readers() {
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static function ( $response ) {
				return $response['code'];
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static function ( $response ) {
				return $response['body'];
			}
		);
		Functions\when( 'wp_remote_retrieve_response_message' )->alias(
			static function ( $response ) {
				return $response['message'];
			}
		);
	}
}
