<?php
/**
 * List View
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_View_List class
 * 
 * Renders entries in an accessible list format.
 */
class QAF_View_List extends QAF_View_Renderer {

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

        $list_class = 'qaf-entries-list';
        if ( $this->config['compact'] ) {
            $list_class .= ' qaf-list-compact';
        }

        $output = sprintf(
            '<ul class="%s" role="list" aria-label="%s">',
            esc_attr( $list_class ),
            esc_attr__( 'Form entries', 'quill-accessible-forms' )
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
     * @param QAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_list_item( $entry ) {
        $item_class = 'qaf-list-item';
        if ( ! $entry->is_read ) {
            $item_class .= ' qaf-unread';
        }
        if ( $entry->is_starred ) {
            $item_class .= ' qaf-starred';
        }

        $output = sprintf(
            '<li class="%s" data-entry-id="%d">',
            esc_attr( $item_class ),
            (int) $entry->id
        );

        $output .= '<article class="qaf-list-article">';

        // Avatar (if enabled)
        if ( $this->config['show_avatar'] ) {
            $output .= $this->render_avatar( $entry );
        }

        // Content
        $output .= '<div class="qaf-list-content">';

        // Primary content
        $output .= '<div class="qaf-list-primary">';
        $output .= $this->render_primary_content( $entry );
        $output .= '</div>';

        // Secondary content
        if ( $this->config['secondary_field'] ) {
            $output .= '<div class="qaf-list-secondary">';
            $output .= $this->render_secondary_content( $entry );
            $output .= '</div>';
        }

        // Detail/excerpt
        if ( $this->config['detail_field'] ) {
            $output .= '<div class="qaf-list-detail">';
            $output .= $this->render_detail_content( $entry );
            $output .= '</div>';
        }

        // Meta
        if ( $this->config['show_meta'] ) {
            $output .= $this->render_meta( $entry );
        }

        $output .= '</div>'; // .qaf-list-content

        // Actions
        $output .= $this->render_actions( $entry );

        $output .= '</article>';
        $output .= '</li>';

        return $output;
    }

    /**
     * Render avatar
     *
     * @param QAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_avatar( $entry ) {
        $avatar_html = '';
        
        if ( $entry->user_id ) {
            $avatar_html = get_avatar( $entry->user_id, 48, '', '', array( 'class' => 'qaf-avatar' ) );
        } else {
            // Check for email field
            $email = $entry->get_field_value( 'email' );
            if ( $email ) {
                $avatar_html = get_avatar( $email, 48, '', '', array( 'class' => 'qaf-avatar' ) );
            }
        }

        if ( empty( $avatar_html ) ) {
            $avatar_html = '<span class="qaf-avatar qaf-avatar-placeholder" aria-hidden="true"></span>';
        }

        return '<div class="qaf-list-avatar">' . $avatar_html . '</div>';
    }

    /**
     * Render primary content
     *
     * @param QAF_Entry $entry Entry object.
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
            $primary = sprintf( __( 'Entry #%d', 'quill-accessible-forms' ), $entry->id );
        }

        return sprintf(
            '<h3 class="qaf-list-title"><a href="#" class="qaf-entry-link" data-entry-id="%d">%s</a></h3>',
            (int) $entry->id,
            esc_html( $primary )
        );
    }

    /**
     * Render secondary content
     *
     * @param QAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_secondary_content( $entry ) {
        $secondary = $this->get_display_value( $entry, $this->config['secondary_field'] );
        return esc_html( $secondary );
    }

    /**
     * Render detail content
     *
     * @param QAF_Entry $entry Entry object.
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
     * @param QAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_meta( $entry ) {
        $output = '<div class="qaf-list-meta">';

        // Date
        $output .= sprintf(
            '<time datetime="%s" class="qaf-list-date">%s</time>',
            esc_attr( $entry->created_at ),
            esc_html( human_time_diff( strtotime( $entry->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'quill-accessible-forms' ) )
        );

        // Status badge
        if ( $entry->status !== 'active' ) {
            $output .= sprintf(
                '<span class="qaf-status-badge qaf-status-%s">%s</span>',
                esc_attr( $entry->status ),
                esc_html( ucfirst( $entry->status ) )
            );
        }

        // Star indicator
        if ( $entry->is_starred ) {
            $output .= '<span class="qaf-star-indicator" aria-label="' . esc_attr__( 'Starred', 'quill-accessible-forms' ) . '">★</span>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render action buttons
     *
     * @param QAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_actions( $entry ) {
        $output = '<div class="qaf-list-actions">';

        // Star toggle
        $star_label = $entry->is_starred ? __( 'Unstar', 'quill-accessible-forms' ) : __( 'Star', 'quill-accessible-forms' );
        $output .= sprintf(
            '<button type="button" class="qaf-action-star" data-entry-id="%d" aria-pressed="%s" aria-label="%s">
                <span class="dashicons %s" aria-hidden="true"></span>
            </button>',
            (int) $entry->id,
            $entry->is_starred ? 'true' : 'false',
            esc_attr( $star_label ),
            $entry->is_starred ? 'dashicons-star-filled' : 'dashicons-star-empty'
        );

        // Actions menu
        $output .= sprintf(
            '<button type="button" class="qaf-action-menu-toggle" aria-expanded="false" aria-haspopup="true" aria-label="%s">
                <span class="dashicons dashicons-ellipsis" aria-hidden="true"></span>
            </button>',
            esc_attr__( 'Entry actions', 'quill-accessible-forms' )
        );

        $output .= '<div class="qaf-action-menu" hidden>';
        $output .= sprintf(
            '<button type="button" class="qaf-action-view" data-entry-id="%d">%s</button>',
            (int) $entry->id,
            esc_html__( 'View', 'quill-accessible-forms' )
        );
        $output .= sprintf(
            '<button type="button" class="qaf-action-edit" data-entry-id="%d">%s</button>',
            (int) $entry->id,
            esc_html__( 'Edit', 'quill-accessible-forms' )
        );
        $output .= sprintf(
            '<button type="button" class="qaf-action-delete" data-entry-id="%d">%s</button>',
            (int) $entry->id,
            esc_html__( 'Delete', 'quill-accessible-forms' )
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
            '<div class="qaf-empty-state" role="status">
                <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                <p>%s</p>
            </div>',
            esc_html__( 'No entries found.', 'quill-accessible-forms' )
        );
    }
}
