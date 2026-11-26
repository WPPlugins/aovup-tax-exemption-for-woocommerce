<?php
/**
 * The "I am tax exempt" claim at checkout, for the classic checkout and the Checkout block.
 *
 * @package AovUp_Tax_Exemption
 */

defined( 'ABSPATH' ) || exit;

/**
 * Checkout claim.
 */
final class AovUp_TEF_Checkout {

	/**
	 * Block checkout field IDs.
	 */
	const FIELD_CLAIM  = 'aovup-tef/claim';
	const FIELD_NUMBER = 'aovup-tef/number';

	/**
	 * Exemption service.
	 *
	 * @var AovUp_TEF_Exemption
	 */
	private $exemption;

	/**
	 * Hook into WooCommerce.
	 *
	 * @param AovUp_TEF_Exemption $exemption Exemption service.
	 */
	public function __construct( AovUp_TEF_Exemption $exemption ) {
		$this->exemption = $exemption;

		// Classic checkout.
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_classic_fields' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_classic_assets' ) );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'classic_update_order_review' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'classic_checkout_process' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'classic_validate' ), 10, 2 );

		// Both checkouts.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_meta' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'after_order_placed' ), 10, 3 );

		// Checkout block (Store API).
		add_action( 'woocommerce_init', array( $this, 'register_block_fields' ) );
		add_action( 'woocommerce_store_api_checkout_update_draft', array( $this, 'block_update_draft' ) );
		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'block_update_customer' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'block_update_order' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'block_order_processed' ) );
	}

	/**
	 * Whether the current visitor should see the claim fields.
	 *
	 * @return bool
	 */
	private function show_claim() {
		if ( ! AovUp_TEF_Exemption::claim_allowed() || ! wc_tax_enabled() ) {
			return false;
		}
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$status = $this->exemption->status( $user_id );
			if ( $status['exempt'] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Number field mode: required, optional or hidden.
	 *
	 * @return string
	 */
	private function number_mode() {
		$mode = (string) AovUp_TEF_Plugin::option( 'aovup_tef_number_mode' );
		return in_array( $mode, array( 'required', 'optional', 'hidden' ), true ) ? $mode : 'required';
	}

	/**
	 * Clean an exemption number from user input.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function clean_number( $value ) {
		$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		return substr( $value, 0, 100 );
	}

	/**
	 * Number to prefill: the session claim, else the number remembered on the account.
	 *
	 * @return string
	 */
	private function prefill_number() {
		$claim = $this->exemption->get_claim();
		if ( '' !== $claim['number'] ) {
			return $claim['number'];
		}
		$user_id = get_current_user_id();
		if ( $user_id && AovUp_TEF_Plugin::enabled( 'aovup_tef_remember_number' ) ) {
			return (string) get_user_meta( $user_id, 'aovup_tef_last_number', true );
		}
		return '';
	}

	/**
	 * Classic checkout: show a notice for exempt accounts, or the claim fields.
	 *
	 * @param WC_Checkout $checkout Checkout object.
	 */
	public function render_classic_fields( $checkout ) {
		unset( $checkout );
		$user_id = get_current_user_id();
		if ( $user_id && wc_tax_enabled() ) {
			$status = $this->exemption->status( $user_id );
			if ( $status['exempt'] ) {
				echo '<p class="aovup-tef-account-exempt">' . esc_html__( 'Your account is tax exempt, so no tax is charged on this order.', 'aovup-tax-exemption-for-woocommerce' ) . '</p>';
				return;
			}
		}
		if ( ! $this->show_claim() ) {
			return;
		}

		$claim = $this->exemption->get_claim();
		$mode  = $this->number_mode();
		$note  = trim( (string) AovUp_TEF_Plugin::option( 'aovup_tef_claim_note' ) );

		echo '<div class="aovup-tef-claim" id="aovup-tef-claim">';
		woocommerce_form_field(
			'aovup_tef_claim',
			array(
				'type'        => 'checkbox',
				'class'       => array( 'form-row-wide', 'aovup-tef-claim-field' ),
				'label'       => esc_html( AovUp_TEF_Plugin::label( 'aovup_tef_claim_label' ) ),
				'description' => '' !== $note ? esc_html( $note ) : '',
			),
			$claim['claimed'] ? 1 : 0
		);
		if ( 'hidden' !== $mode ) {
			woocommerce_form_field(
				'aovup_tef_number',
				array(
					'type'              => 'text',
					'class'             => array( 'form-row-wide', 'aovup-tef-number-field' ),
					'label'             => esc_html( AovUp_TEF_Plugin::label( 'aovup_tef_number_label' ) ),
					'required'          => 'required' === $mode,
					'custom_attributes' => array( 'maxlength' => '100' ),
				),
				$this->prefill_number()
			);
		}
		echo '</div>';
	}

	/**
	 * Classic checkout script: show the number field when the box is ticked and refresh totals.
	 */
	public function enqueue_classic_assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() || ! $this->show_claim() ) {
			return;
		}
		wp_enqueue_style( 'aovup-tef-checkout', AOVUP_TEF_URL . 'assets/css/checkout.css', array(), AOVUP_TEF_VERSION );
		wp_enqueue_script( 'aovup-tef-checkout', AOVUP_TEF_URL . 'assets/js/checkout.js', array( 'jquery' ), AOVUP_TEF_VERSION, true );
	}

	/**
	 * Classic checkout AJAX refresh. WooCommerce checks the update-order-review nonce before this runs.
	 *
	 * @param string $post_data Serialized checkout form.
	 */
	public function classic_update_order_review( $post_data ) {
		if ( ! $this->show_claim() ) {
			return;
		}
		$data = array();
		parse_str( is_string( $post_data ) ? $post_data : '', $data );
		$claimed = ! empty( $data['aovup_tef_claim'] );
		$number  = isset( $data['aovup_tef_number'] ) ? self::clean_number( wp_unslash( $data['aovup_tef_number'] ) ) : '';
		$this->exemption->set_claim( $claimed, $number );
	}

	/**
	 * Classic checkout submit, before totals are recalculated. WooCommerce has verified the
	 * woocommerce-process-checkout-nonce before this action fires.
	 */
	public function classic_checkout_process() {
		if ( ! $this->show_claim() ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by WC_Checkout::process_checkout().
		$claimed = ! empty( $_POST['aovup_tef_claim'] );
		$number  = isset( $_POST['aovup_tef_number'] ) ? self::clean_number( sanitize_text_field( wp_unslash( $_POST['aovup_tef_number'] ) ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$this->exemption->set_claim( $claimed, $number );
	}

	/**
	 * Classic checkout validation: the exemption number is required when configured.
	 *
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Validation errors.
	 */
	public function classic_validate( $data, $errors ) {
		unset( $data );
		if ( ! $this->show_claim() || 'required' !== $this->number_mode() ) {
			return;
		}
		$claim = $this->exemption->get_claim();
		if ( $claim['claimed'] && '' === $claim['number'] ) {
			$errors->add(
				'aovup_tef_number_required',
				sprintf(
					/* translators: %s: label of the exemption number field. */
					__( '%s is required when you claim tax exemption.', 'aovup-tax-exemption-for-woocommerce' ),
					'<strong>' . esc_html( AovUp_TEF_Plugin::label( 'aovup_tef_number_label' ) ) . '</strong>'
				)
			);
		}
	}

	/**
	 * Save who granted the exemption and the number on the order (classic checkout).
	 *
	 * @param WC_Order $order Order being created.
	 */
	public function save_order_meta( $order ) {
		self::store_status_on_order( $order, $this->exemption->status() );
	}

	/**
	 * Write exemption details to an order.
	 *
	 * @param WC_Order $order  Order.
	 * @param array    $status Exemption status.
	 */
	public static function store_status_on_order( $order, $status ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( empty( $status['exempt'] ) ) {
			$order->delete_meta_data( '_aovup_tef_source' );
			$order->delete_meta_data( '_aovup_tef_number' );
			return;
		}
		$order->update_meta_data( '_aovup_tef_source', sanitize_key( $status['source'] ) );
		$order->update_meta_data( '_aovup_tef_number', (string) $status['number'] );
	}

	/**
	 * After a classic checkout order: remember the number on the account and forget the claim.
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Posted data.
	 * @param WC_Order $order       Order.
	 */
	public function after_order_placed( $order_id, $posted_data, $order ) {
		unset( $order_id, $posted_data );
		$this->finish_claim( $order );
	}

	/**
	 * Remember the claimed number on the customer account (if enabled) and forget the claim.
	 *
	 * @param WC_Order|null $order Order.
	 */
	private function finish_claim( $order ) {
		$claim = $this->exemption->get_claim();
		if ( $claim['claimed'] && '' !== $claim['number'] && $order instanceof WC_Order && $order->get_customer_id() && AovUp_TEF_Plugin::enabled( 'aovup_tef_remember_number' ) ) {
			update_user_meta( $order->get_customer_id(), 'aovup_tef_last_number', $claim['number'] );
		}
		$this->exemption->clear_claim();
	}

	/**
	 * Checkout block: register the claim fields in the order section.
	 */
	public function register_block_fields() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) || ! $this->show_claim() ) {
			return;
		}

		// True only when the request really carries the claim box ticked. Missing data must not count as claimed
		// (API clients and express payment buttons may send no additional fields at all).
		$claimed_rule     = array(
			'type'       => 'object',
			'required'   => array( 'checkout' ),
			'properties' => array(
				'checkout' => array(
					'type'       => 'object',
					'required'   => array( 'additional_fields' ),
					'properties' => array(
						'additional_fields' => array(
							'type'       => 'object',
							'required'   => array( self::FIELD_CLAIM ),
							'properties' => array(
								self::FIELD_CLAIM => array( 'const' => true ),
							),
						),
					),
				),
			),
		);
		$not_claimed_rule = array( 'not' => $claimed_rule );

		woocommerce_register_additional_checkout_field(
			array(
				'id'                         => self::FIELD_CLAIM,
				'label'                      => AovUp_TEF_Plugin::label( 'aovup_tef_claim_label' ),
				// The box itself is optional; without this WooCommerce shows "(optional)" after the label.
				'optionalLabel'              => AovUp_TEF_Plugin::label( 'aovup_tef_claim_label' ),
				'location'                   => 'order',
				'type'                       => 'checkbox',
				'show_in_order_confirmation' => false,
			)
		);

		$mode = $this->number_mode();
		if ( 'hidden' !== $mode ) {
			woocommerce_register_additional_checkout_field(
				array(
					'id'                         => self::FIELD_NUMBER,
					'label'                      => AovUp_TEF_Plugin::label( 'aovup_tef_number_label' ),
					'location'                   => 'order',
					'type'                       => 'text',
					'required'                   => 'required' === $mode ? $claimed_rule : false,
					'hidden'                     => $not_claimed_rule,
					'attributes'                 => array( 'maxLength' => 100 ),
					'show_in_order_confirmation' => false,
					'sanitize_callback'          => array( __CLASS__, 'clean_number' ),
				)
			);
		}
	}

	/**
	 * Read the claim fields from a Store API request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|null { claimed: bool, number: string }, or null when the request carries no claim field.
	 */
	private function claim_from_request( $request ) {
		$fields = $request instanceof WP_REST_Request ? $request->get_param( 'additional_fields' ) : null;
		if ( ! is_array( $fields ) || ! array_key_exists( self::FIELD_CLAIM, $fields ) ) {
			return null;
		}
		$claimed = rest_sanitize_boolean( $fields[ self::FIELD_CLAIM ] );
		$number  = isset( $fields[ self::FIELD_NUMBER ] ) ? self::clean_number( $fields[ self::FIELD_NUMBER ] ) : '';
		return array(
			'claimed' => (bool) $claimed,
			'number'  => $claimed ? $number : '',
		);
	}

	/**
	 * Checkout block: the customer changed the form (PATCH without an order yet). Update the
	 * claim and recalculate so the returned totals reflect it.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function block_update_draft( $request ) {
		if ( ! $this->show_claim() ) {
			return;
		}
		$claim = $this->claim_from_request( $request );
		if ( null === $claim ) {
			return;
		}
		$this->exemption->set_claim( $claim['claimed'], $claim['number'] );
		if ( WC()->cart ) {
			WC()->cart->calculate_totals();
		}
	}

	/**
	 * Checkout block: place-order request, before totals are recalculated.
	 *
	 * @param WC_Customer     $customer Customer.
	 * @param WP_REST_Request $request  Request.
	 */
	public function block_update_customer( $customer, $request ) {
		unset( $customer );
		if ( ! $this->show_claim() ) {
			return;
		}
		$claim = $this->claim_from_request( $request );
		if ( null !== $claim ) {
			$this->exemption->set_claim( $claim['claimed'], $claim['number'] );
		}
	}

	/**
	 * Checkout block: save exemption details on the order.
	 *
	 * @param WC_Order        $order   Order.
	 * @param WP_REST_Request $request Request.
	 */
	public function block_update_order( $order, $request ) {
		if ( $this->show_claim() ) {
			$claim = $this->claim_from_request( $request );
			if ( null !== $claim ) {
				$this->exemption->set_claim( $claim['claimed'], $claim['number'] );
			}
		}
		self::store_status_on_order( $order, $this->exemption->status() );
	}

	/**
	 * Checkout block: order placed.
	 *
	 * @param WC_Order $order Order.
	 */
	public function block_order_processed( $order ) {
		$this->finish_claim( $order );
	}
}
