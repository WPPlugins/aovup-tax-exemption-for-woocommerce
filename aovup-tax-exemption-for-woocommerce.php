<?php
/**
 * Plugin Name:          AovUp Tax Exemption for WooCommerce
 * Plugin URI:           https://aovup.com/plugins/tax-exempt/
 * Description:          Remove tax for exempt customers: tax-exempt user roles, tax-exempt customer accounts, and an "I am tax exempt" claim with an exemption number at checkout.
 * Version:              1.0.0
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               AovUp
 * Author URI:           https://aovup.com/
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          aovup-tax-exemption-for-woocommerce
 * Domain Path:          /languages
 * WC requires at least: 9.0
 * WC tested up to:      11.2
 *
 * @package AovUp_Tax_Exemption
 */

defined( 'ABSPATH' ) || exit;

define( 'AOVUP_TEF_VERSION', '1.0.0' );
define( 'AOVUP_TEF_FILE', __FILE__ );
define( 'AOVUP_TEF_DIR', plugin_dir_path( __FILE__ ) );
define( 'AOVUP_TEF_URL', plugin_dir_url( __FILE__ ) );

require_once AOVUP_TEF_DIR . 'includes/class-aovup-tef-plugin.php';

register_activation_hook( __FILE__, array( 'AovUp_TEF_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'AovUp_TEF_Plugin', 'boot' ), 20 );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);
