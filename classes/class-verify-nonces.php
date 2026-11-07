<?php
/**
 * Main Algolia Verify Nonces class
 * Called from main plugin file class-algolia-woo-indexer.php
 *
 * @package algolia-woo-indexer
 */

namespace Algowoo;

/**
 * Abort if this file is called directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Algolia_Verify_Nonces' ) ) {
	/**
	 * Verify submitted nonces
	 */
	class Algolia_Verify_Nonces {

		/**
		 * Capability required to change plugin settings or trigger indexing.
		 */
		const REQUIRED_CAPABILITY = 'manage_options';

		/**
		 * Verify the settings form nonce before we update options and settings.
		 *
		 * A nonce only protects against CSRF, so the capability is checked as well.
		 *
		 * @return bool True only when the settings form was submitted by an authorised user.
		 */
		public static function verify_settings_nonce() {
			$settings_nonce = filter_input( INPUT_POST, 'algolia_woo_indexer_admin_api_nonce_name', FILTER_DEFAULT );

			/**
			 * The settings form was not submitted, so there is nothing to authorise.
			 */
			if ( ! is_string( $settings_nonce ) || '' === $settings_nonce ) {
				return false;
			}

			if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
				wp_die( esc_html__( 'Action is not allowed.', 'algolia-woo-indexer' ), esc_html__( 'Error!', 'algolia-woo-indexer' ), array( 'response' => 403 ) );
			}

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $settings_nonce ) ), 'algolia_woo_indexer_admin_api_nonce_action' ) ) {
				wp_die( esc_html__( 'Action is not allowed.', 'algolia-woo-indexer' ), esc_html__( 'Error!', 'algolia-woo-indexer' ), array( 'response' => 403 ) );
			}

			return true;
		}

		/**
		 * Check if we are sending products to Algolia.
		 *
		 * A nonce only protects against CSRF, so the capability is checked as well.
		 *
		 * @return bool True only when the send form was submitted by an authorised user.
		 */
		public static function verify_send_products_nonce() {
			$send_products_nonce      = filter_input( INPUT_POST, 'send_products_to_algolia_nonce_name', FILTER_DEFAULT );
			$send_products_to_algolia = filter_input( INPUT_POST, 'send_products_to_algolia', FILTER_DEFAULT );

			/**
			 * The send products form was not submitted, so there is nothing to authorise.
			 */
			if ( null === $send_products_to_algolia ) {
				return false;
			}

			if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
				wp_die( esc_html__( 'Action is not allowed.', 'algolia-woo-indexer' ), esc_html__( 'Error!', 'algolia-woo-indexer' ), array( 'response' => 403 ) );
			}

			if ( ! is_string( $send_products_nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $send_products_nonce ) ), 'send_products_to_algolia_nonce_action' ) ) {
				wp_die( esc_html__( 'Action is not allowed.', 'algolia-woo-indexer' ), esc_html__( 'Error!', 'algolia-woo-indexer' ), array( 'response' => 403 ) );
			}

			return true;
		}
	}
}
