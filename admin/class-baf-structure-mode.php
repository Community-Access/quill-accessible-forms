<?php
/**
 * Structure Mode Class
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Structure_Mode class
 * 
 * Keyboard-first tree view for form organization.
 */
class BAF_Structure_Mode {

    /**
     * Render structure mode tree view
     *
     * @param array $form_config Form configuration.
     * @param int   $form_id     Form ID.
     * @return string Structure mode HTML.
     */
    public function render( $form_config, $form_id = 0 ) {
        $fields = $form_config['fields'] ?? array();
        $pages = $form_config['pages'] ?? array();

        ob_start();
        ?>
        <div class="baf-structure-mode" 
             role="application" 
             aria-label="<?php esc_attr_e( 'Form structure tree view', 'bits-accessible-forms' ); ?>"
             data-baf-focus-container="true"
             data-baf-roving-tabindex="true">
            
            <div class="baf-structure-header">
                <h3 id="baf-structure-title"><?php esc_html_e( 'Form Structure', 'bits-accessible-forms' ); ?></h3>
                <div class="baf-structure-actions">
                    <button type="button" class="baf-btn baf-btn-secondary" id="baf-expand-all">
                        <?php esc_html_e( 'Expand All', 'bits-accessible-forms' ); ?>
                    </button>
                    <button type="button" class="baf-btn baf-btn-secondary" id="baf-collapse-all">
                        <?php esc_html_e( 'Collapse All', 'bits-accessible-forms' ); ?>
                    </button>
                </div>
            </div>
            
            <div class="baf-structure-instructions" id="baf-structure-instructions">
                <p><?php esc_html_e( 'Use arrow keys to navigate. Press Enter to edit, Space to select, Delete to remove.', 'bits-accessible-forms' ); ?></p>
            </div>
            
            <ul class="baf-structure-tree" 
                role="tree" 
                aria-labelledby="baf-structure-title"
                aria-describedby="baf-structure-instructions">
                
                <?php if ( empty( $pages ) || count( $pages ) <= 1 ) : ?>
                    <?php echo $this->render_fields_tree( $fields, 1 ); ?>
                <?php else : ?>
                    <?php echo $this->render_pages_tree( $pages, $fields ); ?>
                <?php endif; ?>
                
            </ul>
            
            <div class="baf-structure-keyboard-help">
                <button type="button" class="baf-keyboard-help-toggle" aria-expanded="false" aria-controls="baf-keyboard-shortcuts">
                    <span aria-hidden="true">⌨️</span>
                    <?php esc_html_e( 'Keyboard Shortcuts', 'bits-accessible-forms' ); ?>
                </button>
                <div id="baf-keyboard-shortcuts" class="baf-keyboard-shortcuts" hidden>
                    <?php echo $this->render_keyboard_shortcuts(); ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render pages tree
     *
     * @param array $pages  Pages configuration.
     * @param array $fields All fields.
     * @return string HTML.
     */
    private function render_pages_tree( $pages, $fields ) {
        $html = '';

        foreach ( $pages as $page_index => $page ) {
            $page_id = $page['id'] ?? 'page-' . $page_index;
            $page_title = $page['title'] ?? sprintf( __( 'Page %d', 'bits-accessible-forms' ), $page_index + 1 );
            $page_fields = array_filter( $fields, function( $field ) use ( $page_id ) {
                return ( $field['page'] ?? '' ) === $page_id;
            } );

            $html .= sprintf(
                '<li class="baf-tree-item baf-tree-page" role="treeitem" aria-expanded="true" data-item-type="page" data-item-id="%s" tabindex="%s">
                    <div class="baf-tree-item-content">
                        <span class="baf-tree-expand" aria-hidden="true">▼</span>
                        <span class="baf-tree-icon" aria-hidden="true">📄</span>
                        <span class="baf-tree-label">%s</span>
                        %s
                    </div>
                    <ul role="group">%s</ul>
                </li>',
                esc_attr( $page_id ),
                $page_index === 0 ? '0' : '-1',
                esc_html( $page_title ),
                $this->render_item_actions( $page_title, 'page' ),
                $this->render_fields_tree( array_values( $page_fields ), $page_index + 1 )
            );
        }

        return $html;
    }

    /**
     * Render fields tree
     *
     * @param array $fields Fields configuration.
     * @param int   $level  Nesting level.
     * @return string HTML.
     */
    private function render_fields_tree( $fields, $level = 1 ) {
        $html = '';
        $total = count( $fields );

        foreach ( $fields as $index => $field ) {
            $field_id = $field['id'] ?? '';
            $field_label = $field['label'] ?? __( 'Untitled Field', 'bits-accessible-forms' );
            $field_type = $field['type'] ?? 'text';
            $is_required = ! empty( $field['required'] );
            $position = $index + 1;

            // Check if this is a container field (section, columns, repeater)
            $is_container = in_array( $field_type, array( 'section', 'columns', 'repeater', 'fieldset' ), true );
            $has_children = $is_container && ! empty( $field['fields'] );

            $type_icons = array(
                'text'      => '📝',
                'textarea'  => '📄',
                'email'     => '📧',
                'number'    => '#️⃣',
                'phone'     => '📱',
                'select'    => '📋',
                'radio'     => '🔘',
                'checkbox'  => '☑️',
                'date'      => '📅',
                'time'      => '🕐',
                'file'      => '📎',
                'section'   => '📁',
                'columns'   => '▥',
                'repeater'  => '🔄',
                'name'      => '👤',
                'address'   => '🏠',
            );
            $icon = $type_icons[ $field_type ] ?? '📝';

            $aria_generator = new BAF_ARIA_Generator();
            $move_attrs = $aria_generator->generate_for_move_buttons( $field_label, $position, $total );

            $html .= sprintf(
                '<li class="baf-tree-item baf-tree-field" 
                    role="treeitem" 
                    %s
                    data-item-type="field" 
                    data-item-id="%s"
                    data-field-type="%s"
                    data-position="%d"
                    tabindex="-1">
                    <div class="baf-tree-item-content">
                        %s
                        <span class="baf-tree-icon" aria-hidden="true">%s</span>
                        <span class="baf-tree-label">%s</span>
                        <span class="baf-tree-type">(%s)</span>
                        %s
                        %s
                    </div>
                    %s
                </li>',
                $has_children ? 'aria-expanded="true"' : '',
                esc_attr( $field_id ),
                esc_attr( $field_type ),
                $position,
                $has_children ? '<span class="baf-tree-expand" aria-hidden="true">▼</span>' : '<span class="baf-tree-spacer"></span>',
                esc_html( $icon ),
                esc_html( $field_label ),
                esc_html( $field_type ),
                $is_required ? '<span class="baf-tree-required" aria-label="' . esc_attr__( 'Required', 'bits-accessible-forms' ) . '">[required]</span>' : '',
                $this->render_item_actions( $field_label, 'field', $position, $total ),
                $has_children ? '<ul role="group">' . $this->render_fields_tree( $field['fields'], $level + 1 ) . '</ul>' : ''
            );
        }

        return $html;
    }

    /**
     * Render item action buttons
     *
     * @param string $label    Item label.
     * @param string $type     Item type (page, section, field).
     * @param int    $position Current position.
     * @param int    $total    Total items.
     * @return string HTML.
     */
    private function render_item_actions( $label, $type = 'field', $position = 1, $total = 1 ) {
        $aria = new BAF_ARIA_Generator();
        $move_attrs = $aria->generate_for_move_buttons( $label, $position, $total );

        ob_start();
        ?>
        <span class="baf-tree-actions" role="toolbar" aria-label="<?php echo esc_attr( sprintf( __( 'Actions for %s', 'bits-accessible-forms' ), $label ) ); ?>">
            <button type="button" 
                    class="baf-tree-action baf-tree-move-up" 
                    data-action="move-up"
                    aria-label="<?php echo esc_attr( $move_attrs['move_up']['aria-label'] ); ?>"
                    <?php echo $move_attrs['move_up']['disabled'] ? 'disabled' : ''; ?>>
                <span aria-hidden="true">↑</span>
            </button>
            <button type="button" 
                    class="baf-tree-action baf-tree-move-down" 
                    data-action="move-down"
                    aria-label="<?php echo esc_attr( $move_attrs['move_down']['aria-label'] ); ?>"
                    <?php echo $move_attrs['move_down']['disabled'] ? 'disabled' : ''; ?>>
                <span aria-hidden="true">↓</span>
            </button>
            <button type="button" 
                    class="baf-tree-action baf-tree-edit" 
                    data-action="edit"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Edit %s', 'bits-accessible-forms' ), $label ) ); ?>">
                <span aria-hidden="true">✏️</span>
            </button>
            <button type="button" 
                    class="baf-tree-action baf-tree-duplicate" 
                    data-action="duplicate"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Duplicate %s', 'bits-accessible-forms' ), $label ) ); ?>">
                <span aria-hidden="true">📋</span>
            </button>
            <button type="button" 
                    class="baf-tree-action baf-tree-delete" 
                    data-action="delete"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Delete %s', 'bits-accessible-forms' ), $label ) ); ?>">
                <span aria-hidden="true">🗑️</span>
            </button>
        </span>
        <?php
        return ob_get_clean();
    }

    /**
     * Render keyboard shortcuts help
     *
     * @return string HTML.
     */
    private function render_keyboard_shortcuts() {
        $shortcuts = array(
            array( 'keys' => '↑ / ↓', 'action' => __( 'Navigate items', 'bits-accessible-forms' ) ),
            array( 'keys' => '← / →', 'action' => __( 'Collapse / Expand', 'bits-accessible-forms' ) ),
            array( 'keys' => 'Enter', 'action' => __( 'Edit item', 'bits-accessible-forms' ) ),
            array( 'keys' => 'Space', 'action' => __( 'Select item', 'bits-accessible-forms' ) ),
            array( 'keys' => 'Ctrl+↑', 'action' => __( 'Move item up', 'bits-accessible-forms' ) ),
            array( 'keys' => 'Ctrl+↓', 'action' => __( 'Move item down', 'bits-accessible-forms' ) ),
            array( 'keys' => 'Ctrl+C', 'action' => __( 'Copy item', 'bits-accessible-forms' ) ),
            array( 'keys' => 'Ctrl+V', 'action' => __( 'Paste item', 'bits-accessible-forms' ) ),
            array( 'keys' => 'Ctrl+D', 'action' => __( 'Duplicate item', 'bits-accessible-forms' ) ),
            array( 'keys' => 'Delete', 'action' => __( 'Remove item', 'bits-accessible-forms' ) ),
            array( 'keys' => 'F2', 'action' => __( 'Rename item', 'bits-accessible-forms' ) ),
            array( 'keys' => 'Home', 'action' => __( 'Jump to first item', 'bits-accessible-forms' ) ),
            array( 'keys' => 'End', 'action' => __( 'Jump to last item', 'bits-accessible-forms' ) ),
        );

        ob_start();
        ?>
        <dl class="baf-shortcut-list">
            <?php foreach ( $shortcuts as $shortcut ) : ?>
            <div class="baf-shortcut-item">
                <dt class="baf-shortcut-keys"><kbd><?php echo esc_html( $shortcut['keys'] ); ?></kbd></dt>
                <dd class="baf-shortcut-action"><?php echo esc_html( $shortcut['action'] ); ?></dd>
            </div>
            <?php endforeach; ?>
        </dl>
        <?php
        return ob_get_clean();
    }
}
