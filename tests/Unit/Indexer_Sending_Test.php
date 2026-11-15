<?php
/**
 * Tests for the Algolia_Woo_Indexer send triggers.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\Algolia_Woo_Indexer;
use Algowoo\Tests\Support\Indexer_Test_Case;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the manual send form and the save_post auto-send trigger.
 *
 * PHPMD suppression: the plugin's API is static, so it has to be called
 * statically until the production code is refactored.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
#[CoversClass( Algolia_Woo_Indexer::class )]
final class Indexer_Sending_Test extends Indexer_Test_Case {

	/**
	 * Without the send-products form nothing is sent.
	 *
	 * @return void
	 */
	public function test_maybe_send_without_form() {
		$notices = $this->stop_sender_at_autoloader();

		Algolia_Woo_Indexer::maybe_send_products();

		self::assertCount( 0, $notices );
	}

	/**
	 * A verified send-products form starts a full send.
	 *
	 * @return void
	 */
	public function test_maybe_send_after_verification() {
		$this->set_post_data(
			array(
				'send_products_to_algolia'            => 'true',
				'send_products_to_algolia_nonce_name' => 'nonce',
			)
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		$notices = $this->stop_sender_at_autoloader();

		Algolia_Woo_Indexer::maybe_send_products();

		self::assertStringContainsString( 'The Algolia PHP client is missing.', $this->render_notices( $notices )[0] );
	}

	/**
	 * Posts that must not be sent when saved.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function posts_not_sent() {
		return array(
			'draft product'  => array( 'draft', 'product' ),
			'published post' => array( 'publish', 'post' ),
			'private page'   => array( 'private', 'page' ),
		);
	}

	/**
	 * Only published products are sent from save_post.
	 *
	 * @param string $status    Post status.
	 * @param string $post_type Post type.
	 * @return void
	 */
	#[DataProvider( 'posts_not_sent' )]
	public function test_ignores_unpublished_products( $status, $post_type ) {
		$notices = $this->stop_sender_at_autoloader();
		$post    = (object) array(
			'post_status' => $status,
			'post_type'   => $post_type,
		);

		Algolia_Woo_Indexer::send_new_product_to_algolia( 7, $post );

		self::assertCount( 0, $notices );
	}

	/**
	 * A published product is sent when saved.
	 *
	 * @return void
	 */
	public function test_sends_published_product() {
		$notices = $this->stop_sender_at_autoloader();
		$post    = (object) array(
			'post_status' => 'publish',
			'post_type'   => 'product',
		);

		Algolia_Woo_Indexer::send_new_product_to_algolia( 7, $post );

		self::assertCount( 1, $notices );
	}
}
