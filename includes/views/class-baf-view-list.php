<?php
/**
 * List View
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_View_List class
 * 
 * Renders entries in an accessible list format.
 */
class BAF_View_List extends BAF_View_Renderer {

    /**
     * Get view type
     *
     * @return string View type identifier.
     */
    protected function get_type() {
        return 'list';
    }

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    protected function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'primary_field'   => '',
            'secondary_field' => '',
            'detail_field'    => '',
            'show_meta'       => true,
            'show_avatar'     => false,
            'compact'         => false,
        ) );
    }

    /**
     * Render view content
     *
     * @return string HTML output.
     */
    protected function render_content() {
        if ( empty( $this->entries ) ) {
            return $this->render_empty_state();
        }

        $list_class = 'baf-entries-list';
        if ( $this->config['compact'] ) {
            $list_class .= ' baf-list-compact';
        }

        $output = sprintf(
            '<ul class="%s" role="list" aria-label="%s">',
            esc_attr( $list_class ),
            esc_attr__( 'Form entries', 'bits-accessible-forms' )
        );

        foreach ( $this->entries as $entry ) {
            $output .= $this->render_list_item( $entry );
        }

        $output .= '</ul>';

        return $output;
    }

    /**
     * Render single list item
     *
     * @param BAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_list_item( $entry ) {
        $item_class = 'baf-list-item';
        if ( ! $entry->is_read ) {
            $item_class .= ' baf-unread';
        }
        if ( $entry->is_starred ) {
            $item_class .= ' baf-starred';
        }

        $output = sprintf(
            '<li class="%s" data-entry-id="%d">',
            esc_attr( $item_class ),
            (int) $entry->id
        );

        $output .= '<article class="baf-list-article">';

        // Avatar (if enabled)
        if ( $this->config['show_avatar'] ) {
            $output .= $this->render_avatar( $entry );
        }

        // Content
        $output .= '<div class="baf-list-content">';

        // Primary content
        $output .= '<div class="baf-list-primary">';
        $output .= $this->render_primary_content( $entry );
        $output .= '</div>';

        // Secondary content
        if ( $this->config['secondary_field'] ) {
            $output .= '<div class="baf-list-secondary">';
            $output .= $this->render_secondary_content( $entry );
            $output .= '</div>';
        }

        // Detail/excerpt
        if ( $this->config['detail_field'] ) {
            $output .= '<div class="baf-list-detail">';
            $output .= $this->render_detail_content( $entry );
            $output .= '</div>';
        }

        // Meta
        if ( $this->config['show_meta'] ) {
            $output .= $this->render_meta( $entry );
        }

        $output .= '</div>'; // .baf-list-content

        // Actions
        $output .= $this->render_actions( $entry );

        $output .= '</article>';
        $output .= '</li>';

        return $output;
    }

    /**
     * Render avatar
     *
     * @param BAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_avatar( $entry ) {
        $avatar_html = '';
        
        if ( $entry->user_id ) {
            $avatar_html = get_avatar( $entry->user_id, 48, '', '', array( 'class' => 'baf-avatar' ) );
        } else {
            // Check for email field
            $email = $entry->get_field_value( 'email' );
            if ( $email ) {
                $avatar_html = get_avatar( $email, 48, '', '', array( 'class' => 'baf-avatar' ) );
            }
        }

        if ( empty( $avatar_html ) ) {
            $avatar_html = '<span class="baf-avatar baf-avatar-placeholder" aria-hidden="true"></span>';
        }

        return '<div class="baf-list-avatar">' . $avatar_html . '</div>';
    }

    /**
     * Render primary content
     *
     * @param BAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_primary_content( $entry ) {
        $primary = '';

        if ( $this->config['primary_field'] ) {
            $primary = $this->get_display_value( $entry, $this->config['primary_field'] );
        } else {
            // Auto-detect: use first text-like field or entry ID
            if ( $this->form ) {
                foreach ( $this->form->get_fields() as $field ) {
                    if ( in_array( $field['type'], array( 'text', 'name', 'email' ), true ) ) {
                        $primary = $this->get_display_value( $entry, $field['id'] );
                        if ( $primary ) {
                            break;
                        }
                    }
                }
            }
        }

        if ( empty( $primary ) ) {
            $primary = sprintf( __( 'Entry #%d', 'bits-accessible-forms' ), $entry->id );
        }

        return sprintf(
            '<h3 class="baf-list-title"><a href="#" class="baf-entry-link" data-entry-id="%d">%s</a></h3>',
            (int) $entry->id,
            esc_html( $primary )
        );
    }

    /**
     * Render secondary content
     *
     * @param BAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_secondary_content( $entry ) {
        $secondary = $this->get_display_value( $entry, $this->config['secondary_field'] );
        return esc_html( $secondary );
    }

    /**
     * Render detail content
     *
     * @param BAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_detail_content( $entry ) {
        $detail = $this->get_display_value( $entry, $this->config['detail_field'] );
        
        // Truncate if too long
        if ( strlen( $detail ) > 150 ) {
            $detail = wp_trim_words( $detail, 25, '...' );
        }

        return esc_html( $detail );
    }

    /**
     * Render meta information
     *
     * @param BAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_meta( $entry ) {
        $output = '<div class="baf-list-meta">';

        // Date
        $output .= sprintf(
            '<time datetime="%s" class="baf-list-date">%s</time>',
            esc_attr( $entry->created_at ),
            esc_html( human_time_diff( strtotime( $entry->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'bits-accessible-forms' ) )
        );

        // Status badge
        if ( $entry->status !== 'active' ) {
            $output .= sprintf(
                '<span class="baf-status-badge baf-status-%s">%s</span>',
                esc_attr( $entry->status ),
                esc_html( ucfirst( $entry->status ) )
            );
        }

        // Star indicator
        if ( $entry->is_starred ) {
            $output .= '<span class="baf-star-indicator" aria-label="' . esc_attr__( 'Starred', 'bits-accessible-forms' ) . '">★</span>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render action buttons
     *
     * @param BAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_actions( $entry ) {
        $output = '<div class="baf-list-actions">';

        // Star toggle
        $star_label = $entry->is_starred ? __( 'Unstar', 'bits-accessible-forms' ) : __( 'Star', 'bits-accessible-forms' );
        $output .= sprintf(
            '<button type="button" class="baf-action-star" data-entry-id="%d" aria-pressed="%s" aria-label="%s">
                <span class="dashicons %s" aria-hidden="true"></span>
            </button>',
            (int) $entry->id,
            $entry->is_starred ? 'true' : 'false',
            esc_attr( $star_label ),
            $entry->is_starred ? 'dashicons-star-filled' : 'dashicons-star-empty'
        );

        // Actions menu
        $output .= sprintf(
            '<button type="button" class="baf-action-menu-toggle" aria-expanded="false" aria-haspopup="true" aria-label="%s">
                <span class="dashicons dashicons-ellipsis" aria-hidden="true"></span>
            </button>',
            esc_attr__( 'Entry actions', 'bits-accessible-forms' )
        );

        $output .= '<div class="baf-action-menu" hidden>';
        $output .= sprintf(
            '<button type="button" class="baf-action-view" data-entry-id="%d">%s</button>',
            (int) $entry->id,
            esc_html__( 'View', 'bits-accessible-forms' )
        );
        $output .= sprintf(
            '<button type="button" class="baf-action-edit" data-entry-id="%d">%s</button>',
            (int) $entry->id,
            esc_html__( 'Edit', 'bits-accessible-forms' )
        );
        $output .= sprintf(
            '<button type="button" class="baf-action-delete" data-entry-id="%d">%s</button>',
            (int) $entry->id,
            esc_html__( 'Delete', 'bits-accessible-forms' )
        );
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * Render empty state
     *
     * @return string HTML output.
     */
    private function render_empty_state() {
        return sprintf(
            '<div class="baf-empty-state" role="status">
                <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                <p>%s</p>
            </div>',
            esc_html__( 'No entries found.', 'bits-accessible-forms' )
        );
    }
}
