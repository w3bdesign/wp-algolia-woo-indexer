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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers the bootstrap: loading classes, lifecycle hooks and WooCommerce compatibility.
 *
 * There is no CoversClass attribute, because a plain file has no class to
 * name. Coverage is attributed to whatever the test executes.
 *
 * WordPress loads the plugin file once per request, so each test runs in its
 * own process and loads it once with require_once. That also keeps the
 * FeaturesUtil alias mock from leaking into other tests.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
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

		require_once self::plugin_dir() . '/algolia-woo-indexer.php';

		return $callbacks;
	}

	/**
	 * Activation and deactivation hooks are registered on the singleton.
	 *
	 * @return void
	 */
	public function test_registers_lifecycle_hooks() {
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

		$instance = $this->get_static_property( Algolia_Woo_Indexer::class, 'instance' );
		self::assertInstanceOf( Algolia_Woo_Indexer::class, $instance );
		self::assertSame( array( $plugin_file, array( $instance, 'activate_plugin' ) ), $registered['activation'] );
		self::assertSame( array( $plugin_file, array( $instance, 'deactivate_plugin' ) ), $registered['deactivation'] );
	}

	/**
	 * Without WooCommerce's FeaturesUtil the compatibility callback does nothing.
	 *
	 * @return void
	 */
	public function test_compat_skipped_without_woocommerce() {
		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );

		$callbacks = $this->load_plugin_file();

		self::assertCount( 1, $callbacks );
		self::assertNull( $callbacks[0]() );
	}

	/**
	 * With WooCommerce loaded, HPOS and Cart/Checkout Blocks compatibility are declared.
	 *
	 * @return void
	 */
	public function test_declares_hpos_and_blocks() {
		$plugin_file = self::plugin_dir() . '/algolia-woo-indexer.php';
		$declared    = new ArrayObject();
		mock( 'alias:Automattic\WooCommerce\Utilities\FeaturesUtil' )
			->shouldReceive( 'declare_compatibility' )
			->andReturnUsing(
				static function ( ...$args ) use ( $declared ) {
					$declared[] = $args;
				}
			);
		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );

		$callbacks = $this->load_plugin_file();
		$callbacks[0]();

		self::assertSame(
			array(
				array( 'custom_order_tables', $plugin_file, true ),
				array( 'cart_checkout_blocks', $plugin_file, true ),
			),
			$declared->getArrayCopy()
		);
	}
}
