<?php
/**
 * Social Profile Field
 *
 * Social media URL input with validation.
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Social class
 */
class BAF_Field_Social extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'social';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'contact';

    /**
     * Supported social networks
     *
     * @var array
     */
    protected $networks = array(
        'facebook'  => array(
            'label'   => 'Facebook',
            'icon'    => 'facebook-alt',
            'pattern' => 'facebook\.com',
            'prefix'  => 'https://facebook.com/',
            'placeholder' => 'username or page',
        ),
        'twitter'   => array(
            'label'   => 'Twitter / X',
            'icon'    => 'twitter',
            'pattern' => '(twitter|x)\.com',
            'prefix'  => 'https://x.com/',
            'placeholder' => '@username',
        ),
        'instagram' => array(
            'label'   => 'Instagram',
            'icon'    => 'instagram',
            'pattern' => 'instagram\.com',
            'prefix'  => 'https://instagram.com/',
            'placeholder' => 'username',
        ),
        'linkedin'  => array(
            'label'   => 'LinkedIn',
            'icon'    => 'linkedin',
            'pattern' => 'linkedin\.com',
            'prefix'  => 'https://linkedin.com/in/',
            'placeholder' => 'username or profile URL',
        ),
        'youtube'   => array(
            'label'   => 'YouTube',
            'icon'    => 'youtube',
            'pattern' => 'youtube\.com',
            'prefix'  => 'https://youtube.com/',
            'placeholder' => '@channel or channel URL',
        ),
        'tiktok'    => array(
            'label'   => 'TikTok',
            'icon'    => 'video-alt3',
            'pattern' => 'tiktok\.com',
            'prefix'  => 'https://tiktok.com/@',
            'placeholder' => '@username',
        ),
        'github'    => array(
            'label'   => 'GitHub',
            'icon'    => 'admin-plugins',
            'pattern' => 'github\.com',
            'prefix'  => 'https://github.com/',
            'placeholder' => 'username',
        ),
        'pinterest' => array(
            'label'   => 'Pinterest',
            'icon'    => 'pinterest',
            'pattern' => 'pinterest\.com',
            'prefix'  => 'https://pinterest.com/',
            'placeholder' => 'username',
        ),
        'snapchat'  => array(
            'label'   => 'Snapchat',
            'icon'    => 'camera',
            'pattern' => 'snapchat\.com',
            'prefix'  => 'https://snapchat.com/add/',
            'placeholder' => 'username',
        ),
        'threads'   => array(
            'label'   => 'Threads',
            'icon'    => 'format-status',
            'pattern' => 'threads\.net',
            'prefix'  => 'https://threads.net/@',
            'placeholder' => '@username',
        ),
        'mastodon'  => array(
            'label'   => 'Mastodon',
            'icon'    => 'share-alt',
            'pattern' => '.*',  // Various instances
            'prefix'  => '',
            'placeholder' => '@user@instance.social',
        ),
        'bluesky'   => array(
            'label'   => 'Bluesky',
            'icon'    => 'cloud',
            'pattern' => 'bsky\.app',
            'prefix'  => 'https://bsky.app/profile/',
            'placeholder' => 'handle.bsky.social',
        ),
        'discord'   => array(
            'label'   => 'Discord',
            'icon'    => 'groups',
            'pattern' => 'discord\.(gg|com)',
            'prefix'  => '',
            'placeholder' => 'username#0000 or invite link',
        ),
        'twitch'    => array(
            'label'   => 'Twitch',
            'icon'    => 'video-alt2',
            'pattern' => 'twitch\.tv',
            'prefix'  => 'https://twitch.tv/',
            'placeholder' => 'channel name',
        ),
        'custom'    => array(
            'label'   => 'Website/Other',
            'icon'    => 'admin-site-alt3',
            'pattern' => '.*',
            'prefix'  => '',
            'placeholder' => 'https://...',
        ),
    );

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'mode'             => 'single',          // 'single', 'multiple', 'specific'
            'network'          => 'linkedin',        // For single mode
            'networks'         => array( 'linkedin', 'twitter', 'github' ), // For specific mode
            'max_profiles'     => 5,                 // For multiple mode
            'show_icons'       => true,
            'validate_url'     => true,
            'allow_username'   => true,              // Accept username without full URL
            'normalize_url'    => true,              // Convert username to full URL
            'open_in_new_tab'  => true,
        ) );
    }

    /**
     * Get field settings for admin UI
     *
     * @return array Settings configuration.
     */
    public function get_field_settings() {
        return array(
            'general' => array(
                'label' => array(
                    'type'  => 'text',
                    'label' => __( 'Label', 'bits-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'bits-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'bits-accessible-forms' ),
                ),
            ),
            'mode' => array(
                'mode' => array(
                    'type'    => 'select',
                    'label'   => __( 'Mode', 'bits-accessible-forms' ),
                    'options' => array(
                        'single'   => __( 'Single Network (one specific platform)', 'bits-accessible-forms' ),
                        'specific' => __( 'Specific Networks (select which to show)', 'bits-accessible-forms' ),
                        'multiple' => __( 'Multiple (user picks any networks)', 'bits-accessible-forms' ),
                    ),
                ),
                'network' => array(
                    'type'    => 'select',
                    'label'   => __( 'Network', 'bits-accessible-forms' ),
                    'options' => $this->get_network_options(),
                    'desc'    => __( 'For single mode', 'bits-accessible-forms' ),
                ),
                'networks' => array(
                    'type'    => 'multiselect',
                    'label'   => __( 'Networks', 'bits-accessible-forms' ),
                    'options' => $this->get_network_options(),
                    'desc'    => __( 'For specific mode', 'bits-accessible-forms' ),
                ),
                'max_profiles' => array(
                    'type'  => 'number',
                    'label' => __( 'Max Profiles', 'bits-accessible-forms' ),
                    'desc'  => __( 'For multiple mode', 'bits-accessible-forms' ),
                    'min'   => 1,
                    'max'   => 20,
                ),
            ),
            'display' => array(
                'show_icons' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Network Icons', 'bits-accessible-forms' ),
                ),
            ),
            'validation' => array(
                'validate_url' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Validate URL Format', 'bits-accessible-forms' ),
                ),
                'allow_username' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Username Only', 'bits-accessible-forms' ),
                    'desc'  => __( 'Accept just username without full URL', 'bits-accessible-forms' ),
                ),
                'normalize_url' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Normalize to Full URL', 'bits-accessible-forms' ),
                    'desc'  => __( 'Convert usernames to full profile URLs', 'bits-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the field HTML
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id     = $this->get_html_id();
        $name        = 'baf_field_' . $this->get_id();
        $value       = $this->get_value();
        $mode        = $this->config['mode'] ?? 'single';
        $show_icons  = $this->config['show_icons'] ?? true;

        $wrapper_class = $this->get_wrapper_classes();
        $wrapper_class[] = 'baf-social-wrapper';
        $wrapper_class[] = 'baf-social-mode-' . $mode;

        $field = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $field .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $field .= ' data-mode="' . esc_attr( $mode ) . '">';

        // Label
        $field .= $this->render_label();

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $field .= $this->render_description();
        }

        switch ( $mode ) {
            case 'single':
                $field .= $this->render_single_mode( $html_id, $name, $value, $show_icons );
                break;

            case 'specific':
                $field .= $this->render_specific_mode( $html_id, $name, $value, $show_icons );
                break;

            case 'multiple':
                $field .= $this->render_multiple_mode( $html_id, $name, $value, $show_icons );
                break;
        }

        // Description below
        if ( $this->get_description_placement() === 'below' ) {
            $field .= $this->render_description();
        }

        // Error container
        $field .= '<div id="' . esc_attr( $html_id ) . '-error" class="baf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $field .= $this->render_errors();
        }
        $field .= '</div>';

        $field .= '</div>';

        return $field;
    }

    /**
     * Render single network mode
     *
     * @param string $html_id    Field ID.
     * @param string $name       Field name.
     * @param mixed  $value      Current value.
     * @param bool   $show_icons Show icons.
     * @return string HTML.
     */
    protected function render_single_mode( $html_id, $name, $value, $show_icons ) {
        $network = $this->config['network'] ?? 'linkedin';
        $network_config = $this->networks[ $network ] ?? $this->networks['custom'];

        $html = '<div class="baf-social-input-group">';
        
        if ( $show_icons ) {
            $html .= '<span class="baf-social-icon">';
            $html .= '<span class="dashicons dashicons-' . esc_attr( $network_config['icon'] ) . '" aria-hidden="true"></span>';
            $html .= '</span>';
        }

        $html .= '<input type="url" id="' . esc_attr( $html_id ) . '" name="' . esc_attr( $name ) . '" ';
        $html .= 'value="' . esc_attr( $value ) . '" ';
        $html .= 'class="baf-social-input" ';
        $html .= 'placeholder="' . esc_attr( $network_config['placeholder'] ) . '" ';
        $html .= 'data-network="' . esc_attr( $network ) . '">';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render specific networks mode
     *
     * @param string $html_id    Field ID.
     * @param string $name       Field name.
     * @param mixed  $value      Current value.
     * @param bool   $show_icons Show icons.
     * @return string HTML.
     */
    protected function render_specific_mode( $html_id, $name, $value, $show_icons ) {
        $networks = $this->config['networks'] ?? array( 'linkedin', 'twitter' );
        
        if ( ! is_array( $value ) ) {
            $value = array();
        }

        $html = '<div class="baf-social-list">';

        foreach ( $networks as $network ) {
            if ( ! isset( $this->networks[ $network ] ) ) {
                continue;
            }

            $network_config = $this->networks[ $network ];
            $network_value = $value[ $network ] ?? '';
            $input_id = $html_id . '-' . $network;

            $html .= '<div class="baf-social-row">';
            
            $html .= '<label for="' . esc_attr( $input_id ) . '" class="baf-social-label">';
            if ( $show_icons ) {
                $html .= '<span class="dashicons dashicons-' . esc_attr( $network_config['icon'] ) . '" aria-hidden="true"></span>';
            }
            $html .= esc_html( $network_config['label'] );
            $html .= '</label>';

            $html .= '<input type="url" id="' . esc_attr( $input_id ) . '" ';
            $html .= 'name="' . esc_attr( $name ) . '[' . esc_attr( $network ) . ']" ';
            $html .= 'value="' . esc_attr( $network_value ) . '" ';
            $html .= 'class="baf-social-input" ';
            $html .= 'placeholder="' . esc_attr( $network_config['placeholder'] ) . '" ';
            $html .= 'data-network="' . esc_attr( $network ) . '">';

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render multiple networks mode
     *
     * @param string $html_id    Field ID.
     * @param string $name       Field name.
     * @param mixed  $value      Current value.
     * @param bool   $show_icons Show icons.
     * @return string HTML.
     */
    protected function render_multiple_mode( $html_id, $name, $value, $show_icons ) {
        $max_profiles = (int) ( $this->config['max_profiles'] ?? 5 );
        
        if ( ! is_array( $value ) ) {
            $value = array();
        }

        $html = '<div class="baf-social-multiple" data-max="' . esc_attr( $max_profiles ) . '">';

        // Existing entries
        $html .= '<div class="baf-social-entries" id="' . esc_attr( $html_id ) . '-entries">';
        
        $entry_index = 0;
        foreach ( $value as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }
            $html .= $this->render_multiple_entry( $html_id, $name, $entry, $entry_index, $show_icons );
            $entry_index++;
        }

        $html .= '</div>';

        // Add button
        $count = count( $value );
        $add_disabled = $count >= $max_profiles ? ' disabled' : '';
        
        $html .= '<button type="button" class="baf-social-add-btn"' . $add_disabled . '>';
        $html .= '<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>';
        $html .= esc_html__( 'Add Social Profile', 'bits-accessible-forms' );
        $html .= '</button>';

        // Template for new entries (hidden)
        $html .= '<template class="baf-social-template">';
        $html .= $this->render_multiple_entry( $html_id, $name, array(), '{{index}}', $show_icons );
        $html .= '</template>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a multiple mode entry
     *
     * @param string $html_id    Field ID.
     * @param string $name       Field name.
     * @param array  $entry      Entry data.
     * @param mixed  $index      Entry index.
     * @param bool   $show_icons Show icons.
     * @return string HTML.
     */
    protected function render_multiple_entry( $html_id, $name, $entry, $index, $show_icons ) {
        $network = $entry['network'] ?? '';
        $url = $entry['url'] ?? '';

        $html = '<div class="baf-social-entry" data-index="' . esc_attr( $index ) . '">';

        // Network selector
        $html .= '<div class="baf-social-network-select">';
        $html .= '<label for="' . esc_attr( $html_id ) . '-' . esc_attr( $index ) . '-network" class="screen-reader-text">';
        $html .= esc_html__( 'Select network', 'bits-accessible-forms' );
        $html .= '</label>';
        $html .= '<select id="' . esc_attr( $html_id ) . '-' . esc_attr( $index ) . '-network" ';
        $html .= 'name="' . esc_attr( $name ) . '[' . esc_attr( $index ) . '][network]" class="baf-social-network-dropdown">';
        $html .= '<option value="">' . esc_html__( 'Select...', 'bits-accessible-forms' ) . '</option>';
        
        foreach ( $this->networks as $key => $config ) {
            $selected = $key === $network ? ' selected' : '';
            $html .= '<option value="' . esc_attr( $key ) . '"' . $selected . '>' . esc_html( $config['label'] ) . '</option>';
        }
        
        $html .= '</select>';
        $html .= '</div>';

        // URL input
        $html .= '<div class="baf-social-url-input">';
        $html .= '<input type="url" id="' . esc_attr( $html_id ) . '-' . esc_attr( $index ) . '-url" ';
        $html .= 'name="' . esc_attr( $name ) . '[' . esc_attr( $index ) . '][url]" ';
        $html .= 'value="' . esc_attr( $url ) . '" ';
        $html .= 'class="baf-social-input" ';
        $html .= 'placeholder="' . esc_attr__( 'Profile URL or username', 'bits-accessible-forms' ) . '">';
        $html .= '</div>';

        // Remove button
        $html .= '<button type="button" class="baf-social-remove-btn" aria-label="' . esc_attr__( 'Remove this profile', 'bits-accessible-forms' ) . '">';
        $html .= '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
        $html .= '</button>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Get network options for settings
     *
     * @return array Network options.
     */
    protected function get_network_options() {
        $options = array();
        foreach ( $this->networks as $key => $config ) {
            $options[ $key ] = $config['label'];
        }
        return $options;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return mixed Sanitized value.
     */
    public function sanitize( $value ) {
        $mode = $this->config['mode'] ?? 'single';
        $normalize = $this->config['normalize_url'] ?? true;

        if ( $mode === 'single' ) {
            return $this->sanitize_url( $value, $this->config['network'] ?? 'custom', $normalize );
        }

        if ( ! is_array( $value ) ) {
            return array();
        }

        $sanitized = array();

        if ( $mode === 'specific' ) {
            foreach ( $value as $network => $url ) {
                if ( isset( $this->networks[ $network ] ) && ! empty( $url ) ) {
                    $sanitized[ $network ] = $this->sanitize_url( $url, $network, $normalize );
                }
            }
        } else {
            // Multiple mode
            foreach ( $value as $entry ) {
                if ( ! is_array( $entry ) || empty( $entry['network'] ) || empty( $entry['url'] ) ) {
                    continue;
                }
                $sanitized[] = array(
                    'network' => sanitize_key( $entry['network'] ),
                    'url'     => $this->sanitize_url( $entry['url'], $entry['network'], $normalize ),
                );
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize a single URL
     *
     * @param string $url       URL or username.
     * @param string $network   Network key.
     * @param bool   $normalize Whether to normalize.
     * @return string Sanitized URL.
     */
    protected function sanitize_url( $url, $network, $normalize ) {
        $url = trim( $url );
        
        if ( empty( $url ) ) {
            return '';
        }

        // Check if it's already a URL
        if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return esc_url_raw( $url );
        }

        // If username allowed and we should normalize
        if ( $normalize && isset( $this->networks[ $network ] ) ) {
            $config = $this->networks[ $network ];
            
            // Remove @ prefix if present
            $username = ltrim( $url, '@' );
            
            // Build full URL
            if ( ! empty( $config['prefix'] ) ) {
                return esc_url_raw( $config['prefix'] . $username );
            }
        }

        // Return as-is if URL validation allowed usernames
        if ( $this->config['allow_username'] ?? true ) {
            return sanitize_text_field( $url );
        }

        return '';
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $mode = $this->config['mode'] ?? 'single';
        $validate_url = $this->config['validate_url'] ?? true;

        if ( ! $validate_url ) {
            return;
        }

        if ( $mode === 'single' ) {
            if ( ! empty( $value ) && ! $this->is_valid_social_url( $value, $this->config['network'] ?? 'custom' ) ) {
                $this->add_error( __( 'Please enter a valid profile URL.', 'bits-accessible-forms' ) );
            }
            return;
        }

        if ( ! is_array( $value ) ) {
            return;
        }

        foreach ( $value as $key => $entry ) {
            if ( $mode === 'specific' ) {
                $network = $key;
                $url = $entry;
            } else {
                $network = $entry['network'] ?? 'custom';
                $url = $entry['url'] ?? '';
            }

            if ( ! empty( $url ) && ! $this->is_valid_social_url( $url, $network ) ) {
                $network_label = $this->networks[ $network ]['label'] ?? $network;
                $this->add_error( sprintf(
                    /* translators: %s: network name */
                    __( 'Please enter a valid %s profile URL.', 'bits-accessible-forms' ),
                    $network_label
                ) );
            }
        }
    }

    /**
     * Check if URL is valid for network
     *
     * @param string $url     URL to check.
     * @param string $network Network key.
     * @return bool True if valid.
     */
    protected function is_valid_social_url( $url, $network ) {
        // Allow usernames if configured
        if ( $this->config['allow_username'] ?? true ) {
            if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
                return true; // Accept non-URL values as usernames
            }
        }

        // Validate URL format
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        // Check network pattern
        if ( isset( $this->networks[ $network ] ) ) {
            $pattern = $this->networks[ $network ]['pattern'];
            if ( $pattern !== '.*' ) {
                return (bool) preg_match( '/' . $pattern . '/i', $url );
            }
        }

        return true;
    }

    /**
     * Get display value
     *
     * @param mixed $value Field value.
     * @return string Display value.
     */
    public function get_display_value( $value ) {
        $mode = $this->config['mode'] ?? 'single';

        if ( $mode === 'single' ) {
            return is_string( $value ) ? $value : '';
        }

        if ( ! is_array( $value ) || empty( $value ) ) {
            return '';
        }

        $parts = array();

        if ( $mode === 'specific' ) {
            foreach ( $value as $network => $url ) {
                if ( ! empty( $url ) ) {
                    $label = $this->networks[ $network ]['label'] ?? $network;
                    $parts[] = $label . ': ' . $url;
                }
            }
        } else {
            foreach ( $value as $entry ) {
                if ( ! empty( $entry['url'] ) ) {
                    $network = $entry['network'] ?? 'custom';
                    $label = $this->networks[ $network ]['label'] ?? $network;
                    $parts[] = $label . ': ' . $entry['url'];
                }
            }
        }

        return implode( "\n", $parts );
    }
}
