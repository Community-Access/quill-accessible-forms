<?php
/**
 * Structure Mode Class
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Structure_Mode class
 * 
 * Keyboard-first tree view for form organization.
 */
class QAF_Structure_Mode {

    /**
     * Render structure mode tree view
     *
     * The tree is drawn from the form model, so sections, columns and
     * repeaters show their questions inside them, and every condition is
     * read out as a sentence ("Shown when Type is Business").
     *
     * @param array      $form_config Form configuration (the builder's shape).
     * @param int        $form_id     Form ID.
     * @param array|null $model       The form model; read from $form_config when not given.
     * @return string Structure mode HTML.
     */
    public function render( $form_config, $form_id = 0, $model = null ) {
        if ( ! is_array( $model ) ) {
            $model = QAF_Form_Model_Converter::from_legacy( array( 'config' => is_array( $form_config ) ? $form_config : array() ) )['model'];
        }
        $pages = QAF_BFL_Service::outline( $model );

        ob_start();
        ?>
        <div class="qaf-structure-mode" 
             role="application" 
             aria-label="<?php esc_attr_e( 'Form structure tree view', 'quill-accessible-forms' ); ?>"
             data-qaf-focus-container="true"
             data-qaf-roving-tabindex="true">
            
            <div class="qaf-structure-header">
                <h3 id="qaf-structure-title"><?php esc_html_e( 'Form Structure', 'quill-accessible-forms' ); ?></h3>
                <div class="qaf-structure-actions">
                    <button type="button" class="qaf-btn qaf-btn-secondary" id="qaf-expand-all">
                        <?php esc_html_e( 'Expand All', 'quill-accessible-forms' ); ?>
                    </button>
                    <button type="button" class="qaf-btn qaf-btn-secondary" id="qaf-collapse-all">
                        <?php esc_html_e( 'Collapse All', 'quill-accessible-forms' ); ?>
                    </button>
                </div>
            </div>
            
            <div class="qaf-structure-instructions" id="qaf-structure-instructions">
                <p><?php esc_html_e( 'Use arrow keys to navigate. Press Enter to edit, Space to select, Delete to remove.', 'quill-accessible-forms' ); ?></p>
            </div>
            
            <ul class="qaf-structure-tree" 
                role="tree" 
                aria-labelledby="qaf-structure-title"
                aria-describedby="qaf-structure-instructions">
                
                <?php if ( count( $pages ) <= 1 ) : ?>
                    <?php echo $this->render_fields_tree( $pages[0]['items'] ?? array(), 1 ); ?>
                <?php else : ?>
                    <?php echo $this->render_pages_tree( $pages ); ?>
                <?php endif; ?>
                
            </ul>
            
            <div class="qaf-structure-keyboard-help">
                <button type="button" class="qaf-keyboard-help-toggle" aria-expanded="false" aria-controls="qaf-keyboard-shortcuts">
                    <span aria-hidden="true">⌨️</span>
                    <?php esc_html_e( 'Keyboard Shortcuts', 'quill-accessible-forms' ); ?>
                </button>
                <div id="qaf-keyboard-shortcuts" class="qaf-keyboard-shortcuts" hidden>
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
     * @param array $pages Pages from QAF_BFL_Service::outline().
     * @return string HTML.
     */
    private function render_pages_tree( $pages ) {
        $html = '';

        foreach ( $pages as $page_index => $page ) {
            $page_id = $page['id'] !== '' ? $page['id'] : 'page-' . $page_index;
            $page_title = $page['title'] !== '' ? $page['title'] : sprintf( __( 'Page %d', 'quill-accessible-forms' ), $page_index + 1 );

            $html .= sprintf(
                '<li class="qaf-tree-item qaf-tree-page" role="treeitem" aria-expanded="true" data-item-type="page" data-item-id="%s" tabindex="%s">
                    <div class="qaf-tree-item-content">
                        <span class="qaf-tree-expand" aria-hidden="true">▼</span>
                        <span class="qaf-tree-icon" aria-hidden="true">📄</span>
                        <span class="qaf-tree-label">%s</span>
                        %s
                        %s
                    </div>
                    <ul role="group">%s</ul>
                </li>',
                esc_attr( $page_id ),
                $page_index === 0 ? '0' : '-1',
                esc_html( $page_title ),
                $this->render_condition( $page['when'] ),
                $this->render_item_actions( $page_title, 'page' ),
                $this->render_fields_tree( $page['items'], 2 )
            );
        }

        return $html;
    }

    /**
     * Render fields tree
     *
     * @param array $fields Items from QAF_BFL_Service::outline().
     * @param int   $level  Nesting level.
     * @return string HTML.
     */
    private function render_fields_tree( $fields, $level = 1 ) {
        $html = '';
        $total = count( $fields );

        foreach ( $fields as $index => $field ) {
            $field_id = $field['id'] ?? '';
            $field_label = ( $field['label'] ?? '' ) !== '' ? $field['label'] : __( 'Untitled Field', 'quill-accessible-forms' );
            $field_type = $field['type'] ?? 'text';
            $is_required = ! empty( $field['required'] );
            $position = $index + 1;

            // Check if this is a container field (section, columns, repeater)
            $has_children = ! empty( $field['children'] );

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

            $aria_generator = new QAF_ARIA_Generator();
            $move_attrs = $aria_generator->generate_for_move_buttons( $field_label, $position, $total );

            $html .= sprintf(
                '<li class="qaf-tree-item qaf-tree-field" 
                    role="treeitem" 
                    %s
                    data-item-type="field" 
                    data-item-id="%s"
                    data-field-type="%s"
                    data-position="%d"
                    tabindex="-1">
                    <div class="qaf-tree-item-content">
                        %s
                        <span class="qaf-tree-icon" aria-hidden="true">%s</span>
                        <span class="qaf-tree-label">%s</span>
                        <span class="qaf-tree-type">(%s)</span>
                        %s
                        %s
                        %s
                    </div>
                    %s
                </li>',
                $has_children ? 'aria-expanded="true"' : '',
                esc_attr( $field_id ),
                esc_attr( $field_type ),
                $position,
                $has_children ? '<span class="qaf-tree-expand" aria-hidden="true">▼</span>' : '<span class="qaf-tree-spacer"></span>',
                esc_html( $icon ),
                esc_html( $field_label ),
                esc_html( $field_type ),
                $is_required ? '<span class="qaf-tree-required" aria-label="' . esc_attr__( 'Required', 'quill-accessible-forms' ) . '">[required]</span>' : '',
                $this->render_condition( $field['when'] ?? '' ),
                $this->render_item_actions( $field_label, 'field', $position, $total ),
                $has_children ? '<ul role="group">' . $this->render_fields_tree( $field['children'], $level + 1 ) . '</ul>' : ''
            );
        }

        return $html;
    }

    /**
     * The sentence of an item's condition, read with the item.
     *
     * @param string $sentence Sentence from the outline, or ''.
     * @return string HTML.
     */
    private function render_condition( $sentence ) {
        if ( $sentence === '' ) {
            return '';
        }
        /* translators: %s: a condition as a sentence, such as "Type is Business". */
        return '<span class="qaf-tree-when">' . esc_html( sprintf( __( 'Shown when %s', 'quill-accessible-forms' ), $sentence ) ) . '</span>';
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
        $aria = new QAF_ARIA_Generator();
        $move_attrs = $aria->generate_for_move_buttons( $label, $position, $total );

        ob_start();
        ?>
        <span class="qaf-tree-actions" role="toolbar" aria-label="<?php echo esc_attr( sprintf( __( 'Actions for %s', 'quill-accessible-forms' ), $label ) ); ?>">
            <button type="button" 
                    class="qaf-tree-action qaf-tree-move-up" 
                    data-action="move-up"
                    aria-label="<?php echo esc_attr( $move_attrs['move_up']['aria-label'] ); ?>"
                    <?php echo $move_attrs['move_up']['disabled'] ? 'disabled' : ''; ?>>
                <span aria-hidden="true">↑</span>
            </button>
            <button type="button" 
                    class="qaf-tree-action qaf-tree-move-down" 
                    data-action="move-down"
                    aria-label="<?php echo esc_attr( $move_attrs['move_down']['aria-label'] ); ?>"
                    <?php echo $move_attrs['move_down']['disabled'] ? 'disabled' : ''; ?>>
                <span aria-hidden="true">↓</span>
            </button>
            <button type="button" 
                    class="qaf-tree-action qaf-tree-edit" 
                    data-action="edit"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Edit %s', 'quill-accessible-forms' ), $label ) ); ?>">
                <span aria-hidden="true">✏️</span>
            </button>
            <?php if ( 'field' === $type ) : ?>
            <button type="button" 
                    class="qaf-tree-action qaf-tree-convert" 
                    data-action="convert"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Convert %s to different type', 'quill-accessible-forms' ), $label ) ); ?>"
                    aria-haspopup="dialog">
                <span aria-hidden="true">🔄</span>
            </button>
            <?php endif; ?>
            <button type="button" 
                    class="qaf-tree-action qaf-tree-duplicate" 
                    data-action="duplicate"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Duplicate %s', 'quill-accessible-forms' ), $label ) ); ?>">
                <span aria-hidden="true">📋</span>
            </button>
            <button type="button" 
                    class="qaf-tree-action qaf-tree-delete" 
                    data-action="delete"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Delete %s', 'quill-accessible-forms' ), $label ) ); ?>">
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
            array( 'keys' => '↑ / ↓', 'action' => __( 'Navigate items', 'quill-accessible-forms' ) ),
            array( 'keys' => '← / →', 'action' => __( 'Collapse / Expand', 'quill-accessible-forms' ) ),
            array( 'keys' => 'Enter', 'action' => __( 'Edit item', 'quill-accessible-forms' ) ),
            array( 'keys' => 'Space', 'action' => __( 'Select item', 'quill-accessible-forms' ) ),
            array( 'keys' => 'Ctrl+↑', 'action' => __( 'Move item up', 'quill-accessible-forms' ) ),
            array( 'keys' => 'Ctrl+↓', 'action' => __( 'Move item down', 'quill-accessible-forms' ) ),
            array( 'keys' => 'Ctrl+C', 'action' => __( 'Copy item', 'quill-accessible-forms' ) ),
            array( 'keys' => 'Ctrl+V', 'action' => __( 'Paste item', 'quill-accessible-forms' ) ),
            array( 'keys' => 'Ctrl+D', 'action' => __( 'Duplicate item', 'quill-accessible-forms' ) ),
            array( 'keys' => 'Delete', 'action' => __( 'Remove item', 'quill-accessible-forms' ) ),
            array( 'keys' => 'F2', 'action' => __( 'Rename item', 'quill-accessible-forms' ) ),
            array( 'keys' => 'Home', 'action' => __( 'Jump to first item', 'quill-accessible-forms' ) ),
            array( 'keys' => 'End', 'action' => __( 'Jump to last item', 'quill-accessible-forms' ) ),
        );

        ob_start();
        ?>
        <dl class="qaf-shortcut-list">
            <?php foreach ( $shortcuts as $shortcut ) : ?>
            <div class="qaf-shortcut-item">
                <dt class="qaf-shortcut-keys"><kbd><?php echo esc_html( $shortcut['keys'] ); ?></kbd></dt>
                <dd class="qaf-shortcut-action"><?php echo esc_html( $shortcut['action'] ); ?></dd>
            </div>
            <?php endforeach; ?>
        </dl>
        <?php
        return ob_get_clean();
    }

    /**
     * Render field type conversion modal
     *
     * @return string HTML.
     */
    public function render_conversion_modal() {
        $converter = new QAF_Field_Type_Converter();
        $field_types = $converter->get_all_field_types();

        ob_start();
        ?>
        <div id="qaf-conversion-modal" 
             class="qaf-modal" 
             role="dialog" 
             aria-modal="true"
             aria-labelledby="qaf-conversion-modal-title"
             hidden>
            <div class="qaf-modal-overlay" data-close-modal></div>
            <div class="qaf-modal-content">
                <div class="qaf-modal-header">
                    <h2 id="qaf-conversion-modal-title">
                        <?php esc_html_e( 'Convert Field Type', 'quill-accessible-forms' ); ?>
                    </h2>
                    <button type="button" class="qaf-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close modal', 'quill-accessible-forms' ); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="qaf-modal-body">
                    <div class="qaf-conversion-info" aria-live="polite">
                        <p class="qaf-conversion-current">
                            <?php esc_html_e( 'Current type:', 'quill-accessible-forms' ); ?>
                            <strong id="qaf-conversion-current-type"></strong>
                        </p>
                    </div>

                    <div class="qaf-conversion-form">
                        <fieldset>
                            <legend><?php esc_html_e( 'Select target field type', 'quill-accessible-forms' ); ?></legend>
                            
                            <div class="qaf-field-type-grid" role="radiogroup" aria-describedby="qaf-conversion-compatibility">
                                <?php foreach ( $field_types as $group_name => $types ) : ?>
                                    <div class="qaf-type-group">
                                        <h3 class="qaf-type-group-title"><?php echo esc_html( $group_name ); ?></h3>
                                        <div class="qaf-type-options">
                                            <?php foreach ( $types as $type_id => $type_info ) : ?>
                                                <label class="qaf-type-option" data-type="<?php echo esc_attr( $type_id ); ?>">
                                                    <input type="radio" 
                                                           name="qaf_target_type" 
                                                           value="<?php echo esc_attr( $type_id ); ?>"
                                                           class="qaf-sr-only">
                                                    <span class="qaf-type-icon" aria-hidden="true"><?php echo esc_html( $type_info['icon'] ?? '📝' ); ?></span>
                                                    <span class="qaf-type-name"><?php echo esc_html( $type_info['label'] ); ?></span>
                                                    <span class="qaf-type-compat" aria-label="<?php esc_attr_e( 'Compatibility status', 'quill-accessible-forms' ); ?>"></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <div id="qaf-conversion-compatibility" class="qaf-conversion-compat-info" hidden>
                            <h4><?php esc_html_e( 'Compatibility Report', 'quill-accessible-forms' ); ?></h4>
                            <div class="qaf-compat-details">
                                <div class="qaf-compat-status"></div>
                                <div class="qaf-compat-warnings" role="alert"></div>
                            </div>
                        </div>

                        <div id="qaf-conversion-preview" class="qaf-conversion-preview" hidden>
                            <h4><?php esc_html_e( 'Conversion Preview', 'quill-accessible-forms' ); ?></h4>
                            <div class="qaf-preview-changes">
                                <div class="qaf-preview-preserved">
                                    <h5><?php esc_html_e( 'Properties Preserved', 'quill-accessible-forms' ); ?></h5>
                                    <ul id="qaf-preview-preserved-list"></ul>
                                </div>
                                <div class="qaf-preview-lost">
                                    <h5><?php esc_html_e( 'Properties Removed', 'quill-accessible-forms' ); ?></h5>
                                    <ul id="qaf-preview-lost-list"></ul>
                                </div>
                                <div class="qaf-preview-added">
                                    <h5><?php esc_html_e( 'New Defaults Added', 'quill-accessible-forms' ); ?></h5>
                                    <ul id="qaf-preview-added-list"></ul>
                                </div>
                            </div>
                        </div>

                        <div id="qaf-conditional-warnings" class="qaf-conditional-warnings" hidden role="alert">
                            <h4><?php esc_html_e( 'Conditional Logic Impact', 'quill-accessible-forms' ); ?></h4>
                            <div class="qaf-conditional-details">
                                <p class="qaf-conditional-message"></p>
                                <ul class="qaf-conditional-affected"></ul>
                            </div>
                            <div class="qaf-conditional-options">
                                <label>
                                    <input type="checkbox" name="qaf_auto_repair" value="1">
                                    <?php esc_html_e( 'Automatically repair compatible conditions', 'quill-accessible-forms' ); ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="qaf-modal-footer">
                    <button type="button" class="qaf-btn qaf-btn-secondary" data-close-modal>
                        <?php esc_html_e( 'Cancel', 'quill-accessible-forms' ); ?>
                    </button>
                    <button type="button" class="qaf-btn qaf-btn-primary" id="qaf-conversion-confirm" disabled>
                        <?php esc_html_e( 'Convert Field', 'quill-accessible-forms' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get field types for JavaScript
     *
     * @return array Field types data.
     */
    public function get_field_types_data() {
        $converter = new QAF_Field_Type_Converter();
        return $converter->get_all_field_types();
    }

    /**
     * Get compatibility matrix for JavaScript
     *
     * @return array Compatibility data.
     */
    public function get_compatibility_data() {
        $converter = new QAF_Field_Type_Converter();
        return $converter->get_compatibility_matrix();
    }
}
