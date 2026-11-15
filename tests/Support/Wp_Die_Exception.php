<?php
/**
 * Exception thrown by the wp_die() test stub.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo\Tests\Support;

/**
 * Carries the title and HTTP response code wp_die() was called with, so tests can assert on them.
 */
final class Wp_Die_Exception extends \RuntimeException {

	/**
	 * Title passed to wp_die().
	 *
	 * @var string
	 */
	public $title;

	/**
	 * HTTP response code from the 'response' argument passed to wp_die(), or 0 when absent.
	 *
	 * @var int
	 */
	public $response;

	/**
	 * Create the exception.
	 *
	 * @param string $message  Message passed to wp_die().
	 * @param string $title    Title passed to wp_die().
	 * @param int    $response HTTP response code passed to wp_die().
	 */
	public function __construct( $message, $title, $response ) {
		parent::__construct( $message );
		$this->title    = $title;
		$this->response = $response;
	}
}
