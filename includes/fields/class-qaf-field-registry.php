<?php
/**
 * Field Registry class
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Registry class
 * 
 * Manages registration and instantiation of field types.
 */
class QAF_Field_Registry {

    /**
     * Registry instance
     *
     * @var QAF_Field_Registry
     */
    private static $instance = null;

    /**
     * Registered field types
     *
     * @var array
     */
    private $field_types = array();

    /**
     * Field categories
     *
     * @var array
     */
    private $categories = array();

    /**
     * Get registry instance
     *
     * @return QAF_Field_Registry
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_categories();
        $this->register_core_fields();
    }

    /**
     * Initialize field categories
     */
    private function init_categories() {
        $this->categories = array(
            'standard' => array(
                'label'    => __( 'Standard Fields', 'quill-accessible-forms' ),
                'icon'     => 'text',
                'priority' => 10,
            ),
            'advanced' => array(
                'label'    => __( 'Advanced Fields', 'quill-accessible-forms' ),
                'icon'     => 'star',
                'priority' => 20,
            ),
            'post' => array(
                'label'    => __( 'Post Fields', 'quill-accessible-forms' ),
                'icon'     => 'post',
                'priority' => 30,
            ),
            'pricing' => array(
                'label'    => __( 'Pricing Fields', 'quill-accessible-forms' ),
                'icon'     => 'currency',
                'priority' => 40,
            ),
            'choice' => array(
                'label'    => __( 'Choice Fields', 'quill-accessible-forms' ),
                'icon'     => 'list',
                'priority' => 50,
            ),
            'form_helper' => array(
                'label'    => __( 'Form Helpers', 'quill-accessible-forms' ),
                'icon'     => 'info',
                'priority' => 60,
            ),
            'layout' => array(
                'label'    => __( 'Layout Elements', 'quill-accessible-forms' ),
                'icon'     => 'layout',
                'priority' => 70,
            ),
            'date_time' => array(
                'label'    => __( 'Date & Time', 'quill-accessible-forms' ),
                'icon'     => 'calendar',
                'priority' => 80,
            ),
            'user' => array(
                'label'    => __( 'User Fields', 'quill-accessible-forms' ),
                'icon'     => 'user',
                'priority' => 90,
            ),
        );

        /**
         * Filter field categories
         *
         * @param array $categories Field categories.
         */
        $this->categories = apply_filters( 'qaf_field_categories', $this->categories );
    }

    /**
     * Register core field types
     */
    private function register_core_fields() {
        // Standard Fields
        $this->register( 'text', 'QAF_Field_Text', array(
            'label'    => __( 'Single Line Text', 'quill-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'text',
            'priority' => 10,
        ) );

        $this->register( 'textarea', 'QAF_Field_Textarea', array(
            'label'    => __( 'Paragraph Text', 'quill-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'paragraph',
            'priority' => 20,
        ) );

        $this->register( 'number', 'QAF_Field_Number', array(
            'label'    => __( 'Number', 'quill-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'number',
            'priority' => 30,
        ) );

        $this->register( 'email', 'QAF_Field_Email', array(
            'label'    => __( 'Email', 'quill-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'email',
            'priority' => 40,
        ) );

        $this->register( 'phone', 'QAF_Field_Phone', array(
            'label'    => __( 'Phone', 'quill-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'phone',
            'priority' => 50,
        ) );

        $this->register( 'website', 'QAF_Field_Website', array(
            'label'    => __( 'Website / URL', 'quill-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'link',
            'priority' => 60,
        ) );

        $this->register( 'hidden', 'QAF_Field_Hidden', array(
            'label'    => __( 'Hidden', 'quill-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'hidden',
            'priority' => 70,
        ) );

        $this->register( 'password', 'QAF_Field_Password', array(
            'label'    => __( 'Password', 'quill-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'lock',
            'priority' => 80,
        ) );

        $this->register( 'color', 'QAF_Field_Color', array(
            'label'    => __( 'Color Picker', 'quill-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'color',
            'priority' => 90,
        ) );

        $this->register( 'range', 'QAF_Field_Range', array(
            'label'    => __( 'Range Slider', 'quill-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'slider',
            'priority' => 100,
        ) );

        // Choice Fields
        $this->register( 'select', 'QAF_Field_Select', array(
            'label'    => __( 'Dropdown', 'quill-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'dropdown',
            'priority' => 10,
        ) );

        $this->register( 'multiselect', 'QAF_Field_Multiselect', array(
            'label'    => __( 'Multi-Select', 'quill-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'multiselect',
            'priority' => 20,
        ) );

        $this->register( 'radio', 'QAF_Field_Radio', array(
            'label'    => __( 'Radio Buttons', 'quill-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'radio',
            'priority' => 30,
        ) );

        $this->register( 'checkbox', 'QAF_Field_Checkbox', array(
            'label'    => __( 'Checkboxes', 'quill-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'checkbox',
            'priority' => 40,
        ) );

        $this->register( 'consent', 'QAF_Field_Consent', array(
            'label'    => __( 'Consent / Terms', 'quill-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'consent',
            'priority' => 50,
        ) );

        $this->register( 'toggle', 'QAF_Field_Toggle', array(
            'label'    => __( 'Toggle Switch', 'quill-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'toggle',
            'priority' => 55,
        ) );

        $this->register( 'likert', 'QAF_Field_Likert', array(
            'label'    => __( 'Likert Scale', 'quill-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'scale',
            'priority' => 60,
        ) );

        $this->register( 'ranking', 'QAF_Field_Ranking', array(
            'label'    => __( 'Ranking', 'quill-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'ranking',
            'priority' => 70,
        ) );

        $this->register( 'rating', 'QAF_Field_Rating', array(
            'label'    => __( 'Star Rating', 'quill-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'star',
            'priority' => 80,
        ) );

        $this->register( 'nps', 'QAF_Field_NPS', array(
            'label'    => __( 'Net Promoter Score', 'quill-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'nps',
            'priority' => 90,
        ) );

        // Date/Time Fields
        $this->register( 'date', 'QAF_Field_Date', array(
            'label'    => __( 'Date Picker', 'quill-accessible-forms' ),
            'category' => 'date_time',
            'icon'     => 'calendar',
            'priority' => 10,
        ) );

        $this->register( 'time', 'QAF_Field_Time', array(
            'label'    => __( 'Time Picker', 'quill-accessible-forms' ),
            'category' => 'date_time',
            'icon'     => 'clock',
            'priority' => 20,
        ) );

        $this->register( 'datetime', 'QAF_Field_DateTime', array(
            'label'    => __( 'Date & Time', 'quill-accessible-forms' ),
            'category' => 'date_time',
            'icon'     => 'datetime',
            'priority' => 30,
        ) );

        $this->register( 'daterange', 'QAF_Field_DateRange', array(
            'label'    => __( 'Date Range', 'quill-accessible-forms' ),
            'category' => 'date_time',
            'icon'     => 'range',
            'priority' => 40,
        ) );

        // Advanced Fields
        $this->register( 'name', 'QAF_Field_Name', array(
            'label'    => __( 'Name', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'user',
            'priority' => 10,
        ) );

        $this->register( 'address', 'QAF_Field_Address', array(
            'label'    => __( 'Address', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'home',
            'priority' => 20,
        ) );

        $this->register( 'fileupload', 'QAF_Field_FileUpload', array(
            'label'    => __( 'File Upload', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'upload',
            'priority' => 30,
        ) );

        $this->register( 'signature', 'QAF_Field_Signature', array(
            'label'    => __( 'Signature', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'signature',
            'priority' => 40,
        ) );

        $this->register( 'captcha', 'QAF_Field_Captcha', array(
            'label'    => __( 'Captcha', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'shield',
            'priority' => 50,
        ) );

        $this->register( 'repeater', 'QAF_Field_Repeater', array(
            'label'    => __( 'Repeater', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'repeat',
            'priority' => 60,
        ) );

        $this->register( 'calculation', 'QAF_Field_Calculation', array(
            'label'    => __( 'Calculation', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'calculator',
            'priority' => 70,
        ) );

        $this->register( 'currency', 'QAF_Field_Currency', array(
            'label'    => __( 'Currency', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'currency',
            'priority' => 75,
        ) );

        $this->register( 'otp', 'QAF_Field_OTP', array(
            'label'    => __( 'OTP / Verification Code', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'lock',
            'priority' => 80,
        ) );

        $this->register( 'typed_signature', 'QAF_Field_Typed_Signature', array(
            'label'    => __( 'Typed Signature', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'signature',
            'priority' => 85,
        ) );

        $this->register( 'slider', 'QAF_Field_Slider', array(
            'label'    => __( 'Slider', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'slider',
            'priority' => 90,
        ) );

        $this->register( 'image_choice', 'QAF_Field_Image_Choice', array(
            'label'    => __( 'Image Choice', 'quill-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'images-alt2',
            'priority' => 40,
        ) );

        $this->register( 'matrix', 'QAF_Field_Matrix', array(
            'label'    => __( 'Matrix / Grid', 'quill-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'grid-view',
            'priority' => 45,
        ) );

        $this->register( 'rich_text', 'QAF_Field_Rich_Text', array(
            'label'    => __( 'Rich Text Editor', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'editor-paragraph',
            'priority' => 95,
        ) );

        $this->register( 'quiz', 'QAF_Field_Quiz', array(
            'label'    => __( 'Quiz Question', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'welcome-learn-more',
            'priority' => 100,
        ) );

        $this->register( 'appointment', 'QAF_Field_Appointment', array(
            'label'    => __( 'Appointment Picker', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'calendar-alt',
            'priority' => 105,
        ) );

        $this->register( 'map', 'QAF_Field_Map', array(
            'label'    => __( 'Map / Location Picker', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'location-alt',
            'priority' => 110,
        ) );

        $this->register( 'social', 'QAF_Field_Social', array(
            'label'    => __( 'Social Profile', 'quill-accessible-forms' ),
            'category' => 'contact',
            'icon'     => 'share',
            'priority' => 50,
        ) );

        $this->register( 'table', 'QAF_Field_Table', array(
            'label'    => __( 'Table Input', 'quill-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'editor-table',
            'priority' => 115,
        ) );

        $this->register( 'payment', 'QAF_Field_Payment', array(
            'label'    => __( 'Payment', 'quill-accessible-forms' ),
            'category' => 'ecommerce',
            'icon'     => 'money-alt',
            'priority' => 10,
        ) );

        // Pricing Fields
        $this->register( 'product', 'QAF_Field_Product', array(
            'label'    => __( 'Product', 'quill-accessible-forms' ),
            'category' => 'pricing',
            'icon'     => 'product',
            'priority' => 10,
        ) );

        $this->register( 'quantity', 'QAF_Field_Quantity', array(
            'label'    => __( 'Quantity', 'quill-accessible-forms' ),
            'category' => 'pricing',
            'icon'     => 'quantity',
            'priority' => 20,
        ) );

        $this->register( 'price', 'QAF_Field_Price', array(
            'label'    => __( 'Price', 'quill-accessible-forms' ),
            'category' => 'pricing',
            'icon'     => 'currency',
            'priority' => 30,
        ) );

        $this->register( 'total', 'QAF_Field_Total', array(
            'label'    => __( 'Total', 'quill-accessible-forms' ),
            'category' => 'pricing',
            'icon'     => 'total',
            'priority' => 40,
        ) );

        $this->register( 'coupon', 'QAF_Field_Coupon', array(
            'label'    => __( 'Coupon', 'quill-accessible-forms' ),
            'category' => 'pricing',
            'icon'     => 'coupon',
            'priority' => 50,
        ) );

        // Layout Elements
        $this->register( 'section', 'QAF_Field_Section', array(
            'label'    => __( 'Section Break', 'quill-accessible-forms' ),
            'category' => 'layout',
            'icon'     => 'section',
            'priority' => 10,
        ) );

        $this->register( 'page', 'QAF_Field_Page', array(
            'label'    => __( 'Page Break', 'quill-accessible-forms' ),
            'category' => 'layout',
            'icon'     => 'page',
            'priority' => 20,
        ) );

        $this->register( 'html', 'QAF_Field_HTML', array(
            'label'    => __( 'HTML', 'quill-accessible-forms' ),
            'category' => 'layout',
            'icon'     => 'code',
            'priority' => 30,
        ) );

        $this->register( 'columns', 'QAF_Field_Columns', array(
            'label'    => __( 'Columns', 'quill-accessible-forms' ),
            'category' => 'layout',
            'icon'     => 'columns',
            'priority' => 40,
        ) );

        $this->register( 'progress', 'QAF_Field_Progress', array(
            'label'    => __( 'Progress Indicator', 'quill-accessible-forms' ),
            'category' => 'layout',
            'icon'     => 'performance',
            'priority' => 50,
        ) );

        $this->register( 'summary', 'QAF_Field_Summary', array(
            'label'    => __( 'Summary / Review', 'quill-accessible-forms' ),
            'category' => 'layout',
            'icon'     => 'list-view',
            'priority' => 60,
        ) );

        // Form Helpers
        $this->register( 'submit', 'QAF_Field_Submit', array(
            'label'    => __( 'Submit Button', 'quill-accessible-forms' ),
            'category' => 'form_helper',
            'icon'     => 'button',
            'priority' => 10,
        ) );

        $this->register( 'save', 'QAF_Field_Save', array(
            'label'    => __( 'Save Progress Button', 'quill-accessible-forms' ),
            'category' => 'form_helper',
            'icon'     => 'save',
            'priority' => 20,
        ) );

        // Post Fields
        $this->register( 'post_title', 'QAF_Field_PostTitle', array(
            'label'    => __( 'Post Title', 'quill-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'title',
            'priority' => 10,
        ) );

        $this->register( 'post_content', 'QAF_Field_PostContent', array(
            'label'    => __( 'Post Content', 'quill-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'content',
            'priority' => 20,
        ) );

        $this->register( 'post_excerpt', 'QAF_Field_PostExcerpt', array(
            'label'    => __( 'Post Excerpt', 'quill-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'excerpt',
            'priority' => 30,
        ) );

        $this->register( 'post_category', 'QAF_Field_PostCategory', array(
            'label'    => __( 'Post Category', 'quill-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'category',
            'priority' => 40,
        ) );

        $this->register( 'post_tags', 'QAF_Field_PostTags', array(
            'label'    => __( 'Post Tags', 'quill-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'tags',
            'priority' => 50,
        ) );

        $this->register( 'post_image', 'QAF_Field_PostImage', array(
            'label'    => __( 'Featured Image', 'quill-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'image',
            'priority' => 60,
        ) );

        $this->register( 'post_custom', 'QAF_Field_PostCustom', array(
            'label'    => __( 'Custom Field', 'quill-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'custom',
            'priority' => 70,
        ) );

        // User Fields
        $this->register( 'user_login', 'QAF_Field_UserLogin', array(
            'label'    => __( 'Username', 'quill-accessible-forms' ),
            'category' => 'user',
            'icon'     => 'user',
            'priority' => 10,
        ) );

        $this->register( 'user_email', 'QAF_Field_UserEmail', array(
            'label'    => __( 'User Email', 'quill-accessible-forms' ),
            'category' => 'user',
            'icon'     => 'email',
            'priority' => 20,
        ) );

        $this->register( 'user_password', 'QAF_Field_UserPassword', array(
            'label'    => __( 'User Password', 'quill-accessible-forms' ),
            'category' => 'user',
            'icon'     => 'lock',
            'priority' => 30,
        ) );

        /**
         * Allow plugins to register custom fields
         *
         * @param QAF_Field_Registry $registry Field registry instance.
         */
        do_action( 'qaf_register_fields', $this );
    }

    /**
     * Register a field type
     *
     * @param string $type       Field type identifier.
     * @param string $class_name Field class name.
     * @param array  $args       Field type arguments.
     */
    public function register( $type, $class_name, $args = array() ) {
        $defaults = array(
            'label'    => $type,
            'category' => 'standard',
            'icon'     => 'text',
            'priority' => 100,
            'supports' => array(),
        );

        $args = wp_parse_args( $args, $defaults );
        $args['class'] = $class_name;
        $args['type']  = $type;

        $this->field_types[ $type ] = $args;
    }

    /**
     * Unregister a field type
     *
     * @param string $type Field type identifier.
     */
    public function unregister( $type ) {
        unset( $this->field_types[ $type ] );
    }

    /**
     * Check if field type is registered
     *
     * @param string $type Field type identifier.
     * @return bool True if registered.
     */
    public function is_registered( $type ) {
        return isset( $this->field_types[ $type ] );
    }

    /**
     * Get field type info
     *
     * @param string $type Field type identifier.
     * @return array|null Field type info or null if not found.
     */
    public function get_field_type( $type ) {
        return isset( $this->field_types[ $type ] ) ? $this->field_types[ $type ] : null;
    }

    /**
     * Get all field types
     *
     * @return array All registered field types.
     */
    public function get_all_field_types() {
        return $this->field_types;
    }

    /**
     * Get field types by category
     *
     * @param string $category Category identifier.
     * @return array Field types in category.
     */
    public function get_field_types_by_category( $category ) {
        $fields = array();

        foreach ( $this->field_types as $type => $info ) {
            if ( $info['category'] === $category ) {
                $fields[ $type ] = $info;
            }
        }

        // Sort by priority
        uasort( $fields, function( $a, $b ) {
            return $a['priority'] - $b['priority'];
        } );

        return $fields;
    }

    /**
     * Get all categories
     *
     * @return array Field categories.
     */
    public function get_categories() {
        // Sort by priority
        uasort( $this->categories, function( $a, $b ) {
            return $a['priority'] - $b['priority'];
        } );

        return $this->categories;
    }

    /**
     * Create field instance
     *
     * @param array $config  Field configuration.
     * @param int   $form_id Form ID.
     * @return QAF_Field|null Field instance or null if type not found.
     */
    public function create_field( $config, $form_id = 0 ) {
        $type = isset( $config['type'] ) ? $config['type'] : '';

        if ( ! $this->is_registered( $type ) ) {
            return null;
        }

        $class = $this->field_types[ $type ]['class'];

        // Load the field class file if needed
        $this->maybe_load_field_class( $type, $class );

        if ( ! class_exists( $class ) ) {
            return null;
        }

        return new $class( $config, $form_id );
    }

    /**
     * Maybe load field class file
     *
     * @param string $type  Field type.
     * @param string $class Field class name.
     */
    private function maybe_load_field_class( $type, $class ) {
        if ( class_exists( $class ) ) {
            return;
        }

        // Determine file path based on naming convention
        $file_name = 'class-qaf-field-' . str_replace( '_', '-', $type ) . '.php';
        $file_path = QAF_PLUGIN_DIR . 'includes/fields/' . $file_name;

        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }
    }

    /**
     * Get field types for builder UI
     *
     * @return array Structured field types for UI.
     */
    public function get_for_builder() {
        $result = array();

        foreach ( $this->get_categories() as $cat_id => $category ) {
            $fields = $this->get_field_types_by_category( $cat_id );

            if ( empty( $fields ) ) {
                continue;
            }

            $result[ $cat_id ] = array(
                'label'  => $category['label'],
                'icon'   => $category['icon'],
                'fields' => array(),
            );

            foreach ( $fields as $type => $info ) {
                $result[ $cat_id ]['fields'][] = array(
                    'type'  => $type,
                    'label' => $info['label'],
                    'icon'  => $info['icon'],
                );
            }
        }

        return $result;
    }
}
