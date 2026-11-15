<?php
/**
 * Tests for the Algolia_Woo_Indexer lifecycle.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\Algolia_Woo_Indexer;
use Algowoo\AlgoliaConnectionStatus;
use Algowoo\Tests\Support\Indexer_Test_Case;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;

/**
 * Covers construction, the singleton, activation and deactivation.
 *
 * PHPMD suppression: the plugin's API is static, so it has to be called
 * statically until the production code is refactored.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
#[CoversClass( Algolia_Woo_Indexer::class )]
final class Indexer_Lifecycle_Test extends Indexer_Test_Case {

	/**
	 * The singleton is created once and runs init().
	 *
	 * @return void
	 */
	public function test_get_instance_is_singleton() {
		$first  = Algolia_Woo_Indexer::get_instance();
		$second = Algolia_Woo_Indexer::get_instance();

		self::assertInstanceOf( Algolia_Woo_Indexer::class, $first );
		self::assertSame( $first, $second );
		self::assertNotFalse( has_action( 'plugins_loaded', array( Algolia_Woo_Indexer::class, 'load_textdomain' ) ) );
	}

	/**
	 * Without an injected service, the constructor creates a real connection checker.
	 *
	 * @return void
	 */
	public function test_constructor_creates_status() {
		$indexer = new Algolia_Woo_Indexer();

		self::assertInstanceOf(
			AlgoliaConnectionStatus::class,
			( new ReflectionProperty( Algolia_Woo_Indexer::class, 'connection_status' ) )->getValue( $indexer )
		);
	}

	/**
	 * Activation seeds placeholder defaults for unset options and sets the notice transient.
	 *
	 * @return void
	 */
	public function test_activation_seeds_defaults() {
		$added = array();
		Functions\when( 'add_option' )->alias(
			static function ( $name, $value ) use ( &$added ) {
				$added[ $name ] = $value;
			}
		);
		Functions\expect( 'set_transient' )->once()->with( 'algowoo-plugin-notice', true );

		Algolia_Woo_Indexer::activate_plugin();

		self::assertSame(
			array(
				'_algolia_woo_indexer_automatically_send_new_products' => '0',
				'_algolia_woo_indexer_application_id' => 'Change me',
				'_algolia_woo_indexer_admin_api_key'  => 'Change me',
				'_algolia_woo_indexer_index_name'     => 'Change me',
				'_algolia_woo_indexer_search_api_key' => '',
			),
			$added
		);
	}

	/**
	 * Activation keeps existing options, including an empty Search API Key.
	 *
	 * @return void
	 */
	public function test_activation_keeps_existing() {
		$this->stub_options(
			array(
				'_algolia_woo_indexer_automatically_send_new_products' => '1',
				'_algolia_woo_indexer_application_id' => 'APP',
				'_algolia_woo_indexer_admin_api_key'  => 'write',
				'_algolia_woo_indexer_search_api_key' => '',
				'_algolia_woo_indexer_index_name'     => 'products',
			)
		);
		$added      = $this->record_calls( 'add_option' );
		$transients = $this->record_calls( 'set_transient' );

		Algolia_Woo_Indexer::activate_plugin();

		self::assertCount( 0, $added );
		self::assertSame( array( array( 'algowoo-plugin-notice', true ) ), $transients->getArrayCopy() );
	}

	/**
	 * Deactivation removes only the notice transient.
	 *
	 * @return void
	 */
	public function test_deactivation_deletes_transient() {
		$transients = $this->record_calls( 'delete_transient' );
		$options    = $this->record_calls( 'delete_option' );

		Algolia_Woo_Indexer::deactivate_plugin();

		self::assertSame( array( array( 'algowoo-plugin-notice' ) ), $transients->getArrayCopy() );
		self::assertCount( 0, $options );
	}
}
