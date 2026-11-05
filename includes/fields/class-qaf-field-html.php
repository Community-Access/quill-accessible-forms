<?php
/**
 * HTML Field - Custom HTML content
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Html class
 */
class QAF_Field_Html extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'html';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'layout';

    /**
     * Whether field stores data
     *
     * @var bool
     */
    protected $has_value = false;

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array(
            'content'             => '',
            'disable_smart_tags'  => false,
            'container_tag'       => 'div',
            'conditional'         => array(),
            'css_class'           => '',
        );
    }

    /**
     * Get field settings for admin UI
     *
     * @return array Settings configuration.
     */
    public function get_field_settings() {
        return array(
            'general' => array(
                'content' => array(
                    'type'  => 'wysiwyg',
                    'label' => __( 'HTML Content', 'quill-accessible-forms' ),
                    'desc'  => __( 'Enter custom HTML. Shortcodes and Smart Tags are supported.', 'quill-accessible-forms' ),
                ),
            ),
            'options' => array(
                'disable_smart_tags' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Disable Smart Tags', 'quill-accessible-forms' ),
                ),
                'container_tag' => array(
                    'type'    => 'select',
                    'label'   => __( 'Container Element', 'quill-accessible-forms' ),
                    'options' => array(
                        'div'     => 'div',
                        'section' => 'section',
                        'article' => 'article',
                        'aside'   => 'aside',
                        'none'    => __( 'No container', 'quill-accessible-forms' ),
                    ),
                ),
                'css_class' => array(
                    'type'  => 'text',
                    'label' => __( 'CSS Class', 'quill-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the HTML field
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $content = $this->config['content'] ?? '';
        $tag = $this->config['container_tag'] ?? 'div';

        // Process smart tags unless disabled
        if ( empty( $this->config['disable_smart_tags'] ) ) {
            $content = $this->process_smart_tags( $content );
        }

        // Process shortcodes
        $content = do_shortcode( $content );

        // Allow safe HTML
        $content = wp_kses_post( $content );

        // No container
        if ( $tag === 'none' ) {
            return $content;
        }

        $wrapper_class = array( 'qaf-html-field' );
        
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<' . esc_attr( $tag ) . ' class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';
        $html .= $content;
        $html .= '</' . esc_attr( $tag ) . '>';

        return $html;
    }

    /**
     * Process smart tags in content
     *
     * @param string $content Content with smart tags.
     * @return string Processed content.
     */
    protected function process_smart_tags( $content ) {
        $tags = array(
            '{site_name}'     => get_bloginfo( 'name' ),
            '{site_url}'      => home_url(),
            '{admin_email}'   => get_option( 'admin_email' ),
            '{current_date}'  => wp_date( get_option( 'date_format' ) ),
            '{current_time}'  => wp_date( get_option( 'time_format' ) ),
            '{current_year}'  => wp_date( 'Y' ),
        );

        // User tags
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $tags['{user_id}']           = $user->ID;
            $tags['{user_email}']        = $user->user_email;
            $tags['{user_login}']        = $user->user_login;
            $tags['{user_display_name}'] = $user->display_name;
            $tags['{user_first_name}']   = $user->first_name;
            $tags['{user_last_name}']    = $user->last_name;
        } else {
            $tags['{user_id}']           = '';
            $tags['{user_email}']        = '';
            $tags['{user_login}']        = '';
            $tags['{user_display_name}'] = '';
            $tags['{user_first_name}']   = '';
            $tags['{user_last_name}']    = '';
        }

        // Query var tags
        if ( preg_match_all( '/\{query_var:([a-zA-Z0-9_]+)\}/', $content, $matches ) ) {
            foreach ( $matches[1] as $index => $key ) {
                $value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
                $tags[ $matches[0][ $index ] ] = $value;
            }
        }

        // Allow filtering smart tags
        $tags = apply_filters( 'qaf_smart_tags', $tags, $this );

        return str_replace( array_keys( $tags ), array_values( $tags ), $content );
    }

    /**
     * HTML fields don't need validation
     *
     * @return bool Always true.
     */
    public function validate() {
        return true;
    }

    /**
     * HTML fields don't store values
     *
     * @param mixed $value Value.
     * @return null
     */
    public function sanitize( $value ) {
        return null;
    }
}
