<?php
/**
 * WooCommerce > Settings > Tax exemption tab.
 *
 * @package AovUp_Tax_Exemption
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings tab. WooCommerce's settings API handles the nonce, capability check and sanitizing.
 */
final class AovUp_TEF_Settings extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'aovup_tax_exemption';
		$this->label = __( 'Tax exemption', 'aovup-tax-exemption-for-woocommerce' );
		parent::__construct();
		add_action( 'woocommerce_sections_' . $this->id, array( $this, 'taxes_disabled_notice' ), 5 );
	}

	/**
	 * Warn when WooCommerce taxes are switched off.
	 */
	public function taxes_disabled_notice() {
		if ( wc_tax_enabled() ) {
			return;
		}
		$url = admin_url( 'admin.php?page=wc-settings&tab=general' );
		echo '<div class="notice notice-warning inline"><p>' . wp_kses_post(
			sprintf(
				/* translators: %s: URL of WooCommerce general settings. */
				__( 'Taxes are switched off in WooCommerce, so there is nothing to exempt yet. Turn on "Enable tax rates and calculations" in <a href="%s">WooCommerce &gt; Settings &gt; General</a>.', 'aovup-tax-exemption-for-woocommerce' ),
				esc_url( $url )
			)
		) . '</p></div>';
	}

	/**
	 * Role choices.
	 *
	 * @return array
	 */
	private function role_options() {
		$roles = array();
		foreach ( wp_roles()->get_names() as $role => $name ) {
			if ( 'administrator' === $role ) {
				continue;
			}
			$roles[ $role ] = translate_user_role( $name );
		}
		return $roles;
	}

	/**
	 * Settings fields.
	 *
	 * @return array
	 */
	protected function get_settings_for_default_section() {
		$pro_features = array(
			__( 'Exemption rules by country, product, category, user or role', 'aovup-tax-exemption-for-woocommerce' ),
			__( 'Tax exemption through coupon codes', 'aovup-tax-exemption-for-woocommerce' ),
			__( 'Prices with or without tax and custom price suffixes per customer group', 'aovup-tax-exemption-for-woocommerce' ),
			__( 'Tax certificate uploads and a form builder for exemption fields', 'aovup-tax-exemption-for-woocommerce' ),
		);

		return array(
			array(
				'title' => __( 'Tax-exempt customers', 'aovup-tax-exemption-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Customers with these roles never pay tax. You can also mark single customers as tax exempt on their user profile (Users > Edit user > Tax exemption).', 'aovup-tax-exemption-for-woocommerce' ),
				'id'    => 'aovup_tef_customers',
			),
			array(
				'title'   => __( 'Tax-exempt roles', 'aovup-tax-exemption-for-woocommerce' ),
				'id'      => 'aovup_tef_exempt_roles',
				'type'    => 'multiselect',
				'class'   => 'wc-enhanced-select',
				'css'     => 'min-width: 350px;',
				'options' => $this->role_options(),
				'default' => array( AovUp_TEF_Plugin::ROLE ),
				'desc'    => __( 'The plugin adds a "Tax exempt customer" role you can give to customers.', 'aovup-tax-exemption-for-woocommerce' ),
			),
			array(
				'title'   => __( 'Price display', 'aovup-tax-exemption-for-woocommerce' ),
				'desc'    => __( 'Show prices without tax to tax-exempt customers', 'aovup-tax-exemption-for-woocommerce' ),
				'id'      => 'aovup_tef_display_excl',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'aovup_tef_customers',
			),
			array(
				'title' => __( 'Claim at checkout', 'aovup-tax-exemption-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Let customers tick "I am tax exempt" at checkout and enter their exemption number. Tax is removed from the order straight away, in the classic checkout and in the Checkout block. The number is shown on the order and in order emails.', 'aovup-tax-exemption-for-woocommerce' ),
				'id'    => 'aovup_tef_claim',
			),
			array(
				'title'   => __( 'Checkout claim', 'aovup-tax-exemption-for-woocommerce' ),
				'desc'    => __( 'Let customers claim tax exemption at checkout', 'aovup-tax-exemption-for-woocommerce' ),
				'id'      => 'aovup_tef_claim_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Who can claim', 'aovup-tax-exemption-for-woocommerce' ),
				'id'      => 'aovup_tef_claim_who',
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => 'everyone',
				'options' => array(
					'everyone'  => __( 'Everyone, including guests', 'aovup-tax-exemption-for-woocommerce' ),
					'logged_in' => __( 'Logged-in customers only', 'aovup-tax-exemption-for-woocommerce' ),
				),
			),
			array(
				'title'       => __( 'Checkbox label', 'aovup-tax-exemption-for-woocommerce' ),
				'id'          => 'aovup_tef_claim_label',
				'type'        => 'text',
				'default'     => '',
				'placeholder' => __( 'I am tax exempt', 'aovup-tax-exemption-for-woocommerce' ),
			),
			array(
				'title'    => __( 'Text under the checkbox', 'aovup-tax-exemption-for-woocommerce' ),
				'id'       => 'aovup_tef_claim_note',
				'type'     => 'textarea',
				'default'  => '',
				'desc_tip' => __( 'Optional, classic checkout only. For example: "Only for registered resellers. We may ask for your certificate."', 'aovup-tax-exemption-for-woocommerce' ),
			),
			array(
				'title'   => __( 'Exemption number', 'aovup-tax-exemption-for-woocommerce' ),
				'id'      => 'aovup_tef_number_mode',
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => 'required',
				'options' => array(
					'required' => __( 'Required', 'aovup-tax-exemption-for-woocommerce' ),
					'optional' => __( 'Optional', 'aovup-tax-exemption-for-woocommerce' ),
					'hidden'   => __( 'Do not ask', 'aovup-tax-exemption-for-woocommerce' ),
				),
			),
			array(
				'title'       => __( 'Number field label', 'aovup-tax-exemption-for-woocommerce' ),
				'id'          => 'aovup_tef_number_label',
				'type'        => 'text',
				'default'     => '',
				'placeholder' => __( 'Tax exemption number', 'aovup-tax-exemption-for-woocommerce' ),
			),
			array(
				'title'   => __( 'Remember the number', 'aovup-tax-exemption-for-woocommerce' ),
				'desc'    => __( 'Fill in the last number a logged-in customer used', 'aovup-tax-exemption-for-woocommerce' ),
				'id'      => 'aovup_tef_remember_number',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'aovup_tef_claim',
			),
			array(
				'title' => __( 'Need more?', 'aovup-tax-exemption-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => wp_kses_post(
					sprintf(
						/* translators: 1: list of Pro features, 2: URL of the Pro version. */
						__( 'AovUp Tax Exemption Pro adds: %1$s. <a href="%2$s" target="_blank" rel="noopener noreferrer">See AovUp Tax Exemption Pro</a>', 'aovup-tax-exemption-for-woocommerce' ),
						esc_html( implode( '; ', $pro_features ) ),
						esc_url( AovUp_TEF_Plugin::PRO_URL )
					)
				),
				'id'    => 'aovup_tef_pro',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'aovup_tef_pro',
			),
		);
	}
}
