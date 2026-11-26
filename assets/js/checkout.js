/**
 * AovUp Tax Exemption: classic checkout claim.
 * Shows the exemption number field when "I am tax exempt" is ticked and refreshes the order totals.
 */
( function ( $ ) {
	'use strict';

	function toggleNumber() {
		$( '#aovup_tef_number_field' ).toggle( $( '#aovup_tef_claim' ).is( ':checked' ) );
	}

	$( document.body ).on( 'change', '#aovup_tef_claim', function () {
		toggleNumber();
		$( document.body ).trigger( 'update_checkout' );
	} );

	$( document.body ).on( 'change', '#aovup_tef_number', function () {
		if ( $( '#aovup_tef_claim' ).is( ':checked' ) ) {
			$( document.body ).trigger( 'update_checkout' );
		}
	} );

	$( document.body ).on( 'updated_checkout', toggleNumber );
	$( toggleNumber );
}( jQuery ) );
