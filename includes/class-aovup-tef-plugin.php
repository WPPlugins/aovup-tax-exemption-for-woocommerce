<?php
/**
 * Plugin bootstrap: settings defaults, Pro detection and component wiring.
 *
 * @package AovUp_Tax_Exemption
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
final class AovUp_TEF_Plugin {

	/**
	 * Role created for tax-exempt customers.
	 */
	const ROLE = 'aovup_tef_exempt_customer';

	/**
	 * Upgrade page for the Pro version.
	 */
	const PRO_URL = 'https://aovup.com/plugins/tax-exempt/';

	/**
	 * Default option values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'aovup_tef_exempt_roles'    => array( self::ROLE ),
			'aovup_tef_claim_enabled'   => 'no',
			'aovup_tef_claim_who'       => 'everyone',
			'aovup_tef_claim_label'     => '',
			'aovup_tef_claim_note'      => '',
			'aovup_tef_number_mode'     => 'required',
			'aovup_tef_number_label'    => '',
			'aovup_tef_remember_number' => 'yes',
			'aovup_tef_display_excl'    => 'yes',
		);
	}

	/**
	 * Read a plugin option with its default.
	 *
	 * @param string $key Option name.
	 * @return mixed
	 */
	public static function option( $key ) {
		$defaults = self::defaults();
		$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
		$value    = get_option( $key, $default );
		if ( is_array( $default ) && ! is_array( $value ) ) {
			$value = $default;
		}
		return $value;
	}

	/**
	 * Customer-facing label, falling back to the translated default when the setting is empty.
	 *
	 * @param string $key aovup_tef_claim_label or aovup_tef_number_label.
	 * @return string
	 */
	public static function label( $key ) {
		$value = trim( (string) self::option( $key ) );
		if ( '' !== $value ) {
			return $value;
		}
		if ( 'aovup_tef_number_label' === $key ) {
			return __( 'Tax exemption number', 'aovup-tax-exemption-for-woocommerce' );
		}
		return __( 'I am tax exempt', 'aovup-tax-exemption-for-woocommerce' );
	}

	/**
	 * Whether a yes/no option is on.
	 *
	 * @param string $key Option name.
	 * @return bool
	 */
	public static function enabled( $key ) {
		return 'yes' === self::option( $key );
	}

	/**
	 * Whether the paid AovUp Tax Exemption plugin is active. The paid plugin takes over when it is.
	 *
	 * @return bool
	 */
	public static function is_pro_active() {
		// The aovup.com Pro plugin, or AovUp's WooCommerce.com Marketplace edition.
		$active = class_exists( 'Woosuite_Tax_Exemption', false ) || defined( 'WTE_FILE' ) || class_exists( 'AovUp_Tax_Exemption', false ) || defined( 'AOVUP_TE_VERSION' );

		/**
		 * Filters whether the paid AovUp Tax Exemption plugin is active.
		 *
		 * @param bool $active Whether the paid plugin was detected.
		 */
		return (bool) apply_filters( 'aovup_tef_pro_active', $active );
	}

	/**
	 * Activation: add the tax-exempt customer role and default options.
	 */
	public static function activate() {
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, __( 'Tax exempt customer', 'aovup-tax-exemption-for-woocommerce' ), array( 'read' => true ) );
		}
		foreach ( self::defaults() as $key => $value ) {
			add_option( $key, $value );
		}
	}

	/**
	 * Boot on plugins_loaded.
	 */
	public static function boot() {
		if ( ! class_exists( 'WooCommerce', false ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_woocommerce_missing' ) );
			return;
		}

		require_once AOVUP_TEF_DIR . 'includes/class-aovup-tef-admin.php';
		new AovUp_TEF_Admin();

		if ( self::is_pro_active() ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_pro_active' ) );
			return;
		}

		require_once AOVUP_TEF_DIR . 'includes/class-aovup-tef-exemption.php';
		require_once AOVUP_TEF_DIR . 'includes/class-aovup-tef-checkout.php';
		require_once AOVUP_TEF_DIR . 'includes/class-aovup-tef-order.php';
		require_once AOVUP_TEF_DIR . 'includes/class-aovup-tef-user-profile.php';

		$exemption = new AovUp_TEF_Exemption();
		new AovUp_TEF_Checkout( $exemption );
		new AovUp_TEF_Order();
		new AovUp_TEF_User_Profile();

		add_filter( 'woocommerce_shop_manager_editable_roles', array( __CLASS__, 'shop_manager_editable_roles' ) );

		if ( is_admin() ) {
			add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'add_settings_page' ) );
		}
	}

	/**
	 * Let shop managers give customers the tax-exempt role and edit those customers.
	 *
	 * @param array $roles Roles a shop manager may edit.
	 * @return array
	 */
	public static function shop_manager_editable_roles( $roles ) {
		$roles   = (array) $roles;
		$roles[] = self::ROLE;
		return array_values( array_unique( $roles ) );
	}

	/**
	 * Register the settings tab.
	 *
	 * @param array $pages WooCommerce settings pages.
	 * @return array
	 */
	public static function add_settings_page( $pages ) {
		require_once AOVUP_TEF_DIR . 'includes/class-aovup-tef-settings.php';
		$pages[] = new AovUp_TEF_Settings();
		return $pages;
	}

	/**
	 * URL of the settings tab.
	 *
	 * @return string
	 */
	public static function settings_url() {
		return admin_url( 'admin.php?page=wc-settings&tab=aovup_tax_exemption' );
	}

	/**
	 * Our admin notices only appear on the Plugins screen, so they never take over the rest of the dashboard.
	 *
	 * @return bool
	 */
	private static function on_plugins_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && in_array( $screen->id, array( 'plugins', 'plugins-network' ), true );
	}

	/**
	 * Admin notice: WooCommerce is required.
	 */
	public static function notice_woocommerce_missing() {
		if ( ! current_user_can( 'activate_plugins' ) || ! self::on_plugins_screen() ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'AovUp Tax Exemption for WooCommerce needs WooCommerce. Please install and activate WooCommerce.', 'aovup-tax-exemption-for-woocommerce' ) . '</p></div>';
	}

	/**
	 * Admin notice: the paid plugin is active, so this free plugin stays idle.
	 */
	public static function notice_pro_active() {
		if ( ! current_user_can( 'activate_plugins' ) || ! self::on_plugins_screen() ) {
			return;
		}
		echo '<div class="notice notice-info"><p>' . esc_html__( 'AovUp Tax Exemption Pro is active, so the free AovUp Tax Exemption for WooCommerce plugin is paused. You can deactivate and delete the free plugin.', 'aovup-tax-exemption-for-woocommerce' ) . '</p></div>';
	}
}
