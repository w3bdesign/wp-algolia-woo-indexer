<?php
/**
 * PHPUnit bootstrap.
 *
 * Plugin class files are not loaded here. Test_Case loads them once per
 * process, so that tests running in a separate process (such as the
 * uninstall test) can start without the plugin's global constants.
 *
 * @package algolia-woo-indexer
 */

/*
 * Every plugin file exits unless ABSPATH is defined. It points at a fixture
 * tree holding an empty wp-admin/includes/plugin.php.
 */
define( 'ABSPATH', __DIR__ . '/fixtures/wordpress/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/shims.php';
