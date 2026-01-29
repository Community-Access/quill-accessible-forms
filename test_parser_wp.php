<?php
// Define WordPress constants for standalone usage
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

echo "Starting test...\n";

// Load the necessary classes
echo "Loading lexer...\n";
require_once 'includes/bfl/class-baf-bfl-lexer.php';

echo "Loading parser...\n";
require_once 'includes/bfl/class-baf-bfl-parser.php';

// Check if class exists
if (class_exists('BAF_BFL_Parser')) {
    echo "BAF_BFL_Parser class found\n";
    $parser = new BAF_BFL_Parser();
    echo "Parser instantiated successfully\n";
    
    // Test Case 1: Indentation modifiers
    $test1 = <<<'EOT'
form {
  section {
    text "Name" name;
    email "Email" email;
  }
}
EOT;

    // Test Case 2: Brace modifier block
    $test2 = <<<'EOT'
form {
  page 1 {
    text "Field" field1 { required; }
    select "Choose" field2 { option "A" a; option "B" b; }
  }
}
EOT;

    // Test Case 3: Columns with braces and column braces
    $test3 = <<<'EOT'
form {
  page 1 {
    columns 2 {
      text "First" col1;
      text "Second" col2;
    }
  }
}
EOT;

    // Test Case 4: Conditional begin/end
    $test4 = <<<'EOT'
form {
  page 1 {
    text "Show if" base_field;
    conditional base_field {
      text "Conditional" cond_field;
    }
  }
}
EOT;

    // Parse all test cases
    $tests = [
        'Test 1: Indentation modifiers' => $test1,
        'Test 2: Brace modifier block' => $test2,
        'Test 3: Columns with braces' => $test3,
        'Test 4: Conditional begin/end' => $test4,
    ];

    foreach ($tests as $label => $source) {
        echo "\n=== " . $label . " ===\n";
        
        $result = $parser->parse($source);
        
        if (isset($result['form']) && isset($result['form']['fields'])) {
            $fields = $result['form']['fields'];
            echo "Fields:\n";
            echo json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        }
        
        if (!empty($result['errors'])) {
            echo "ERRORS:\n";
            foreach ($result['errors'] as $error) {
                echo "  - " . $error . "\n";
            }
        }
    }
} else {
    echo "BAF_BFL_Parser class NOT found\n";
}

echo "\nTest complete\n";
?>
