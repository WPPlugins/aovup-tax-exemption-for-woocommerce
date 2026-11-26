<?php
/**
 * Shows the exemption on orders: admin order screen, emails and the customer's order view.
 *
 * @package AovUp_Tax_Exemption
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order display.
 */
final class AovUp_TEF_Order {

	/**
	 * Hook into WooCommerce.
	 */
	public function __construct() {
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_order' ) );
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'email_fields' ), 10, 3 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'customer_order' ) );
		add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'hide_block_fields_in_view' ), 20, 3 );
	}

	/**
	 * The order screen already shows the exemption (see admin_order()), so don't list the raw
	 * Checkout block fields a second time. They stay available in the edit form.
	 *
	 * @param array         $fields  Shipping fields shown on the order screen.
	 * @param WC_Order|bool $order   Order.
	 * @param string        $context view or edit.
	 * @return array
	 */
	public function hide_block_fields_in_view( $fields, $order = null, $context = 'edit' ) {
		unset( $order );
		if ( 'view' === $context && is_array( $fields ) ) {
			unset( $fields[ AovUp_TEF_Checkout::FIELD_CLAIM ], $fields[ AovUp_TEF_Checkout::FIELD_NUMBER ] );
		}
		return $fields;
	}

	/**
	 * Exemption details stored on an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array|null { source_label: string, number: string } or null when the order was not exempt by this plugin.
	 */
	public static function details( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return null;
		}
		$source = (string) $order->get_meta( '_aovup_tef_source' );
		if ( '' === $source ) {
			return null;
		}
		$labels = array(
			'customer' => __( 'Tax-exempt customer account', 'aovup-tax-exemption-for-woocommerce' ),
			'role'     => __( 'Tax-exempt user role', 'aovup-tax-exemption-for-woocommerce' ),
			'claim'    => __( 'Claimed by the customer at checkout', 'aovup-tax-exemption-for-woocommerce' ),
		);
		return array(
			'source_label' => isset( $labels[ $source ] ) ? $labels[ $source ] : $source,
			'number'       => (string) $order->get_meta( '_aovup_tef_number' ),
		);
	}

	/**
	 * Admin order screen, under the billing address.
	 *
	 * @param WC_Order $order Order.
	 */
	public function admin_order( $order ) {
		$details = self::details( $order );
		if ( ! $details ) {
			return;
		}
		echo '<div class="aovup-tef-order-details"><p><strong>' . esc_html__( 'Tax exemption', 'aovup-tax-exemption-for-woocommerce' ) . ':</strong><br>' . esc_html( $details['source_label'] );
		if ( '' !== $details['number'] ) {
			echo '<br>' . esc_html( AovUp_TEF_Plugin::label( 'aovup_tef_number_label' ) ) . ': ' . esc_html( $details['number'] );
		}
		echo '</p></div>';
	}

	/**
	 * Order emails.
	 *
	 * @param array    $fields        Email meta fields.
	 * @param bool     $sent_to_admin Whether the email goes to the shop.
	 * @param WC_Order $order         Order.
	 * @return array
	 */
	public function email_fields( $fields, $sent_to_admin, $order ) {
		unset( $sent_to_admin );
		$details = self::details( $order );
		if ( ! $details ) {
			return $fields;
		}
		$fields['aovup_tef_exemption'] = array(
			'label' => __( 'Tax exemption', 'aovup-tax-exemption-for-woocommerce' ),
			'value' => $details['source_label'],
		);
		if ( '' !== $details['number'] ) {
			$fields['aovup_tef_number'] = array(
				'label' => AovUp_TEF_Plugin::label( 'aovup_tef_number_label' ),
				'value' => $details['number'],
			);
		}
		return $fields;
	}

	/**
	 * Customer's order view (thank-you page and My Account).
	 *
	 * @param WC_Order $order Order.
	 */
	public function customer_order( $order ) {
		$details = self::details( $order );
		if ( ! $details ) {
			return;
		}
		echo '<section class="aovup-tef-order-exemption"><p><strong>' . esc_html__( 'Tax exemption', 'aovup-tax-exemption-for-woocommerce' ) . ':</strong> ' . esc_html__( 'No tax was charged on this order.', 'aovup-tax-exemption-for-woocommerce' );
		if ( '' !== $details['number'] ) {
			echo '<br>' . esc_html( AovUp_TEF_Plugin::label( 'aovup_tef_number_label' ) ) . ': ' . esc_html( $details['number'] );
		}
		echo '</p></section>';
	}
}
