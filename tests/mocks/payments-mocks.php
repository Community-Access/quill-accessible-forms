<?php
/**
 * What the payment tests need from WordPress when the WordPress test suite
 * is not installed: options, capabilities, REST objects, and a memory store.
 * Nothing here reaches a network: wp_remote_request fails the test if called,
 * because every provider call in a test goes through a fake transport.
 *
 * @package QAF
 */

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['qaf_test_options'] ?? array() ) ? $GLOBALS['qaf_test_options'][ $name ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['qaf_test_options'][ $name ]  = $value;
		$GLOBALS['qaf_test_autoload'][ $name ] = $autoload;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['qaf_test_options'][ $name ] );
		return true;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $name, $value, $expires = 0 ) {
		$GLOBALS['qaf_test_options'][ '_transient_' . $name ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $name ) {
		return $GLOBALS['qaf_test_options'][ '_transient_' . $name ] ?? false;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $name ) {
		unset( $GLOBALS['qaf_test_options'][ '_transient_' . $name ] );
		return true;
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) {
		return in_array( $cap, $GLOBALS['qaf_test_caps'] ?? array(), true );
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}
if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'https://example.test/wp-json/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $s ) {
		return rtrim( $s, '/\\' );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $c ) {
		return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $v ) {
		return is_string( $v ) ? stripslashes( $v ) : $v;
	}
}
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $ts = null ) {
		return gmdate( $format, $ts ?? time() );
	}
}
if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $value ) {
		return $value;
	}
}
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args ) {
		$GLOBALS['qaf_test_routes'][ $namespace . $route ] = $args;
		return true;
	}
}
if ( ! function_exists( 'wp_remote_request' ) ) {
	function wp_remote_request( $url, $args = array() ) {
		throw new RuntimeException( 'A test tried to reach the network: ' . $url );
	}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) {
		$GLOBALS['qaf_test_scripts'][ $handle ] = $src;
	}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	}
}
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $name, $data ) {
		$GLOBALS['qaf_test_localized'][ $name ] = $data;
	}
}
if ( ! defined( 'QAF_PLUGIN_URL' ) ) {
	define( 'QAF_PLUGIN_URL', 'https://example.test/wp-content/plugins/quill-accessible-forms/' );
}
if ( ! defined( 'QAF_VERSION' ) ) {
	define( 'QAF_VERSION', '1.0.0' );
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public $data;
		public $status;
		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}
		public function get_data() {
			return $this->data;
		}
		public function get_status() {
			return $this->status;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $params  = array();
		private $body    = '';
		private $headers = array();
		public function __construct( $method = 'GET', $route = '' ) {
		}
		public function set_param( $k, $v ) {
			$this->params[ $k ] = $v;
		}
		public function get_param( $k ) {
			return $this->params[ $k ] ?? null;
		}
		public function has_param( $k ) {
			return array_key_exists( $k, $this->params );
		}
		public function set_body( $b ) {
			$this->body = $b;
		}
		public function get_body() {
			return $this->body;
		}
		public function set_header( $k, $v ) {
			// WordPress keeps header names lower case with underscores.
			$this->headers[ str_replace( '-', '_', strtolower( $k ) ) ] = array( $v );
		}
		public function get_headers() {
			return $this->headers;
		}
	}
}

/**
 * Payment attempts in memory, with the same compare-and-set and once-only
 * event rules as the database store.
 */
class QAF_Payment_Memory_Store implements QAF_Payment_Store {
	public $rows   = array();
	public $events = array();
	private $next  = 1;

	public function insert( $row ) {
		$id = $this->next++;
		$this->rows[ $id ] = array_merge( array(
			'submission'     => '',
			'provider'       => '',
			'capture'        => 'automatic',
			'provider_ref'   => '',
			'capture_ref'    => '',
			'refunded_minor' => 0,
			'receipt_url'    => '',
			'tries'          => 0,
			'created_at'     => '2026-11-07 00:00:00',
			'updated_at'     => '2026-11-07 00:00:00',
		), $row, array( 'id' => $id ) );
		return $id;
	}

	public function get( $id ) {
		return $this->rows[ (int) $id ] ?? null;
	}

	public function find( $column, $value ) {
		foreach ( array_reverse( $this->rows ) as $row ) {
			if ( (string) ( $row[ $column ] ?? '' ) === (string) $value && $value !== '' ) {
				return $row;
			}
		}
		return null;
	}

	public function update( $id, $fields, $expected = null ) {
		if ( ! isset( $this->rows[ $id ] ) || ( $expected !== null && $this->rows[ $id ]['status'] !== $expected ) ) {
			return false;
		}
		$this->rows[ $id ] = array_merge( $this->rows[ $id ], $fields );
		return true;
	}

	public function record_event( $provider, $event_id ) {
		$key = $provider . '|' . $event_id;
		if ( isset( $this->events[ $key ] ) ) {
			return false;
		}
		$this->events[ $key ] = true;
		return true;
	}

	public function recent( $args = array() ) {
		return array_values( array_reverse( $this->rows ) );
	}
}
