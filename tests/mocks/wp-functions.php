<?php
/**
 * WordPress mock functions for unit tests
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) {
        return esc_html( $text );
    }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( '_e' ) ) {
    function _e( $text, $domain = 'default' ) {
        echo $text;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $options = 0 ) {
        return json_encode( $data, $options );
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return htmlspecialchars( strip_tags( $str ), ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ) {
        return filter_var( $email, FILTER_SANITIZE_EMAIL );
    }
}

if ( ! function_exists( 'is_email' ) ) {
    function is_email( $email ) {
        return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
    }
}

if ( ! function_exists( 'absint' ) ) {
    function absint( $val ) {
        return abs( intval( $val ) );
    }
}

if ( ! function_exists( 'wp_parse_args' ) ) {
    function wp_parse_args( $args, $defaults = array() ) {
        if ( is_object( $args ) ) {
            $args = get_object_vars( $args );
        }
        return array_merge( $defaults, $args );
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value ) {
        return $value;
    }
}

if ( ! function_exists( 'do_action' ) ) {
    function do_action( $tag, ...$args ) {
        // Recorded, so tests can count how often a hook fired.
        $GLOBALS['qaf_test_actions'][] = array( $tag, $args );
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
        // No-op
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
        // No-op
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = 0 ) {
        if ( $type === 'mysql' ) {
            return date( 'Y-m-d H:i:s' );
        }
        return time();
    }
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
    function wp_generate_uuid4() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $code;
        private $message;
        private $data;

        public function __construct( $code = '', $message = '', $data = '' ) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

if ( ! function_exists( 'sanitize_title' ) ) {
    // Matches WordPress for plain ASCII titles, which is all the tests use.
    function sanitize_title( $title ) {
        return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $title ) ), '-' );
    }
}

if ( ! function_exists( 'esc_attr__' ) ) {
    function esc_attr__( $text, $domain = 'default' ) {
        return esc_attr( $text );
    }
}

if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $text, $domain = 'default' ) {
        echo esc_html( $text );
    }
}

if ( ! function_exists( 'esc_attr_e' ) ) {
    function esc_attr_e( $text, $domain = 'default' ) {
        echo esc_attr( $text );
    }
}

if ( ! function_exists( '_n' ) ) {
    function _n( $single, $plural, $number, $domain = 'default' ) {
        return 1 === (int) $number ? $single : $plural;
    }
}

// Field and view rendering (added with the field-fix tests).
if ( ! function_exists( 'esc_textarea' ) ) {
    function esc_textarea( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $text ) {
        return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', (string) $text ) ) );
    }
}

if ( ! function_exists( 'wp_kses' ) ) {
    // Tag allow-list only; enough to tell kept markup from stripped markup.
    function wp_kses( $text, $allowed ) {
        $tags = '';
        foreach ( array_keys( (array) $allowed ) as $tag ) {
            $tags .= '<' . $tag . '>';
        }
        return strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', (string) $text ), $tags );
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $text ) {
        return wp_kses( $text, array_fill_keys( array( 'a', 'abbr', 'b', 'blockquote', 'br', 'code', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'i', 'li', 'ol', 'p', 'pre', 'span', 'strong', 'u', 'ul' ), array() ) );
    }
}

if ( ! function_exists( 'is_admin' ) ) {
    function is_admin() {
        return false;
    }
}

if ( ! function_exists( 'date_i18n' ) ) {
    function date_i18n( $format, $timestamp = false ) {
        return gmdate( $format ? $format : 'Y-m-d', false === $timestamp ? time() : (int) $timestamp );
    }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    // Like sanitize_text_field() but keeps line breaks.
    function sanitize_textarea_field( $str ) {
        $lines = preg_split( '/\r\n|\r|\n/', (string) $str );
        return implode( "\n",array_map( 'sanitize_text_field', $lines ) );
    }
}
