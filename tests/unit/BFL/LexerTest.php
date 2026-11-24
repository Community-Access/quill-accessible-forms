<?php
/**
 * BFL Lexer Tests
 *
 * @package QAF
 */

class QAF_BFL_Lexer_Test extends QAF_Unit_Test_Case {

    /**
     * Lexer instance
     *
     * @var QAF_BFL_Lexer
     */
    private $lexer;

    protected function setUp(): void {
        parent::setUp();
        
        require_once QAF_PLUGIN_DIR . 'includes/bfl/class-qaf-bfl-lexer.php';
        $this->lexer = new QAF_BFL_Lexer();
    }

    public function test_tokenize_empty_string() {
        $tokens = $this->lexer->tokenize( '' );
        
        $this->assertIsArray( $tokens );
        $this->assertCount( 1, $tokens );
        $this->assertEquals( 'EOF', $tokens[0]['type'] );
    }

    public function test_tokenize_form_keyword() {
        $tokens = $this->lexer->tokenize( 'form' );
        
        $this->assertTokenExists( $tokens, 'KEYWORD', 'form' );
    }

    public function test_tokenize_field_types() {
        $field_types = array( 'text', 'email', 'number', 'textarea', 'select', 'radio', 'checkbox' );
        
        foreach ( $field_types as $type ) {
            $tokens = $this->lexer->tokenize( $type );
            $this->assertTokenExists( $tokens, 'KEYWORD', $type );
        }
    }

    public function test_tokenize_string() {
        $tokens = $this->lexer->tokenize( '"Hello World"' );
        
        $this->assertTokenExists( $tokens, 'STRING', 'Hello World' );
    }

    public function test_tokenize_number() {
        $tokens = $this->lexer->tokenize( '42' );
        
        $this->assertTokenExists( $tokens, 'NUMBER', '42' );
    }

    public function test_tokenize_identifier() {
        $tokens = $this->lexer->tokenize( '#my_field' );

        $this->assertTokenExists( $tokens, 'IDENTIFIER', 'my_field' );
    }

    public function test_tokenize_braces() {
        $tokens = $this->lexer->tokenize( '{ }' );
        
        $this->assertTokenExists( $tokens, 'BRACE_OPEN', '{' );
        $this->assertTokenExists( $tokens, 'BRACE_CLOSE', '}' );
    }

    public function test_tokenize_modifiers() {
        $modifiers = array( 'required', 'readonly', 'hidden' );
        
        foreach ( $modifiers as $modifier ) {
            $tokens = $this->lexer->tokenize( '@' . $modifier );
            $this->assertTokenExists( $tokens, 'MODIFIER', $modifier );
        }
    }

    public function test_tokenize_simple_form() {
        $bfl = 'form "Test"' . "\n" . 'text #name "Name" @required';
        $tokens = $this->lexer->tokenize( $bfl );

        $this->assertTokenExists( $tokens, 'KEYWORD', 'form' );
        $this->assertTokenExists( $tokens, 'STRING', 'Test' );
        $this->assertTokenExists( $tokens, 'KEYWORD', 'text' );
        $this->assertTokenExists( $tokens, 'IDENTIFIER', 'name' );
        $this->assertTokenExists( $tokens, 'STRING', 'Name' );
        $this->assertTokenExists( $tokens, 'MODIFIER', 'required' );
    }

    public function test_tokenize_preserves_line_numbers() {
        $bfl = "form\n\"Test\"\n{\n}";
        $tokens = $this->lexer->tokenize( $bfl );
        
        $form_token = $this->findToken( $tokens, 'KEYWORD', 'form' );
        $this->assertEquals( 1, $form_token['line'] );
        
        $string_token = $this->findToken( $tokens, 'STRING', 'Test' );
        $this->assertEquals( 2, $string_token['line'] );
    }

    public function test_tokenize_comments() {
        $bfl = "form \"Test\"\n// This is a comment\ntext #name \"Name\"";
        $tokens = $this->lexer->tokenize( $bfl );

        // The lexer keeps the comment as one token, on its own line...
        $comment = $this->findToken( $tokens, 'COMMENT', '// This is a comment' );
        $this->assertNotNull( $comment );
        $this->assertEquals( 2, $comment['line'] );

        // ...and the parser skips it.
        require_once QAF_PLUGIN_DIR . 'includes/bfl/class-qaf-bfl-parser.php';
        $parser = new QAF_BFL_Parser();
        $result = $parser->parse( $bfl );
        $this->assertEmpty( $result['errors'] );
        $this->assertCount( 1, $result['form']['fields'] );
        $this->assertEquals( 'name', $result['form']['fields'][0]['id'] );
    }

    public function test_tokenize_multiline_string() {
        $bfl = 'description "Line 1\nLine 2"';
        $tokens = $this->lexer->tokenize( $bfl );
        
        $this->assertTokenExists( $tokens, 'STRING', "Line 1\nLine 2" );
    }

    public function test_tokenize_escaped_quotes() {
        $bfl = 'label "Say \"Hello\""';
        $tokens = $this->lexer->tokenize( $bfl );
        
        $this->assertTokenExists( $tokens, 'STRING', 'Say "Hello"' );
    }

    /**
     * Assert token exists in token list
     */
    private function assertTokenExists( $tokens, $type, $value ) {
        $found = $this->findToken( $tokens, $type, $value );
        $this->assertNotNull( $found, "Token {$type}:{$value} should exist" );
    }

    /**
     * Find token by type and value
     */
    private function findToken( $tokens, $type, $value ) {
        foreach ( $tokens as $token ) {
            if ( $token['type'] === $type && $token['value'] === $value ) {
                return $token;
            }
        }
        return null;
    }
}
