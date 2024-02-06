<?php
/**
 * Response Parser.
 *
 * @package acjwp-community-connector
 * @sub-package WordPress
 */

namespace Acj\Wpcc;

/**
 * Parser class
 */
class ResponseParser {

	/**
	 * Mixed data.
	 *
	 * @var mixed
	 */
	private mixed $data;

	/**
	 * WP Rest request.
	 *
	 * @var \WP_REST_Request
	 */
	private \WP_REST_Request $request;

	/**
	 * Transformed rest request route.
	 *
	 * @var string
	 */
	private string $route;

	/**
	 * Response cache time.
	 *
	 * @var int
	 */
	private int $cache_time = MINUTE_IN_SECONDS;

	/**
	 * Response cache key.
	 *
	 * @var string
	 */
	private string $cache_key;


	/**
	 * Construct
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_REST_Request  $request Request object.
	 */
	public function __construct( \WP_REST_Response $response, \WP_REST_Request $request ) {
		$this->data    = $response->get_data();
		$this->request = $request;

		$this->route = $this->convert_key_to_title( $this->request->get_route() );
	}

	/**
	 * Returns Parse Data.
	 *
	 * @param string $root_key Root key.
	 *
	 * @return array
	 */
	public function init( string $root_key = '' ): array {
		if ( empty( $this->data ) ) {
			return array();
		}

		if ( ! str_contains( $this->request->get_route(), '/' . ACJ_WPCC_REPORTS_ENDPOINT ) ) {
			return $this->data;
		}

		$cache_key       = $this->string_md5( $this->request->get_route() );
		$this->cache_key = $cache_key;
		$cached_data     = get_transient( $this->cache_key ) ?: array(); //@phpcs:ignore
		$options_data    = get_option( $this->cache_key, array() );
		$cached_info     = array_merge( $cached_data, $options_data );
		if ( $cached_info && $this->request->get_param( 'skeleton_type' ) ) {
			return $cached_info;
		}

		$data = $this->data;
		if ( ! $this->is_indexed_array( $this->data ) ) {
			$data = array( $this->data );
		}

		$updates_data   = ( ! empty( $root_key ) ) ? $data[ $root_key ] : $data;
		$get_draft_data = array();
		foreach ( $updates_data as $n_key => $n_data ) {
			if ( ! is_array( $n_data ) ) {
				$get_draft_data[ $this->convert_key_to_title( $n_key ) ] = $this->validate_value( $n_data );
			} else {
				$get_draft_data[ $n_key ] = $this->convert_keys_to_strings( $n_data );
			}
		}

		if ( $this->request->get_param( 'skeleton' ) ) {
			return $this->fetch_skeleton( $get_draft_data );
		}

		if ( ! $this->is_indexed_array( $this->data ) ) {
			return $get_draft_data[0];
		}

		return $get_draft_data;
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

	/**
	 * Get all keys from the WP route.
	 *
	 * @param array $data Data.
	 * @param array $response Response.
	 *
	 * @return array
	 */
	private function fetch_skeleton( array $data, array &$response = array() ): array {
		$inline_data = $this->request->get_param( 'skeleton_type' ) ?? false;
		foreach ( $data as $n_key => $n_data ) {
			if ( is_array( $n_data ) ) {
				$this->fetch_skeleton( $n_data, $response );
			} elseif ( ! is_numeric( $n_key ) && ! $inline_data ) {
				$response[] = $n_key;
			} elseif ( ! is_numeric( $n_key ) && $inline_data ) {
				$response[ $n_key ] = $this->validate_value( $n_data, $n_key, $inline_data );
			}
		}

		return $response;
	}

	/**
	 * Converted data.
	 *
	 * @param array  $data Data Set.
	 * @param string $prefix Data Prefix.
	 *
	 * @return array
	 */
	private function convert_keys_to_strings( array $data, string $prefix = '' ): array {
		$inline_edit = $this->request->get_param( 'inline_edit' ) ?? false;
		$result      = array();
		foreach ( $data as $key => $value ) {
			$new_key = $prefix . ( $prefix ? '.' : '' ) . $key;
			if ( is_array( $value ) ) {
				$result = array_merge( $result, $this->convert_keys_to_strings( (array) $value, $new_key ) );
			} else {
				$result[ $this->convert_key_to_title( $new_key ) ] = $this->validate_value( $value, $new_key, $inline_edit );
			}
		}

		return $result;
	}

	/**
	 * Convert key to Name.
	 *
	 * @param string $key Object key.
	 *
	 * @return string
	 */
	private function get_name( string $key ): string {
		$transformed_key = $this->convert_key_to_title( $key );

		$updated_key = str_replace( '/', ' ', $transformed_key );
		$updated_key = str_replace( '_', ' ', $updated_key );
		$updated_key = str_replace( '.', ' ', $updated_key );
		$updated_key = str_replace( '-', ' ', $updated_key );
		$updated_key = str_replace( ':', ' ', $updated_key );
		$updated_key = ucwords( str_replace( '_', ' ', $updated_key ) );
		$updated_key = str_replace( ':', '_', $updated_key );
		$updated_key = str_replace( '.', '_', $updated_key );

		return apply_filters( 'acj_wpcc_report_name' . $this->route . '_' . $transformed_key, trim( $updated_key ), $key );
	}

	/**
	 * Convert key to Description.
	 *
	 * @param string $key Object key.
	 *
	 * @return string
	 */
	private function get_description( string $key ): string {
		return $this->get_name( $key );
	}

	/**
	 * Object key.
	 *
	 * @param string $key Object key.
	 *
	 * @return string
	 */
	private function convert_key_to_title( string $key ): string {
		$updated_key = str_replace( '_', ' ', trim( $key ) );
		$updated_key = str_replace( ' ', '_', trim( $updated_key ) );
		$updated_key = str_replace( '.', '_', trim( $updated_key ) );
		$updated_key = str_replace( '-', '_', trim( $updated_key ) );
		$updated_key = str_replace( ':', '_', trim( $updated_key ) );
		$updated_key = str_replace( '/', '_', trim( $updated_key ) );

		return trim( $updated_key );
	}

	/**
	 * Key validation.
	 *
	 * @param mixed  $value Value.
	 * @param string $key Key.
	 * @param bool   $process Convert values.
	 *
	 * @return mixed
	 */
	private function validate_value( mixed $value, string $key = '', bool $process = false ): mixed {
		if ( ! $process ) {
			return strtotime( $value ) !== false ? strtotime( $value ) : $value;
		}

		$return = array(
			'name'        => $this->get_name( $key ),
			'description' => $this->get_description( $key ),
			'formula'     => '',
			'type'        => '',
			'aggregation' => 'NONE',
		);

		/**
		 * Check Data.
		 */
		if ( $this->is_time( $value ) ) {
			return array_merge(
				$return,
				array(
					'value' => $value,
					'type'  => 'DURATION',
				)
			);
		}

		/**
		 * Check URL.
		 */
		if ( filter_var( $value, FILTER_VALIDATE_URL ) !== false ) {
			if ( $this->is_image_url( $value ) ) {
				return array_merge(
					$return,
					array(
						'value'   => $value,
						'type'    => 'IMAGE',
						'formula' => "IMAGE(${value}, 'Alt Text')",
					)
				);
			}

			return array_merge(
				$return,
				array(
					'value'   => $value,
					'type'    => 'URL',
					'formula' => "HYPERLINK(${value}, 'Link Description')",
				)
			);
		}

		$type = 'integer' === getType( $value ) ? 'NUMBER' : getType( $value );
		$type = 'string' === getType( $value ) ? 'TEXT' : $type;
		$type = 'boolean' === getType( $value ) ? 'BOOLEAN' : $type;

		return array_merge(
			$return,
			array(
				'value' => $value,
				'type'  => $type,
			)
		);
	}

	/**
	 * Check if is date.
	 *
	 * @param string $value value.
	 *
	 * @return bool
	 */
	private function is_time( string $value = '' ): bool {
		// Check if the value is a valid timestamp.
		if ( (int) $value === $value && strlen( $value ) === 10 ) {
			return true;
		} else {
			// It's not a timestamp, try to parse it as a date.
			$timestamp = strtotime( $value );

			if ( $timestamp !== false ) {
				// It's a valid date, you can use $timestamp.
				return true;
			} else {
				// It's neither a valid timestamp nor a valid date.
				return false;
			}
		}
	}

	/**
	 * Check is Index array.
	 *
	 * @param array $data Data.
	 *
	 * @return bool
	 */
	private function is_indexed_array( array $data ): bool {
		$keys  = array_keys( $data );
		$count = count( $keys );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( $keys[ $i ] !== $i ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check is associative array.
	 *
	 * @param array $data Data.
	 *
	 * @return bool
	 */
	private function is_associative_array( array $data ): bool {
		$keys = array_keys( $data );
		foreach ( $keys as $key ) {
			if ( ! is_int( $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if it is a URL.
	 *
	 * @param string $url URl.
	 *
	 * @return bool
	 */
	private function is_image_url( string $url ): bool {
		if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
			return false;
		}

		$response = wp_safe_remote_request( $url );

		// Check if the response was successful.
		if ( is_wp_error( $response ) ) {
			return false;
		}

		// Get the content type from the response headers.
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		// Check if the content type indicates an image.
		if ( str_starts_with( $content_type, 'image/' ) ) {
			return true;
		}

		return false;
	}
}
