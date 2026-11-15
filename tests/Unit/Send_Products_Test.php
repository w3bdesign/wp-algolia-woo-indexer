<?php
/**
 * Tests for Algolia_Send_Products that stop before the Algolia client is used.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\Algolia_Send_Products;
use Algowoo\Tests\Support\Test_Case;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers price selection and the early exits of send_products_to_algolia().
 *
 * PHPMD suppression: the plugin's API is static, so it has to be called
 * statically until the production code is refactored.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
#[CoversClass( Algolia_Send_Products::class )]
final class Send_Products_Test extends Test_Case {

	/**
	 * Simple products use their own sale and regular prices.
	 *
	 * @return void
	 */
	public function test_simple_product_prices() {
		$product = mock();
		$product->shouldReceive( 'is_type' )->with( 'simple' )->andReturn( true );
		$product->shouldReceive( 'get_sale_price' )->andReturn( '5' );
		$product->shouldReceive( 'get_regular_price' )->andReturn( '10' );

		self::assertSame(
			array(
				'sale_price'    => '5',
				'regular_price' => '10',
			),
			Algolia_Send_Products::get_product_type_price( $product )
		);
	}

	/**
	 * Variable products use the lowest sale and highest regular variation prices.
	 *
	 * @return void
	 */
	public function test_variable_product_prices() {
		$product = mock();
		$product->shouldReceive( 'is_type' )->with( 'simple' )->andReturn( false );
		$product->shouldReceive( 'is_type' )->with( 'variable' )->andReturn( true );
		$product->shouldReceive( 'get_variation_sale_price' )->once()->with( 'min', true )->andReturn( '3' );
		$product->shouldReceive( 'get_variation_regular_price' )->once()->with( 'max', true )->andReturn( '9' );

		self::assertSame(
			array(
				'sale_price'    => '3',
				'regular_price' => '9',
			),
			Algolia_Send_Products::get_product_type_price( $product )
		);
	}

	/**
	 * Other product types, such as grouped or external, get zero prices.
	 *
	 * @return void
	 */
	public function test_other_types_get_zero_prices() {
		$product = mock();
		$product->shouldReceive( 'is_type' )->andReturn( false );

		self::assertSame(
			array(
				'sale_price'    => 0,
				'regular_price' => 0,
			),
			Algolia_Send_Products::get_product_type_price( $product )
		);
	}

	/**
	 * Without the Composer autoloader a notice is queued and nothing else happens.
	 *
	 * @return void
	 */
	public function test_missing_autoloader_queues_notice() {
		$checked_paths = array();
		$this->override_built_in(
			'file_exists',
			static function ( $path ) use ( &$checked_paths ) {
				$checked_paths[] = $path;
				return false;
			}
		);
		Functions\expect( 'get_option' )->never();
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Send_Products::send_products_to_algolia();

		self::assertSame( array( self::plugin_dir() . '//vendor/autoload.php' ), $checked_paths );
		self::assertSame(
			array( '<div class="notice notice-error"><p>The Algolia PHP client is missing. Run &quot;composer install&quot; in the plugin directory.</p></div>' ),
			$this->render_notices( $notices )
		);
	}

	/**
	 * Incomplete settings queue the settings notice and stop before fetching products.
	 *
	 * @return void
	 */
	public function test_incomplete_settings_stop_early() {
		$this->stub_options(
			array(
				'_algolia_woo_indexer_application_id' => 'APP',
				'_algolia_woo_indexer_admin_api_key'  => '',
				'_algolia_woo_indexer_index_name'     => 'products',
			)
		);
		Functions\expect( 'wc_get_products' )->never();
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Send_Products::send_products_to_algolia();

		self::assertStringContainsString( 'All settings need to be set', $this->render_notices( $notices )[0] );
	}
}
