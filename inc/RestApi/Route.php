<?php
/**
 * Setup Custom rest api.
 *
 * @package           acjwp-community-connector
 * @sub-package       WordPress
 */

namespace Acj\Wpcc\RestApi;

/**
 * Class
 */
class Route {

	/**
	 * Response cache time.
	 *
	 * @var int
	 */
	private int $cache_time = MINUTE_IN_SECONDS;

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	protected string $name_space = 'wpcc/v1';

	/**
	 * Init class actions.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Register Custom Endpoint.
	 *
	 * @return void
	 */
	public function register(): void {
		register_rest_route(
			$this->name_space,
			'routes',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_routes' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->name_space,
			'routes/save',
			array(
				'methods'             => 'post',
				'callback'            => array( $this, 'save_config' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Callback function.
	 *
	 * @param \WP_REST_Request $request Rest Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_routes( \WP_REST_Request $request ): \WP_REST_Response {
		$nonce  = $request->get_param( '_nonce' );
		$verify = wp_verify_nonce( $nonce, 'acj_wpcc_nonce_get' );
		if ( ! $verify ) {
			return rest_ensure_response( array() );
		}

		$routes = rest_get_server()->get_routes();
		$list   = array();
		foreach ( $routes as $route => $args ) {
			if ( ! str_contains( $route, '/' . ACJ_WPCC_REPORTS_ENDPOINT ) ) {
				if ( ! str_contains( $route, $this->name_space ) ) {
					$methods = wp_list_pluck( $args, 'methods' );
					if ( empty( $methods ) ) {
						continue;
					}

					if ( in_array( $route, $this->excluded_routes(), true ) ) {
						continue;
					}

					$method_types = wp_list_pluck( $methods, 'GET' );
					if ( empty( $method_types ) ) {
						continue;
					}

					if ( ! in_array( true, $method_types, true ) ) {
						continue;
					}

					if ( $route === '/' ) {
						continue;
					}

					if ( str_contains( $route, '?P<' ) ) {
						continue;
					}

					$filtered_args       = wp_list_pluck( $args, 'args' );
					$required            = wp_list_pluck( $filtered_args[0], 'required' );
					$permission_callback = wp_list_pluck( $args, 'permission_callback' );
					if ( ! empty( array_filter( $required ) ) ) {
						continue;
					}

					if ( $this->check_permission() ) {
						continue;
					}

					$list[] = $route;
				}
			}
		}

		return rest_ensure_response( $list );
	}

	/**
	 * Exclude routes from modifying.
	 *
	 * @return string[]
	 */
	public function excluded_routes(): array {
		return array(
			'/oembed/1.0',
			'/wp/v2',
			'/wp/v2/taxonomies',
			'/wp/v2/menu-items',
			'/wp/v2/media',
			'/wp/v2/blocks',
			'/wp/v2/templates',
			'/wp/v2/template-parts',
			'/wp/v2/types',
			'/wp/v2/statuses',
			'/wp/v2/block-types',
			'/wp/v2/settings',
			'/wp/v2/themes',
			'/wp/v2/plugins',
			'/wp/v2/sidebars',
			'/wp/v2/widget-types',
			'/wp/v2/widgets',
			'/wp/v2/pattern-directory',
			'/wp/v2/pattern-directory/patterns',
			'/wp/v2/pattern-directory/patterns',
			'/wp/v2/block-patterns/patterns',
			'/wp/v2/block-patterns/categories',
			'/wp-block-editor/v1',
			'/wp-block-editor/v1/url-details',
			'/wp-block-editor/v1/navigation-fallback',
			'/wp-block-editor/v1/export',
			'/wp-site-health/v1',
			'/wp-site-health/v1/tests/background-updates',
			'/wp-site-health/v1/tests/loopback-requests',
			'/wp-site-health/v1/tests/https-status',
			'/wp-site-health/v1/tests/dotorg-communication',
			'/wp-site-health/v1/tests/authorization-header',
			'/wp-site-health/v1/directory-sizes',
			'/wp-site-health/v1/tests/page-cache',
		);
	}

	/**
	 * Check permission to allow users for future case.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return false;
	}

	/**
	 * Save Configuration.
	 *
	 * @param \WP_REST_Request $request Rest Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function save_config( \WP_REST_Request $request ): \WP_REST_Response {
		$data   = $request->get_param( 'data' );
		$route  = $request->get_param( 'route' );
		$key    = $this->string_md5( $route );
		$nonce  = $request->get_param( '_nonce' );
		$verify = wp_verify_nonce( $nonce, 'acj_wpcc_nonce_save' );
		if ( ! $verify ) {
			return rest_ensure_response( array() );
		}

		update_option( $key, $data );
		delete_transient( $key );
		set_transient( $key, $data, $this->cache_time );

		return rest_ensure_response(
			array(
				'route' => $route,
				'data'  => $data,
				'key'   => $key,
			)
		);
	}

	/**
	 * Convert string to MD5 HASH.
	 *
	 * @param string $key_string String to convert.
	 *
	 * @return string
	 */
	public function string_md5( string $key_string ): string {
		return md5( $key_string );
	}
}
