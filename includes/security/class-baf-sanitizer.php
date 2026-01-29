<?php
/**
 * Input Sanitizer
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Sanitizer class
 * 
 * Sanitizes form field inputs based on field type.
 */
class BAF_Sanitizer {

    /**
     * Sanitize a value based on field type
     *
     * @param mixed  $value      Value to sanitize.
     * @param string $field_type Field type.
     * @param array  $options    Additional options for sanitization.
     * @return mixed Sanitized value.
     */
    public function sanitize( $value, $field_type, $options = array() ) {
        // Handle arrays
        if ( is_array( $value ) ) {
            return array_map( function( $v ) use ( $field_type, $options ) {
                return $this->sanitize( $v, $field_type, $options );
            }, $value );
        }

        // Type-specific sanitization
        switch ( $field_type ) {
            case 'text':
            case 'hidden':
                return $this->sanitize_text( $value );

            case 'textarea':
                return $this->sanitize_textarea( $value );

            case 'email':
                return $this->sanitize_email( $value );

            case 'url':
            case 'website':
                return $this->sanitize_url( $value );

            case 'number':
            case 'range':
            case 'rating':
            case 'nps':
                return $this->sanitize_number( $value );

            case 'phone':
                return $this->sanitize_phone( $value );

            case 'date':
            case 'datetime':
            case 'time':
            case 'daterange':
                return $this->sanitize_date( $value );

            case 'color':
                return $this->sanitize_color( $value );

            case 'html':
                return $this->sanitize_html( $value );

            case 'select':
            case 'radio':
            case 'checkbox':
            case 'multiselect':
                return $this->sanitize_choice( $value, $options );

            case 'password':
                return $value; // Don't sanitize passwords

            case 'name':
                return $this->sanitize_name( $value );

            case 'address':
                return $this->sanitize_address( $value );

            case 'calculation':
                return $this->sanitize_number( $value );

            case 'consent':
            case 'toggle':
                return $this->sanitize_boolean( $value );

            case 'signature':
                return $this->sanitize_signature( $value );

            case 'fileupload':
                return $this->sanitize_file( $value );

            case 'likert':
            case 'ranking':
                return $this->sanitize_array( $value );

            default:
                return $this->sanitize_text( $value );
        }
    }

    /**
     * Sanitize text input
     *
     * @param string $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize_text( $value ) {
        return sanitize_text_field( $value );
    }

    /**
     * Sanitize textarea input
     *
     * @param string $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize_textarea( $value ) {
        return sanitize_textarea_field( $value );
    }

    /**
     * Sanitize email input
     *
     * @param string $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize_email( $value ) {
        return sanitize_email( $value );
    }

    /**
     * Sanitize URL input
     *
     * @param string $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize_url( $value ) {
        return esc_url_raw( $value );
    }

    /**
     * Sanitize number input
     *
     * @param mixed $value Value to sanitize.
     * @return float|int Sanitized value.
     */
    public function sanitize_number( $value ) {
        if ( strpos( $value, '.' ) !== false ) {
            return floatval( $value );
        }
        return intval( $value );
    }

    /**
     * Sanitize phone input
     *
     * @param string $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize_phone( $value ) {
        // Remove all non-numeric characters except + and spaces
        return preg_replace( '/[^\d\+\s\-\(\)]/', '', $value );
    }

    /**
     * Sanitize date input
     *
     * @param string $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize_date( $value ) {
        // Validate as date/time format
        if ( strtotime( $value ) !== false ) {
            return sanitize_text_field( $value );
        }
        return '';
    }

    /**
     * Sanitize color input
     *
     * @param string $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize_color( $value ) {
        // Validate hex color
        if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value ) ) {
            return $value;
        }
        
        // Validate rgb/rgba
        if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*[\d.]+\s*)?\)$/i', $value ) ) {
            return $value;
        }

        return '';
    }

    /**
     * Sanitize HTML input (restricted)
     *
     * @param string $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize_html( $value ) {
        return wp_kses_post( $value );
    }

    /**
     * Sanitize choice input
     *
     * @param mixed $value   Value to sanitize.
     * @param array $options Allowed options.
     * @return mixed Sanitized value.
     */
    public function sanitize_choice( $value, $options = array() ) {
        if ( is_array( $value ) ) {
            return array_filter( array_map( 'sanitize_text_field', $value ) );
        }
        return sanitize_text_field( $value );
    }

    /**
     * Sanitize boolean input
     *
     * @param mixed $value Value to sanitize.
     * @return bool Sanitized value.
     */
    public function sanitize_boolean( $value ) {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * Sanitize name input
     *
     * @param mixed $value Value to sanitize.
     * @return array Sanitized value.
     */
    public function sanitize_name( $value ) {
        if ( ! is_array( $value ) ) {
            return array(
                'first' => sanitize_text_field( $value ),
            );
        }

        $sanitized = array();
        $fields = array( 'prefix', 'first', 'middle', 'last', 'suffix' );

        foreach ( $fields as $field ) {
            if ( isset( $value[ $field ] ) ) {
                $sanitized[ $field ] = sanitize_text_field( $value[ $field ] );
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize address input
     *
     * @param mixed $value Value to sanitize.
     * @return array Sanitized value.
     */
    public function sanitize_address( $value ) {
        if ( ! is_array( $value ) ) {
            return array(
                'street' => sanitize_text_field( $value ),
            );
        }

        $sanitized = array();
        $fields = array( 'street', 'street2', 'city', 'state', 'zip', 'country' );

        foreach ( $fields as $field ) {
            if ( isset( $value[ $field ] ) ) {
                $sanitized[ $field ] = sanitize_text_field( $value[ $field ] );
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize signature input
     *
     * @param string $value Value to sanitize (data URL).
     * @return string Sanitized value.
     */
    public function sanitize_signature( $value ) {
        // Validate data URL format
        if ( strpos( $value, 'data:image/' ) !== 0 ) {
            return '';
        }

        // Validate base64 encoding
        $parts = explode( ',', $value );
        if ( count( $parts ) !== 2 ) {
            return '';
        }

        if ( base64_decode( $parts[1], true ) === false ) {
            return '';
        }

        return $value;
    }

    /**
     * Sanitize file upload
     *
     * @param mixed $value Value to sanitize.
     * @return array Sanitized value.
     */
    public function sanitize_file( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $value as $file ) {
            if ( is_array( $file ) && isset( $file['url'] ) ) {
                $sanitized[] = array(
                    'url'  => esc_url_raw( $file['url'] ),
                    'name' => isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '',
                    'type' => isset( $file['type'] ) ? sanitize_mime_type( $file['type'] ) : '',
                    'size' => isset( $file['size'] ) ? absint( $file['size'] ) : 0,
                );
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize array input
     *
     * @param mixed $value Value to sanitize.
     * @return array Sanitized value.
     */
    public function sanitize_array( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        return array_map( function( $item ) {
            if ( is_array( $item ) ) {
                return array_map( 'sanitize_text_field', $item );
            }
            return sanitize_text_field( $item );
        }, $value );
    }

    /**
     * Validate required field
     *
     * @param mixed $value Value to validate.
     * @return bool True if not empty.
     */
    public function validate_required( $value ) {
        if ( is_array( $value ) ) {
            return ! empty( array_filter( $value ) );
        }
        return ! empty( trim( $value ) );
    }

    /**
     * Validate min length
     *
     * @param string $value  Value to validate.
     * @param int    $length Minimum length.
     * @return bool True if valid.
     */
    public function validate_min_length( $value, $length ) {
        return mb_strlen( $value ) >= $length;
    }

    /**
     * Validate max length
     *
     * @param string $value  Value to validate.
     * @param int    $length Maximum length.
     * @return bool True if valid.
     */
    public function validate_max_length( $value, $length ) {
        return mb_strlen( $value ) <= $length;
    }

    /**
     * Validate pattern
     *
     * @param string $value   Value to validate.
     * @param string $pattern Regex pattern.
     * @return bool True if matches.
     */
    public function validate_pattern( $value, $pattern ) {
        // Escape delimiter and validate pattern is safe
        $pattern = str_replace( '/', '\/', $pattern );
        
        // Test if pattern is valid before using
        // Suppress errors and use return value to check validity
        $test = @preg_match( '/' . $pattern . '/', '' );
        if ( $test === false ) {
            // Invalid regex pattern
            return false;
        }
        
        return (bool) preg_match( '/' . $pattern . '/', $value );
    }

    /**
     * Validate range
     *
     * @param float $value Value to validate.
     * @param float $min   Minimum value.
     * @param float $max   Maximum value.
     * @return bool True if within range.
     */
    public function validate_range( $value, $min, $max ) {
        return $value >= $min && $value <= $max;
    }

    /**
     * Validate file size
     *
     * @param int $size     File size in bytes.
     * @param int $max_size Maximum size in bytes.
     * @return bool True if valid.
     */
    public function validate_file_size( $size, $max_size ) {
        return $size <= $max_size;
    }

    /**
     * Validate file type
     *
     * @param string $type          File MIME type.
     * @param array  $allowed_types Allowed MIME types.
     * @return bool True if valid.
     */
    public function validate_file_type( $type, $allowed_types ) {
        return in_array( $type, $allowed_types, true );
    }
}
