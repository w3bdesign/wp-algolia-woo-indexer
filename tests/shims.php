<?php
/**
 * Namespaced shims for the PHP built-ins the plugin calls unqualified.
 *
 * They must be defined before any plugin file is loaded. PHP caches the
 * resolution of an unqualified call the first time it runs, so a shim defined
 * later would be ignored.
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

use Algowoo\Tests\Support\Built_In_Functions;

/**
 * Shim for filter_input(). The CLI has no request data, so tests set it.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 *
 * @param mixed ...$args Original arguments.
 * @return mixed
 */
function filter_input( ...$args ) {
	return Built_In_Functions::call( 'filter_input', $args );
}

/**
 * Shim for extension_loaded().
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 *
 * @param mixed ...$args Original arguments.
 * @return bool
 */
function extension_loaded( ...$args ) {
	return Built_In_Functions::call( 'extension_loaded', $args );
}

/**
 * Shim for function_exists().
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 *
 * @param mixed ...$args Original arguments.
 * @return bool
 */
function function_exists( ...$args ) {
	return Built_In_Functions::call( 'function_exists', $args );
}

/**
 * Shim for file_exists().
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 *
 * @param mixed ...$args Original arguments.
 * @return bool
 */
function file_exists( ...$args ) {
	return Built_In_Functions::call( 'file_exists', $args );
}

/**
 * Shim for version_compare().
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 *
 * @param mixed ...$args Original arguments.
 * @return mixed
 */
function version_compare( ...$args ) {
	return Built_In_Functions::call( 'version_compare', $args );
}
