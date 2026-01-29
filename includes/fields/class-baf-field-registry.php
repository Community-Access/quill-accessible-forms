<?php
/**
 * Field Registry class
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Registry class
 * 
 * Manages registration and instantiation of field types.
 */
class BAF_Field_Registry {

    /**
     * Registry instance
     *
     * @var BAF_Field_Registry
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
     * @return BAF_Field_Registry
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
                'label'    => __( 'Standard Fields', 'bits-accessible-forms' ),
                'icon'     => 'text',
                'priority' => 10,
            ),
            'advanced' => array(
                'label'    => __( 'Advanced Fields', 'bits-accessible-forms' ),
                'icon'     => 'star',
                'priority' => 20,
            ),
            'post' => array(
                'label'    => __( 'Post Fields', 'bits-accessible-forms' ),
                'icon'     => 'post',
                'priority' => 30,
            ),
            'pricing' => array(
                'label'    => __( 'Pricing Fields', 'bits-accessible-forms' ),
                'icon'     => 'currency',
                'priority' => 40,
            ),
            'choice' => array(
                'label'    => __( 'Choice Fields', 'bits-accessible-forms' ),
                'icon'     => 'list',
                'priority' => 50,
            ),
            'form_helper' => array(
                'label'    => __( 'Form Helpers', 'bits-accessible-forms' ),
                'icon'     => 'info',
                'priority' => 60,
            ),
            'layout' => array(
                'label'    => __( 'Layout Elements', 'bits-accessible-forms' ),
                'icon'     => 'layout',
                'priority' => 70,
            ),
            'date_time' => array(
                'label'    => __( 'Date & Time', 'bits-accessible-forms' ),
                'icon'     => 'calendar',
                'priority' => 80,
            ),
            'user' => array(
                'label'    => __( 'User Fields', 'bits-accessible-forms' ),
                'icon'     => 'user',
                'priority' => 90,
            ),
        );

        /**
         * Filter field categories
         *
         * @param array $categories Field categories.
         */
        $this->categories = apply_filters( 'baf_field_categories', $this->categories );
    }

    /**
     * Register core field types
     */
    private function register_core_fields() {
        // Standard Fields
        $this->register( 'text', 'BAF_Field_Text', array(
            'label'    => __( 'Single Line Text', 'bits-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'text',
            'priority' => 10,
        ) );

        $this->register( 'textarea', 'BAF_Field_Textarea', array(
            'label'    => __( 'Paragraph Text', 'bits-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'paragraph',
            'priority' => 20,
        ) );

        $this->register( 'number', 'BAF_Field_Number', array(
            'label'    => __( 'Number', 'bits-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'number',
            'priority' => 30,
        ) );

        $this->register( 'email', 'BAF_Field_Email', array(
            'label'    => __( 'Email', 'bits-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'email',
            'priority' => 40,
        ) );

        $this->register( 'phone', 'BAF_Field_Phone', array(
            'label'    => __( 'Phone', 'bits-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'phone',
            'priority' => 50,
        ) );

        $this->register( 'website', 'BAF_Field_Website', array(
            'label'    => __( 'Website / URL', 'bits-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'link',
            'priority' => 60,
        ) );

        $this->register( 'hidden', 'BAF_Field_Hidden', array(
            'label'    => __( 'Hidden', 'bits-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'hidden',
            'priority' => 70,
        ) );

        $this->register( 'password', 'BAF_Field_Password', array(
            'label'    => __( 'Password', 'bits-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'lock',
            'priority' => 80,
        ) );

        $this->register( 'color', 'BAF_Field_Color', array(
            'label'    => __( 'Color Picker', 'bits-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'color',
            'priority' => 90,
        ) );

        $this->register( 'range', 'BAF_Field_Range', array(
            'label'    => __( 'Range Slider', 'bits-accessible-forms' ),
            'category' => 'standard',
            'icon'     => 'slider',
            'priority' => 100,
        ) );

        // Choice Fields
        $this->register( 'select', 'BAF_Field_Select', array(
            'label'    => __( 'Dropdown', 'bits-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'dropdown',
            'priority' => 10,
        ) );

        $this->register( 'multiselect', 'BAF_Field_Multiselect', array(
            'label'    => __( 'Multi-Select', 'bits-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'multiselect',
            'priority' => 20,
        ) );

        $this->register( 'radio', 'BAF_Field_Radio', array(
            'label'    => __( 'Radio Buttons', 'bits-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'radio',
            'priority' => 30,
        ) );

        $this->register( 'checkbox', 'BAF_Field_Checkbox', array(
            'label'    => __( 'Checkboxes', 'bits-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'checkbox',
            'priority' => 40,
        ) );

        $this->register( 'consent', 'BAF_Field_Consent', array(
            'label'    => __( 'Consent / Terms', 'bits-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'consent',
            'priority' => 50,
        ) );

        $this->register( 'toggle', 'BAF_Field_Toggle', array(
            'label'    => __( 'Toggle Switch', 'bits-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'toggle',
            'priority' => 55,
        ) );

        $this->register( 'likert', 'BAF_Field_Likert', array(
            'label'    => __( 'Likert Scale', 'bits-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'scale',
            'priority' => 60,
        ) );

        $this->register( 'ranking', 'BAF_Field_Ranking', array(
            'label'    => __( 'Ranking', 'bits-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'ranking',
            'priority' => 70,
        ) );

        $this->register( 'rating', 'BAF_Field_Rating', array(
            'label'    => __( 'Star Rating', 'bits-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'star',
            'priority' => 80,
        ) );

        $this->register( 'nps', 'BAF_Field_NPS', array(
            'label'    => __( 'Net Promoter Score', 'bits-accessible-forms' ),
            'category' => 'choice',
            'icon'     => 'nps',
            'priority' => 90,
        ) );

        // Date/Time Fields
        $this->register( 'date', 'BAF_Field_Date', array(
            'label'    => __( 'Date Picker', 'bits-accessible-forms' ),
            'category' => 'date_time',
            'icon'     => 'calendar',
            'priority' => 10,
        ) );

        $this->register( 'time', 'BAF_Field_Time', array(
            'label'    => __( 'Time Picker', 'bits-accessible-forms' ),
            'category' => 'date_time',
            'icon'     => 'clock',
            'priority' => 20,
        ) );

        $this->register( 'datetime', 'BAF_Field_DateTime', array(
            'label'    => __( 'Date & Time', 'bits-accessible-forms' ),
            'category' => 'date_time',
            'icon'     => 'datetime',
            'priority' => 30,
        ) );

        $this->register( 'daterange', 'BAF_Field_DateRange', array(
            'label'    => __( 'Date Range', 'bits-accessible-forms' ),
            'category' => 'date_time',
            'icon'     => 'range',
            'priority' => 40,
        ) );

        // Advanced Fields
        $this->register( 'name', 'BAF_Field_Name', array(
            'label'    => __( 'Name', 'bits-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'user',
            'priority' => 10,
        ) );

        $this->register( 'address', 'BAF_Field_Address', array(
            'label'    => __( 'Address', 'bits-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'home',
            'priority' => 20,
        ) );

        $this->register( 'fileupload', 'BAF_Field_FileUpload', array(
            'label'    => __( 'File Upload', 'bits-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'upload',
            'priority' => 30,
        ) );

        $this->register( 'signature', 'BAF_Field_Signature', array(
            'label'    => __( 'Signature', 'bits-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'signature',
            'priority' => 40,
        ) );

        $this->register( 'captcha', 'BAF_Field_Captcha', array(
            'label'    => __( 'Captcha', 'bits-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'shield',
            'priority' => 50,
        ) );

        $this->register( 'repeater', 'BAF_Field_Repeater', array(
            'label'    => __( 'Repeater', 'bits-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'repeat',
            'priority' => 60,
        ) );

        $this->register( 'calculation', 'BAF_Field_Calculation', array(
            'label'    => __( 'Calculation', 'bits-accessible-forms' ),
            'category' => 'advanced',
            'icon'     => 'calculator',
            'priority' => 70,
        ) );

        // Pricing Fields
        $this->register( 'product', 'BAF_Field_Product', array(
            'label'    => __( 'Product', 'bits-accessible-forms' ),
            'category' => 'pricing',
            'icon'     => 'product',
            'priority' => 10,
        ) );

        $this->register( 'quantity', 'BAF_Field_Quantity', array(
            'label'    => __( 'Quantity', 'bits-accessible-forms' ),
            'category' => 'pricing',
            'icon'     => 'quantity',
            'priority' => 20,
        ) );

        $this->register( 'price', 'BAF_Field_Price', array(
            'label'    => __( 'Price', 'bits-accessible-forms' ),
            'category' => 'pricing',
            'icon'     => 'currency',
            'priority' => 30,
        ) );

        $this->register( 'total', 'BAF_Field_Total', array(
            'label'    => __( 'Total', 'bits-accessible-forms' ),
            'category' => 'pricing',
            'icon'     => 'total',
            'priority' => 40,
        ) );

        $this->register( 'coupon', 'BAF_Field_Coupon', array(
            'label'    => __( 'Coupon', 'bits-accessible-forms' ),
            'category' => 'pricing',
            'icon'     => 'coupon',
            'priority' => 50,
        ) );

        // Layout Elements
        $this->register( 'section', 'BAF_Field_Section', array(
            'label'    => __( 'Section Break', 'bits-accessible-forms' ),
            'category' => 'layout',
            'icon'     => 'section',
            'priority' => 10,
        ) );

        $this->register( 'page', 'BAF_Field_Page', array(
            'label'    => __( 'Page Break', 'bits-accessible-forms' ),
            'category' => 'layout',
            'icon'     => 'page',
            'priority' => 20,
        ) );

        $this->register( 'html', 'BAF_Field_HTML', array(
            'label'    => __( 'HTML', 'bits-accessible-forms' ),
            'category' => 'layout',
            'icon'     => 'code',
            'priority' => 30,
        ) );

        $this->register( 'columns', 'BAF_Field_Columns', array(
            'label'    => __( 'Columns', 'bits-accessible-forms' ),
            'category' => 'layout',
            'icon'     => 'columns',
            'priority' => 40,
        ) );

        // Form Helpers
        $this->register( 'submit', 'BAF_Field_Submit', array(
            'label'    => __( 'Submit Button', 'bits-accessible-forms' ),
            'category' => 'form_helper',
            'icon'     => 'button',
            'priority' => 10,
        ) );

        $this->register( 'save', 'BAF_Field_Save', array(
            'label'    => __( 'Save Progress Button', 'bits-accessible-forms' ),
            'category' => 'form_helper',
            'icon'     => 'save',
            'priority' => 20,
        ) );

        // Post Fields
        $this->register( 'post_title', 'BAF_Field_PostTitle', array(
            'label'    => __( 'Post Title', 'bits-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'title',
            'priority' => 10,
        ) );

        $this->register( 'post_content', 'BAF_Field_PostContent', array(
            'label'    => __( 'Post Content', 'bits-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'content',
            'priority' => 20,
        ) );

        $this->register( 'post_excerpt', 'BAF_Field_PostExcerpt', array(
            'label'    => __( 'Post Excerpt', 'bits-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'excerpt',
            'priority' => 30,
        ) );

        $this->register( 'post_category', 'BAF_Field_PostCategory', array(
            'label'    => __( 'Post Category', 'bits-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'category',
            'priority' => 40,
        ) );

        $this->register( 'post_tags', 'BAF_Field_PostTags', array(
            'label'    => __( 'Post Tags', 'bits-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'tags',
            'priority' => 50,
        ) );

        $this->register( 'post_image', 'BAF_Field_PostImage', array(
            'label'    => __( 'Featured Image', 'bits-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'image',
            'priority' => 60,
        ) );

        $this->register( 'post_custom', 'BAF_Field_PostCustom', array(
            'label'    => __( 'Custom Field', 'bits-accessible-forms' ),
            'category' => 'post',
            'icon'     => 'custom',
            'priority' => 70,
        ) );

        // User Fields
        $this->register( 'user_login', 'BAF_Field_UserLogin', array(
            'label'    => __( 'Username', 'bits-accessible-forms' ),
            'category' => 'user',
            'icon'     => 'user',
            'priority' => 10,
        ) );

        $this->register( 'user_email', 'BAF_Field_UserEmail', array(
            'label'    => __( 'User Email', 'bits-accessible-forms' ),
            'category' => 'user',
            'icon'     => 'email',
            'priority' => 20,
        ) );

        $this->register( 'user_password', 'BAF_Field_UserPassword', array(
            'label'    => __( 'User Password', 'bits-accessible-forms' ),
            'category' => 'user',
            'icon'     => 'lock',
            'priority' => 30,
        ) );

        /**
         * Allow plugins to register custom fields
         *
         * @param BAF_Field_Registry $registry Field registry instance.
         */
        do_action( 'baf_register_fields', $this );
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
     * @return BAF_Field|null Field instance or null if type not found.
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
        $file_name = 'class-baf-field-' . str_replace( '_', '-', $type ) . '.php';
        $file_path = BAF_PLUGIN_DIR . 'includes/fields/' . $file_name;

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
