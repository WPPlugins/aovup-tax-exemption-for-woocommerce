<?php
/**
 * Plugins screen links.
 *
 * @package AovUp_Tax_Exemption
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin helpers.
 */
final class AovUp_TEF_Admin {

	/**
	 * Hook into the plugins screen.
	 */
	public function __construct() {
		add_filter( 'plugin_action_links_' . plugin_basename( AOVUP_TEF_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * Settings link.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public function action_links( $links ) {
		if ( AovUp_TEF_Plugin::is_pro_active() ) {
			return $links;
		}
		array_unshift(
			$links,
			'<a href="' . esc_url( AovUp_TEF_Plugin::settings_url() ) . '">' . esc_html__( 'Settings', 'aovup-tax-exemption-for-woocommerce' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Link to the Pro version in the plugin row.
	 *
	 * @param array  $links Row meta links.
	 * @param string $file  Plugin file.
	 * @return array
	 */
	public function row_meta( $links, $file ) {
		if ( plugin_basename( AOVUP_TEF_FILE ) === $file ) {
			$links[] = '<a href="' . esc_url( AovUp_TEF_Plugin::PRO_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Upgrade to Pro', 'aovup-tax-exemption-for-woocommerce' ) . '</a>';
		}
		return $links;
	}
}
