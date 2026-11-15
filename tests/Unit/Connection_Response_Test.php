<?php
/**
 * Tests for how AlgoliaConnectionStatus reports responses.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\AlgoliaConnectionStatus;
use Algowoo\Tests\Support\Connection_Status_Test_Case;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_Error;

/**
 * Covers response-to-notice mapping, diagnostics redaction and the status notice.
 */
#[CoversClass( AlgoliaConnectionStatus::class )]
final class Connection_Response_Test extends Connection_Status_Test_Case {

	/**
	 * A transport error is reported with both keys redacted, including URL-encoded forms.
	 *
	 * @return void
	 */
	public function test_transport_error_is_redacted() {
		$error = new WP_Error(
			'http_request_failed',
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
	public function test_diagnostic_is_redacted_and_bounded() {
		$message = 'Key ' . self::SEARCH_KEY . ' rejected ' . str_repeat( 'x', 600 );
		$this->stub_http_response( 403, (string) wp_json_encode( array( 'message' => $message ) ) );

		$status = ( new AlgoliaConnectionStatus() )->get_algolia_connection_status( self::settings() );

		self::assertStringNotContainsString( self::SEARCH_KEY, $status['details'] );
		self::assertStringContainsString( 'Key [redacted] rejected', $status['details'] );
		self::assertSame( 501, mb_strlen( $status['details'] ) );
		self::assertStringEndsWith( '…', $status['details'] );
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
