<?php
/**
 * The old REST namespace, baf/v1, kept for one release as a read-only alias
 * of qaf/v1, so a test site that already called BITS Accessible Forms by
 * that name keeps reading its forms and entries.
 *
 * Only GET is answered under baf/v1: every qaf/v1 route that reads is
 * registered again there, with the same callback, permission check and
 * arguments. Writes (POST, PUT, PATCH, DELETE) are not, so nothing changes a
 * site through the old name. Each answer says it is deprecated and names
 * its qaf/v1 successor (Deprecation and Link headers).
 *
 * Remove this file, and its line in quill-accessible-forms.php, in the
 * release after the rename.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Legacy_REST class.
 */
class QAF_Legacy_REST {

    const OLD_NAMESPACE = 'baf/v1';
    const NAMESPACE     = 'qaf/v1';

    /** Hook in, after every qaf/v1 route is registered. */
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register' ), PHP_INT_MAX );
    }

    /** Register the read-only aliases from the routes the server has. */
    public static function register() {
        $server = rest_get_server();
        foreach ( self::aliases( $server->get_routes( self::NAMESPACE ) ) as $route => $endpoints ) {
            foreach ( $endpoints as $i => $endpoint ) {
                $endpoints[ $i ]['callback'] = self::deprecated( $endpoint['callback'], $route );
            }
            register_rest_route( self::OLD_NAMESPACE, $route, $endpoints );
        }
    }

    /**
     * The aliases to register: for each qaf/v1 route that has a GET
     * endpoint, the route under baf/v1 with only its GET endpoints, each
     * answering GET alone.
     *
     * @param array $routes What WP_REST_Server::get_routes( 'qaf/v1' ) gives:
     *                      '/qaf/v1/forms' => list of endpoints, each with
     *                      'methods' as array( 'GET' => true, ... ).
     * @return array Route without the namespace ('/forms') => endpoints.
     */
    public static function aliases( $routes ) {
        $out    = array();
        $prefix = '/' . self::NAMESPACE;
        foreach ( (array) $routes as $route => $endpoints ) {
            if ( strpos( $route, $prefix ) !== 0 ) {
                continue;
            }
            $path = substr( $route, strlen( $prefix ) );
            if ( $path === '' ) {
                continue; // The namespace's own index is registered by WordPress.
            }
            $reads = array();
            foreach ( (array) $endpoints as $endpoint ) {
                $methods = isset( $endpoint['methods'] ) ? $endpoint['methods'] : array();
                $methods = is_array( $methods ) ? array_keys( array_filter( $methods ) ) : preg_split( '/\s*,\s*/', (string) $methods );
                if ( ! in_array( 'GET', array_map( 'strtoupper', $methods ), true ) ) {
                    continue;
                }
                $alias = array(
                    'methods'             => 'GET',
                    'callback'            => $endpoint['callback'] ?? null,
                    'permission_callback' => $endpoint['permission_callback'] ?? '__return_true',
                );
                if ( isset( $endpoint['args'] ) ) {
                    $alias['args'] = $endpoint['args'];
                }
                $reads[] = $alias;
            }
            if ( $reads ) {
                $out[ $path ] = $reads;
            }
        }
        return $out;
    }

    /**
     * A callback that answers as the original and says the name is old.
     *
     * @param callable $callback Original callback.
     * @param string   $route    Route without the namespace.
     * @return callable
     */
    private static function deprecated( $callback, $route ) {
        return function ( $request ) use ( $callback, $route ) {
            $response = rest_ensure_response( call_user_func( $callback, $request ) );
            if ( $response instanceof WP_REST_Response ) {
                $response->header( 'Deprecation', 'true' );
                $response->header( 'Link', '<' . rest_url( self::NAMESPACE . $route ) . '>; rel="successor-version"' );
            }
            return $response;
        };
    }
}
