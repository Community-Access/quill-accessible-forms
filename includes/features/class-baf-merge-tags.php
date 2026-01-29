<?php
/**
 * Merge Tags System
 *
 * Dynamic content replacement using {field_name} syntax with modifiers.
 * Similar to Gravity Forms merge tags functionality.
 *
 * @package BAF
 * @since 1.0.0
 */

namespace BAF\Features;

use BAF\Core\Form;
use BAF\Core\Entry;

/**
 * Class Merge_Tags
 *
 * Processes merge tags for dynamic content replacement.
 */
class Merge_Tags {

    /**
     * Pattern for matching merge tags.
     * Supports: {field_name}, {field_name:modifier}, {field_name:modifier=value}
     *
     * @var string
     */
    private const TAG_PATTERN = '/\{([a-zA-Z0-9_]+)(?::([a-zA-Z0-9_]+)(?:=([^}]+))?)?\}/';

    /**
     * Registered custom tag handlers.
     *
     * @var array
     */
    private array $custom_handlers = [];

    /**
     * Entry data cache.
     *
     * @var array|null
     */
    private ?array $entry_data = null;

    /**
     * Form instance.
     *
     * @var Form|null
     */
    private ?Form $form = null;

    /**
     * Entry instance.
     *
     * @var Entry|null
     */
    private ?Entry $entry = null;

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->register_default_handlers();
    }

    /**
     * Process merge tags in content.
     *
     * @param string     $content Content with merge tags.
     * @param Form|null  $form    Form instance.
     * @param Entry|null $entry   Entry instance.
     * @param array      $context Additional context data.
     * @return string Processed content.
     */
    public function process( string $content, ?Form $form = null, ?Entry $entry = null, array $context = [] ): string {
        $this->form  = $form;
        $this->entry = $entry;
        
        if ( $entry ) {
            $this->entry_data = $entry->get_data();
        } elseif ( isset( $context['entry_data'] ) ) {
            $this->entry_data = $context['entry_data'];
        }

        // Process all merge tags
        $content = preg_replace_callback(
            self::TAG_PATTERN,
            function( $matches ) use ( $context ) {
                return $this->replace_tag( $matches, $context );
            },
            $content
        );

        // Process conditional tags
        $content = $this->process_conditionals( $content );

        // Clean up entry data after processing
        $this->entry_data = null;

        return $content;
    }

    /**
     * Replace a single merge tag.
     *
     * @param array $matches Regex matches.
     * @param array $context Additional context.
     * @return string Replacement value.
     */
    private function replace_tag( array $matches, array $context ): string {
        $full_tag  = $matches[0];
        $tag_name  = $matches[1];
        $modifier  = $matches[2] ?? null;
        $mod_value = $matches[3] ?? null;

        // Check custom handlers first
        if ( isset( $this->custom_handlers[ $tag_name ] ) ) {
            $value = call_user_func(
                $this->custom_handlers[ $tag_name ],
                $modifier,
                $mod_value,
                $this->form,
                $this->entry,
                $context
            );
            return $this->apply_modifier( $value, $modifier, $mod_value );
        }

        // Check system tags
        $value = $this->get_system_tag_value( $tag_name, $modifier, $mod_value, $context );
        if ( null !== $value ) {
            return $this->apply_modifier( $value, $modifier, $mod_value );
        }

        // Check field values
        if ( null !== $this->entry_data && isset( $this->entry_data[ $tag_name ] ) ) {
            $value = $this->entry_data[ $tag_name ];
            return $this->apply_modifier( $value, $modifier, $mod_value );
        }

        // Check form fields for default values
        if ( $this->form ) {
            $field = $this->form->get_field( $tag_name );
            if ( $field ) {
                $value = $field->get_property( 'default' ) ?? '';
                return $this->apply_modifier( $value, $modifier, $mod_value );
            }
        }

        // Allow filtering for unknown tags
        $value = apply_filters( 'baf_merge_tag_value', $full_tag, $tag_name, $modifier, $mod_value, $context );

        // Return original tag if not found (or filtered value)
        return $value;
    }

    /**
     * Get system tag value.
     *
     * @param string      $tag_name  Tag name.
     * @param string|null $modifier  Modifier.
     * @param string|null $mod_value Modifier value.
     * @param array       $context   Context data.
     * @return string|null Value or null if not a system tag.
     */
    private function get_system_tag_value( string $tag_name, ?string $modifier, ?string $mod_value, array $context ): ?string {
        switch ( $tag_name ) {
            // Form tags
            case 'form_id':
                return $this->form ? (string) $this->form->get_id() : '';
                
            case 'form_title':
                return $this->form ? $this->form->get_title() : '';

            case 'form_url':
                return $this->form ? $this->form->get_url() : '';

            // Entry tags
            case 'entry_id':
                return $this->entry ? (string) $this->entry->get_id() : '';

            case 'entry_url':
                return $this->entry ? $this->entry->get_view_url() : '';

            case 'entry_edit_url':
                return $this->entry ? $this->entry->get_edit_url() : '';

            // Date/time tags
            case 'date':
                return $this->format_date( $modifier ?? 'F j, Y', $mod_value );

            case 'time':
                return $this->format_date( $modifier ?? 'g:i a', $mod_value );

            case 'datetime':
                return $this->format_date( $modifier ?? 'F j, Y g:i a', $mod_value );

            case 'date_created':
                if ( $this->entry ) {
                    return $this->format_date(
                        $modifier ?? 'F j, Y g:i a',
                        null,
                        $this->entry->get_date_created()
                    );
                }
                return '';

            case 'date_updated':
                if ( $this->entry ) {
                    return $this->format_date(
                        $modifier ?? 'F j, Y g:i a',
                        null,
                        $this->entry->get_date_updated()
                    );
                }
                return '';

            // User tags
            case 'user_id':
                return is_user_logged_in() ? (string) get_current_user_id() : '';

            case 'user_email':
                $user = wp_get_current_user();
                return $user->ID ? $user->user_email : '';

            case 'user_login':
            case 'username':
                $user = wp_get_current_user();
                return $user->ID ? $user->user_login : '';

            case 'user_display_name':
            case 'display_name':
                $user = wp_get_current_user();
                return $user->ID ? $user->display_name : '';

            case 'user_first_name':
            case 'first_name':
                $user = wp_get_current_user();
                return $user->ID ? $user->first_name : '';

            case 'user_last_name':
            case 'last_name':
                $user = wp_get_current_user();
                return $user->ID ? $user->last_name : '';

            case 'user_role':
                $user = wp_get_current_user();
                return $user->ID ? implode( ', ', $user->roles ) : '';

            case 'user_meta':
                if ( ! $modifier || ! is_user_logged_in() ) {
                    return '';
                }
                return get_user_meta( get_current_user_id(), $modifier, true ) ?: '';

            // Site tags
            case 'site_name':
            case 'blog_name':
                return get_bloginfo( 'name' );

            case 'site_url':
            case 'blog_url':
                return home_url();

            case 'admin_email':
                return get_option( 'admin_email' );

            // Post tags (when form is embedded)
            case 'post_id':
                return isset( $context['post_id'] ) ? (string) $context['post_id'] : (string) get_the_ID();

            case 'post_title':
                $post_id = $context['post_id'] ?? get_the_ID();
                return $post_id ? get_the_title( $post_id ) : '';

            case 'post_url':
            case 'post_permalink':
                $post_id = $context['post_id'] ?? get_the_ID();
                return $post_id ? get_permalink( $post_id ) : '';

            case 'post_meta':
                if ( ! $modifier ) {
                    return '';
                }
                $post_id = $context['post_id'] ?? get_the_ID();
                return $post_id ? get_post_meta( $post_id, $modifier, true ) : '';

            // IP and browser tags
            case 'ip':
            case 'ip_address':
                return $this->get_client_ip();

            case 'user_agent':
                return sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' );

            case 'referer':
            case 'referrer':
                return esc_url( wp_get_referer() ?: '' );

            // All fields/entry summary
            case 'all_fields':
                return $this->get_all_fields_html( $modifier === 'formatted' );

            case 'all_fields_table':
                return $this->get_all_fields_table();

            // Conditional helpers
            case 'if':
                // Handled separately in process_conditionals
                return '';

            // Unique identifiers
            case 'unique_id':
                return wp_generate_uuid4();

            case 'sequence':
                return $this->get_sequence_number();

            // Math operations (for field calculations)
            case 'sum':
            case 'avg':
            case 'min':
            case 'max':
            case 'count':
                return $this->calculate_aggregate( $tag_name, $modifier );

            // Pricing/payment tags
            case 'payment_total':
                return $this->entry ? number_format( (float) $this->entry->get_meta( 'payment_total' ), 2 ) : '';

            case 'payment_status':
                return $this->entry ? $this->entry->get_meta( 'payment_status' ) : '';

            case 'transaction_id':
                return $this->entry ? $this->entry->get_meta( 'transaction_id' ) : '';

            default:
                return null;
        }
    }

    /**
     * Apply modifier to value.
     *
     * @param mixed       $value     Original value.
     * @param string|null $modifier  Modifier name.
     * @param string|null $mod_value Modifier parameter.
     * @return string Modified value.
     */
    private function apply_modifier( $value, ?string $modifier, ?string $mod_value ): string {
        if ( null === $modifier ) {
            return $this->stringify( $value );
        }

        // Handle array values
        if ( is_array( $value ) ) {
            switch ( $modifier ) {
                case 'count':
                    return (string) count( $value );
                case 'first':
                    return $this->stringify( reset( $value ) );
                case 'last':
                    return $this->stringify( end( $value ) );
                case 'json':
                    return wp_json_encode( $value );
                case 'list':
                    $separator = $mod_value ?? ', ';
                    return implode( $separator, array_map( [ $this, 'stringify' ], $value ) );
                case 'ul':
                    return '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $value ) ) . '</li></ul>';
                case 'ol':
                    return '<ol><li>' . implode( '</li><li>', array_map( 'esc_html', $value ) ) . '</li></ol>';
            }
            // Default: convert to comma-separated string
            $value = implode( ', ', $value );
        }

        $value = (string) $value;

        switch ( $modifier ) {
            // Case modifiers
            case 'upper':
            case 'uppercase':
                return strtoupper( $value );
                
            case 'lower':
            case 'lowercase':
                return strtolower( $value );
                
            case 'ucfirst':
            case 'capitalize':
                return ucfirst( $value );
                
            case 'ucwords':
            case 'titlecase':
                return ucwords( $value );

            // String modifiers
            case 'trim':
                return trim( $value );
                
            case 'strip':
            case 'strip_tags':
                return wp_strip_all_tags( $value );
                
            case 'nl2br':
                return nl2br( esc_html( $value ) );
                
            case 'urlsafe':
            case 'slug':
                return sanitize_title( $value );

            case 'urlencode':
                return rawurlencode( $value );

            case 'base64':
                return base64_encode( $value );

            // Truncation
            case 'limit':
            case 'truncate':
                $length = (int) ( $mod_value ?: 100 );
                if ( strlen( $value ) > $length ) {
                    return substr( $value, 0, $length ) . '...';
                }
                return $value;

            case 'words':
                $limit = (int) ( $mod_value ?: 20 );
                return wp_trim_words( $value, $limit, '...' );

            // Number modifiers
            case 'number':
            case 'format_number':
                $decimals = (int) ( $mod_value ?? 0 );
                return number_format( (float) $value, $decimals );
                
            case 'currency':
                $decimals = (int) ( $mod_value ?? 2 );
                return '$' . number_format( (float) $value, $decimals );
                
            case 'percent':
                $decimals = (int) ( $mod_value ?? 0 );
                return number_format( (float) $value * 100, $decimals ) . '%';

            case 'round':
                $precision = (int) ( $mod_value ?? 0 );
                return (string) round( (float) $value, $precision );

            case 'abs':
                return (string) abs( (float) $value );

            // Date modifiers
            case 'date':
                $format = $mod_value ?: 'F j, Y';
                $timestamp = strtotime( $value );
                return $timestamp ? date_i18n( $format, $timestamp ) : $value;

            case 'relative':
            case 'human_time':
                return human_time_diff( strtotime( $value ), current_time( 'timestamp' ) );

            // HTML/escape modifiers
            case 'html':
            case 'esc_html':
                return esc_html( $value );
                
            case 'attr':
            case 'esc_attr':
                return esc_attr( $value );
                
            case 'url':
            case 'esc_url':
                return esc_url( $value );

            case 'raw':
                // Return unescaped (use with caution)
                return $value;

            // Default/fallback
            case 'default':
                return $value ?: ( $mod_value ?? '' );

            // Link generation
            case 'link':
                $text = $mod_value ?: $value;
                if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
                    return sprintf( '<a href="%s">%s</a>', esc_url( $value ), esc_html( $text ) );
                }
                if ( filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
                    return sprintf( '<a href="mailto:%s">%s</a>', esc_attr( $value ), esc_html( $text ) );
                }
                return esc_html( $value );

            // Label for choice fields
            case 'label':
                return $this->get_choice_label( $value );

            // Value for choice fields (opposite of label)
            case 'value':
                return $value;

            default:
                // Allow custom modifiers via filter
                return apply_filters( 'baf_merge_tag_modifier', $value, $modifier, $mod_value );
        }
    }

    /**
     * Process conditional merge tags.
     * Supports: {if field_name}content{/if}
     *           {if field_name = value}content{else}other{/if}
     *
     * @param string $content Content with conditionals.
     * @return string Processed content.
     */
    private function process_conditionals( string $content ): string {
        // Pattern for conditionals: {if condition}...{else}...{/if}
        $pattern = '/\{if\s+([^\}]+)\}(.*?)(?:\{else\}(.*?))?\{\/if\}/s';

        return preg_replace_callback( $pattern, function( $matches ) {
            $condition     = trim( $matches[1] );
            $if_content    = $matches[2];
            $else_content  = $matches[3] ?? '';

            $result = $this->evaluate_condition( $condition );

            // Recursively process nested conditionals
            $chosen_content = $result ? $if_content : $else_content;
            return $this->process_conditionals( $chosen_content );
        }, $content );
    }

    /**
     * Evaluate a condition.
     *
     * @param string $condition Condition string.
     * @return bool Result.
     */
    private function evaluate_condition( string $condition ): bool {
        // Parse operators
        $operators = [ '!==', '===', '!=', '==', '>=', '<=', '>', '<', '=' ];
        
        foreach ( $operators as $op ) {
            if ( strpos( $condition, $op ) !== false ) {
                list( $left, $right ) = array_map( 'trim', explode( $op, $condition, 2 ) );
                
                // Get field value for left side
                $left_value = $this->get_condition_value( $left );
                
                // Parse right side (remove quotes if present)
                $right = trim( $right, '"\'');
                $right_value = $this->get_condition_value( $right );

                switch ( $op ) {
                    case '===':
                        return $left_value === $right_value;
                    case '!==':
                        return $left_value !== $right_value;
                    case '==':
                    case '=':
                        return $left_value == $right_value;
                    case '!=':
                        return $left_value != $right_value;
                    case '>':
                        return (float) $left_value > (float) $right_value;
                    case '<':
                        return (float) $left_value < (float) $right_value;
                    case '>=':
                        return (float) $left_value >= (float) $right_value;
                    case '<=':
                        return (float) $left_value <= (float) $right_value;
                }
            }
        }

        // Simple existence check (truthy)
        $value = $this->get_condition_value( $condition );
        return ! empty( $value );
    }

    /**
     * Get value for condition evaluation.
     *
     * @param string $key Field name or literal.
     * @return mixed Value.
     */
    private function get_condition_value( string $key ) {
        // Check if it's a literal value (quoted string or number)
        if ( is_numeric( $key ) ) {
            return $key;
        }

        // Check entry data
        if ( null !== $this->entry_data && isset( $this->entry_data[ $key ] ) ) {
            return $this->entry_data[ $key ];
        }

        // Return the literal value
        return $key;
    }

    /**
     * Register a custom merge tag handler.
     *
     * @param string   $tag_name Tag name without braces.
     * @param callable $handler  Handler function.
     * @return void
     */
    public function register_handler( string $tag_name, callable $handler ): void {
        $this->custom_handlers[ $tag_name ] = $handler;
    }

    /**
     * Register default custom handlers.
     *
     * @return void
     */
    private function register_default_handlers(): void {
        // Embed URL parameters
        $this->register_handler( 'get', function( $modifier ) {
            if ( ! $modifier ) {
                return '';
            }
            return sanitize_text_field( $_GET[ $modifier ] ?? '' );
        } );

        // Embed POST data
        $this->register_handler( 'post', function( $modifier ) {
            if ( ! $modifier ) {
                return '';
            }
            return sanitize_text_field( $_POST[ $modifier ] ?? '' );
        } );

        // WordPress option
        $this->register_handler( 'option', function( $modifier ) {
            if ( ! $modifier ) {
                return '';
            }
            return get_option( $modifier, '' );
        } );

        // Shortcode
        $this->register_handler( 'shortcode', function( $modifier, $mod_value ) {
            if ( ! $modifier ) {
                return '';
            }
            return do_shortcode( "[{$modifier}]" );
        } );

        // Random number
        $this->register_handler( 'random', function( $modifier, $mod_value ) {
            $min = (int) ( $modifier ?? 1 );
            $max = (int) ( $mod_value ?? 100 );
            return (string) wp_rand( $min, $max );
        } );
    }

    /**
     * Format date with optional timezone.
     *
     * @param string      $format    Date format.
     * @param string|null $timezone  Timezone string.
     * @param string|null $timestamp Timestamp string.
     * @return string Formatted date.
     */
    private function format_date( string $format, ?string $timezone = null, ?string $timestamp = null ): string {
        $time = $timestamp ? strtotime( $timestamp ) : current_time( 'timestamp' );
        
        if ( $timezone ) {
            $tz = new \DateTimeZone( $timezone );
            $dt = new \DateTime( '@' . $time );
            $dt->setTimezone( $tz );
            return $dt->format( $format );
        }

        return date_i18n( $format, $time );
    }

    /**
     * Get all fields as HTML.
     *
     * @param bool $formatted Whether to format nicely.
     * @return string HTML string.
     */
    private function get_all_fields_html( bool $formatted = true ): string {
        if ( ! $this->entry_data || ! $this->form ) {
            return '';
        }

        $html   = '';
        $fields = $this->form->get_fields();

        foreach ( $fields as $field_id => $field ) {
            if ( ! isset( $this->entry_data[ $field_id ] ) ) {
                continue;
            }

            // Skip hidden and internal fields
            if ( $field->get_type() === 'hidden' || $field->get_property( 'admin_only' ) ) {
                continue;
            }

            $label = $field->get_label();
            $value = $this->stringify( $this->entry_data[ $field_id ] );

            if ( $formatted ) {
                $html .= sprintf(
                    '<p><strong>%s:</strong><br>%s</p>',
                    esc_html( $label ),
                    nl2br( esc_html( $value ) )
                );
            } else {
                $html .= sprintf( "%s: %s\n", $label, $value );
            }
        }

        return $html;
    }

    /**
     * Get all fields as table.
     *
     * @return string HTML table.
     */
    private function get_all_fields_table(): string {
        if ( ! $this->entry_data || ! $this->form ) {
            return '';
        }

        $html  = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse;">';
        $html .= '<tbody>';

        $fields = $this->form->get_fields();

        foreach ( $fields as $field_id => $field ) {
            if ( ! isset( $this->entry_data[ $field_id ] ) ) {
                continue;
            }

            if ( $field->get_type() === 'hidden' || $field->get_property( 'admin_only' ) ) {
                continue;
            }

            $label = $field->get_label();
            $value = $this->stringify( $this->entry_data[ $field_id ] );

            $html .= sprintf(
                '<tr><td style="background: #f5f5f5; font-weight: bold;">%s</td><td>%s</td></tr>',
                esc_html( $label ),
                nl2br( esc_html( $value ) )
            );
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Get sequence number for form.
     *
     * @return string Sequence number.
     */
    private function get_sequence_number(): string {
        if ( ! $this->form ) {
            return '1';
        }

        $form_id  = $this->form->get_id();
        $sequence = (int) get_option( "baf_sequence_{$form_id}", 0 );
        
        return (string) ( $sequence + 1 );
    }

    /**
     * Calculate aggregate function on fields.
     *
     * @param string      $function  Aggregate function (sum, avg, min, max, count).
     * @param string|null $field_ids Comma-separated field IDs.
     * @return string Result.
     */
    private function calculate_aggregate( string $function, ?string $field_ids ): string {
        if ( ! $field_ids || null === $this->entry_data ) {
            return '0';
        }

        $ids     = array_map( 'trim', explode( ',', $field_ids ) );
        $values  = [];

        foreach ( $ids as $field_id ) {
            if ( isset( $this->entry_data[ $field_id ] ) ) {
                $val = $this->entry_data[ $field_id ];
                if ( is_numeric( $val ) ) {
                    $values[] = (float) $val;
                }
            }
        }

        if ( empty( $values ) ) {
            return '0';
        }

        switch ( $function ) {
            case 'sum':
                return (string) array_sum( $values );
            case 'avg':
                return (string) ( array_sum( $values ) / count( $values ) );
            case 'min':
                return (string) min( $values );
            case 'max':
                return (string) max( $values );
            case 'count':
                return (string) count( $values );
            default:
                return '0';
        }
    }

    /**
     * Get choice label from value.
     *
     * @param string $value Field value.
     * @return string Label or original value.
     */
    private function get_choice_label( string $value ): string {
        // This would need the original field context to map value to label
        // For now, return the value
        return apply_filters( 'baf_merge_tag_choice_label', $value, $this->form );
    }

    /**
     * Convert value to string.
     *
     * @param mixed $value Value to stringify.
     * @return string String value.
     */
    private function stringify( $value ): string {
        if ( is_array( $value ) ) {
            return implode( ', ', array_map( [ $this, 'stringify' ], $value ) );
        }
        if ( is_bool( $value ) ) {
            return $value ? 'Yes' : 'No';
        }
        return (string) $value;
    }

    /**
     * Get client IP address.
     *
     * @return string IP address.
     */
    private function get_client_ip(): string {
        $ip_keys = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = $_SERVER[ $key ];
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get available merge tags for UI display.
     *
     * @param Form|null $form Form instance for field tags.
     * @return array Categorized merge tags.
     */
    public static function get_available_tags( ?Form $form = null ): array {
        $tags = [
            'Form' => [
                '{form_id}'    => __( 'Form ID', 'jexi-forms' ),
                '{form_title}' => __( 'Form Title', 'jexi-forms' ),
                '{form_url}'   => __( 'Form URL', 'jexi-forms' ),
            ],
            'Entry' => [
                '{entry_id}'       => __( 'Entry ID', 'jexi-forms' ),
                '{entry_url}'      => __( 'Entry View URL', 'jexi-forms' ),
                '{entry_edit_url}' => __( 'Entry Edit URL', 'jexi-forms' ),
                '{date_created}'   => __( 'Date Created', 'jexi-forms' ),
                '{date_updated}'   => __( 'Date Updated', 'jexi-forms' ),
                '{all_fields}'     => __( 'All Fields', 'jexi-forms' ),
            ],
            'User' => [
                '{user_id}'           => __( 'User ID', 'jexi-forms' ),
                '{user_email}'        => __( 'User Email', 'jexi-forms' ),
                '{user_display_name}' => __( 'Display Name', 'jexi-forms' ),
                '{user_first_name}'   => __( 'First Name', 'jexi-forms' ),
                '{user_last_name}'    => __( 'Last Name', 'jexi-forms' ),
            ],
            'Date/Time' => [
                '{date}'     => __( 'Current Date', 'jexi-forms' ),
                '{time}'     => __( 'Current Time', 'jexi-forms' ),
                '{datetime}' => __( 'Current Date/Time', 'jexi-forms' ),
            ],
            'Site' => [
                '{site_name}'   => __( 'Site Name', 'jexi-forms' ),
                '{site_url}'    => __( 'Site URL', 'jexi-forms' ),
                '{admin_email}' => __( 'Admin Email', 'jexi-forms' ),
            ],
            'Post' => [
                '{post_id}'    => __( 'Post ID', 'jexi-forms' ),
                '{post_title}' => __( 'Post Title', 'jexi-forms' ),
                '{post_url}'   => __( 'Post URL', 'jexi-forms' ),
            ],
            'Other' => [
                '{ip}'         => __( 'IP Address', 'jexi-forms' ),
                '{unique_id}'  => __( 'Unique ID', 'jexi-forms' ),
                '{sequence}'   => __( 'Sequence Number', 'jexi-forms' ),
                '{user_agent}' => __( 'Browser User Agent', 'jexi-forms' ),
            ],
        ];

        // Add form field tags
        if ( $form ) {
            $field_tags = [];
            foreach ( $form->get_fields() as $field_id => $field ) {
                $tag              = '{' . $field_id . '}';
                $field_tags[ $tag ] = $field->get_label();
            }
            if ( ! empty( $field_tags ) ) {
                $tags = [ 'Fields' => $field_tags ] + $tags;
            }
        }

        return apply_filters( 'baf_available_merge_tags', $tags, $form );
    }
}
