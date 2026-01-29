<?php
/**
 * File Upload Field
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_FileUpload class
 */
class BAF_Field_FileUpload extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'fileupload';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'advanced';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'allowed_types'    => array( 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx' ),
            'accept'           => '', // MIME types or extensions, e.g., 'image/*,.pdf'
            'capture'          => '', // 'user', 'environment', or empty
            'max_size'         => 10,
            'max_files'        => 1,
            'enable_preview'   => true,
            'enable_drag_drop' => true,
            'media_library'    => false,
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
            'validation' => array(
                'allowed_types' => array(
                    'type'    => 'multiselect',
                    'label'   => __( 'Allowed File Types', 'bits-accessible-forms' ),
                    'options' => array(
                        'jpg'  => __( 'JPG/JPEG', 'bits-accessible-forms' ),
                        'png'  => __( 'PNG', 'bits-accessible-forms' ),
                        'gif'  => __( 'GIF', 'bits-accessible-forms' ),
                        'pdf'  => __( 'PDF', 'bits-accessible-forms' ),
                        'doc'  => __( 'DOC', 'bits-accessible-forms' ),
                        'docx' => __( 'DOCX', 'bits-accessible-forms' ),
                        'xls'  => __( 'XLS', 'bits-accessible-forms' ),
                        'xlsx' => __( 'XLSX', 'bits-accessible-forms' ),
                        'csv'  => __( 'CSV', 'bits-accessible-forms' ),
                        'txt'  => __( 'TXT', 'bits-accessible-forms' ),
                        'zip'  => __( 'ZIP', 'bits-accessible-forms' ),
                        'mp3'  => __( 'MP3', 'bits-accessible-forms' ),
                        'mp4'  => __( 'MP4', 'bits-accessible-forms' ),
                    ),
                ),
                'max_size' => array(
                    'type'  => 'number',
                    'label' => __( 'Max File Size (MB)', 'bits-accessible-forms' ),
                    'min'   => 1,
                    'max'   => 100,
                ),
                'max_files' => array(
                    'type'  => 'number',
                    'label' => __( 'Max Number of Files', 'bits-accessible-forms' ),
                    'min'   => 1,
                    'max'   => 20,
                ),
            ),
            'appearance' => array(
                'enable_preview' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show File Preview', 'bits-accessible-forms' ),
                ),
                'enable_drag_drop' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Enable Drag & Drop', 'bits-accessible-forms' ),
                ),
            ),
            'advanced' => array(
                'media_library' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Add to Media Library', 'bits-accessible-forms' ),
                    'desc'  => __( 'Store uploaded files in WordPress Media Library.', 'bits-accessible-forms' ),
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
        $max_files   = (int) $this->config['max_files'];
        $max_size    = (int) $this->config['max_size'];
        $types       = $this->config['allowed_types'];
        $enable_dd   = ! empty( $this->config['enable_drag_drop'] );

        $wrapper_class = $this->get_wrapper_classes();
        $wrapper_class[] = 'baf-field-fileupload';
        
        if ( $enable_dd ) {
            $wrapper_class[] = 'baf-fileupload-dragdrop';
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-max-files="' . esc_attr( $max_files ) . '"';
        $html .= ' data-max-size="' . esc_attr( $max_size * 1024 * 1024 ) . '"';
        $html .= ' data-allowed-types="' . esc_attr( implode( ',', $types ) ) . '">';

        // Label
        $html .= $this->render_label();

        // File requirements hint
        $described_by = array();
        if ( $this->get_description() ) {
            $described_by[] = $html_id . '-desc';
        }
        $described_by[] = $html_id . '-requirements';

        // Requirements text
        $html .= '<div id="' . esc_attr( $html_id ) . '-requirements" class="baf-file-requirements">';
        $html .= sprintf(
            esc_html__( 'Allowed types: %s. Maximum size: %s MB.', 'bits-accessible-forms' ),
            strtoupper( implode( ', ', $types ) ),
            $max_size
        );
        if ( $max_files > 1 ) {
            $html .= ' ' . sprintf(
                esc_html__( 'Maximum %d files.', 'bits-accessible-forms' ),
                $max_files
            );
        }
        $html .= '</div>';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Drop zone (with keyboard-accessible alternative)
        if ( $enable_dd ) {
            $html .= '<div class="baf-dropzone" role="region" aria-label="' . esc_attr__( 'File drop zone', 'bits-accessible-forms' ) . '">';
            $html .= '<div class="baf-dropzone-content">';
            $html .= '<span class="baf-dropzone-icon" aria-hidden="true"></span>';
            $html .= '<p class="baf-dropzone-text">' . esc_html__( 'Drag files here or use the button below', 'bits-accessible-forms' ) . '</p>';
            $html .= '</div>';
            $html .= '</div>';
        }

        // File input (always available as keyboard alternative)
        // Use custom accept attribute if provided, otherwise build from allowed_types
        $accept = ! empty( $this->config['accept'] ) 
            ? $this->config['accept']
            : '.' . implode( ',.', $types );
        
        $html .= '<div class="baf-file-input-wrapper">';
        $html .= '<input type="file"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . ( $max_files > 1 ? '[]' : '' ) . '"';
        $html .= ' class="baf-file-input"';
        $html .= ' accept="' . esc_attr( $accept ) . '"';
        $html .= ' aria-describedby="' . esc_attr( implode( ' ', $described_by ) ) . '"';
        
        // Capture attribute for mobile camera access
        if ( ! empty( $this->config['capture'] ) ) {
            $html .= ' capture="' . esc_attr( $this->config['capture'] ) . '"';
        }
        
        if ( $max_files > 1 ) {
            $html .= ' multiple';
        }
        
        if ( $this->is_required() ) {
            $html .= ' aria-required="true"';
        }
        
        if ( ! empty( $this->errors ) ) {
            $html .= ' aria-invalid="true"';
            $html .= ' aria-describedby="' . esc_attr( implode( ' ', $described_by ) ) . ' ' . esc_attr( $html_id ) . '-error"';
        }
        
        $html .= '>';

        // Custom button (visually hides the file input)
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="baf-file-button button">';
        $html .= '<span class="baf-button-icon" aria-hidden="true"></span>';
        $html .= esc_html__( 'Choose Files', 'bits-accessible-forms' );
        $html .= '</label>';
        $html .= '</div>';

        // File list container
        $html .= '<div class="baf-file-list" role="list" aria-label="' . esc_attr__( 'Selected files', 'bits-accessible-forms' ) . '" aria-live="polite">';
        $html .= '</div>';

        // Status announcer for screen readers
        $html .= '<div class="baf-file-status" role="status" aria-live="polite" aria-atomic="true"></div>';

        // Description below
        if ( $this->get_description_placement() === 'below' ) {
            $html .= $this->render_description();
        }

        // Error container
        $html .= '<div id="' . esc_attr( $html_id ) . '-error" class="baf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $html .= $this->render_errors();
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return array|string Sanitized value.
     */
    public function sanitize( $value ) {
        // File handling is done separately through WordPress file upload
        if ( is_array( $value ) ) {
            return array_map( 'sanitize_text_field', $value );
        }
        
        return sanitize_text_field( $value );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $files_key = 'baf_field_' . $this->get_id();
        
        if ( ! isset( $_FILES[ $files_key ] ) ) {
            return;
        }

        $files = $_FILES[ $files_key ];
        $max_size = (int) $this->config['max_size'] * 1024 * 1024;
        $max_files = (int) $this->config['max_files'];
        $allowed = $this->config['allowed_types'];

        // Handle both single and multiple files
        if ( is_array( $files['name'] ) ) {
            $file_count = count( $files['name'] );
            
            if ( $file_count > $max_files ) {
                $this->add_error( sprintf(
                    __( 'Maximum %d files allowed.', 'bits-accessible-forms' ),
                    $max_files
                ) );
                return;
            }

            for ( $i = 0; $i < $file_count; $i++ ) {
                if ( $files['error'][ $i ] !== UPLOAD_ERR_OK && $files['error'][ $i ] !== UPLOAD_ERR_NO_FILE ) {
                    $this->add_error( $this->get_upload_error_message( $files['error'][ $i ] ) );
                    continue;
                }

                if ( $files['error'][ $i ] === UPLOAD_ERR_NO_FILE ) {
                    continue;
                }

                $this->validate_single_file(
                    $files['name'][ $i ],
                    $files['size'][ $i ],
                    $files['tmp_name'][ $i ],
                    $allowed,
                    $max_size
                );
            }
        } else {
            if ( $files['error'] !== UPLOAD_ERR_OK && $files['error'] !== UPLOAD_ERR_NO_FILE ) {
                $this->add_error( $this->get_upload_error_message( $files['error'] ) );
                return;
            }

            if ( $files['error'] !== UPLOAD_ERR_NO_FILE ) {
                $this->validate_single_file(
                    $files['name'],
                    $files['size'],
                    $files['tmp_name'],
                    $allowed,
                    $max_size
                );
            }
        }
    }

    /**
     * Validate a single file
     *
     * @param string $name     File name.
     * @param int    $size     File size.
     * @param string $tmp_name Temporary file path.
     * @param array  $allowed  Allowed extensions.
     * @param int    $max_size Maximum size in bytes.
     */
    protected function validate_single_file( $name, $size, $tmp_name, $allowed, $max_size ) {
        // Check file extension
        $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, $allowed, true ) ) {
            $this->add_error( sprintf(
                __( 'File type "%s" is not allowed.', 'bits-accessible-forms' ),
                $ext
            ) );
            return;
        }

        // Check file size
        if ( $size > $max_size ) {
            $this->add_error( sprintf(
                __( 'File "%s" exceeds the maximum size of %s MB.', 'bits-accessible-forms' ),
                $name,
                $this->config['max_size']
            ) );
            return;
        }

        // Verify MIME type using WordPress
        $file_type = wp_check_filetype_and_ext( $tmp_name, $name );
        if ( ! $file_type['ext'] || ! $file_type['type'] ) {
            $this->add_error( sprintf(
                __( 'File "%s" has an invalid file type.', 'bits-accessible-forms' ),
                $name
            ) );
        }
    }

    /**
     * Get upload error message
     *
     * @param int $error_code PHP upload error code.
     * @return string Error message.
     */
    protected function get_upload_error_message( $error_code ) {
        $messages = array(
            UPLOAD_ERR_INI_SIZE   => __( 'The uploaded file exceeds the server limit.', 'bits-accessible-forms' ),
            UPLOAD_ERR_FORM_SIZE  => __( 'The uploaded file exceeds the form limit.', 'bits-accessible-forms' ),
            UPLOAD_ERR_PARTIAL    => __( 'The file was only partially uploaded.', 'bits-accessible-forms' ),
            UPLOAD_ERR_NO_TMP_DIR => __( 'Server configuration error: missing temporary folder.', 'bits-accessible-forms' ),
            UPLOAD_ERR_CANT_WRITE => __( 'Server error: failed to write file.', 'bits-accessible-forms' ),
            UPLOAD_ERR_EXTENSION  => __( 'File upload blocked by server extension.', 'bits-accessible-forms' ),
        );

        return $messages[ $error_code ] ?? __( 'An unknown upload error occurred.', 'bits-accessible-forms' );
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        if ( is_array( $value ) ) {
            $links = array();
            foreach ( $value as $file ) {
                $links[] = $this->format_file_link( $file );
            }
            return implode( ', ', $links );
        }

        return $this->format_file_link( $value );
    }

    /**
     * Format single file as link
     *
     * @param string|array $file File data.
     * @return string File link HTML.
     */
    protected function format_file_link( $file ) {
        if ( is_array( $file ) ) {
            $url  = $file['url'] ?? '';
            $name = $file['name'] ?? basename( $url );
        } else {
            $url  = $file;
            $name = basename( $file );
        }

        if ( empty( $url ) ) {
            return '';
        }

        return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . 
               esc_html( $name ) . 
               ' <span class="screen-reader-text">' . esc_html__( '(opens in new tab)', 'bits-accessible-forms' ) . '</span></a>';
    }
}
