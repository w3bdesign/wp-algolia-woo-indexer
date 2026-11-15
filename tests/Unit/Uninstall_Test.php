<?php
/**
 * Tests for uninstall.php.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Unit;

use Algowoo\Tests\Support\Test_Case;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers option clean-up on uninstall.
 *
 * The uninstall.php file defines ALGOWOO_DB_OPTION itself, as it does in WordPress
 * where the plugin is not loaded during uninstall. It therefore runs in a
 * fresh process without the plugin classes.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
final class Uninstall_Test extends Test_Case {

	/**
	 * Do not load the plugin classes, which would define ALGOWOO_DB_OPTION first.
	 *
	 * @var bool
	 */
	protected static $load_plugin_classes = false;

	/**
	 * Every saved option, including the legacy in-stock option, is deleted.
	 *
	 * @return void
	 */
	public function test_deletes_every_plugin_option() {
		$deleted = array();
		Functions\when( 'delete_option' )->alias(
			static function ( $option ) use ( &$deleted ) {
				$deleted[] = $option;
				return true;
			}
		);
		define( 'WP_UNINSTALL_PLUGIN', 'wp-algolia-woo-indexer/algolia-woo-indexer.php' );

		require self::plugin_dir() . '/uninstall.php';

		self::assertSame( '_algolia_woo_indexer', ALGOWOO_DB_OPTION );
		self::assertSame(
			array(
				'_algolia_woo_indexer_application_id',
				'_algolia_woo_indexer_admin_api_key',
				'_algolia_woo_indexer_search_api_key',
				'_algolia_woo_indexer_index_name',
				'_algolia_woo_indexer_index_in_stock',
				'_algolia_woo_indexer_automatically_send_new_products',
			),
			$deleted
		);
	}
}
