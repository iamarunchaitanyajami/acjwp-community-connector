<?php
/**
 * Setup admin Menu.
 *
 * @package           acjwp-community-connector
 * @sub-package       WordPress
 */

namespace Acj\Wpcc\Admin;

/**
 * Menu class
 */
class Menu {

	/**
	 * Class constructor.
	 */
	public function __construct() {
	}

	/**
	 * Initiate all the actions here.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'menu_page' ) );
	}

	/**
	 * Setup menu page.
	 *
	 * @return void
	 */
	public function menu_page(): void {
		add_menu_page(
			__( 'ACJWPCC', 'acjwp-community-connector' ),
			__( 'ACJWPCC', 'acjwp-community-connector' ),
			'manage_options',
			'ACJWPCC',
			array( $this, 'callback' ),
			ACJ_WPCC_DIR_URL . '/assets/menu-logo.jpg',
			6
		);
	}

	/**
	 * Callback function for admin page.
	 *
	 * @return void
	 */
	public function callback(): void {
		printf( '<div id="acjwpcc-ui">%s</div>', esc_attr( __( 'Testing', 'acjwp-community-connector' ) ) );
	}
}
