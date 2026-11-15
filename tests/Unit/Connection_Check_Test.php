<?php
/**
 * Tests for when and how AlgoliaConnectionStatus checks the connection.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\AlgoliaConnectionStatus;
use Algowoo\Tests\Support\Connection_Status_Test_Case;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers access control, settings validation, the request and caching.
 */
#[CoversClass( AlgoliaConnectionStatus::class )]
final class Connection_Check_Test extends Connection_Status_Test_Case {

	/**
	 * Users without manage_options get the default notice and no request is made.
	 *
	 * @return void
	 */
	public function test_non_admin_gets_default_status() {
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_remote_post' )->never();

		$status = ( new AlgoliaConnectionStatus() )->get_algolia_connection_status( self::settings() );

		self::assertSame( 'warning', $status['type'] );
		self::assertSame( 'Algolia connection not checked', $status['title'] );
		self::assertArrayNotHasKey( 'details', $status );
	}

	/**
	 * Settings that are incomplete or still hold the placeholder.
	 *
	 * @return array<string, array{string, mixed}>
	 */
	public static function incomplete_settings() {
		return array(
			'empty application ID'      => array( 'application_id', '' ),
			'whitespace search key'     => array( 'search_api_key', '   ' ),
			'placeholder index name'    => array( 'index_name', 'Change me' ),
			'upper-case placeholder'    => array( 'api_key', ' CHANGE ME ' ),
			'option missing (false)'    => array( 'application_id', false ),
			'non-string application ID' => array( 'application_id', 12345 ),
		);
	}

	/**
	 * Incomplete settings are not checked against Algolia.
	 *
	 * @param string $name  Setting to break.
	 * @param mixed  $value Value that makes the settings incomplete.
	 * @return void
	 */
	#[DataProvider( 'incomplete_settings' )]
	public function test_incomplete_settings_not_checked( $name, $value ) {
		Functions\expect( 'wp_remote_post' )->never();
		$settings          = self::settings();
		$settings[ $name ] = $value;

		$status = ( new AlgoliaConnectionStatus() )->get_algolia_connection_status( $settings );

		self::assertSame( 'warning', $status['type'] );
		self::assertSame( '⚙️', $status['emoji'] );
		self::assertSame( 'Algolia connection not checked', $status['title'] );
	}

	/**
	 * An Application ID that is not alphanumeric is rejected before any request.
	 *
	 * @return void
	 */
	public function test_invalid_app_id_rejected() {
		Functions\expect( 'wp_remote_post' )->never();
		$settings                   = self::settings();
		$settings['application_id'] = 'evil.example.com/x';

		$status = ( new AlgoliaConnectionStatus() )->get_algolia_connection_status( $settings );

		self::assertSame( 'error', $status['type'] );
		self::assertStringContainsString( 'only letters and numbers', $status['message'] );
	}

	/**
	 * The request uses trimmed settings, the Search API Key, and safe transport options.
	 *
	 * @return void
	 */
	public function test_request_uses_search_key() {
		$settings = array(
			'application_id' => ' APPID1 ',
			'api_key'        => self::WRITE_KEY,
			'search_api_key' => ' ' . self::SEARCH_KEY . ' ',
			'index_name'     => 'my products',
		);
		$this->stub_response_readers();
		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://APPID1.algolia.net/1/indexes/my%20products/query',
				array(
					'timeout'             => 5,
					'redirection'         => 0,
					'limit_response_size' => 65536,
					'headers'             => array(
						'X-Algolia-Application-Id' => 'APPID1',
						'X-Algolia-API-Key'        => self::SEARCH_KEY,
						'Content-Type'             => 'application/json',
					),
					'body'                => '{"query":"","hitsPerPage":0,"analytics":false}',
				)
			)
			->andReturn(
				array(
					'code'    => 200,
					'body'    => '{"hits":[]}',
					'message' => 'OK',
				)
			);

		$status = ( new AlgoliaConnectionStatus() )->get_algolia_connection_status( $settings );

		self::assertSame( 'success', $status['type'] );
	}

	/**
	 * The result is cached, so a second call in the same request makes no new request.
	 *
	 * @return void
	 */
	public function test_status_is_cached() {
		$this->stub_http_response( 200, '{"hits":[]}' );
		$checker = new AlgoliaConnectionStatus();

		$first  = $checker->get_algolia_connection_status( self::settings() );
		$second = $checker->get_algolia_connection_status( array() );

		self::assertSame( $first, $second );
	}

	/**
	 * Incomplete settings are cached as well.
	 *
	 * @return void
	 */
	public function test_unchecked_status_is_cached() {
		Functions\expect( 'wp_remote_post' )->never();
		$checker = new AlgoliaConnectionStatus();

		$checker->get_algolia_connection_status( array( 'application_id' => '' ) );
		$status = $checker->get_algolia_connection_status( self::settings() );

		self::assertSame( 'Algolia connection not checked', $status['title'] );
	}
}
