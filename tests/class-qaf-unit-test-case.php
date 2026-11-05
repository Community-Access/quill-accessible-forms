<?php
/**
 * Base test case for QAF unit tests
 *
 * @package QAF
 */

use PHPUnit\Framework\TestCase;

/**
 * QAF_Unit_Test_Case class
 *
 * Base class for unit tests that don't require WordPress.
 */
class QAF_Unit_Test_Case extends TestCase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
    }

    /**
     * Tear down after each test
     */
    protected function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Get mock form data
     *
     * @param array $overrides Override values.
     * @return array Form data.
     */
    protected function get_mock_form_data( $overrides = array() ) {
        return array_merge( array(
            'id'       => 1,
            'title'    => 'Mock Form',
            'slug'     => 'mock-form',
            'status'   => 'active',
            'settings' => array(),
            'fields'   => array(),
        ), $overrides );
    }

    /**
     * Get mock entry data
     *
     * @param array $overrides Override values.
     * @return array Entry data.
     */
    protected function get_mock_entry_data( $overrides = array() ) {
        return array_merge( array(
            'id'           => 1,
            'form_id'      => 1,
            'status'       => 'new',
            'field_values' => array(),
            'created_at'   => date( 'Y-m-d H:i:s' ),
        ), $overrides );
    }

    /**
     * Assert array has keys
     *
     * @param array $keys  Expected keys.
     * @param array $array Array to check.
     */
    protected function assertArrayHasKeys( $keys, $array ) {
        foreach ( $keys as $key ) {
            $this->assertArrayHasKey( $key, $array, "Array should have key: {$key}" );
        }
    }

    /**
     * Assert array values match
     *
     * @param array $expected Expected values.
     * @param array $actual   Actual array.
     */
    protected function assertArrayValuesMatch( $expected, $actual ) {
        foreach ( $expected as $key => $value ) {
            $this->assertArrayHasKey( $key, $actual );
            $this->assertEquals( $value, $actual[ $key ], "Value for {$key} should match" );
        }
    }

    /**
     * Assert string is valid JSON
     *
     * @param string $string String to check.
     */
    protected function assertValidJson( $string ) {
        json_decode( $string );
        $this->assertEquals( JSON_ERROR_NONE, json_last_error(), 'String should be valid JSON' );
    }

    /**
     * Assert string is valid BFL
     *
     * @param string $bfl BFL source.
     */
    protected function assertValidBfl( $bfl ) {
        $lexer = new QAF_BFL_Lexer();
        $tokens = $lexer->tokenize( $bfl );
        $this->assertNotEmpty( $tokens, 'BFL should tokenize' );

        $parser = new QAF_BFL_Parser();
        $result = $parser->parse( $tokens );
        $this->assertNotEmpty( $result, 'BFL should parse' );
    }

    /**
     * Create mock object with methods
     *
     * @param string $class   Class name.
     * @param array  $methods Methods to mock.
     * @return object Mock object.
     */
    protected function create_mock( $class, $methods = array() ) {
        $mock = $this->createMock( $class );

        foreach ( $methods as $method => $return ) {
            $mock->method( $method )->willReturn( $return );
        }

        return $mock;
    }
}
