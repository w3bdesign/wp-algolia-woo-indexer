<?php
/**
 * Tests for Algolia_Check_Requirements.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\Algolia_Check_Requirements;
use Algowoo\Tests\Support\Built_In_Functions;
use Algowoo\Tests\Support\Test_Case;
use Brain\Monkey\Actions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the version, settings and PHP extension checks.
 *
 * PHPMD suppressions: the plugin API, Mockery and the built-in override
 * registry are static, test names describe the behaviour under test, and one
 * test class per production class keeps related tests together.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.LongMethodName)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
#[CoversClass( Algolia_Check_Requirements::class )]
final class Check_Requirements_Test extends Test_Case {

	/**
	 * The minimum versions match the plugin header.
	 *
	 * @return void
	 */
	public function test_minimum_versions_match_the_plugin_header() {
		$header = (string) file_get_contents( self::plugin_dir() . '/algolia-woo-indexer.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file.

		self::assertSame( '8.5', ALGOLIA_MIN_PHP_VERSION );
		self::assertSame( '7.1', ALGOLIA_MIN_WP_VERSION );
		self::assertStringContainsString( 'Requires PHP: ' . ALGOLIA_MIN_PHP_VERSION, $header );
		self::assertStringContainsString( 'Requires at least: ' . ALGOLIA_MIN_WP_VERSION, $header );
	}

	/**
	 * The running PHP, which is 8.5 or newer, passes the PHP check.
	 *
	 * @return void
	 */
	public function test_php_version_check_passes_on_the_running_php() {
		self::assertTrue( Algolia_Check_Requirements::algolia_php_version_check() );
	}

	/**
	 * A PHP version below the minimum fails the PHP check.
	 *
	 * @return void
	 */
	public function test_php_version_check_fails_below_the_minimum() {
		Built_In_Functions::override(
			'version_compare',
			static function ( $version, $minimum, $operator ) {
				unset( $version );
				return \version_compare( '8.4.99', $minimum, $operator );
			}
		);

		self::assertFalse( Algolia_Check_Requirements::algolia_php_version_check() );
	}

	/**
	 * WordPress versions on both sides of the minimum.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function wp_versions() {
		return array(
			'exact minimum' => array( '7.1', true ),
			'newer'         => array( '7.2.1', true ),
			'older'         => array( '7.0.3', false ),
		);
	}

	/**
	 * The WordPress check compares the global wp_version to the minimum.
	 *
	 * @param string $version  WordPress version under test.
	 * @param bool   $expected Expected check result.
	 * @return void
	 */
	#[DataProvider( 'wp_versions' )]
	public function test_wp_version_check( $version, $expected ) {
		global $wp_version;
		$wp_version = $version; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored in tearDown().

		self::assertSame( $expected, Algolia_Check_Requirements::algolia_wp_version_check() );
	}

	/**
	 * Settings combinations where one required value is empty.
	 *
	 * @return array<string, array{mixed, mixed, mixed}>
	 */
	public static function incomplete_settings() {
		return array(
			'missing application ID' => array( '', 'key', 'index' ),
			'missing API key'        => array( 'app', '', 'index' ),
			'missing index name'     => array( 'app', 'key', '' ),
			'all missing'            => array( null, false, '0' ),
		);
	}

	/**
	 * Any empty setting fails the check and queues one notice.
	 *
	 * @param mixed $app_id     Application ID.
	 * @param mixed $api_key    Write API key.
	 * @param mixed $index_name Index name.
	 * @return void
	 */
	#[DataProvider( 'incomplete_settings' )]
	public function test_incomplete_settings_fail_and_queue_a_notice( $app_id, $api_key, $index_name ) {
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		self::assertFalse( Algolia_Check_Requirements::check_algolia_input_values( $app_id, $api_key, $index_name ) );
		self::assertSame(
			array( '<div class="notice notice-error"><p>All settings need to be set for the plugin to work.</p></div>' ),
			$this->render_notices( $notices )
		);
	}

	/**
	 * Complete settings pass the check without a notice.
	 *
	 * @return void
	 */
	public function test_complete_settings_pass_without_a_notice() {
		Actions\expectAdded( 'admin_notices' )->never();

		self::assertTrue( Algolia_Check_Requirements::check_algolia_input_values( 'app', 'key', 'index' ) );
	}

	/**
	 * Queued notices escape their message when rendered.
	 *
	 * @return void
	 */
	public function test_queued_notice_escapes_the_message() {
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Check_Requirements::queue_admin_notice( 'Use <b>this</b> & that' );

		self::assertSame(
			array( '<div class="notice notice-error"><p>Use &lt;b&gt;this&lt;/b&gt; &amp; that</p></div>' ),
			$this->render_notices( $notices )
		);
	}

	/**
	 * Combinations of missing extensions and the notices each one queues.
	 *
	 * @return array<string, array{string[], bool, string[]}>
	 */
	public static function extension_states() {
		return array(
			'all present'       => array( array(), true, array() ),
			'mbstring missing'  => array( array( 'mbstring' ), true, array( '"mbstring" PHP extension' ) ),
			'mbregex disabled'  => array( array(), false, array( '"mbregex" NOT to be disabled' ) ),
			'curl missing'      => array( array( 'curl' ), true, array( '"cURL" PHP extension' ) ),
			'mbstring and curl' => array( array( 'mbstring', 'curl' ), false, array( '"mbstring" PHP extension', '"cURL" PHP extension' ) ),
			'mbregex and curl'  => array( array( 'curl' ), false, array( '"mbregex" NOT to be disabled', '"cURL" PHP extension' ) ),
		);
	}

	/**
	 * Each missing extension queues its own notice. A missing mbstring hides the mbregex check.
	 *
	 * @param string[] $missing_extensions Extensions reported as not loaded.
	 * @param bool     $has_mbregex        Whether mb_ereg_replace() exists.
	 * @param string[] $expected_fragments Text expected in each queued notice, in order.
	 * @return void
	 */
	#[DataProvider( 'extension_states' )]
	public function test_unmet_requirements_queue_one_notice_per_problem( array $missing_extensions, $has_mbregex, array $expected_fragments ) {
		Built_In_Functions::override(
			'extension_loaded',
			static function ( $extension ) use ( $missing_extensions ) {
				return ! in_array( $extension, $missing_extensions, true );
			}
		);
		Built_In_Functions::override(
			'function_exists',
			static function ( $name ) use ( $has_mbregex ) {
				return 'mb_ereg_replace' === $name ? $has_mbregex : \function_exists( $name );
			}
		);
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Check_Requirements::check_unmet_requirements();

		$rendered = $this->render_notices( $notices );
		self::assertCount( count( $expected_fragments ), $rendered );
		foreach ( $expected_fragments as $position => $fragment ) {
			self::assertStringContainsString( htmlspecialchars( $fragment, ENT_QUOTES ), $rendered[ $position ] );
		}
	}
}
