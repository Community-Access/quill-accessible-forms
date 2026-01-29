<?php
// Define WordPress constants for standalone usage
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

// Load the necessary classes
require_once 'includes/bfl/class-baf-bfl-lexer.php';
require_once 'includes/bfl/class-baf-bfl-parser.php';

$parser = new BAF_BFL_Parser();

// Test Case 1: Real BFL syntax with form declaration, field types, #ids, and @modifiers
$test1 = <<<'EOT'
form "Contact Form"

text #name "Your Name"
  @required
  @placeholder "John Doe"

email #email "Email Address"
  @required

textarea #message "Your Message"
  @required
  @minlength 20
  @maxlength 1000
  @rows 5
EOT;

// Test Case 2: Choice fields with + choices syntax
$test2 = <<<'EOT'
form "Preferences"

select #country "Country"
  @required
  + USA|United States
  + CAN|Canada
  + MEX|Mexico

radio #size "Size"
  @required
  + small|Small
  + medium|Medium
  + large|Large
  + xl|Extra Large

checkbox #terms "Accept Terms"
  @required
EOT;

// Test Case 3: Columns/column blocks layout
$test3 = <<<'EOT'
form "Address Form"

columns 2
  column
    text #first_name "First Name"
      @required
    
    text #last_name "Last Name"
      @required
  
  column
    email #email "Email"
      @required
    
    phone #phone "Phone"
      @placeholder "(555) 123-4567"
EOT;

// Test Case 4: Conditional logic with begin/end blocks
$test4 = <<<'EOT'
form "Shipping"

select #ship_method "Shipping Method"
  @required
  + standard|Standard (5-7 days)
  + express|Express (2-3 days)
  + overnight|Overnight (Next day)

conditional #ship_method is "express"
  text #express_fee "Express Fee"
    @default "25.00"

conditional #ship_method is "overnight"
  text #overnight_fee "Overnight Fee"
    @default "50.00"
EOT;

// Test Case 5: Multiple field types with mixed modifiers
$test5 = <<<'EOT'
form "Registration"

section "Personal Information"

text #username "Username"
  @required
  @minlength 3
  @maxlength 20

password #password "Password"
  @required
  @minlength 8

number #age "Age"
  @min 18
  @max 120

date #birthdate "Birth Date"

checkbox #newsletter "Subscribe to Newsletter"
  @default "1"

section "Address"

text #street "Street Address"
  @required

columns 2
  column
    text #city "City"
      @required
  
  column
    text #zip "Zip Code"
      @required
EOT;

// Parse all test cases
$tests = [
    'Test 1: Form declaration + field types + #ids + @modifiers' => $test1,
    'Test 2: Choice fields with + choices syntax' => $test2,
    'Test 3: Columns/column blocks' => $test3,
    'Test 4: Conditional begin/end blocks' => $test4,
    'Test 5: Mixed field types with modifiers' => $test5,
];

$test_count = 0;
$pass_count = 0;
$fail_count = 0;

foreach ($tests as $label => $source) {
    $test_count++;
    echo "\n╔════════════════════════════════════════════════════════════╗\n";
    echo "║ " . str_pad($label, 58) . " ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    $result = $parser->parse($source);
    
    if (isset($result['form']) && isset($result['form']['fields'])) {
        echo "Form Title: " . $result['form']['title'] . "\n";
        echo "Field Count: " . count($result['form']['fields']) . "\n\n";
        echo "Parsed Fields (JSON):\n";
        echo json_encode($result['form']['fields'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        
        if (!empty($result['errors'])) {
            echo "\nErrors:\n";
            foreach ($result['errors'] as $error) {
                echo "  ✗ " . $error . "\n";
            }
            $fail_count++;
        } else {
            echo "\n✓ Parse successful - No errors\n";
            $pass_count++;
        }
    } else {
        echo "✗ FAILED to parse\n";
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                echo "  Error: " . $error . "\n";
            }
        }
        $fail_count++;
    }
}

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║ " . str_pad("SUMMARY", 58) . " ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║ Total Tests: " . str_pad($test_count, 43) . " ║\n";
echo "║ Passed: " . str_pad($pass_count, 49) . " ║\n";
echo "║ Failed: " . str_pad($fail_count, 49) . " ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

?>
