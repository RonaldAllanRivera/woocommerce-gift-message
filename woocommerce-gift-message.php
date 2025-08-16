<?php
/**
 * Plugin Name: WooCommerce Gift Message
 * Description: Adds a Gift Message field to WooCommerce product pages and saves it through cart → order → admin → email.
 * Version: 1.0.0
 * Author: Inspry
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: woocommerce-gift-message
 * WC tested up to: 9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WCGM_VERSION' ) ) {
	define( 'WCGM_VERSION', '1.0.0' );
}

if ( ! defined( 'WCGM_PLUGIN_FILE' ) ) {
	define( 'WCGM_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WCGM_PLUGIN_DIR' ) ) {
	define( 'WCGM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WCGM_PLUGIN_URL' ) ) {
	define( 'WCGM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Declare compatibility with WooCommerce features (HPOS & Cart/Checkout Blocks).
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WCGM_PLUGIN_FILE, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WCGM_PLUGIN_FILE, true );
    }
} );

// Activation dependency check.
register_activation_hook( __FILE__, function() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'WooCommerce Gift Message requires WooCommerce to be installed and active.', 'woocommerce-gift-message' ) );
	}
} );

// Admin notice if WooCommerce is not active.
add_action( 'admin_init', function() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce Gift Message requires WooCommerce to be installed and active.', 'woocommerce-gift-message' ) . '</p></div>';
		} );
	}
} );

// Bootstrap plugin.
add_action( 'plugins_loaded', function() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	require_once WCGM_PLUGIN_DIR . 'includes/class-wcgm.php';
	WCGM::instance();
} );
