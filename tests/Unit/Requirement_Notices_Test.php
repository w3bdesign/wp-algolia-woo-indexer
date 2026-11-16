<?php
/**
 * Tests for the settings and PHP extension requirement notices.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\Algolia_Check_Requirements;
use Algowoo\Tests\Support\Test_Case;
use Brain\Monkey\Actions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the settings check, the extension checks and the notices they queue.
 *
 * PHPMD suppression: the plugin's API is static, so it has to be called
 * statically until the production code is refactored.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
#[CoversClass( Algolia_Check_Requirements::class )]
final class Requirement_Notices_Test extends Test_Case {

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
	public function test_incomplete_settings_queue_notice( $app_id, $api_key, $index_name ) {
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
	public function test_complete_settings_pass() {
		Actions\expectAdded( 'admin_notices' )->never();

		self::assertTrue( Algolia_Check_Requirements::check_algolia_input_values( 'app', 'key', 'index' ) );
	}

	/**
	 * Queued notices escape their message when rendered.
	 *
	 * @return void
	 */
	public function test_queued_notice_is_escaped() {
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
	public function test_each_unmet_requirement_queued( array $missing_extensions, $has_mbregex, array $expected_fragments ) {
		$this->override_built_in(
			'extension_loaded',
			static function ( $extension ) use ( $missing_extensions ) {
				return ! in_array( $extension, $missing_extensions, true );
			}
		);
		$this->override_built_in(
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
