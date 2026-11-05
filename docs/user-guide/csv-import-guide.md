# CSV Import Guide

Import forms directly from CSV files with intelligent field type detection.

## Overview

The CSV Import Wizard analyzes your CSV file and automatically:
- Detects field types (email, phone, date, etc.)
- Identifies dropdown options from repeated values
- Discovers cascade relationships (country/state)
- Maps columns to appropriate form fields

## Quick Start

1. **Navigate to**: QAF Forms → Import CSV
2. **Upload**: Drag and drop your CSV file or click to browse
3. **Review**: Check the detected field types and adjust as needed
4. **Create**: Generate your form with one click

## Supported Formats

- **Delimiters**: Comma (`,`), Semicolon (`;`), Tab, Pipe (`|`)
- **Encoding**: UTF-8 (with or without BOM)
- **Headers**: Optional first row as column names

## Field Type Detection

The wizard uses both header names and data patterns to infer field types:

| Header Keywords | Detected Type |
|-----------------|---------------|
| email, mail     | Email         |
| phone, tel, mobile | Phone      |
| website, url    | URL           |
| date, dob, birth | Date         |
| description, comments, notes | Textarea |
| price, amount, cost | Number    |

### Data Pattern Analysis

When header names aren't conclusive, the wizard analyzes actual values:

- **Email**: Values matching email format
- **Phone**: Values matching phone patterns (e.g., `555-123-4567`)
- **URL**: Values starting with `http://`, `https://`, or `www.`
- **Date**: Values matching date formats (ISO, US, European)
- **Number**: Numeric values including currency

## Dropdown Detection

The wizard automatically detects columns that should be dropdowns when:

- The column has ≤20 unique values
- The same values repeat frequently (>80% of rows)

Example CSV that triggers dropdown detection:
```csv
Name,Status
John,Active
Jane,Pending
Bob,Active
Mary,Inactive
```

The "Status" column will be detected as a dropdown with options: Active, Pending, Inactive.

## Cascade Relationships

The wizard detects parent-child relationships between columns:

```csv
Country,State,City
USA,California,Los Angeles
USA,California,San Francisco
USA,Texas,Austin
Canada,Ontario,Toronto
```

This will create:
- **Country**: Dropdown with USA, Canada
- **State**: Cascading dropdown filtered by Country
- **City**: Cascading dropdown filtered by State

### Detected Cascade Patterns

- country → state/province
- state → city
- category → subcategory
- department → team
- parent → child

## Lookup Tables

You can load choice options from a separate CSV file:

1. Click "Edit" on any field
2. Click "Load from CSV"
3. Upload your lookup table CSV
4. Specify value and label columns

### Lookup Table Format

```csv
code,name
US,United States
CA,Canada
MX,Mexico
```

## Column Configuration

For each detected column, you can customize:

| Setting | Description |
|---------|-------------|
| **Include** | Whether to include this column in the form |
| **Field Type** | Override the detected type |
| **Label** | The field label shown to users |
| **Required** | Whether the field is mandatory |
| **Choices** | Edit dropdown options (for select/radio fields) |

## Generated Output

The wizard generates:

1. **Form Configuration**: Ready-to-use WordPress form
2. **QAF Form Language**: Exportable form definition you can edit

### Example the QAF form language Output

```bfl
form "Contact Form"

text #first_name "First Name"
  @required

email #email "Email Address"
  @required
  @autocomplete "email"

select #status "Status"
  + active|Active
  + pending|Pending
  + inactive|Inactive

submit "Submit"
```

## Best Practices

### Preparing Your CSV

1. **Use headers**: Always include a header row
2. **Be consistent**: Use the same format throughout each column
3. **Clean data**: Remove leading/trailing spaces
4. **Use UTF-8**: Save files with UTF-8 encoding

### Handling Large Files

- The wizard analyzes the first 100 rows to infer types
- Forms can handle any number of submission rows
- For very large lookup tables, consider using API-based autocomplete

### After Import

1. **Review fields**: Check each field's settings in the form builder
2. **Add validation**: Add any custom validation rules
3. **Test the form**: Submit a test entry to verify everything works
4. **Refine labels**: Improve labels and help text for clarity

## Troubleshooting

### "Type detection has low confidence"

This warning appears when the wizard can't confidently determine a field type. To resolve:

1. Click "Edit" on the field
2. Select the correct field type manually
3. Add choices if it should be a dropdown

### "No cascade detected"

Cascades require:
- Recognizable header names (country/state, category/subcategory)
- Multiple unique parent values
- Child values mostly unique to each parent

### Special Characters

If your CSV contains special characters:
1. Ensure UTF-8 encoding
2. Use quoted values in your CSV
3. Check the delimiter setting matches your file

## API Usage

### Programmatic Import

```php
$builder = new QAF_CSV_Form_Builder();

// Analyze CSV
$analysis = $builder->analyze( '/path/to/file.csv', array(
    'delimiter'      => ',',
    'has_header'     => true,
    'infer_types'    => true,
    'detect_choices' => true,
    'detect_cascades' => true,
) );

// Generate form
$form = $builder->generate_form( $analysis, array(
    'title' => 'My Imported Form',
) );

// Get QAF form language code
$bfl = $builder->generate_bfl( $form );

// Or create directly
$result = $builder->import_and_create( '/path/to/file.csv', array(
    'title' => 'My Form',
) );
```

### Loading Lookup Tables

```php
$builder = new QAF_CSV_Form_Builder();

// Load choices from lookup CSV
$choices = $builder->load_lookup_table(
    '/path/to/countries.csv',
    0, // value column index
    1, // label column index
    array( 'has_header' => true )
);

// Load cascade mapping
$mapping = $builder->load_cascade_mapping(
    '/path/to/states.csv',
    0, // country column
    1, // state column
    2, // optional state label column
);
```

## See Also

- [QAF Form Language Guide](qaf-form-language-guide.md) - Edit generated QAF form language code
- [Field Types Guide](field-types-guide.md) - Available field configurations
- [Complete Guide](complete-guide.md) - Full plugin documentation
