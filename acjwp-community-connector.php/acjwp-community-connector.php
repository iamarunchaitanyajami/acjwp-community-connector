<?php
/**
 * Plugin Name:       AJCWP Community Connector
 * Plugin URI:        https://github.com/arunchaitanyajami/acjwp-community-connector
 * Requires WP:       6.0 ( Minimal )
 * Requires PHP:      8.0
 * Version:           1.0.5
 * Author:            achaitanyajami
 * Text Domain:       acjwp-community-connector
 * Domain Path:       /language/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package           acjwp-community-connector
 * @sub-package       WordPress
 */

namespace Acj\Wpcc;

/**
 * If this file is called directly, abort.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'ACJ_WPCC_PLUGIN_VERSION', '1.0.5' );
define( 'ACJ_WPCC_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACJ_WPCC_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'ACJ_WPCC_REPORTS_ENDPOINT', 'reports' );

/**
 * Composer Autoload file.
 */
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	include __DIR__ . '/vendor/autoload.php';
}

use Acj\Wpcc\Admin\Menu;
use Acj\Wpcc\ResponseParser as ResponseConverter;
use Acj\Wpcc\RestApi\Route;

/**
 * Generate a report endpoint.
 */
add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		foreach ( $endpoints as $route => $endpoint ) {
			$modified_route               = $route . '/' . ACJ_WPCC_REPORTS_ENDPOINT;
			$endpoints[ $modified_route ] = $endpoint;
		}

		return $endpoints;
	}
);

/**
 * Transform all the reports endpoint for CC connector.
 */
add_filter(
	'rest_request_after_callbacks',
	function ( $response, $handler, \WP_REST_Request $request ) {
		if ( ! str_contains( $request->get_route(), '/' . ACJ_WPCC_REPORTS_ENDPOINT ) ) {
			return $response;
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( is_admin() || 'edit' === $request->get_param( 'context' ) ) {
			return $response;
		}

		if ( is_array( $response ) ) {
			$response = new \WP_REST_Response( $response );
		}

		return ( new ResponseConverter( $response, $request ) )->init();
	},
	10,
	3
);

/**
 * Initiate the menu here.
 */
( new Menu() )->init();

/**
 * Register Rest.
 */
( new Route() )->init();

/**
 * Enqueue scripts.
 *
 * @return void
 */
function acj_wpcc_enqueue_scripts(): void {
	$current_screen = get_current_screen();
	if ( $current_screen->base !== 'toplevel_page_WPCC' ) {
		return;
	}

	$block_settings = array(
		'ajaxUrl' => esc_url( admin_url( 'admin-ajax.php', 'relative' ) ),
	);

	/**
	 * Add inline script for live blog and live blog entry.
	 */
	$asset_file = include ACJ_WPCC_DIR_PATH . 'build/index.asset.php';
	wp_register_script( 'acj_wpcc_menu_assets-js', ACJ_WPCC_DIR_URL . 'build/index.js', $asset_file['dependencies'], ACJ_WPCC_PLUGIN_VERSION, true );
	wp_localize_script( 'acj_wpcc_menu_assets-js', 'AcjWpccBlocksEditorSettings', $block_settings );
	wp_enqueue_script( 'acj_wpcc_menu_assets-js' );
	wp_enqueue_style( 'acj_wpcc_menu_assets-global-css', ACJ_WPCC_DIR_URL . 'build/index.css', array(), ACJ_WPCC_PLUGIN_VERSION );
}

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\acj_wpcc_enqueue_scripts' );
