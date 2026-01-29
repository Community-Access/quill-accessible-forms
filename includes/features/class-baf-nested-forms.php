<?php
/**
 * Nested Forms Feature
 *
 * Parent-child form relationships with embedded form instances,
 * collection of child entries, and aggregate calculations.
 *
 * @package BAF
 * @since 1.0.0
 */

namespace BAF\Features;

use BAF\Core\Form;
use BAF\Core\Entry;

/**
 * Class Nested_Forms
 *
 * Manages nested/child form functionality.
 */
class Nested_Forms {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init_hooks(): void {
        // Register nested form field type
        add_filter( 'baf_field_types', [ $this, 'register_field_type' ] );

        // Render nested form field
        add_filter( 'baf_field_render_nested_form', [ $this, 'render_field' ], 10, 3 );

        // Process nested form submissions
        add_filter( 'baf_pre_submission', [ $this, 'process_nested_submissions' ], 20, 2 );

        // Save child entries
        add_action( 'baf_entry_created', [ $this, 'link_child_entries' ], 10, 2 );

        // Process merge tags for nested data
        add_filter( 'baf_merge_tag_value', [ $this, 'process_merge_tags' ], 10, 4 );

        // Delete child entries when parent is deleted
        add_action( 'baf_entry_deleted', [ $this, 'delete_child_entries' ], 10, 1 );

        // AJAX handlers
        add_action( 'wp_ajax_baf_nested_add_entry', [ $this, 'ajax_add_entry' ] );
        add_action( 'wp_ajax_nopriv_baf_nested_add_entry', [ $this, 'ajax_add_entry' ] );
        add_action( 'wp_ajax_baf_nested_edit_entry', [ $this, 'ajax_edit_entry' ] );
        add_action( 'wp_ajax_nopriv_baf_nested_edit_entry', [ $this, 'ajax_edit_entry' ] );
        add_action( 'wp_ajax_baf_nested_remove_entry', [ $this, 'ajax_remove_entry' ] );
        add_action( 'wp_ajax_nopriv_baf_nested_remove_entry', [ $this, 'ajax_remove_entry' ] );
        add_action( 'wp_ajax_baf_nested_get_form', [ $this, 'ajax_get_form' ] );
        add_action( 'wp_ajax_nopriv_baf_nested_get_form', [ $this, 'ajax_get_form' ] );

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /**
     * Register nested form field type.
     *
     * @param array $types Field types.
     * @return array Modified types.
     */
    public function register_field_type( array $types ): array {
        $types['nested_form'] = [
            'label'    => __( 'Nested Form', 'jexi-forms' ),
            'category' => 'advanced',
            'icon'     => 'dashicons-forms',
            'settings' => [
                'child_form_id' => [
                    'type'        => 'select',
                    'label'       => __( 'Child Form', 'jexi-forms' ),
                    'description' => __( 'Select the form to embed as children.', 'jexi-forms' ),
                    'options'     => 'forms', // Dynamically populated
                ],
                'modal_title' => [
                    'type'        => 'text',
                    'label'       => __( 'Modal Title', 'jexi-forms' ),
                    'default'     => __( 'Add Entry', 'jexi-forms' ),
                ],
                'add_label' => [
                    'type'        => 'text',
                    'label'       => __( 'Add Button Label', 'jexi-forms' ),
                    'default'     => __( 'Add Entry', 'jexi-forms' ),
                ],
                'edit_label' => [
                    'type'        => 'text',
                    'label'       => __( 'Edit Button Label', 'jexi-forms' ),
                    'default'     => __( 'Edit', 'jexi-forms' ),
                ],
                'remove_label' => [
                    'type'        => 'text',
                    'label'       => __( 'Remove Button Label', 'jexi-forms' ),
                    'default'     => __( 'Remove', 'jexi-forms' ),
                ],
                'min_entries' => [
                    'type'    => 'number',
                    'label'   => __( 'Minimum Entries', 'jexi-forms' ),
                    'default' => 0,
                ],
                'max_entries' => [
                    'type'    => 'number',
                    'label'   => __( 'Maximum Entries', 'jexi-forms' ),
                    'default' => 0, // 0 = unlimited
                ],
                'display_fields' => [
                    'type'        => 'multiselect',
                    'label'       => __( 'Display Fields in Summary', 'jexi-forms' ),
                    'description' => __( 'Select which child fields to show in the entry summary.', 'jexi-forms' ),
                    'options'     => 'child_fields', // Dynamically populated based on child form
                ],
                'display_style' => [
                    'type'    => 'select',
                    'label'   => __( 'Display Style', 'jexi-forms' ),
                    'options' => [
                        'table' => __( 'Table', 'jexi-forms' ),
                        'list'  => __( 'List', 'jexi-forms' ),
                        'cards' => __( 'Cards', 'jexi-forms' ),
                    ],
                    'default' => 'table',
                ],
            ],
        ];

        return $types;
    }

    /**
     * Render nested form field.
     *
     * @param string $output Current output.
     * @param array  $field  Field configuration.
     * @param Form   $form   Form object.
     * @return string Rendered output.
     */
    public function render_field( string $output, array $field, Form $form ): string {
        $child_form_id   = $field['child_form_id'] ?? 0;
        $display_style   = $field['display_style'] ?? 'table';
        $display_fields  = $field['display_fields'] ?? [];
        $min_entries     = (int) ( $field['min_entries'] ?? 0 );
        $max_entries     = (int) ( $field['max_entries'] ?? 0 );
        $field_id        = $field['id'];
        $form_id         = $form->get_id();

        if ( ! $child_form_id ) {
            return '<p class="baf-error">' . __( 'No child form selected.', 'jexi-forms' ) . '</p>';
        }

        $child_form = Form::get( $child_form_id );
        if ( ! $child_form ) {
            return '<p class="baf-error">' . __( 'Child form not found.', 'jexi-forms' ) . '</p>';
        }

        // Get existing child entries (for editing)
        $existing_entries = $this->get_session_entries( $form_id, $field_id );

        ob_start();
        ?>
        <div class="baf-nested-form" 
             data-field-id="<?php echo esc_attr( $field_id ); ?>"
             data-child-form="<?php echo esc_attr( $child_form_id ); ?>"
             data-parent-form="<?php echo esc_attr( $form_id ); ?>"
             data-min-entries="<?php echo esc_attr( $min_entries ); ?>"
             data-max-entries="<?php echo esc_attr( $max_entries ); ?>"
             data-display-style="<?php echo esc_attr( $display_style ); ?>">

            <?php // Hidden input for serialized entries ?>
            <input type="hidden" 
                   name="<?php echo esc_attr( $field_id ); ?>" 
                   class="baf-nested-entries-data" 
                   value="<?php echo esc_attr( wp_json_encode( $existing_entries ) ); ?>">

            <?php // Entry list container ?>
            <div class="baf-nested-entries baf-display-<?php echo esc_attr( $display_style ); ?>">
                <?php if ( $display_style === 'table' && ! empty( $display_fields ) ): ?>
                    <table class="baf-nested-table">
                        <thead>
                            <tr>
                                <?php foreach ( $display_fields as $df ): ?>
                                    <?php $child_field = $child_form->get_field( $df ); ?>
                                    <th><?php echo esc_html( $child_field['label'] ?? $df ); ?></th>
                                <?php endforeach; ?>
                                <th class="baf-actions"><?php esc_html_e( 'Actions', 'jexi-forms' ); ?></th>
                            </tr>
                        </thead>
                        <tbody class="baf-nested-body">
                            <?php // Entries populated via JavaScript ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="baf-nested-body">
                        <?php // Entries populated via JavaScript ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php // Add button ?>
            <div class="baf-nested-actions">
                <button type="button" class="baf-nested-add-btn baf-button">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php echo esc_html( $field['add_label'] ?? __( 'Add Entry', 'jexi-forms' ) ); ?>
                </button>
            </div>

            <?php // Validation messages ?>
            <div class="baf-nested-validation" aria-live="polite"></div>
        </div>

        <?php // Modal template ?>
        <template class="baf-nested-modal-template">
            <div class="baf-nested-modal" role="dialog" aria-modal="true" aria-labelledby="baf-modal-title-<?php echo esc_attr( $field_id ); ?>">
                <div class="baf-nested-modal-overlay"></div>
                <div class="baf-nested-modal-content">
                    <div class="baf-nested-modal-header">
                        <h3 id="baf-modal-title-<?php echo esc_attr( $field_id ); ?>">
                            <?php echo esc_html( $field['modal_title'] ?? __( 'Add Entry', 'jexi-forms' ) ); ?>
                        </h3>
                        <button type="button" class="baf-nested-modal-close" aria-label="<?php esc_attr_e( 'Close', 'jexi-forms' ); ?>">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <div class="baf-nested-modal-body">
                        <?php // Child form loaded via AJAX ?>
                        <div class="baf-nested-form-container"></div>
                    </div>
                    <div class="baf-nested-modal-footer">
                        <button type="button" class="baf-button baf-button-secondary baf-nested-cancel">
                            <?php esc_html_e( 'Cancel', 'jexi-forms' ); ?>
                        </button>
                        <button type="button" class="baf-button baf-nested-save">
                            <?php esc_html_e( 'Save Entry', 'jexi-forms' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <?php // Entry row template ?>
        <template class="baf-nested-row-template">
            <?php if ( $display_style === 'table' ): ?>
                <tr class="baf-nested-row" data-index="">
                    <?php foreach ( $display_fields as $df ): ?>
                        <td data-field="<?php echo esc_attr( $df ); ?>"></td>
                    <?php endforeach; ?>
                    <td class="baf-actions">
                        <button type="button" class="baf-nested-edit" aria-label="<?php esc_attr_e( 'Edit', 'jexi-forms' ); ?>">
                            <?php echo esc_html( $field['edit_label'] ?? __( 'Edit', 'jexi-forms' ) ); ?>
                        </button>
                        <button type="button" class="baf-nested-remove" aria-label="<?php esc_attr_e( 'Remove', 'jexi-forms' ); ?>">
                            <?php echo esc_html( $field['remove_label'] ?? __( 'Remove', 'jexi-forms' ) ); ?>
                        </button>
                    </td>
                </tr>
            <?php elseif ( $display_style === 'cards' ): ?>
                <div class="baf-nested-card" data-index="">
                    <div class="baf-nested-card-content">
                        <?php foreach ( $display_fields as $df ): ?>
                            <?php $child_field = $child_form->get_field( $df ); ?>
                            <div class="baf-nested-card-field">
                                <span class="baf-label"><?php echo esc_html( $child_field['label'] ?? $df ); ?>:</span>
                                <span data-field="<?php echo esc_attr( $df ); ?>"></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="baf-nested-card-actions">
                        <button type="button" class="baf-nested-edit">
                            <?php echo esc_html( $field['edit_label'] ?? __( 'Edit', 'jexi-forms' ) ); ?>
                        </button>
                        <button type="button" class="baf-nested-remove">
                            <?php echo esc_html( $field['remove_label'] ?? __( 'Remove', 'jexi-forms' ) ); ?>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="baf-nested-list-item" data-index="">
                    <div class="baf-nested-list-content">
                        <?php foreach ( $display_fields as $index => $df ): ?>
                            <span data-field="<?php echo esc_attr( $df ); ?>"></span>
                            <?php if ( $index < count( $display_fields ) - 1 ): ?> - <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="baf-nested-list-actions">
                        <button type="button" class="baf-nested-edit"><?php echo esc_html( $field['edit_label'] ?? 'Edit' ); ?></button>
                        <button type="button" class="baf-nested-remove"><?php echo esc_html( $field['remove_label'] ?? 'Remove' ); ?></button>
                    </div>
                </div>
            <?php endif; ?>
        </template>
        <?php

        return ob_get_clean();
    }

    /**
     * Process nested form submissions.
     *
     * @param array $data Submission data.
     * @param Form  $form Form object.
     * @return array Modified data.
     */
    public function process_nested_submissions( array $data, Form $form ): array {
        $fields = $form->get_fields();

        foreach ( $fields as $field ) {
            if ( ( $field['type'] ?? '' ) !== 'nested_form' ) {
                continue;
            }

            $field_id = $field['id'];
            $raw_data = $data['values'][ $field_id ] ?? '[]';
            
            // Parse JSON data
            $entries = json_decode( $raw_data, true );
            if ( ! is_array( $entries ) ) {
                $entries = [];
            }

            // Validate entry counts
            $min_entries = (int) ( $field['min_entries'] ?? 0 );
            $max_entries = (int) ( $field['max_entries'] ?? 0 );

            if ( $min_entries > 0 && count( $entries ) < $min_entries ) {
                $data['errors'][ $field_id ] = sprintf(
                    /* translators: %d: minimum number of entries */
                    __( 'Please add at least %d entries.', 'jexi-forms' ),
                    $min_entries
                );
            }

            if ( $max_entries > 0 && count( $entries ) > $max_entries ) {
                $data['errors'][ $field_id ] = sprintf(
                    /* translators: %d: maximum number of entries */
                    __( 'Maximum %d entries allowed.', 'jexi-forms' ),
                    $max_entries
                );
            }

            // Validate each child entry
            $child_form_id = $field['child_form_id'] ?? 0;
            $child_form    = Form::get( $child_form_id );

            if ( $child_form ) {
                foreach ( $entries as $index => $entry_data ) {
                    $validation = $child_form->validate( $entry_data );
                    
                    if ( ! empty( $validation['errors'] ) ) {
                        $data['errors'][ $field_id ] = sprintf(
                            /* translators: %d: entry number */
                            __( 'Entry #%d has validation errors.', 'jexi-forms' ),
                            $index + 1
                        );
                        break;
                    }
                }
            }

            // Store processed entries for later linking
            $data['_nested_entries'][ $field_id ] = $entries;
        }

        return $data;
    }

    /**
     * Link child entries to parent after creation.
     *
     * @param Entry $parent_entry Parent entry.
     * @param Form  $form         Form object.
     * @return void
     */
    public function link_child_entries( Entry $parent_entry, Form $form ): void {
        $nested_entries = $parent_entry->get_meta( '_nested_entries_pending' );
        
        if ( empty( $nested_entries ) ) {
            return;
        }

        $fields = $form->get_fields();
        $linked = [];

        foreach ( $fields as $field ) {
            if ( ( $field['type'] ?? '' ) !== 'nested_form' ) {
                continue;
            }

            $field_id      = $field['id'];
            $child_form_id = $field['child_form_id'] ?? 0;
            $entries_data  = $nested_entries[ $field_id ] ?? [];

            if ( empty( $entries_data ) || ! $child_form_id ) {
                continue;
            }

            $child_entry_ids = [];

            foreach ( $entries_data as $entry_data ) {
                // Create child entry
                $child_entry = Entry::create( $child_form_id, $entry_data );
                
                if ( $child_entry ) {
                    // Link to parent
                    $child_entry->update_meta( '_parent_entry_id', $parent_entry->get_id() );
                    $child_entry->update_meta( '_parent_form_id', $form->get_id() );
                    $child_entry->update_meta( '_parent_field_id', $field_id );

                    $child_entry_ids[] = $child_entry->get_id();
                }
            }

            $linked[ $field_id ] = $child_entry_ids;
        }

        // Store references in parent
        if ( ! empty( $linked ) ) {
            $parent_entry->update_meta( '_child_entries', $linked );
        }

        // Clean up pending data
        $parent_entry->delete_meta( '_nested_entries_pending' );
    }

    /**
     * Delete child entries when parent is deleted.
     *
     * @param Entry $entry Parent entry being deleted.
     * @return void
     */
    public function delete_child_entries( Entry $entry ): void {
        $child_entries = $entry->get_meta( '_child_entries' );

        if ( empty( $child_entries ) || ! is_array( $child_entries ) ) {
            return;
        }

        foreach ( $child_entries as $field_id => $entry_ids ) {
            foreach ( (array) $entry_ids as $child_id ) {
                $child = Entry::get( $child_id );
                if ( $child ) {
                    $child->delete();
                }
            }
        }
    }

    /**
     * Process merge tags for nested entries.
     *
     * @param mixed  $value Current value.
     * @param string $tag   Tag name.
     * @param Entry  $entry Entry object.
     * @param Form   $form  Form object.
     * @return mixed Processed value.
     */
    public function process_merge_tags( $value, string $tag, Entry $entry, Form $form ) {
        // Handle nested:field:count, nested:field:sum=subfield, etc.
        if ( ! str_starts_with( $tag, 'nested:' ) ) {
            return $value;
        }

        $parts    = explode( ':', $tag );
        $field_id = $parts[1] ?? '';
        $action   = $parts[2] ?? 'count';

        if ( ! $field_id ) {
            return $value;
        }

        $child_entries = $this->get_child_entries( $entry->get_id(), $field_id );

        // Count
        if ( $action === 'count' ) {
            return count( $child_entries );
        }

        // Sum, average, min, max with subfield
        if ( str_contains( $action, '=' ) ) {
            [ $operation, $subfield ] = explode( '=', $action, 2 );
            
            $values = array_map( function( $child ) use ( $subfield ) {
                return (float) $child->get_field_value( $subfield );
            }, $child_entries );

            switch ( $operation ) {
                case 'sum':
                    return array_sum( $values );
                case 'avg':
                    return count( $values ) ? array_sum( $values ) / count( $values ) : 0;
                case 'min':
                    return count( $values ) ? min( $values ) : 0;
                case 'max':
                    return count( $values ) ? max( $values ) : 0;
            }
        }

        return $value;
    }

    /**
     * Get child entries for a parent entry and field.
     *
     * @param int    $parent_entry_id Parent entry ID.
     * @param string $field_id        Nested form field ID.
     * @return Entry[] Array of child entries.
     */
    public function get_child_entries( int $parent_entry_id, string $field_id ): array {
        $parent = Entry::get( $parent_entry_id );
        
        if ( ! $parent ) {
            return [];
        }

        $child_entries = $parent->get_meta( '_child_entries' );
        $entry_ids     = $child_entries[ $field_id ] ?? [];

        $entries = [];
        foreach ( (array) $entry_ids as $entry_id ) {
            $entry = Entry::get( $entry_id );
            if ( $entry ) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Get session entries (for form not yet submitted).
     *
     * @param int    $form_id  Parent form ID.
     * @param string $field_id Field ID.
     * @return array Entries data.
     */
    private function get_session_entries( int $form_id, string $field_id ): array {
        if ( ! session_id() ) {
            return [];
        }

        $key = "baf_nested_{$form_id}_{$field_id}";
        
        return $_SESSION[ $key ] ?? [];
    }

    /**
     * AJAX: Add nested entry.
     *
     * @return void
     */
    public function ajax_add_entry(): void {
        check_ajax_referer( 'baf_nonce', 'nonce' );

        $parent_form_id = (int) ( $_POST['parent_form_id'] ?? 0 );
        $child_form_id  = (int) ( $_POST['child_form_id'] ?? 0 );
        $field_id       = sanitize_text_field( $_POST['field_id'] ?? '' );
        $entry_data     = isset( $_POST['entry_data'] ) ? json_decode( wp_unslash( $_POST['entry_data'] ), true ) : [];

        if ( ! $child_form_id || ! $field_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'jexi-forms' ) ] );
        }

        $child_form = Form::get( $child_form_id );
        if ( ! $child_form ) {
            wp_send_json_error( [ 'message' => __( 'Child form not found.', 'jexi-forms' ) ] );
        }

        // Validate the entry
        $validation = $child_form->validate( $entry_data );
        
        if ( ! empty( $validation['errors'] ) ) {
            wp_send_json_error( [ 
                'message' => __( 'Please correct the errors.', 'jexi-forms' ),
                'errors'  => $validation['errors'],
            ] );
        }

        // Return sanitized/processed entry
        wp_send_json_success( [
            'entry' => $entry_data,
            'html'  => $this->render_entry_summary( $entry_data, $child_form ),
        ] );
    }

    /**
     * AJAX: Edit nested entry.
     *
     * @return void
     */
    public function ajax_edit_entry(): void {
        check_ajax_referer( 'baf_nonce', 'nonce' );

        $child_form_id = (int) ( $_POST['child_form_id'] ?? 0 );
        $field_id      = sanitize_text_field( $_POST['field_id'] ?? '' );
        $index         = (int) ( $_POST['index'] ?? 0 );
        $entry_data    = isset( $_POST['entry_data'] ) ? json_decode( wp_unslash( $_POST['entry_data'] ), true ) : [];

        if ( ! $child_form_id || ! $field_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'jexi-forms' ) ] );
        }

        $child_form = Form::get( $child_form_id );
        if ( ! $child_form ) {
            wp_send_json_error( [ 'message' => __( 'Child form not found.', 'jexi-forms' ) ] );
        }

        // Validate the entry
        $validation = $child_form->validate( $entry_data );
        
        if ( ! empty( $validation['errors'] ) ) {
            wp_send_json_error( [ 
                'message' => __( 'Please correct the errors.', 'jexi-forms' ),
                'errors'  => $validation['errors'],
            ] );
        }

        wp_send_json_success( [
            'entry' => $entry_data,
            'index' => $index,
            'html'  => $this->render_entry_summary( $entry_data, $child_form ),
        ] );
    }

    /**
     * AJAX: Remove nested entry.
     *
     * @return void
     */
    public function ajax_remove_entry(): void {
        check_ajax_referer( 'baf_nonce', 'nonce' );

        $index = (int) ( $_POST['index'] ?? -1 );

        if ( $index < 0 ) {
            wp_send_json_error( [ 'message' => __( 'Invalid entry index.', 'jexi-forms' ) ] );
        }

        wp_send_json_success( [ 'removed' => $index ] );
    }

    /**
     * AJAX: Get child form HTML.
     *
     * @return void
     */
    public function ajax_get_form(): void {
        check_ajax_referer( 'baf_nonce', 'nonce' );

        $child_form_id = (int) ( $_POST['child_form_id'] ?? 0 );
        $entry_data    = isset( $_POST['entry_data'] ) ? json_decode( wp_unslash( $_POST['entry_data'] ), true ) : [];

        if ( ! $child_form_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid form ID.', 'jexi-forms' ) ] );
        }

        $child_form = Form::get( $child_form_id );
        if ( ! $child_form ) {
            wp_send_json_error( [ 'message' => __( 'Form not found.', 'jexi-forms' ) ] );
        }

        // Render form fields (without form wrapper - we're in a modal)
        $html = $child_form->render_fields( $entry_data, [
            'context' => 'nested',
            'wrapper' => false,
        ] );

        wp_send_json_success( [ 'html' => $html ] );
    }

    /**
     * Render entry summary for display in list.
     *
     * @param array $entry_data Entry data.
     * @param Form  $form       Child form.
     * @return string HTML.
     */
    private function render_entry_summary( array $entry_data, Form $form ): string {
        $parts = [];
        
        foreach ( $form->get_fields() as $field ) {
            $field_id = $field['id'] ?? '';
            $value    = $entry_data[ $field_id ] ?? '';
            
            if ( $value !== '' ) {
                $label   = $field['label'] ?? $field_id;
                $display = is_array( $value ) ? implode( ', ', $value ) : $value;
                $parts[] = "<strong>{$label}:</strong> {$display}";
            }
        }

        return implode( ' | ', $parts );
    }

    /**
     * Enqueue scripts.
     *
     * @return void
     */
    public function enqueue_scripts(): void {
        if ( ! $this->has_nested_form_on_page() ) {
            return;
        }

        wp_enqueue_script(
            'baf-nested-forms',
            plugins_url( 'public/js/baf-nested-forms.js', dirname( __DIR__ ) ),
            [ 'jquery' ],
            BAF_VERSION,
            true
        );

        wp_localize_script( 'baf-nested-forms', 'bafNestedForms', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'baf_nonce' ),
            'i18n'    => [
                'confirmRemove' => __( 'Are you sure you want to remove this entry?', 'jexi-forms' ),
                'minEntries'    => __( 'Please add at least %d entries.', 'jexi-forms' ),
                'maxEntries'    => __( 'Maximum %d entries allowed.', 'jexi-forms' ),
                'loading'       => __( 'Loading...', 'jexi-forms' ),
                'error'         => __( 'An error occurred. Please try again.', 'jexi-forms' ),
            ],
        ] );
    }

    /**
     * Check if current page has nested form field.
     *
     * @return bool
     */
    private function has_nested_form_on_page(): bool {
        // This would need actual implementation based on how forms are rendered
        return true; // For now, always load
    }

    /**
     * Calculate aggregate value from child entries.
     *
     * @param int    $parent_entry_id Parent entry ID.
     * @param string $field_id        Nested form field ID.
     * @param string $operation       Operation (sum, avg, min, max, count).
     * @param string $subfield        Subfield for sum/avg/min/max.
     * @return float|int Result.
     */
    public function calculate_aggregate( int $parent_entry_id, string $field_id, string $operation, string $subfield = '' ): float|int {
        $children = $this->get_child_entries( $parent_entry_id, $field_id );

        if ( $operation === 'count' ) {
            return count( $children );
        }

        if ( ! $subfield ) {
            return 0;
        }

        $values = array_map( function( $child ) use ( $subfield ) {
            return (float) $child->get_field_value( $subfield );
        }, $children );

        if ( empty( $values ) ) {
            return 0;
        }

        switch ( $operation ) {
            case 'sum':
                return array_sum( $values );
            case 'avg':
                return array_sum( $values ) / count( $values );
            case 'min':
                return min( $values );
            case 'max':
                return max( $values );
            default:
                return 0;
        }
    }

    /**
     * Get all entries for a parent form field combination.
     *
     * @param int    $parent_form_id Parent form ID.
     * @param string $field_id       Nested form field ID.
     * @return array Array of child entry data grouped by parent.
     */
    public function get_all_nested_data( int $parent_form_id, string $field_id ): array {
        global $wpdb;

        $entries_table = $wpdb->prefix . 'baf_entries';
        $meta_table    = $wpdb->prefix . 'baf_entry_meta';

        // Get all parent entries for this form
        $parents = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$entries_table} WHERE form_id = %d AND status = 'active'",
                $parent_form_id
            )
        );

        $result = [];

        foreach ( $parents as $parent_id ) {
            $children = $this->get_child_entries( (int) $parent_id, $field_id );
            
            $result[ $parent_id ] = array_map( function( $child ) {
                return [
                    'id'     => $child->get_id(),
                    'values' => $child->get_values(),
                ];
            }, $children );
        }

        return $result;
    }
}
