<?php
/**
 * Tests for the main plugin file, algolia-woo-indexer.php.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\Algolia_Woo_Indexer;
use ArrayObject;
use Algowoo\Tests\Support\Test_Case;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Covers the bootstrap: loading classes, lifecycle hooks and WooCommerce compatibility.
 *
 * There is no CoversClass attribute, because a plain file has no class to
 * name. Coverage is attributed to whatever the test executes.
 *
 * PHPMD suppressions: the plugin API, Mockery and the built-in override registry are static and test names describe the behaviour under test.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.LongMethodName)
 */
final class Plugin_File_Test extends Test_Case {

	/**
	 * Load the plugin file as WordPress would, returning the before_woocommerce_init callbacks.
	 *
	 * @return ArrayObject
	 */
	private function load_plugin_file() {
		Functions\when( 'plugin_dir_path' )->alias(
			static function ( $file ) {
				return dirname( $file ) . '/';
			}
		);
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( false );
		$callbacks = $this->capture_hook_callbacks( 'before_woocommerce_init' );

		require self::plugin_dir() . '/algolia-woo-indexer.php';

		return $callbacks;
	}

	/**
	 * Activation and deactivation hooks are registered on the singleton.
	 *
	 * @return void
	 */
	public function test_registers_lifecycle_hooks_on_singleton() {
		$plugin_file  = self::plugin_dir() . '/algolia-woo-indexer.php';
		$registered   = array();
		$record_hooks = static function ( $kind ) use ( &$registered ) {
			return static function ( $file, $callback ) use ( $kind, &$registered ) {
				$registered[ $kind ] = array( $file, $callback );
			};
		};
		Functions\when( 'register_activation_hook' )->alias( $record_hooks( 'activation' ) );
		Functions\when( 'register_deactivation_hook' )->alias( $record_hooks( 'deactivation' ) );

		$this->load_plugin_file();

		$instance = Algolia_Woo_Indexer::get_instance();
		self::assertSame( array( $plugin_file, array( $instance, 'activate_plugin' ) ), $registered['activation'] );
		self::assertSame( array( $plugin_file, array( $instance, 'deactivate_plugin' ) ), $registered['deactivation'] );
	}

	/**
	 * Without WooCommerce's FeaturesUtil the compatibility callback does nothing.
	 *
	 * @return void
	 */
	public function test_compatibility_callback_skips_without_woocommerce() {
		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );

		$callbacks = $this->load_plugin_file();

		self::assertCount( 1, $callbacks );
		self::assertNull( $callbacks[0]() );
	}

	/**
	 * With WooCommerce loaded, HPOS and Cart/Checkout Blocks compatibility are declared.
	 *
	 * Runs in its own process because the FeaturesUtil alias lasts for the process.
	 *
	 * @return void
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_compatibility_callback_declares_hpos_and_blocks() {
		$plugin_file = self::plugin_dir() . '/algolia-woo-indexer.php';
		$features    = Mockery::mock( 'alias:Automattic\WooCommerce\Utilities\FeaturesUtil' );
		$features->shouldReceive( 'declare_compatibility' )->once()->with( 'custom_order_tables', $plugin_file, true );
		$features->shouldReceive( 'declare_compatibility' )->once()->with( 'cart_checkout_blocks', $plugin_file, true );
		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );

		$callbacks = $this->load_plugin_file();
		$callbacks[0]();
	}
}
