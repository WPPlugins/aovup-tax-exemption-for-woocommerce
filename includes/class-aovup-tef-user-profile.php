<?php
/**
 * Tax-exemption fields on the user profile screen (for shop managers).
 *
 * @package AovUp_Tax_Exemption
 */

defined( 'ABSPATH' ) || exit;

/**
 * User profile fields.
 */
final class AovUp_TEF_User_Profile {

	/**
	 * Hook into the profile screens.
	 */
	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'render' ), 20 );
		add_action( 'edit_user_profile', array( $this, 'render' ), 20 );
		add_action( 'personal_options_update', array( $this, 'save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save' ) );
		add_filter( 'manage_users_columns', array( $this, 'add_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_column' ), 10, 3 );
	}

	/**
	 * Only shop managers and administrators manage exemptions.
	 *
	 * @return bool
	 */
	private static function can_manage() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Render the fields.
	 *
	 * @param WP_User $user User being edited.
	 */
	public function render( $user ) {
		if ( ! self::can_manage() || ! $user instanceof WP_User ) {
			return;
		}
		$data      = AovUp_TEF_Exemption::customer_exemption( $user->ID );
		$exempt    = 'yes' === get_user_meta( $user->ID, 'aovup_tef_exempt', true );
		$by_role   = AovUp_TEF_Exemption::user_has_exempt_role( $user->ID );
		$last_used = (string) get_user_meta( $user->ID, 'aovup_tef_last_number', true );
		wp_nonce_field( 'aovup_tef_save_user_' . $user->ID, 'aovup_tef_user_nonce' );
		?>
		<h2><?php esc_html_e( 'Tax exemption', 'aovup-tax-exemption-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Tax exempt', 'aovup-tax-exemption-for-woocommerce' ); ?></th>
				<td>
					<label for="aovup_tef_exempt">
						<input type="checkbox" name="aovup_tef_exempt" id="aovup_tef_exempt" value="yes" <?php checked( $exempt ); ?> />
						<?php esc_html_e( 'Do not charge tax to this customer', 'aovup-tax-exemption-for-woocommerce' ); ?>
					</label>
					<?php if ( $by_role ) : ?>
						<p class="description"><?php esc_html_e( 'This customer already has a tax-exempt user role.', 'aovup-tax-exemption-for-woocommerce' ); ?></p>
					<?php endif; ?>
					<?php if ( $exempt && ! $data['exempt'] ) : ?>
						<p class="description"><?php esc_html_e( 'The exemption has expired, so tax is charged again.', 'aovup-tax-exemption-for-woocommerce' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aovup_tef_number"><?php echo esc_html( AovUp_TEF_Plugin::label( 'aovup_tef_number_label' ) ); ?></label></th>
				<td>
					<input type="text" class="regular-text" name="aovup_tef_number" id="aovup_tef_number" maxlength="100" value="<?php echo esc_attr( $data['number'] ); ?>" />
					<?php if ( '' !== $last_used && $last_used !== $data['number'] ) : ?>
						<p class="description">
							<?php
							/* translators: %s: exemption number the customer entered at checkout. */
							echo esc_html( sprintf( __( 'Last number entered at checkout: %s', 'aovup-tax-exemption-for-woocommerce' ), $last_used ) );
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aovup_tef_expires"><?php esc_html_e( 'Exemption valid until', 'aovup-tax-exemption-for-woocommerce' ); ?></label></th>
				<td>
					<input type="date" name="aovup_tef_expires" id="aovup_tef_expires" value="<?php echo esc_attr( $data['expires'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Optional. Leave empty if the exemption does not expire.', 'aovup-tax-exemption-for-woocommerce' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the fields.
	 *
	 * @param int $user_id User ID.
	 */
	public function save( $user_id ) {
		if ( ! self::can_manage() || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( ! isset( $_POST['aovup_tef_user_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aovup_tef_user_nonce'] ) ), 'aovup_tef_save_user_' . $user_id ) ) {
			return;
		}

		$exempt  = isset( $_POST['aovup_tef_exempt'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['aovup_tef_exempt'] ) );
		$number  = isset( $_POST['aovup_tef_number'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['aovup_tef_number'] ) ), 0, 100 ) : '';
		$expires = isset( $_POST['aovup_tef_expires'] ) ? sanitize_text_field( wp_unslash( $_POST['aovup_tef_expires'] ) ) : '';
		if ( '' !== $expires && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires ) ) {
			$expires = '';
		}

		if ( $exempt ) {
			update_user_meta( $user_id, 'aovup_tef_exempt', 'yes' );
		} else {
			delete_user_meta( $user_id, 'aovup_tef_exempt' );
		}
		if ( '' !== $number ) {
			update_user_meta( $user_id, 'aovup_tef_number', $number );
		} else {
			delete_user_meta( $user_id, 'aovup_tef_number' );
		}
		if ( '' !== $expires ) {
			update_user_meta( $user_id, 'aovup_tef_expires', $expires );
		} else {
			delete_user_meta( $user_id, 'aovup_tef_expires' );
		}
	}

	/**
	 * Users list column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		if ( self::can_manage() ) {
			$columns['aovup_te'] = __( 'Tax exempt', 'aovup-tax-exemption-for-woocommerce' );
		}
		return $columns;
	}

	/**
	 * Users list column value.
	 *
	 * @param string $output  Column output.
	 * @param string $column  Column name.
	 * @param int    $user_id User ID.
	 * @return string
	 */
	public function render_column( $output, $column, $user_id ) {
		if ( 'aovup_te' !== $column ) {
			return $output;
		}
		$data = AovUp_TEF_Exemption::customer_exemption( $user_id );
		if ( $data['exempt'] ) {
			return esc_html__( 'Yes (account)', 'aovup-tax-exemption-for-woocommerce' );
		}
		if ( AovUp_TEF_Exemption::user_has_exempt_role( $user_id ) ) {
			return esc_html__( 'Yes (role)', 'aovup-tax-exemption-for-woocommerce' );
		}
		return '&ndash;';
	}
}
