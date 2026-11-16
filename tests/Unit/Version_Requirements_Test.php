<?php
/**
 * Tests for the PHP and WordPress version checks.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\Algolia_Check_Requirements;
use Algowoo\Tests\Support\Test_Case;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the minimum versions and the checks against them.
 *
 * PHPMD suppression: the plugin's API is static, so it has to be called
 * statically until the production code is refactored.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
#[CoversClass( Algolia_Check_Requirements::class )]
final class Version_Requirements_Test extends Test_Case {

	/**
	 * The minimum versions match the plugin header.
	 *
	 * @return void
	 */
	public function test_minimums_match_plugin_header() {
		$header = implode( '', file( self::plugin_dir() . '/algolia-woo-indexer.php' ) );

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
	public function test_php_check_passes_on_running_php() {
		self::assertTrue( Algolia_Check_Requirements::algolia_php_version_check() );
	}

	/**
	 * A PHP version below the minimum fails the PHP check.
	 *
	 * @return void
	 */
	public function test_php_check_fails_below_minimum() {
		$this->override_built_in(
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
}
