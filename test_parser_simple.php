<?php
echo "Starting test...\n";

// Try to load lexer class
echo "Loading lexer...\n";
if (file_exists('includes/bfl/class-baf-bfl-lexer.php')) {
    echo "Lexer file found\n";
    require_once 'includes/bfl/class-baf-bfl-lexer.php';
    echo "Lexer loaded\n";
} else {
    echo "Lexer file NOT found\n";
}

// Try to load parser class
echo "Loading parser...\n";
if (file_exists('includes/bfl/class-baf-bfl-parser.php')) {
    echo "Parser file found\n";
    require_once 'includes/bfl/class-baf-bfl-parser.php';
    echo "Parser class loaded\n";
} else {
    echo "Parser file NOT found\n";
}

// Check if class exists
if (class_exists('BAF_BFL_Parser')) {
    echo "BAF_BFL_Parser class found\n";
    $parser = new BAF_BFL_Parser();
    echo "Parser instantiated successfully\n";
    
    // Try simple parse
    $source = 'form { }';
    echo "Parsing: " . $source . "\n";
    $result = $parser->parse($source);
    echo "Parse result: " . json_encode($result) . "\n";
} else {
    echo "BAF_BFL_Parser class NOT found\n";
}

echo "Test complete\n";
?>
