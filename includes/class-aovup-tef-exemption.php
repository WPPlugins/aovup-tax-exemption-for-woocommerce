<?php
/**
 * Decides whether the current customer is tax exempt and applies it to WooCommerce.
 *
 * WooCommerce's own VAT-exempt flag on the customer does the tax removal, so cart, checkout
 * (classic and block) and the order all follow it.
 *
 * @package AovUp_Tax_Exemption
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exemption logic.
 */
final class AovUp_TEF_Exemption {

	/**
	 * Session key holding the checkout claim: array( 'claimed' => bool, 'number' => string ).
	 */
	const SESSION_CLAIM = 'aovup_tef_claim';

	/**
	 * Session key set when this plugin switched the customer's VAT-exempt flag on.
	 */
	const SESSION_APPLIED = 'aovup_tef_applied';

	/**
	 * Per-request cache of exemption status by user ID.
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Hook into WooCommerce.
	 */
	public function __construct() {
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'apply_to_customer' ), 5 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_to_customer' ), 5 );
		add_filter( 'woocommerce_order_is_vat_exempt', array( $this, 'order_is_vat_exempt' ), 10, 2 );
		add_filter( 'option_woocommerce_tax_display_shop', array( $this, 'tax_display' ) );
		add_filter( 'option_woocommerce_tax_display_cart', array( $this, 'tax_display' ) );
	}

	/**
	 * Exemption status for a user (or the current visitor).
	 *
	 * @param int|null $user_id User ID, or null for the current visitor (includes the checkout claim).
	 * @return array { exempt: bool, source: string (customer|role|claim|''), number: string }
	 */
	public function status( $user_id = null ) {
		$visitor = null === $user_id;
		$user_id = $visitor ? get_current_user_id() : absint( $user_id );
		$key     = $visitor ? 'visitor-' . $user_id : 'user-' . $user_id;

		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$status = array(
			'exempt' => false,
			'source' => '',
			'number' => '',
		);

		if ( $user_id ) {
			$customer = self::customer_exemption( $user_id );
			if ( $customer['exempt'] ) {
				$status = array(
					'exempt' => true,
					'source' => 'customer',
					'number' => $customer['number'],
				);
			} elseif ( self::user_has_exempt_role( $user_id ) ) {
				$status = array(
					'exempt' => true,
					'source' => 'role',
					'number' => $customer['number'],
				);
			}
		}

		if ( ! $status['exempt'] && $visitor && self::claim_allowed() ) {
			$claim = $this->get_claim();
			if ( $claim['claimed'] ) {
				$status = array(
					'exempt' => true,
					'source' => 'claim',
					'number' => $claim['number'],
				);
			}
		}

		$this->cache[ $key ] = $status;
		return $status;
	}

	/**
	 * Exemption saved on a customer account by a shop manager.
	 *
	 * @param int $user_id User ID.
	 * @return array { exempt: bool, number: string, expires: string }
	 */
	public static function customer_exemption( $user_id ) {
		$exempt  = 'yes' === get_user_meta( $user_id, 'aovup_tef_exempt', true );
		$number  = (string) get_user_meta( $user_id, 'aovup_tef_number', true );
		$expires = (string) get_user_meta( $user_id, 'aovup_tef_expires', true );

		if ( $exempt && '' !== $expires && $expires < current_time( 'Y-m-d' ) ) {
			$exempt = false;
		}

		return array(
			'exempt'  => $exempt,
			'number'  => $number,
			'expires' => $expires,
		);
	}

	/**
	 * Whether the user has one of the tax-exempt roles.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_has_exempt_role( $user_id ) {
		$roles = (array) AovUp_TEF_Plugin::option( 'aovup_tef_exempt_roles' );
		if ( empty( $roles ) ) {
			return false;
		}
		$user = get_userdata( $user_id );
		return $user && array_intersect( $roles, (array) $user->roles );
	}

	/**
	 * Whether the current visitor may claim exemption at checkout.
	 *
	 * @return bool
	 */
	public static function claim_allowed() {
		if ( ! AovUp_TEF_Plugin::enabled( 'aovup_tef_claim_enabled' ) ) {
			return false;
		}
		return 'logged_in' !== AovUp_TEF_Plugin::option( 'aovup_tef_claim_who' ) || is_user_logged_in();
	}

	/**
	 * Current checkout claim from the WooCommerce session.
	 *
	 * @return array { claimed: bool, number: string }
	 */
	public function get_claim() {
		$claim = ( function_exists( 'WC' ) && WC()->session ) ? WC()->session->get( self::SESSION_CLAIM ) : null;
		return array(
			'claimed' => is_array( $claim ) && ! empty( $claim['claimed'] ),
			'number'  => is_array( $claim ) && isset( $claim['number'] ) ? (string) $claim['number'] : '',
		);
	}

	/**
	 * Store the checkout claim in the session and re-apply exemption.
	 *
	 * @param bool   $claimed Whether the customer ticked the claim box.
	 * @param string $number  Exemption number (already sanitized).
	 */
	public function set_claim( $claimed, $number ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		WC()->session->set(
			self::SESSION_CLAIM,
			array(
				'claimed' => (bool) $claimed,
				'number'  => (string) $number,
			)
		);
		$this->cache = array();
		$this->apply_to_customer();
	}

	/**
	 * Forget the checkout claim (after an order is placed).
	 */
	public function clear_claim() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_CLAIM, null );
		}
		$this->cache = array();
	}

	/**
	 * Switch WooCommerce's VAT-exempt flag on the customer to match the exemption status.
	 * Only switches it off again if this plugin switched it on, so other plugins' exemptions are left alone.
	 */
	public function apply_to_customer() {
		if ( ! function_exists( 'WC' ) || ! WC()->customer || ! WC()->session ) {
			return;
		}

		$status  = $this->status();
		$applied = (bool) WC()->session->get( self::SESSION_APPLIED );

		if ( $status['exempt'] ) {
			if ( ! WC()->customer->get_is_vat_exempt() ) {
				WC()->customer->set_is_vat_exempt( true );
				WC()->session->set( self::SESSION_APPLIED, true );
			}
		} elseif ( $applied ) {
			WC()->customer->set_is_vat_exempt( false );
			WC()->session->set( self::SESSION_APPLIED, false );
		}
	}

	/**
	 * Orders created by a shop manager (no VAT-exempt flag stored yet) follow the customer's exemption
	 * when taxes are calculated.
	 *
	 * @param bool     $is_exempt Whether the order is VAT exempt.
	 * @param WC_Order $order     Order.
	 * @return bool
	 */
	public function order_is_vat_exempt( $is_exempt, $order ) {
		if ( $is_exempt || ! $order instanceof WC_Order || '' !== (string) $order->get_meta( 'is_vat_exempt' ) ) {
			return $is_exempt;
		}
		$customer_id = $order->get_customer_id();
		if ( ! $customer_id ) {
			return $is_exempt;
		}
		$status = $this->status( $customer_id );
		return $status['exempt'] ? true : $is_exempt;
	}

	/**
	 * Show prices without tax to tax-exempt visitors, since that is what they pay.
	 *
	 * @param string $value Tax display setting (incl|excl).
	 * @return string
	 */
	public function tax_display( $value ) {
		if ( 'excl' === $value || ! AovUp_TEF_Plugin::enabled( 'aovup_tef_display_excl' ) ) {
			return $value;
		}
		if ( ( is_admin() && ! wp_doing_ajax() ) || ! did_action( 'woocommerce_init' ) ) {
			return $value;
		}
		$status = $this->status();
		return $status['exempt'] ? 'excl' : $value;
	}
}
