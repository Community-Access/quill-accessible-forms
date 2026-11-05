<?php
/**
 * CSV Form Builder Tests
 *
 * @package QAF
 * @subpackage Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Mock WordPress functions if not available
if ( ! function_exists( 'wp_parse_args' ) ) {
    function wp_parse_args( $args, $defaults = array() ) {
        if ( is_object( $args ) ) {
            $r = get_object_vars( $args );
        } elseif ( is_array( $args ) ) {
            $r =& $args;
        } else {
            parse_str( $args, $r );
        }
        return array_merge( $defaults, $r );
    }
}

if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( $title ) {
        $title = strip_tags( $title );
        $title = preg_replace( '/\s+/', '-', $title );
        $title = preg_replace( '/[^a-z0-9\-_]/', '', strtolower( $title ) );
        return trim( $title, '-' );
    }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $code;
        private $message;
        public function __construct( $code = '', $message = '' ) {
            $this->code = $code;
            $this->message = $message;
        }
        public function get_error_code() { return $this->code; }
        public function get_error_message() { return $this->message; }
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

// Include the class under test
require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/includes/import/class-qaf-csv-form-builder.php';

/**
 * Test class for QAF_CSV_Form_Builder
 */
class CSVFormBuilderTest extends TestCase {

    /**
     * @var QAF_CSV_Form_Builder
     */
    private $builder;

    /**
     * @var string
     */
    private $temp_dir;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        $this->builder = new QAF_CSV_Form_Builder();
        $this->temp_dir = sys_get_temp_dir() . '/qaf_csv_tests_' . uniqid();
        mkdir( $this->temp_dir );
    }

    /**
     * Tear down test fixtures
     */
    protected function tearDown(): void {
        // Clean up temp files
        $files = glob( $this->temp_dir . '/*' );
        foreach ( $files as $file ) {
            unlink( $file );
        }
        rmdir( $this->temp_dir );
    }

    /**
     * Create a temporary CSV file
     */
    private function create_csv( string $content ): string {
        $file = $this->temp_dir . '/test_' . uniqid() . '.csv';
        file_put_contents( $file, $content );
        return $file;
    }

    // ========================================
    // Basic Analysis Tests
    // ========================================

    /**
     * Test basic CSV analysis
     */
    public function test_analyze_basic_csv(): void {
        $csv = $this->create_csv( <<<CSV
Name,Email,Age
John Doe,john@example.com,30
Jane Smith,jane@example.com,25
Bob Wilson,bob@example.com,35
CSV
        );

        $result = $this->builder->analyze( $csv );

        $this->assertIsArray( $result );
        $this->assertEquals( 3, count( $result['columns'] ) );
        $this->assertEquals( 3, $result['total_rows'] );
        $this->assertEquals( array( 'Name', 'Email', 'Age' ), $result['headers'] );
    }

    /**
     * Test analysis with missing file
     */
    public function test_analyze_missing_file(): void {
        $result = $this->builder->analyze( '/nonexistent/file.csv' );
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertEquals( 'file_not_found', $result->get_error_code() );
    }

    /**
     * Test analysis with BOM
     */
    public function test_analyze_csv_with_bom(): void {
        $csv = $this->create_csv( "\xEF\xBB\xBF" . "Name,Email\nJohn,john@example.com" );
        $result = $this->builder->analyze( $csv );

        $this->assertIsArray( $result );
        $this->assertEquals( 'Name', $result['headers'][0] );
    }

    /**
     * Test CSV without headers
     */
    public function test_analyze_csv_without_headers(): void {
        $csv = $this->create_csv( "John,john@example.com,30\nJane,jane@example.com,25" );
        $result = $this->builder->analyze( $csv, array( 'has_header' => false ) );

        $this->assertIsArray( $result );
        $this->assertEquals( 'Column 1', $result['headers'][0] );
        $this->assertEquals( 'Column 2', $result['headers'][1] );
        $this->assertEquals( 2, $result['total_rows'] );
    }

    // ========================================
    // Type Inference Tests
    // ========================================

    /**
     * Test email type inference
     */
    public function test_infer_email_type(): void {
        $csv = $this->create_csv( <<<CSV
Contact Email
john@example.com
jane@example.org
bob@company.net
CSV
        );

        $result = $this->builder->analyze( $csv );
        $this->assertEquals( 'email', $result['columns'][0]['suggested_type'] );
    }

    /**
     * Test phone type inference
     */
    public function test_infer_phone_type(): void {
        $csv = $this->create_csv( <<<CSV
Phone Number
555-123-4567
(555) 987-6543
+1 555 123 4567
CSV
        );

        $result = $this->builder->analyze( $csv );
        $this->assertEquals( 'phone', $result['columns'][0]['suggested_type'] );
    }

    /**
     * Test number type inference
     */
    public function test_infer_number_type(): void {
        $csv = $this->create_csv( <<<CSV
Age,Amount
25,100.50
30,250.75
35,300.00
CSV
        );

        $result = $this->builder->analyze( $csv );
        $this->assertEquals( 'number', $result['columns'][0]['suggested_type'] );
        $this->assertEquals( 'number', $result['columns'][1]['suggested_type'] );
    }

    /**
     * Test date type inference
     */
    public function test_infer_date_type(): void {
        $csv = $this->create_csv( <<<CSV
Birth Date,Created
1990-01-15,2024-01-01
1985-06-20,2024-01-02
1992-12-25,2024-01-03
CSV
        );

        $result = $this->builder->analyze( $csv );
        $this->assertEquals( 'date', $result['columns'][0]['suggested_type'] );
        $this->assertEquals( 'date', $result['columns'][1]['suggested_type'] );
    }

    /**
     * Test URL type inference
     */
    public function test_infer_url_type(): void {
        $csv = $this->create_csv( <<<CSV
Website
https://example.com
http://example.org
www.example.net
CSV
        );

        $result = $this->builder->analyze( $csv );
        $this->assertEquals( 'url', $result['columns'][0]['suggested_type'] );
    }

    /**
     * Test textarea inference from header
     */
    public function test_infer_textarea_from_header(): void {
        $csv = $this->create_csv( <<<CSV
Comments,Description,Notes
Short text,Another text,Some notes
More text,More description,More notes
CSV
        );

        $result = $this->builder->analyze( $csv );
        $this->assertEquals( 'textarea', $result['columns'][0]['suggested_type'] );
        $this->assertEquals( 'textarea', $result['columns'][1]['suggested_type'] );
        $this->assertEquals( 'textarea', $result['columns'][2]['suggested_type'] );
    }

    // ========================================
    // Choice Field Detection Tests
    // ========================================

    /**
     * Test dropdown detection
     */
    public function test_detect_dropdown_field(): void {
        $csv = $this->create_csv( <<<CSV
Status
Active
Inactive
Pending
Active
Inactive
Active
Pending
Active
Inactive
Active
CSV
        );

        $result = $this->builder->analyze( $csv );
        $this->assertEquals( 'select', $result['columns'][0]['suggested_type'] );
        $this->assertEquals( 3, count( $result['columns'][0]['choices'] ) );
    }

    /**
     * Test radio detection (2 values)
     */
    public function test_detect_radio_field(): void {
        $csv = $this->create_csv( <<<CSV
Active
Yes
No
Yes
No
Yes
Yes
No
CSV
        );

        $result = $this->builder->analyze( $csv );
        $type = $result['columns'][0]['suggested_type'];
        // Could be radio, checkbox, or boolean-detected
        $this->assertContains( $type, array( 'radio', 'checkbox', 'select' ) );
    }

    /**
     * Test no dropdown for high cardinality
     */
    public function test_no_dropdown_for_unique_values(): void {
        $csv = $this->create_csv( <<<CSV
Full Name
John Smith
Jane Doe
Bob Wilson
Alice Brown
Charlie Davis
Eve Johnson
Frank Miller
Grace Lee
Henry Taylor
Ivy Anderson
Jack Thomas
Kate Martin
Leo Garcia
Mia Rodriguez
Nick Martinez
Olivia Hernandez
Peter Lopez
Quinn Gonzalez
Rose Wilson
Sam Moore
Tom Jackson
CSV
        );

        $result = $this->builder->analyze( $csv );
        // Should remain text due to high unique count
        $this->assertContains( $result['columns'][0]['suggested_type'], array( 'text', 'name' ) );
        $this->assertEmpty( $result['columns'][0]['choices'] );
    }

    // ========================================
    // Cascade Detection Tests
    // ========================================

    /**
     * Test country/state cascade detection
     */
    public function test_detect_country_state_cascade(): void {
        $csv = $this->create_csv( <<<CSV
Country,State
USA,California
USA,Texas
USA,New York
Canada,Ontario
Canada,Quebec
Canada,British Columbia
Mexico,Jalisco
Mexico,Mexico City
CSV
        );

        $result = $this->builder->analyze( $csv );
        $this->assertCount( 1, $result['cascades'] );

        $cascade = $result['cascades'][0];
        $this->assertEquals( 'Country', $cascade['parent_column'] );
        $this->assertEquals( 'State', $cascade['child_column'] );
        $this->assertArrayHasKey( 'USA', $cascade['mapping'] );
        $this->assertContains( 'California', $cascade['mapping']['USA'] );
    }

    /**
     * Test category/subcategory cascade
     */
    public function test_detect_category_subcategory_cascade(): void {
        $csv = $this->create_csv( <<<CSV
Category,Subcategory
Electronics,Phones
Electronics,Laptops
Electronics,Tablets
Clothing,Shirts
Clothing,Pants
Clothing,Shoes
Food,Fruits
Food,Vegetables
CSV
        );

        $result = $this->builder->analyze( $csv );
        $this->assertCount( 1, $result['cascades'] );
        $this->assertEquals( 'Category', $result['cascades'][0]['parent_column'] );
        $this->assertEquals( 'Subcategory', $result['cascades'][0]['child_column'] );
    }

    // ========================================
    // Required Field Detection Tests
    // ========================================

    /**
     * Test required field detection
     */
    public function test_detect_required_fields(): void {
        $csv = $this->create_csv( <<<CSV
Required Field,Optional Field
value1,
value2,data
value3,
CSV
        );

        $result = $this->builder->analyze( $csv );
        $this->assertTrue( $result['columns'][0]['is_required'] );
        $this->assertFalse( $result['columns'][1]['is_required'] );
    }

    // ========================================
    // Form Generation Tests
    // ========================================

    /**
     * Test basic form generation
     */
    public function test_generate_form_basic(): void {
        $csv = $this->create_csv( <<<CSV
Name,Email,Age
John,john@example.com,30
Jane,jane@example.com,25
CSV
        );

        $analysis = $this->builder->analyze( $csv );
        $form = $this->builder->generate_form( $analysis, array(
            'title' => 'Test Form',
        ));

        $this->assertEquals( 'Test Form', $form['title'] );
        $this->assertCount( 4, $form['fields'] ); // 3 fields + submit
        $this->assertEquals( 'submit', $form['fields'][3]['type'] );
    }

    /**
     * Test form generation with column exclusion
     */
    public function test_generate_form_exclude_columns(): void {
        $csv = $this->create_csv( <<<CSV
Name,Email,Internal ID
John,john@example.com,ABC123
Jane,jane@example.com,DEF456
CSV
        );

        $analysis = $this->builder->analyze( $csv );
        $form = $this->builder->generate_form( $analysis, array(
            'exclude_columns' => array( 2 ), // Exclude Internal ID
        ));

        // Should have Name, Email, Submit (3 total)
        $this->assertCount( 3, $form['fields'] );
    }

    /**
     * Test form generation with field overrides
     */
    public function test_generate_form_with_overrides(): void {
        $csv = $this->create_csv( <<<CSV
Name
John
Jane
CSV
        );

        $analysis = $this->builder->analyze( $csv );
        $form = $this->builder->generate_form( $analysis, array(
            'field_overrides' => array(
                0 => array(
                    'placeholder' => 'Enter your name',
                    'required'    => true,
                ),
            ),
        ));

        $this->assertEquals( 'Enter your name', $form['fields'][0]['placeholder'] );
        $this->assertTrue( $form['fields'][0]['required'] );
    }

    /**
     * Test form generation with cascade
     */
    public function test_generate_form_with_cascade(): void {
        $csv = $this->create_csv( <<<CSV
Country,State
USA,California
USA,Texas
Canada,Ontario
Canada,Quebec
CSV
        );

        $analysis = $this->builder->analyze( $csv );
        $form = $this->builder->generate_form( $analysis );

        // Find state field
        $state_field = null;
        foreach ( $form['fields'] as $field ) {
            if ( $field['id'] === 'state' ) {
                $state_field = $field;
                break;
            }
        }

        $this->assertNotNull( $state_field );
        $this->assertEquals( 'country', $state_field['cascade_from'] );
        $this->assertArrayHasKey( 'cascade_mapping', $state_field );
    }

    // ========================================
    // BFL Generation Tests
    // ========================================

    /**
     * Test BFL generation
     */
    public function test_generate_bfl(): void {
        $csv = $this->create_csv( <<<CSV
Full Name,Email Address
John Doe,john@example.com
Jane Smith,jane@example.com
CSV
        );

        $analysis = $this->builder->analyze( $csv );
        $form = $this->builder->generate_form( $analysis, array(
            'title' => 'Contact Form',
        ));
        $bfl = $this->builder->generate_bfl( $form );

        $this->assertStringContainsString( 'form "Contact Form"', $bfl );
        $this->assertStringContainsString( '#full_name', $bfl );
        $this->assertStringContainsString( '#email_address', $bfl );
        $this->assertStringContainsString( 'submit', $bfl );
    }

    /**
     * Test BFL generation with choices
     */
    public function test_generate_bfl_with_choices(): void {
        $csv = $this->create_csv( <<<CSV
Status
Active
Inactive
Pending
Active
Inactive
CSV
        );

        $analysis = $this->builder->analyze( $csv );
        $form = $this->builder->generate_form( $analysis );
        $bfl = $this->builder->generate_bfl( $form );

        $this->assertStringContainsString( 'select', $bfl );
        $this->assertStringContainsString( '+ Active', $bfl );
        $this->assertStringContainsString( '+ Inactive', $bfl );
        $this->assertStringContainsString( '+ Pending', $bfl );
    }

    /**
     * Test BFL generation with required fields
     */
    public function test_generate_bfl_with_required(): void {
        $csv = $this->create_csv( <<<CSV
Name
John
Jane
Bob
CSV
        );

        $analysis = $this->builder->analyze( $csv );
        $form = $this->builder->generate_form( $analysis );
        $bfl = $this->builder->generate_bfl( $form );

        $this->assertStringContainsString( '@required', $bfl );
    }

    // ========================================
    // Field ID Generation Tests
    // ========================================

    /**
     * Test field ID generation
     */
    public function test_field_id_generation(): void {
        $csv = $this->create_csv( <<<CSV
First Name,Last Name,Email Address,123 Number
John,Doe,john@example.com,100
CSV
        );

        $analysis = $this->builder->analyze( $csv );

        $this->assertEquals( 'first_name', $analysis['columns'][0]['field_id'] );
        $this->assertEquals( 'last_name', $analysis['columns'][1]['field_id'] );
        $this->assertEquals( 'email_address', $analysis['columns'][2]['field_id'] );
        $this->assertEquals( 'field_123_number', $analysis['columns'][3]['field_id'] );
    }

    // ========================================
    // Report Generation Tests
    // ========================================

    /**
     * Test report generation
     */
    public function test_get_report(): void {
        $csv = $this->create_csv( <<<CSV
Name,Email,Status,Country,State
John,john@example.com,Active,USA,California
Jane,jane@example.com,Inactive,USA,Texas
Bob,bob@example.com,Active,Canada,Ontario
CSV
        );

        $analysis = $this->builder->analyze( $csv );
        $report = $this->builder->get_report( $analysis );

        $this->assertArrayHasKey( 'summary', $report );
        $this->assertArrayHasKey( 'columns', $report );
        $this->assertEquals( 5, $report['summary']['total_columns'] );
        $this->assertEquals( 3, $report['summary']['total_rows'] );
    }

    // ========================================
    // Edge Cases
    // ========================================

    /**
     * Test empty CSV
     */
    public function test_empty_csv(): void {
        $csv = $this->create_csv( "" );
        $result = $this->builder->analyze( $csv );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result['columns'] );
    }

    /**
     * Test CSV with only headers
     */
    public function test_headers_only_csv(): void {
        $csv = $this->create_csv( "Name,Email,Age" );
        $result = $this->builder->analyze( $csv );

        $this->assertIsArray( $result );
        $this->assertEquals( 3, count( $result['columns'] ) );
        $this->assertEquals( 0, $result['total_rows'] );
    }

    /**
     * Test CSV with special characters in headers
     */
    public function test_special_characters_in_headers(): void {
        $csv = $this->create_csv( <<<CSV
"Name (Full)","Email Address!","Age #"
John Doe,john@example.com,30
CSV
        );

        $result = $this->builder->analyze( $csv );
        $this->assertEquals( 'name_full', $result['columns'][0]['field_id'] );
    }

    /**
     * Test tab-delimited file
     */
    public function test_tab_delimited(): void {
        $csv = $this->create_csv( "Name\tEmail\tAge\nJohn\tjohn@example.com\t30" );
        $result = $this->builder->analyze( $csv, array( 'delimiter' => "\t" ) );

        $this->assertIsArray( $result );
        $this->assertEquals( 3, count( $result['columns'] ) );
        $this->assertEquals( array( 'Name', 'Email', 'Age' ), $result['headers'] );
    }

    /**
     * Test semicolon-delimited file
     */
    public function test_semicolon_delimited(): void {
        $csv = $this->create_csv( "Name;Email;Age\nJohn;john@example.com;30" );
        $result = $this->builder->analyze( $csv, array( 'delimiter' => ';' ) );

        $this->assertIsArray( $result );
        $this->assertEquals( 3, count( $result['columns'] ) );
    }

    /**
     * Test mixed date formats
     */
    public function test_mixed_date_formats(): void {
        $csv = $this->create_csv( <<<CSV
Event Date
2024-01-15
01/20/2024
January 25, 2024
CSV
        );

        $result = $this->builder->analyze( $csv );
        $this->assertEquals( 'date', $result['columns'][0]['suggested_type'] );
    }

    /**
     * Test currency values detected as numbers
     */
    public function test_currency_as_number(): void {
        $csv = $this->create_csv( <<<CSV
Price
$100.00
$250.50
$75.99
CSV
        );

        $result = $this->builder->analyze( $csv );
        $this->assertEquals( 'number', $result['columns'][0]['suggested_type'] );
    }
}
