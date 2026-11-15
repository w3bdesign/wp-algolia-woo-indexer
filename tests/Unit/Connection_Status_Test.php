<?php
/**
 * Tests for AlgoliaConnectionStatus.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\AlgoliaConnectionStatus;
use Algowoo\Tests\Support\Test_Case;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the connection check, its diagnostics and the status notice.
 *
 * PHPMD suppressions: the plugin API, Mockery and the built-in override
 * registry are static, test names describe the behaviour under test, and one
 * test class per production class keeps related tests together.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.LongMethodName)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
#[CoversClass( AlgoliaConnectionStatus::class )]
final class Connection_Status_Test extends Test_Case {

	const WRITE_KEY  = 'write/secret+1';
	const SEARCH_KEY = 'search-secret';

	/**
	 * Complete, valid connection settings.
	 *
	 * @return array
	 */
	private static function settings() {
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
	private function stub_http_response( $code, $body, $message = 'Reason' ) {
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
	private function stub_response_readers() {
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

	/**
	 * Users without manage_options get the default notice and no request is made.
	 *
	 * @return void
	 */
	public function test_non_administrators_get_default_status_without_a_request() {
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
	public function test_incomplete_settings_are_not_checked( $name, $value ) {
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
	public function test_invalid_application_id_is_rejected_without_a_request() {
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
	public function test_request_uses_search_key_and_bounded_options() {
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
	 * A transport error is reported with both keys redacted, including URL-encoded forms.
	 *
	 * @return void
	 */
	public function test_transport_error_is_reported_with_keys_redacted() {
		$error = Mockery::mock( 'WP_Error' );
		$error->shouldReceive( 'get_error_code' )->andReturn( 'http_request_failed' );
		$error->shouldReceive( 'get_error_message' )->andReturn(
			'cURL error 28 with ' . self::SEARCH_KEY . ' and ' . rawurlencode( self::WRITE_KEY ) . ' and ' . self::WRITE_KEY
		);
		Functions\expect( 'wp_remote_post' )->once()->andReturn( $error );

		$status = ( new AlgoliaConnectionStatus() )->get_algolia_connection_status( self::settings() );

		self::assertSame( 'error', $status['type'] );
		self::assertStringContainsString( 'could not reach Algolia', $status['message'] );
		self::assertSame(
			'Transport error (http_request_failed): cURL error 28 with [redacted] and [redacted] and [redacted]',
			$status['details']
		);
	}

	/**
	 * HTTP responses and the notice each one produces.
	 *
	 * A successful check replaces the whole status, so it carries no diagnostics.
	 *
	 * @return array<string, array{int, string, string, string, string|null}>
	 */
	public static function http_responses() {
		return array(
			'200 with hits'     => array( 200, '{"hits":[],"nbHits":0}', 'success', 'Your Algolia search connection is working.', null ),
			'200 without hits'  => array( 200, '{"nbHits":0}', 'error', 'The connection check failed.', 'unexpected search response' ),
			'400 bad request'   => array( 400, '{"message":"Index name is invalid"}', 'error', 'rejected the connection-check request as invalid', 'HTTP 400 (Reason): Index name is invalid' ),
			'401 unauthorised'  => array( 401, '{"message":"Invalid Application-ID or API key"}', 'error', 'rejected the Search API Key', 'Invalid Application-ID or API key' ),
			'403 forbidden'     => array( 403, '{"message":"Method not allowed with this API key"}', 'error', 'rejected the Search API Key', 'Method not allowed' ),
			'404 missing index' => array( 404, '{"message":"Index does not exist"}', 'warning', 'configured index was not found', 'Index does not exist' ),
			'429 rate limited'  => array( 429, '{"message":"Too many requests"}', 'error', 'rate-limited the connection check', 'Too many requests' ),
			'503 non-JSON'      => array( 503, '<html>Service Unavailable</html>', 'error', 'returned a server error', 'The response was not valid JSON.' ),
			'302 unexpected'    => array( 302, '{"message":42}', 'error', 'The connection check failed.', 'did not include an Algolia error message' ),
		);
	}

	/**
	 * Each HTTP status maps to the right notice and diagnostic.
	 *
	 * @param int         $code            HTTP status code.
	 * @param string      $body            Raw response body.
	 * @param string      $type            Expected notice type.
	 * @param string      $message_part    Text expected in the notice message.
	 * @param string|null $diagnostic_part Text expected in the diagnostic details, or null for none.
	 * @return void
	 */
	#[DataProvider( 'http_responses' )]
	public function test_http_response_maps_to_notice( $code, $body, $type, $message_part, $diagnostic_part ) {
		$this->stub_http_response( $code, $body );

		$status = ( new AlgoliaConnectionStatus() )->get_algolia_connection_status( self::settings() );

		self::assertSame( $type, $status['type'] );
		self::assertStringContainsString( $message_part, $status['message'] );
		if ( null === $diagnostic_part ) {
			self::assertArrayNotHasKey( 'details', $status );
			return;
		}
		self::assertStringContainsString( $diagnostic_part, $status['details'] );
		self::assertStringStartsWith( 'HTTP ' . $code . ' (Reason): ', $status['details'] );
	}

	/**
	 * The 404 notice uses its own title and emoji.
	 *
	 * @return void
	 */
	public function test_missing_index_has_warning_title() {
		$this->stub_http_response( 404, '{"message":"Index does not exist"}' );

		$status = ( new AlgoliaConnectionStatus() )->get_algolia_connection_status( self::settings() );

		self::assertSame( '⚠️', $status['emoji'] );
		self::assertSame( 'Algolia connection not verified', $status['title'] );
	}

	/**
	 * Keys echoed back by Algolia are redacted, and long diagnostics are cut to 500 characters.
	 *
	 * @return void
	 */
	public function test_http_diagnostic_is_redacted_and_bounded() {
		$message = 'Key ' . self::SEARCH_KEY . ' rejected ' . str_repeat( 'x', 600 );
		$this->stub_http_response( 403, (string) wp_json_encode( array( 'message' => $message ) ) );

		$status = ( new AlgoliaConnectionStatus() )->get_algolia_connection_status( self::settings() );

		self::assertStringNotContainsString( self::SEARCH_KEY, $status['details'] );
		self::assertStringContainsString( 'Key [redacted] rejected', $status['details'] );
		self::assertSame( 501, mb_strlen( $status['details'] ) );
		self::assertStringEndsWith( '…', $status['details'] );
	}

	/**
	 * The result is cached, so a second call in the same request makes no new request.
	 *
	 * @return void
	 */
	public function test_status_is_cached_per_instance() {
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

	/**
	 * The notice shows the title and message, and no diagnostics when there are none.
	 *
	 * @return void
	 */
	public function test_display_without_details() {
		Functions\when( 'current_user_can' )->justReturn( false );
		$checker = new AlgoliaConnectionStatus();

		$output = $this->capture_output(
			static function () use ( $checker ) {
				$checker->display_algolia_connection_status( self::settings() );
			}
		);

		self::assertStringContainsString( 'class="notice notice-warning inline"', $output );
		self::assertStringContainsString( '<strong>Algolia connection not checked</strong>', $output );
		self::assertStringNotContainsString( 'Diagnostic details:', $output );
	}

	/**
	 * Diagnostics are shown escaped below the message.
	 *
	 * @return void
	 */
	public function test_display_with_escaped_details() {
		$this->stub_http_response( 401, '{"message":"Bad key & <app>"}' );
		$checker = new AlgoliaConnectionStatus();

		$output = $this->capture_output(
			static function () use ( $checker ) {
				$checker->display_algolia_connection_status( self::settings() );
			}
		);

		self::assertStringContainsString( 'class="notice notice-error inline"', $output );
		self::assertStringContainsString( '<strong>Diagnostic details:</strong>', $output );
		self::assertStringContainsString( 'Bad key &amp;', $output );
		self::assertStringNotContainsString( '<app>', $output );
	}
}
