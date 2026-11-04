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
            $model = BAF_Form_Model_Converter::from_legacy( array( 'config' => is_array( $form_config ) ? $form_config : array() ) )['model'];
        }
        $pages = BAF_BFL_Service::outline( $model );

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
                
                <?php if ( count( $pages ) <= 1 ) : ?>
                    <?php echo $this->render_fields_tree( $pages[0]['items'] ?? array(), 1 ); ?>
                <?php else : ?>
                    <?php echo $this->render_pages_tree( $pages ); ?>
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
     * @param array $pages Pages from BAF_BFL_Service::outline().
     * @return string HTML.
     */
    private function render_pages_tree( $pages ) {
        $html = '';

        foreach ( $pages as $page_index => $page ) {
            $page_id = $page['id'] !== '' ? $page['id'] : 'page-' . $page_index;
            $page_title = $page['title'] !== '' ? $page['title'] : sprintf( __( 'Page %d', 'bits-accessible-forms' ), $page_index + 1 );

            $html .= sprintf(
                '<li class="baf-tree-item baf-tree-page" role="treeitem" aria-expanded="true" data-item-type="page" data-item-id="%s" tabindex="%s">
                    <div class="baf-tree-item-content">
                        <span class="baf-tree-expand" aria-hidden="true">▼</span>
                        <span class="baf-tree-icon" aria-hidden="true">📄</span>
                        <span class="baf-tree-label">%s</span>
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
     * @param array $fields Items from BAF_BFL_Service::outline().
     * @param int   $level  Nesting level.
     * @return string HTML.
     */
    private function render_fields_tree( $fields, $level = 1 ) {
        $html = '';
        $total = count( $fields );

        foreach ( $fields as $index => $field ) {
            $field_id = $field['id'] ?? '';
            $field_label = ( $field['label'] ?? '' ) !== '' ? $field['label'] : __( 'Untitled Field', 'bits-accessible-forms' );
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
        return '<span class="baf-tree-when">' . esc_html( sprintf( __( 'Shown when %s', 'bits-accessible-forms' ), $sentence ) ) . '</span>';
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
            <?php if ( 'field' === $type ) : ?>
            <button type="button" 
                    class="baf-tree-action baf-tree-convert" 
                    data-action="convert"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Convert %s to different type', 'bits-accessible-forms' ), $label ) ); ?>"
                    aria-haspopup="dialog">
                <span aria-hidden="true">🔄</span>
            </button>
            <?php endif; ?>
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

    /**
     * Render field type conversion modal
     *
     * @return string HTML.
     */
    public function render_conversion_modal() {
        $converter = new BAF_Field_Type_Converter();
        $field_types = $converter->get_all_field_types();

        ob_start();
        ?>
        <div id="baf-conversion-modal" 
             class="baf-modal" 
             role="dialog" 
             aria-modal="true"
             aria-labelledby="baf-conversion-modal-title"
             hidden>
            <div class="baf-modal-overlay" data-close-modal></div>
            <div class="baf-modal-content">
                <div class="baf-modal-header">
                    <h2 id="baf-conversion-modal-title">
                        <?php esc_html_e( 'Convert Field Type', 'bits-accessible-forms' ); ?>
                    </h2>
                    <button type="button" class="baf-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close modal', 'bits-accessible-forms' ); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="baf-modal-body">
                    <div class="baf-conversion-info" aria-live="polite">
                        <p class="baf-conversion-current">
                            <?php esc_html_e( 'Current type:', 'bits-accessible-forms' ); ?>
                            <strong id="baf-conversion-current-type"></strong>
                        </p>
                    </div>

                    <div class="baf-conversion-form">
                        <fieldset>
                            <legend><?php esc_html_e( 'Select target field type', 'bits-accessible-forms' ); ?></legend>
                            
                            <div class="baf-field-type-grid" role="radiogroup" aria-describedby="baf-conversion-compatibility">
                                <?php foreach ( $field_types as $group_name => $types ) : ?>
                                    <div class="baf-type-group">
                                        <h3 class="baf-type-group-title"><?php echo esc_html( $group_name ); ?></h3>
                                        <div class="baf-type-options">
                                            <?php foreach ( $types as $type_id => $type_info ) : ?>
                                                <label class="baf-type-option" data-type="<?php echo esc_attr( $type_id ); ?>">
                                                    <input type="radio" 
                                                           name="baf_target_type" 
                                                           value="<?php echo esc_attr( $type_id ); ?>"
                                                           class="baf-sr-only">
                                                    <span class="baf-type-icon" aria-hidden="true"><?php echo esc_html( $type_info['icon'] ?? '📝' ); ?></span>
                                                    <span class="baf-type-name"><?php echo esc_html( $type_info['label'] ); ?></span>
                                                    <span class="baf-type-compat" aria-label="<?php esc_attr_e( 'Compatibility status', 'bits-accessible-forms' ); ?>"></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <div id="baf-conversion-compatibility" class="baf-conversion-compat-info" hidden>
                            <h4><?php esc_html_e( 'Compatibility Report', 'bits-accessible-forms' ); ?></h4>
                            <div class="baf-compat-details">
                                <div class="baf-compat-status"></div>
                                <div class="baf-compat-warnings" role="alert"></div>
                            </div>
                        </div>

                        <div id="baf-conversion-preview" class="baf-conversion-preview" hidden>
                            <h4><?php esc_html_e( 'Conversion Preview', 'bits-accessible-forms' ); ?></h4>
                            <div class="baf-preview-changes">
                                <div class="baf-preview-preserved">
                                    <h5><?php esc_html_e( 'Properties Preserved', 'bits-accessible-forms' ); ?></h5>
                                    <ul id="baf-preview-preserved-list"></ul>
                                </div>
                                <div class="baf-preview-lost">
                                    <h5><?php esc_html_e( 'Properties Removed', 'bits-accessible-forms' ); ?></h5>
                                    <ul id="baf-preview-lost-list"></ul>
                                </div>
                                <div class="baf-preview-added">
                                    <h5><?php esc_html_e( 'New Defaults Added', 'bits-accessible-forms' ); ?></h5>
                                    <ul id="baf-preview-added-list"></ul>
                                </div>
                            </div>
                        </div>

                        <div id="baf-conditional-warnings" class="baf-conditional-warnings" hidden role="alert">
                            <h4><?php esc_html_e( 'Conditional Logic Impact', 'bits-accessible-forms' ); ?></h4>
                            <div class="baf-conditional-details">
                                <p class="baf-conditional-message"></p>
                                <ul class="baf-conditional-affected"></ul>
                            </div>
                            <div class="baf-conditional-options">
                                <label>
                                    <input type="checkbox" name="baf_auto_repair" value="1">
                                    <?php esc_html_e( 'Automatically repair compatible conditions', 'bits-accessible-forms' ); ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="baf-modal-footer">
                    <button type="button" class="baf-btn baf-btn-secondary" data-close-modal>
                        <?php esc_html_e( 'Cancel', 'bits-accessible-forms' ); ?>
                    </button>
                    <button type="button" class="baf-btn baf-btn-primary" id="baf-conversion-confirm" disabled>
                        <?php esc_html_e( 'Convert Field', 'bits-accessible-forms' ); ?>
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
        $converter = new BAF_Field_Type_Converter();
        return $converter->get_all_field_types();
    }

    /**
     * Get compatibility matrix for JavaScript
     *
     * @return array Compatibility data.
     */
    public function get_compatibility_data() {
        $converter = new BAF_Field_Type_Converter();
        return $converter->get_compatibility_matrix();
    }
}
