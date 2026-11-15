<?php
/**
 * Shared set-up for the Algolia_Woo_Indexer tests.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Support;

use ArrayObject;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

/**
 * Stubs a front-end request on a server that meets every requirement.
 */
abstract class Indexer_Test_Case extends Test_Case {

	const SETTINGS_URL = 'https://shop.test/wp-admin/options-general.php?page=algolia-woo-indexer-settings';

	/**
	 * Options written by update_option() during the test.
	 *
	 * @var array<string, mixed>
	 */
	protected $updated_options = array();

	/**
	 * Stub WordPress for a front-end request with every requirement met.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->override_built_in(
			'extension_loaded',
			static function () {
				return true;
			}
		);
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_plugin_active' )->justReturn( true );
		Functions\when( 'admin_url' )->alias(
			static function ( $path ) {
				return 'https://shop.test/wp-admin/' . $path;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->updated_options[ $name ] = $value;
				return true;
			}
		);
		$this->stub_options( array() );
	}

	/**
	 * Stub a valid settings form submission by an administrator.
	 *
	 * @param array $fields Settings fields to add to the POST data.
	 * @return void
	 */
	protected function submit_settings_form( array $fields ) {
		$this->set_post_data( array( 'algolia_woo_indexer_admin_api_nonce_name' => 'nonce' ) + $fields );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
	}

	/**
	 * Make the next call to send_products_to_algolia() stop at the autoloader check.
	 *
	 * That check queues a notice, which shows that the sender was reached
	 * without involving the Algolia client.
	 *
	 * @return ArrayObject Notice callbacks.
	 */
	protected function stop_sender_at_autoloader() {
		$this->override_built_in(
			'file_exists',
			static function () {
				return false;
			}
		);

		return $this->capture_hook_callbacks( 'admin_notices' );
	}

	/**
	 * Record every save_post registration with its priority and accepted arguments.
	 *
	 * @return ArrayObject One array of add_action() arguments per registration.
	 */
	protected function record_save_post_hooks() {
		$hooks = new ArrayObject();
		Actions\expectAdded( 'save_post' )
			->zeroOrMoreTimes()
			->whenHappen(
				static function ( ...$args ) use ( $hooks ) {
					$hooks[] = $args;
				}
			);

		return $hooks;
	}
}
