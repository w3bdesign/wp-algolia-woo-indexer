<?php
/**
 * Tests for Algolia_Woo_Indexer::init().
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\Algolia_Woo_Indexer;
use Algowoo\Tests\Support\Indexer_Test_Case;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers hook registration and the requirement notices queued by init().
 *
 * PHPMD suppression: the plugin's API is static, so it has to be called
 * statically until the production code is refactored.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
#[CoversClass( Algolia_Woo_Indexer::class )]
final class Indexer_Init_Test extends Indexer_Test_Case {

	/**
	 * Front-end requests register only the text domain loader.
	 *
	 * @return void
	 */
	public function test_front_end_only_loads_textdomain() {
		Actions\expectAdded( 'admin_notices' )->never();

		Algolia_Woo_Indexer::init();

		self::assertNotFalse( has_action( 'plugins_loaded', array( Algolia_Woo_Indexer::class, 'load_textdomain' ) ) );
		self::assertFalse( has_action( 'admin_menu' ) );
		self::assertFalse( has_action( 'admin_init' ) );
		self::assertFalse( has_action( 'save_post' ) );
		self::assertSame( '', $this->get_static_property( Algolia_Woo_Indexer::class, 'plugin_url' ) );
	}

	/**
	 * Admin requests register the menu, styles and admin_init handlers, and set the form URL.
	 *
	 * @return void
	 */
	public function test_admin_registers_admin_hooks() {
		Functions\when( 'is_admin' )->justReturn( true );
		Actions\expectAdded( 'admin_notices' )->never();

		Algolia_Woo_Indexer::init();

		self::assertNotFalse( has_action( 'admin_menu', array( Algolia_Woo_Indexer::class, 'admin_menu' ) ) );
		self::assertNotFalse( has_action( 'admin_enqueue_scripts', array( Algolia_Woo_Indexer::class, 'enqueue_settings_styles' ) ) );
		self::assertNotFalse( has_action( 'admin_init', array( Algolia_Woo_Indexer::class, 'setup_settings_sections' ) ) );
		self::assertNotFalse( has_action( 'admin_init', array( Algolia_Woo_Indexer::class, 'update_settings_options' ) ) );
		self::assertNotFalse( has_action( 'admin_init', array( Algolia_Woo_Indexer::class, 'maybe_send_products' ) ) );
		self::assertSame( self::SETTINGS_URL, $this->get_static_property( Algolia_Woo_Indexer::class, 'plugin_url' ) );
	}

	/**
	 * Admin and front-end requests.
	 *
	 * @return array<string, array{bool}>
	 */
	public static function request_types() {
		return array(
			'admin'     => array( true ),
			'front end' => array( false ),
		);
	}

	/**
	 * Automatic indexing hooks save_post on every request type, not only in admin.
	 *
	 * @param bool $is_admin Whether the request is an admin request.
	 * @return void
	 */
	#[DataProvider( 'request_types' )]
	public function test_auto_send_hooks_save_post( $is_admin ) {
		Functions\when( 'is_admin' )->justReturn( $is_admin );
		$this->stub_options( array( '_algolia_woo_indexer_automatically_send_new_products' => '1' ) );
		$hooks = $this->record_save_post_hooks();

		Algolia_Woo_Indexer::init();

		self::assertSame(
			array( array( array( Algolia_Woo_Indexer::class, 'send_new_product_to_algolia' ), 10, 2 ) ),
			$hooks->getArrayCopy()
		);
	}

	/**
	 * Auto-send values that must not register the save_post hook.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function auto_send_disabled_values() {
		return array(
			'zero'        => array( '0' ),
			'unset'       => array( false ),
			'integer one' => array( 1 ),
		);
	}

	/**
	 * Only the string '1' enables automatic indexing.
	 *
	 * @param mixed $value Saved auto-send option.
	 * @return void
	 */
	#[DataProvider( 'auto_send_disabled_values' )]
	public function test_save_post_needs_string_one( $value ) {
		$this->stub_options( array( '_algolia_woo_indexer_automatically_send_new_products' => $value ) );
		$hooks = $this->record_save_post_hooks();

		Algolia_Woo_Indexer::init();

		self::assertCount( 0, $hooks );
	}

	/**
	 * An old WordPress version queues a notice naming both minimum versions.
	 *
	 * @return void
	 */
	public function test_warns_when_wordpress_too_old() {
		global $wp_version;
		$wp_version = '6.9'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored in tearDown().
		$notices    = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Woo_Indexer::init();

		$rendered = $this->render_notices( $notices );
		self::assertCount( 1, $rendered );
		self::assertStringContainsString( 'requires minimum PHP version 8.5 and WordPress version 7.1', $rendered[0] );
	}

	/**
	 * An old PHP version queues the same requirements notice.
	 *
	 * @return void
	 */
	public function test_warns_when_php_too_old() {
		$this->override_built_in(
			'version_compare',
			static function ( $version, $minimum, $operator ) {
				return PHP_VERSION === $version ? \version_compare( '8.4.0', $minimum, $operator ) : \version_compare( $version, $minimum, $operator );
			}
		);
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Woo_Indexer::init();

		self::assertCount( 1, $notices );
	}

	/**
	 * Missing PHP extensions are reported through check_unmet_requirements().
	 *
	 * @return void
	 */
	public function test_runs_extension_checks() {
		$this->override_built_in(
			'extension_loaded',
			static function ( $extension ) {
				return 'curl' !== $extension;
			}
		);
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Woo_Indexer::init();

		self::assertStringContainsString( '&quot;cURL&quot; PHP extension', $this->render_notices( $notices )[0] );
	}

	/**
	 * Admin requests warn when WooCommerce is not active.
	 *
	 * @return void
	 */
	public function test_warns_when_woocommerce_inactive() {
		Functions\when( 'is_admin' )->justReturn( true );
		$checked_plugins = array();
		Functions\when( 'is_plugin_active' )->alias(
			static function ( $plugin ) use ( &$checked_plugins ) {
				$checked_plugins[] = $plugin;
				return false;
			}
		);
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Woo_Indexer::init();

		$rendered = $this->render_notices( $notices );
		self::assertSame( array( 'woocommerce/woocommerce.php' ), $checked_plugins );
		self::assertCount( 1, $rendered );
		self::assertStringContainsString( 'WooCommerce plugin must be enabled', $rendered[0] );
	}
}
