<?php
/**
 * Tests for Algolia_Verify_Nonces.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\Algolia_Verify_Nonces;
use Algowoo\Tests\Support\Test_Case;
use Algowoo\Tests\Support\Wp_Die_Exception;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the settings and send-products form verification.
 *
 * PHPMD suppression: the plugin's API is static, so it has to be called
 * statically until the production code is refactored.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
#[CoversClass( Algolia_Verify_Nonces::class )]
final class Verify_Nonces_Test extends Test_Case {

	const SETTINGS_FIELD  = 'algolia_woo_indexer_admin_api_nonce_name';
	const SETTINGS_ACTION = 'algolia_woo_indexer_admin_api_nonce_action';
	const SEND_FIELD      = 'send_products_to_algolia_nonce_name';
	const SEND_ACTION     = 'send_products_to_algolia_nonce_action';
	const SEND_FLAG       = 'send_products_to_algolia';

	/**
	 * Assert that the callback calls wp_die() with a 403 response.
	 *
	 * @param callable $callback Code expected to die.
	 * @return void
	 */
	private function assert_dies_with_403( callable $callback ) {
		try {
			$callback();
		} catch ( Wp_Die_Exception $exception ) {
			self::assertSame( 'Action is not allowed.', $exception->getMessage() );
			self::assertSame( 'Error!', $exception->title );
			self::assertSame( array( 'response' => 403 ), $exception->args );
			return;
		}

		self::fail( 'Expected wp_die() to be called.' );
	}

	/**
	 * POST data where the settings form was not really submitted.
	 *
	 * @return array<string, array{array}>
	 */
	public static function settings_not_submitted() {
		return array(
			'no POST data'     => array( array() ),
			'empty nonce'      => array( array( self::SETTINGS_FIELD => '' ) ),
			'array nonce'      => array( array( self::SETTINGS_FIELD => array( 'x' ) ) ),
			'only send fields' => array( array( self::SEND_FLAG => 'true' ) ),
		);
	}

	/**
	 * Without a settings nonce there is nothing to authorise, so it returns false.
	 *
	 * @param array $post Simulated POST data.
	 * @return void
	 */
	#[DataProvider( 'settings_not_submitted' )]
	public function test_settings_form_not_submitted( array $post ) {
		$this->set_post_data( $post );
		Functions\expect( 'current_user_can' )->never();
		Functions\expect( 'wp_verify_nonce' )->never();

		self::assertFalse( Algolia_Verify_Nonces::verify_settings_nonce() );
	}

	/**
	 * A user without manage_options is stopped before the nonce is checked.
	 *
	 * @return void
	 */
	public function test_settings_needs_capability() {
		$this->set_post_data( array( self::SETTINGS_FIELD => 'abc' ) );
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		Functions\expect( 'wp_verify_nonce' )->never();

		$this->assert_dies_with_403( array( Algolia_Verify_Nonces::class, 'verify_settings_nonce' ) );
	}

	/**
	 * An invalid nonce from an administrator is rejected.
	 *
	 * @return void
	 */
	public function test_settings_rejects_bad_nonce() {
		$this->set_post_data( array( self::SETTINGS_FIELD => 'forged' ) );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'wp_verify_nonce' )->once()->with( 'forged', self::SETTINGS_ACTION )->andReturn( false );

		$this->assert_dies_with_403( array( Algolia_Verify_Nonces::class, 'verify_settings_nonce' ) );
	}

	/**
	 * A valid nonce from an administrator is accepted after unslashing and sanitizing it.
	 *
	 * @return void
	 */
	public function test_settings_accepts_valid_nonce() {
		$this->set_post_data( array( self::SETTINGS_FIELD => ' abc\\123 ' ) );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'wp_verify_nonce' )->once()->with( 'abc123', self::SETTINGS_ACTION )->andReturn( 1 );

		self::assertTrue( Algolia_Verify_Nonces::verify_settings_nonce() );
	}

	/**
	 * Without the hidden send flag there is nothing to authorise, even with a nonce.
	 *
	 * @return void
	 */
	public function test_send_form_not_submitted() {
		$this->set_post_data( array( self::SEND_FIELD => 'abc' ) );
		Functions\expect( 'current_user_can' )->never();
		Functions\expect( 'wp_verify_nonce' )->never();

		self::assertFalse( Algolia_Verify_Nonces::verify_send_products_nonce() );
	}

	/**
	 * A user without manage_options cannot send products.
	 *
	 * @return void
	 */
	public function test_send_needs_capability() {
		$this->set_post_data(
			array(
				self::SEND_FLAG  => 'true',
				self::SEND_FIELD => 'abc',
			)
		);
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		Functions\expect( 'wp_verify_nonce' )->never();

		$this->assert_dies_with_403( array( Algolia_Verify_Nonces::class, 'verify_send_products_nonce' ) );
	}

	/**
	 * A missing nonce is rejected without calling wp_verify_nonce().
	 *
	 * @return void
	 */
	public function test_send_rejects_missing_nonce() {
		$this->set_post_data( array( self::SEND_FLAG => 'true' ) );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'wp_verify_nonce' )->never();

		$this->assert_dies_with_403( array( Algolia_Verify_Nonces::class, 'verify_send_products_nonce' ) );
	}

	/**
	 * An invalid nonce is rejected.
	 *
	 * @return void
	 */
	public function test_send_rejects_bad_nonce() {
		$this->set_post_data(
			array(
				self::SEND_FLAG  => 'true',
				self::SEND_FIELD => 'forged',
			)
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'wp_verify_nonce' )->once()->with( 'forged', self::SEND_ACTION )->andReturn( false );

		$this->assert_dies_with_403( array( Algolia_Verify_Nonces::class, 'verify_send_products_nonce' ) );
	}

	/**
	 * A valid nonce and the send flag from an administrator are accepted.
	 *
	 * @return void
	 */
	public function test_send_accepts_valid_request() {
		$this->set_post_data(
			array(
				self::SEND_FLAG  => 'true',
				self::SEND_FIELD => 'abc',
			)
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'wp_verify_nonce' )->once()->with( 'abc', self::SEND_ACTION )->andReturn( 2 );

		self::assertTrue( Algolia_Verify_Nonces::verify_send_products_nonce() );
	}
}
