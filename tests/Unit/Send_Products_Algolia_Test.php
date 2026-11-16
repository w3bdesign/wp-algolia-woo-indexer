<?php
/**
 * Tests for Algolia_Send_Products that reach the Algolia client.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Response\NullResponse;
use Algowoo\Algolia_Send_Products;
use Algowoo\Tests\Support\Test_Case;
use Brain\Monkey\Functions;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use stdClass;

/**
 * Covers record building and the outcomes of saveObjects().
 *
 * The plugin calls the static SearchClient::create(), so each test replaces
 * that class with a Mockery alias. An alias can only be declared before the
 * real class is autoloaded, and it lasts for the whole process, so every test
 * here runs in its own process.
 *
 * PHPMD suppression: the plugin's API is static, so it has to be called
 * statically until the production code is refactored.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
#[CoversClass( Algolia_Send_Products::class )]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
final class Send_Products_Algolia_Test extends Test_Case {

	/**
	 * Mocked Algolia index.
	 *
	 * @var MockInterface
	 */
	private $index;

	/**
	 * Number of wp_reset_postdata() calls.
	 *
	 * @var int
	 */
	private $postdata_resets = 0;

	/**
	 * Arguments SearchClient::create() was called with.
	 *
	 * @var array
	 */
	private $client_args = array();

	/**
	 * Replace the Algolia client and stub complete settings.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->index = mock();
		$client      = mock();
		$client->shouldReceive( 'initIndex' )->andReturnUsing(
			function ( $index_name ) {
				$this->client_args[] = $index_name;
				return $this->index;
			}
		);
		mock( 'alias:Algolia\AlgoliaSearch\SearchClient' )
			->shouldReceive( 'create' )
			->andReturnUsing(
				function ( $app_id, $api_key ) use ( $client ) {
					$this->client_args = array( $app_id, $api_key );
					return $client;
				}
			);

		$this->stub_options(
			array(
				'_algolia_woo_indexer_application_id' => 'APP',
				'_algolia_woo_indexer_admin_api_key'  => 'write-key',
				'_algolia_woo_indexer_index_name'     => 'products',
			)
		);
		Functions\when( 'wp_reset_postdata' )->alias(
			function () {
				++$this->postdata_resets;
			}
		);
	}

	/**
	 * Build a mock WooCommerce product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $type       Product type.
	 * @param string $image      HTML returned by get_image().
	 * @return MockInterface
	 */
	private function product( $product_id, $type, $image ) {
		$product = mock();
		$product->shouldReceive( 'is_type' )->andReturnUsing(
			static function ( $checked_type ) use ( $type ) {
				return $checked_type === $type;
			}
		);
		$product->shouldReceive( 'get_sale_price' )->andReturn( '8' );
		$product->shouldReceive( 'get_regular_price' )->andReturn( '10' );
		$product->shouldReceive( 'get_variation_sale_price' )->andReturn( '4' );
		$product->shouldReceive( 'get_variation_regular_price' )->andReturn( '12' );
		$product->shouldReceive( 'get_image' )->andReturn( $image );
		$product->shouldReceive( 'get_id' )->andReturn( $product_id );
		$product->shouldReceive( 'get_name' )->andReturn( 'Product ' . $product_id );
		$product->shouldReceive( 'get_short_description' )->andReturn( 'Short ' . $product_id );
		$product->shouldReceive( 'is_on_sale' )->andReturn( 'variable' === $type );

		return $product;
	}

	/**
	 * Sending everything fetches all published products and saves one record per product.
	 *
	 * @return void
	 */
	public function test_sends_all_products() {
		Functions\expect( 'wc_get_products' )
			->once()
			->with(
				array(
					'status'   => 'publish',
					'limit'    => -1,
					'paginate' => false,
				)
			)
			->andReturn(
				array(
					$this->product( 11, 'simple', '<img width="300" src="https://shop.test/a.jpg" class="wp-post-image" />' ),
					$this->product( 12, 'variable', '' ),
				)
			);
		$this->index->shouldReceive( 'saveObjects' )
			->once()
			->with(
				array(
					array(
						'objectID'          => 11,
						'product_name'      => 'Product 11',
						'product_image'     => 'https://shop.test/a.jpg',
						'short_description' => 'Short 11',
						'regular_price'     => '10',
						'sale_price'        => '8',
						'on_sale'           => false,
					),
					array(
						'objectID'          => 12,
						'product_name'      => 'Product 12',
						'product_image'     => null,
						'short_description' => 'Short 12',
						'regular_price'     => '12',
						'sale_price'        => '4',
						'on_sale'           => true,
					),
				)
			)
			->andReturn( new stdClass() );
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Send_Products::send_products_to_algolia();

		self::assertSame( 1, $this->postdata_resets );
		self::assertSame( array( 'APP', 'write-key', 'products' ), $this->client_args );
		self::assertSame(
			array( '<div class="notice notice-success is-dismissible"><p>2 products sent to Algolia index &quot;products&quot;. Indexing may still be in progress.</p></div>' ),
			$this->render_notices( $notices )
		);
	}

	/**
	 * Sending one product fetches it by ID and uses the singular notice.
	 *
	 * @return void
	 */
	public function test_sends_single_product() {
		Functions\expect( 'wc_get_products' )
			->once()
			->with(
				array(
					'status'   => 'publish',
					'include'  => array( 42 ),
					'paginate' => false,
				)
			)
			->andReturn( array( $this->product( 42, 'grouped', '' ) ) );
		$this->index->shouldReceive( 'saveObjects' )->once()->andReturn( new stdClass() );
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Send_Products::send_products_to_algolia( 42 );

		self::assertStringContainsString( '1 product sent to Algolia index', $this->render_notices( $notices )[0] );
	}

	/**
	 * A null ID is treated like no ID and fetches every published product.
	 *
	 * @return void
	 */
	public function test_null_id_sends_all_products() {
		$queries = $this->record_calls( 'wc_get_products', array() );

		Algolia_Send_Products::send_products_to_algolia( null );

		self::assertSame(
			array(
				array(
					array(
						'status'   => 'publish',
						'limit'    => -1,
						'paginate' => false,
					),
				),
			),
			$queries->getArrayCopy()
		);
	}

	/**
	 * Non-string options fall back to the placeholder, which still passes the empty check.
	 *
	 * @return void
	 */
	public function test_non_string_options_use_placeholder() {
		$this->stub_options( array() );
		Functions\when( 'wc_get_products' )->justReturn( array() );

		Algolia_Send_Products::send_products_to_algolia();

		self::assertSame( array( 'change me', 'change me', 'change me' ), $this->client_args );
	}

	/**
	 * With no published products nothing is sent and no notice is queued.
	 *
	 * @return void
	 */
	public function test_no_products_sends_nothing() {
		Functions\expect( 'wc_get_products' )->once()->andReturn( array() );
		$this->index->shouldReceive( 'saveObjects' )->never();
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Send_Products::send_products_to_algolia();

		self::assertSame( 0, $this->postdata_resets );
		self::assertCount( 0, $notices );
	}

	/**
	 * An Algolia error is caught and reported as a notice about the Write API Key.
	 *
	 * @return void
	 */
	public function test_algolia_error_queues_error_notice() {
		Functions\when( 'wc_get_products' )->justReturn( array( $this->product( 1, 'simple', '' ) ) );
		$this->index->shouldReceive( 'saveObjects' )->once()->andThrow( new AlgoliaException( 'Forbidden' ) );
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Send_Products::send_products_to_algolia();

		$rendered = $this->render_notices( $notices );
		self::assertCount( 1, $rendered );
		self::assertStringContainsString( '<div class="error notice">', $rendered[0] );
		self::assertStringContainsString( 'Unable to send products to Algolia.', $rendered[0] );
		self::assertStringContainsString( 'A Search API Key cannot upload products.', $rendered[0] );
	}

	/**
	 * A NullResponse from the client is reported instead of a success message.
	 *
	 * @return void
	 */
	public function test_null_response_queues_notice() {
		Functions\when( 'wc_get_products' )->justReturn( array( $this->product( 1, 'simple', '' ) ) );
		$this->index->shouldReceive( 'saveObjects' )->once()->andReturn( new NullResponse() );
		$notices = $this->capture_hook_callbacks( 'admin_notices' );

		Algolia_Send_Products::send_products_to_algolia();

		self::assertSame(
			array( '<div class="notice notice-error"><p>No response from the server. Please check your settings and try again.</p></div>' ),
			$this->render_notices( $notices )
		);
	}
}
