<?php
// Minimal test script for BAF_BFL_Parser

// Load the necessary classes
require_once 'includes/bfl/class-baf-bfl-lexer.php';
require_once 'includes/bfl/class-baf-bfl-parser.php';

// Create parser instance
$parser = new BAF_BFL_Parser();

// Test Case 1: Indentation modifiers
$test1 = <<<'EOT'
form {
  section {
    text "Name" name;
    # indented with spaces
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
      # end col1
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
        echo json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
    
    if (!empty($result['errors'])) {
        echo "ERRORS:\n";
        foreach ($result['errors'] as $error) {
            echo "  - " . $error . "\n";
        }
    }
}
?>
