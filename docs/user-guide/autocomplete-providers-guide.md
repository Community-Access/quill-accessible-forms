# Autocomplete Providers Guide

This guide explains BAF's autocomplete provider system, which allows intelligent address completion, static datasets, and graceful fallback when services are unavailable.

## Overview

BAF uses a provider-based system for autocomplete functionality. This design ensures:

- **Graceful degradation** - Forms work without JavaScript or external services
- **Provider flexibility** - Easily swap between Google Places, static data, or browser autocomplete
- **Accessibility** - All autocomplete features are keyboard and screen reader accessible
- **Privacy** - Control exactly what data is sent to external services

## Available Providers

### 1. Browser Autocomplete (`browser`)

Uses native HTML5 `autocomplete` tokens for browser-managed autofill. Always available, no external services required.

**Supported tokens:**

| Category | Tokens |
|----------|--------|
| Personal | `name`, `given-name`, `family-name`, `nickname`, `username`, `bday` |
| Contact | `email`, `tel`, `tel-country-code`, `tel-national` |
| Address | `street-address`, `address-line1`, `address-line2`, `address-level1`, `address-level2`, `postal-code`, `country` |
| Payment | `cc-name`, `cc-number`, `cc-exp`, `cc-csc`, `cc-type` |

**BFL Example:**
```bfl
email "Email Address" email
    @autocomplete "email"

address_line_1 "Street Address" text
    @autocomplete "street-address"

city "City" text  
    @autocomplete "address-level2"
```

### 2. Static Dataset (`static`)

Provides searchable lists of common data like countries, states, timezones, etc. All data is local - no network requests.

**Available datasets:**
- `countries` - 195 countries with ISO codes
- `us_states` - 54 US states and territories
- `ca_provinces` - 13 Canadian provinces and territories
- `timezones` - All IANA timezone identifiers
- `languages` - Major world languages with ISO codes
- `currencies` - World currencies with ISO codes and symbols

**BFL Example:**
```bfl
country "Country" select
    @autocomplete_provider "static"
    @autocomplete_dataset "countries"

state "State" select
    @cascade_from "country"
    @cascade_library "us_states"
```

### 3. Google Places (`google_places`)

Provides intelligent address autocomplete using Google Places API. Requires API key configuration.

**Features:**
- Real-time address suggestions
- Address component parsing (street, city, state, zip)
- Auto-fill related fields
- Supports place type filtering (address, establishment, regions)

**Configuration in Settings:**
1. Go to BAF > Settings
2. Enter your Google Maps API key
3. Enable "Places Autocomplete"

**BFL Example:**
```bfl
address_search "Address" text
    @autocomplete_provider "google_places"
    @autocomplete_types "address"
    @autocomplete_countries "us,ca"
```

### 4. Fallback Provider (`fallback`)

Automatically activated when Google Places is unavailable. Provides country/state dropdowns using static data.

**Modes:**
- `country_state` - Cascading country/state dropdowns
- `static_only` - Static lists without cascading
- `browser` - Browser autocomplete tokens only
- `none` - Manual entry only

## Using Autocomplete in BFL

### Basic Browser Autocomplete

```bfl
form "Contact Form"
    first_name "First Name" text
        @autocomplete "given-name"
    
    last_name "Last Name" text
        @autocomplete "family-name"
    
    email "Email" email
        @autocomplete "email"
    
    phone "Phone" phone
        @autocomplete "tel"
```

### Static Dataset Dropdown

```bfl
form "Location Form"
    timezone "Your Timezone" select
        @autocomplete_provider "static"
        @autocomplete_dataset "timezones"
        @searchable true  # Enable type-to-filter
```

### Geo Cascading Fields

```bfl
form "Address Form"
    country "Country" select
        @autocomplete_provider "static"
        @autocomplete_dataset "countries"
    
    state "State/Province" select
        @cascade_from "country"
        @cascade_datasets {
            "US": "us_states",
            "CA": "ca_provinces"
        }
```

### Google Places with Fallback

```bfl
form "Address Form"
    # Uses Google Places if available, falls back to static
    address_search "Start typing your address" text
        @autocomplete_provider "google_places"
        @autocomplete_types "address"
        @autocomplete_countries "us"
        @autocomplete_fallback "country_state"
    
    # These fields are auto-filled when address is selected
    street "Street Address" text
        @autocomplete "street-address"
        @readonly_when_autofilled true
    
    city "City" text
        @autocomplete "address-level2"
    
    state "State" text
        @autocomplete "address-level1"
    
    zip "ZIP Code" text
        @autocomplete "postal-code"
```

## Provider Availability

BAF automatically handles provider availability:

| Provider | Available When |
|----------|----------------|
| `browser` | Always |
| `static` | Always |
| `google_places` | API key configured and valid |
| `fallback` | Always (uses static/browser) |

When a preferred provider is unavailable, BAF falls back gracefully:

1. Check preferred provider availability
2. If unavailable, use specified fallback
3. If no fallback specified, use `static` or `browser`
4. Field always remains functional as standard input

## Accessibility Considerations

All autocomplete features are fully accessible:

- **Keyboard navigation** - Arrow keys navigate suggestions, Enter selects
- **Screen reader announcements** - Result counts, selection changes announced
- **Focus management** - Focus trapped in dropdown when open
- **High contrast** - Suggestions visible in high contrast mode
- **No mouse required** - Full functionality via keyboard

## Adding Custom Providers

Developers can register custom autocomplete providers:

```php
add_action( 'baf_register_autocomplete_providers', function( $registry ) {
    require_once 'class-my-custom-provider.php';
    $registry->register( new My_Custom_Provider() );
});
```

Your provider must implement `BAF_Autocomplete_Provider`:

```php
interface BAF_Autocomplete_Provider {
    public function get_id();
    public function get_name();
    public function is_available();
    public function get_unavailable_reason();
    public function get_options_schema();
    public function get_suggestions( $query, $options = array() );
    public function get_js_config( $options = array() );
}
```

## Troubleshooting

### Google Places not working

1. Verify API key is entered in BAF Settings
2. Check that Places API is enabled in Google Cloud Console
3. Verify API key has no HTTP referrer restrictions (or add your domain)
4. Check browser console for JavaScript errors

### Static datasets not appearing

1. Ensure `@autocomplete_provider "static"` is set
2. Verify dataset name is correct (case-sensitive)
3. Check that the field type supports autocomplete (select, text)

### Suggestions not filtering

1. Enable `@searchable true` for select fields
2. Ensure dataset is loaded (check Network tab)
3. Verify minimum characters setting (default: 1)

## Best Practices

1. **Use browser autocomplete for standard fields** - Fastest, most accessible option
2. **Reserve Google Places for address search** - Don't overuse external APIs
3. **Always provide fallbacks** - Ensure forms work without JavaScript
4. **Test with screen readers** - Verify announcements are helpful
5. **Consider privacy** - Inform users about external service usage
