<?php
/**
 * Uninstall: remove the plugin's options and role. Order and customer data is kept.
 *
 * @package AovUp_Tax_Exemption
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'aovup_tef_exempt_roles', 'aovup_tef_claim_enabled', 'aovup_tef_claim_who', 'aovup_tef_claim_label', 'aovup_tef_claim_note', 'aovup_tef_number_mode', 'aovup_tef_number_label', 'aovup_tef_remember_number', 'aovup_tef_display_excl' ) as $aovup_tef_option ) {
	delete_option( $aovup_tef_option );
}

if ( get_role( 'aovup_tef_exempt_customer' ) ) {
	$aovup_tef_users = get_users(
		array(
			'role'   => 'aovup_tef_exempt_customer',
			'fields' => 'ID',
		)
	);
	foreach ( $aovup_tef_users as $aovup_tef_user_id ) {
		$aovup_tef_user = new WP_User( $aovup_tef_user_id );
		$aovup_tef_user->remove_role( 'aovup_tef_exempt_customer' );
		if ( empty( $aovup_tef_user->roles ) ) {
			$aovup_tef_user->add_role( get_role( 'customer' ) ? 'customer' : get_option( 'default_role', 'subscriber' ) );
		}
	}
	remove_role( 'aovup_tef_exempt_customer' );
}
