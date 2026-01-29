# BAF Choice Libraries Reference

**Version:** 1.0  
**Last Updated:** January 28, 2026  
**Total Libraries:** 125+

BITS Accessible Forms includes an extensive collection of predefined choice libraries for dropdown selects, radio buttons, and checkboxes. These libraries provide instant access to commonly-needed option lists with full internationalization support and intelligent cascading.

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Geographic Libraries](#geographic-libraries)
3. [Time & Date Libraries](#time--date-libraries)
4. [Financial Libraries](#financial-libraries)
5. [Language & Contact Libraries](#language--contact-libraries)
6. [Demographic Libraries](#demographic-libraries)
7. [Professional Libraries](#professional-libraries)
8. [E-commerce & Product Libraries](#e-commerce--product-libraries)
9. [Rating & Feedback Libraries](#rating--feedback-libraries)
10. [Health & Medical Libraries](#health--medical-libraries)
11. [Technology Libraries](#technology-libraries)
12. [Boolean & Simple Libraries](#boolean--simple-libraries)
13. [Events & Support Libraries](#events--support-libraries)
14. [Units of Measurement](#units-of-measurement)
15. [Cascading Relationships](#cascading-relationships)
16. [Custom Libraries](#custom-libraries)
17. [API Reference](#api-reference)

---

## Quick Start

### Using a Library in BFL

```bfl
select country {
    label "Country"
    library countries
}
```

### Using Cascading Libraries

```bfl
select country {
    label "Country"
    library countries
}

select state {
    label "State/Province"
    cascade_from country
    library states
    empty_message "Please select a country first"
}
```

### Using in Visual Builder

1. Add a Select, Radio, or Checkbox field
2. In the field settings, find "Choices Source"
3. Select "Library" 
4. Choose your desired library from the dropdown

---

## Geographic Libraries

### Countries

| Library Key | Description | Count |
|-------------|-------------|-------|
| `countries` | All countries (ISO 3166-1 alpha-2) | 250+ |
| `countries_common` | Most commonly used countries | 50 |

**Sample Values:** Afghanistan (AF), Albania (AL), ... Zimbabwe (ZW)

### Continents & Regions

| Library Key | Description | Count |
|-------------|-------------|-------|
| `continents` | 7 continents | 7 |
| `world_regions` | Geographic regions | 8 |

**Continents:** Africa, Antarctica, Asia, Europe, North America, Oceania, South America

**World Regions:** Africa, Asia, Caribbean, Central America, Europe, Middle East, North America, Oceania, South America

### North America

| Library Key | Description | Count |
|-------------|-------------|-------|
| `us_states` | US states + DC + territories | 51+ |
| `ca_provinces` | Canadian provinces & territories | 13 |
| `mx_states` | Mexican states | 32 |

**US States:** Alabama (AL), Alaska (AK), Arizona (AZ), ... Wyoming (WY), plus DC and territories

**Canadian Provinces:** Alberta (AB), British Columbia (BC), Manitoba (MB), New Brunswick (NB), Newfoundland and Labrador (NL), Northwest Territories (NT), Nova Scotia (NS), Nunavut (NU), Ontario (ON), Prince Edward Island (PE), Quebec (QC), Saskatchewan (SK), Yukon (YT)

### Europe

| Library Key | Description | Count |
|-------------|-------------|-------|
| `uk_counties` | UK ceremonial counties | ~45 |
| `de_states` | German Bundesländer | 16 |
| `fr_regions` | French regions | 13 |
| `it_regions` | Italian regions | 20 |
| `es_regions` | Spanish autonomous communities | 19 |
| `nl_provinces` | Dutch provinces | 12 |
| `be_provinces` | Belgian provinces | 11 |
| `ch_cantons` | Swiss cantons | 26 |
| `at_states` | Austrian states | 9 |
| `ie_counties` | Irish counties | 26 |
| `pl_voivodeships` | Polish voivodeships | 16 |
| `ru_regions` | Russian major regions | 20 |

**German States:** Baden-Württemberg, Bavaria, Berlin, Brandenburg, Bremen, Hamburg, Hesse, Lower Saxony, Mecklenburg-Vorpommern, North Rhine-Westphalia, Rhineland-Palatinate, Saarland, Saxony, Saxony-Anhalt, Schleswig-Holstein, Thuringia

**French Regions:** Auvergne-Rhône-Alpes, Bourgogne-Franche-Comté, Brittany, Centre-Val de Loire, Corsica, Grand Est, Hauts-de-France, Île-de-France, Normandy, Nouvelle-Aquitaine, Occitanie, Pays de la Loire, Provence-Alpes-Côte d'Azur

### South America

| Library Key | Description | Count |
|-------------|-------------|-------|
| `br_states` | Brazilian states | 27 |
| `ar_provinces` | Argentine provinces | 24 |

### Asia-Pacific

| Library Key | Description | Count |
|-------------|-------------|-------|
| `au_states` | Australian states & territories | 8 |
| `nz_regions` | New Zealand regions | 16 |
| `in_states` | Indian states & territories | 36 |
| `cn_provinces` | Chinese provinces | 34 |
| `jp_prefectures` | Japanese prefectures | 47 |
| `kr_provinces` | South Korean provinces | 17 |

**Australian States:** Australian Capital Territory (ACT), New South Wales (NSW), Northern Territory (NT), Queensland (QLD), South Australia (SA), Tasmania (TAS), Victoria (VIC), Western Australia (WA)

**Japanese Prefectures:** Hokkaido, Aomori, Iwate, Miyagi, Akita, Yamagata, Fukushima, Ibaraki, Tochigi, Gunma, Saitama, Chiba, Tokyo, Kanagawa, Niigata, Toyama, Ishikawa, Fukui, Yamanashi, Nagano, Gifu, Shizuoka, Aichi, Mie, Shiga, Kyoto, Osaka, Hyogo, Nara, Wakayama, Tottori, Shimane, Okayama, Hiroshima, Yamaguchi, Tokushima, Kagawa, Ehime, Kochi, Fukuoka, Saga, Nagasaki, Kumamoto, Oita, Miyazaki, Kagoshima, Okinawa

### Africa

| Library Key | Description | Count |
|-------------|-------------|-------|
| `za_provinces` | South African provinces | 9 |

**South African Provinces:** Eastern Cape, Free State, Gauteng, KwaZulu-Natal, Limpopo, Mpumalanga, Northern Cape, North West, Western Cape

### Cities

| Library Key | Description | Count |
|-------------|-------------|-------|
| `cities_us` | Major US cities | 40 |
| `cities_world` | Major world cities | 48 |

**Major US Cities:** New York, Los Angeles, Chicago, Houston, Phoenix, Philadelphia, San Antonio, San Diego, Dallas, San Jose, Austin, Jacksonville, Fort Worth, Columbus, Charlotte, San Francisco, Indianapolis, Seattle, Denver, Washington DC, Boston, Nashville, Detroit, Portland, Memphis, Las Vegas, Baltimore, Milwaukee, Albuquerque, Tucson, Atlanta, Miami, Minneapolis, Cleveland, Pittsburgh, New Orleans

**Major World Cities:** Tokyo, Delhi, Shanghai, São Paulo, Mexico City, Cairo, Mumbai, Beijing, Dhaka, Osaka, Karachi, Buenos Aires, Istanbul, Kolkata, Manila, Lagos, Rio de Janeiro, Moscow, Paris, London, Seoul, Bangkok, Hong Kong, Singapore, Sydney, Toronto, Berlin, Madrid, Rome, Amsterdam, Dubai, Cape Town, Melbourne

---

## Time & Date Libraries

### Timezones

| Library Key | Description | Count |
|-------------|-------------|-------|
| `timezones` | All IANA timezones | 400+ |
| `timezones_us` | US timezones only | 10 |
| `timezones_africa` | Africa timezones | ~50 |
| `timezones_america` | Americas timezones | ~140 |
| `timezones_asia` | Asia timezones | ~80 |
| `timezones_atlantic` | Atlantic timezones | ~10 |
| `timezones_australia` | Australia timezones | ~15 |
| `timezones_europe` | Europe timezones | ~60 |
| `timezones_indian` | Indian Ocean timezones | ~10 |
| `timezones_pacific` | Pacific timezones | ~40 |
| `timezone_regions` | Timezone region names | 9 |

**Timezone Regions:** Africa, America, Antarctica, Asia, Atlantic, Australia, Europe, Indian, Pacific

### Days & Months

| Library Key | Description | Count |
|-------------|-------------|-------|
| `days` | Days of the week | 7 |
| `days_short` | Short day names (Mon, Tue...) | 7 |
| `months` | Months of the year | 12 |
| `months_short` | Short month names (Jan, Feb...) | 12 |

**Days:** Sunday, Monday, Tuesday, Wednesday, Thursday, Friday, Saturday

**Months:** January, February, March, April, May, June, July, August, September, October, November, December

### Years

| Library Key | Description | Count |
|-------------|-------------|-------|
| `years_past` | Past 100 years (descending) | 100 |
| `years_future` | Next 10 years (ascending) | 10 |
| `years_birth` | Birth years (1920-present) | ~105 |

### Time Slots

| Library Key | Description | Count |
|-------------|-------------|-------|
| `time_slots_hourly` | Hourly slots (12am-11pm) | 24 |
| `time_slots_30min` | 30-minute intervals | 48 |
| `time_slots_15min` | 15-minute intervals | 96 |

**Hourly Slots:** 12:00 AM, 1:00 AM, 2:00 AM, ... 11:00 PM

---

## Financial Libraries

### Currencies

| Library Key | Description | Count |
|-------------|-------------|-------|
| `currencies` | World currencies (ISO 4217) | 58 |
| `currencies_major` | Major world currencies | 8 |
| `cryptocurrencies` | Popular cryptocurrencies | 12 |

**Major Currencies:** US Dollar (USD), Euro (EUR), British Pound (GBP), Japanese Yen (JPY), Australian Dollar (AUD), Canadian Dollar (CAD), Swiss Franc (CHF), Chinese Yuan (CNY)

**Cryptocurrencies:** Bitcoin (BTC), Ethereum (ETH), Tether (USDT), Binance Coin (BNB), USD Coin (USDC), Ripple (XRP), Cardano (ADA), Dogecoin (DOGE), Solana (SOL), Polkadot (DOT), Polygon (MATIC), Litecoin (LTC)

### Budget & Revenue

| Library Key | Description | Count |
|-------------|-------------|-------|
| `budget_ranges` | Budget brackets | 10 |
| `annual_revenue` | Company revenue ranges | 10 |

**Budget Ranges:** Under $1,000, $1,000 - $5,000, $5,000 - $10,000, $10,000 - $25,000, $25,000 - $50,000, $50,000 - $100,000, $100,000 - $250,000, $250,000 - $500,000, $500,000 - $1,000,000, Over $1,000,000

**Annual Revenue:** Under $100,000, $100,000 - $500,000, $500,000 - $1 million, $1 million - $5 million, $5 million - $10 million, $10 million - $50 million, $50 million - $100 million, $100 million - $500 million, $500 million - $1 billion, Over $1 billion

### Payment & Shipping

| Library Key | Description | Count |
|-------------|-------------|-------|
| `payment_methods` | Payment method options | 14 |
| `shipping_methods` | Shipping options | 11 |

**Payment Methods:** Credit card, Debit card, PayPal, Apple Pay, Google Pay, Venmo, Bank transfer, Wire transfer, Check, Cash, Cryptocurrency, Invoice/Net terms, Financing, Other

**Shipping Methods:** Standard shipping, Express shipping, Overnight shipping, 2-day shipping, Same-day delivery, In-store pickup, Locker pickup, Curbside pickup, Freight, International, Digital delivery

---

## Language & Contact Libraries

### Languages

| Library Key | Description | Count |
|-------------|-------------|-------|
| `languages` | World languages (ISO 639-1) | 50 |
| `languages_major` | Major world languages | 12 |

**Major Languages:** English, Spanish, French, German, Chinese, Japanese, Portuguese, Russian, Arabic, Hindi, Italian, Korean

**Full Language List:** English, Chinese (Mandarin), Hindi, Spanish, French, Arabic, Bengali, Portuguese, Russian, Japanese, German, Korean, Italian, Turkish, Vietnamese, Polish, Ukrainian, Dutch, Greek, Czech, Swedish, Hungarian, Romanian, Thai, Indonesian, Malay, Danish, Finnish, Norwegian, Hebrew, Persian (Farsi), Urdu, Tamil, Telugu, Marathi, Gujarati, Kannada, Malayalam, Punjabi, Swahili, Tagalog/Filipino, Catalan, Croatian, Slovak, Slovenian, Bulgarian, Serbian, Lithuanian, Latvian, Estonian

### Phone Codes

| Library Key | Description | Count |
|-------------|-------------|-------|
| `phone_codes` | Country dialing codes | 51 |

**Sample Codes:** +1 (US/Canada), +44 (United Kingdom), +61 (Australia), +49 (Germany), +33 (France), +81 (Japan), +86 (China), +91 (India), +55 (Brazil), +52 (Mexico)

### Contact Preferences

| Library Key | Description | Count |
|-------------|-------------|-------|
| `contact_preferences` | Preferred contact methods | 8 |
| `contact_times` | Preferred contact times | 6 |
| `notification_preferences` | Notification settings | 7 |

**Contact Preferences:** Email, Phone call, Text message (SMS), WhatsApp, Video call, Postal mail, In person, No preference

**Contact Times:** Morning (8am - 12pm), Afternoon (12pm - 5pm), Evening (5pm - 8pm), Weekdays only, Weekends only, Anytime

---

## Demographic Libraries

### Age & Gender

| Library Key | Description | Count |
|-------------|-------------|-------|
| `age_ranges` | Age brackets | 9 |
| `gender` | Gender options (inclusive) | 6 |

**Age Ranges:** Under 18, 18-24, 25-34, 35-44, 45-54, 55-64, 65-74, 75+, Prefer not to say

**Gender Options:** Female, Male, Non-binary, Genderqueer, Prefer to self-describe, Prefer not to say

### Marital & Relationship

| Library Key | Description | Count |
|-------------|-------------|-------|
| `marital_status` | Marital status options | 8 |
| `relationship_statuses` | Relationship status | 9 |

**Marital Status:** Single, Married, Domestic Partnership, Divorced, Separated, Widowed, Other, Prefer not to say

**Relationship Status:** Single, In a relationship, Engaged, Married, Domestic partnership, Separated, Divorced, Widowed, Prefer not to say

### Education & Income

| Library Key | Description | Count |
|-------------|-------------|-------|
| `education_levels` | Education attainment | 10 |
| `income_ranges` | Income brackets | 9 |
| `household_sizes` | Household size | 8 |

**Education Levels:** Some high school, High school diploma/GED, Some college, Associate degree, Bachelor's degree, Master's degree, Professional degree (JD, MD), Doctorate degree, Trade/Vocational certificate, Prefer not to say

**Income Ranges:** Under $25,000, $25,000 - $49,999, $50,000 - $74,999, $75,000 - $99,999, $100,000 - $149,999, $150,000 - $199,999, $200,000 - $299,999, $300,000+, Prefer not to say

### Other Demographics

| Library Key | Description | Count |
|-------------|-------------|-------|
| `religions` | World religions | 17 |
| `ethnicities` | Ethnicity options | 10 |
| `political_affiliations` | Political leanings | 10 |

**Religions:** Christianity, Islam, Hinduism, Buddhism, Sikhism, Judaism, Baha'i, Jainism, Shinto, Confucianism, Taoism, Zoroastrianism, Agnostic, Atheist, Spiritual but not religious, Prefer not to say, Other

---

## Professional Libraries

### Employment

| Library Key | Description | Count |
|-------------|-------------|-------|
| `employment_status` | Employment types | 11 |
| `employment_types` | Work arrangement | 6 |

**Employment Status:** Employed full-time, Employed part-time, Self-employed, Freelancer/Contractor, Unemployed - seeking, Unemployed - not seeking, Student, Retired, Homemaker, Unable to work, Prefer not to say

**Employment Types:** Full-time, Part-time, Contract, Freelance, Internship, Temporary

### Industries & Functions

| Library Key | Description | Count |
|-------------|-------------|-------|
| `industries` | Industry sectors | 61 |
| `job_functions` | Job departments | 33 |
| `fields_of_study` | Academic majors | 43 |

**Industries:** Accounting & Finance, Advertising & Marketing, Aerospace & Defense, Agriculture & Farming, Architecture & Planning, Arts & Entertainment, Automotive, Banking & Financial Services, Biotechnology, Broadcasting & Media, Chemicals, Construction, Consulting, Consumer Goods, Education, Electronics, Energy & Utilities, Engineering, Environmental Services, Fashion & Apparel, Food & Beverage, Gaming & Casinos, Government & Public Sector, Healthcare, Hospitality & Hotels, Human Resources, Information Technology, Insurance, Internet & E-commerce, Investment & Venture Capital, Legal Services, Logistics & Supply Chain, Machinery & Equipment, Manufacturing, Maritime & Shipping, Mining & Metals, Music & Recording, Non-Profit & NGO, Oil & Gas, Packaging & Containers, Pharmaceuticals, Printing & Publishing, Real Estate, Recreation & Leisure, Religious Organizations, Research & Development, Restaurants & Food Service, Retail, Security & Investigations, Semiconductors, Software & SaaS, Sports & Recreation, Staffing & Recruiting, Telecommunications, Textiles, Transportation, Travel & Tourism, Veterinary, Warehousing & Storage, Wholesale Trade, Other

**Job Functions:** Accounting/Finance, Administrative, Art/Design, Business Development, Community/Social Services, Consulting, Customer Service, Data Science/Analytics, Education/Training, Engineering, Executive/Management, Facilities/Maintenance, Healthcare, Human Resources, Information Technology, Legal, Logistics/Supply Chain, Manufacturing/Production, Marketing, Media/Communications, Military/Protective Services, Operations, Product Management, Project Management, Purchasing/Procurement, Quality Assurance, Research, Sales, Science, Skilled Trades, Strategy/Planning, Writing/Editing, Other

### Experience & Seniority

| Library Key | Description | Count |
|-------------|-------------|-------|
| `experience_levels` | Career levels | 9 |
| `years_experience` | Experience ranges | 8 |
| `company_sizes` | Company employee counts | 9 |

**Experience Levels:** Entry level, Junior, Mid-level, Senior, Lead, Manager, Director, VP, C-Level

**Years of Experience:** No experience, Less than 1 year, 1-2 years, 3-5 years, 6-10 years, 11-15 years, 16-20 years, More than 20 years

**Company Sizes:** Self-employed, 2-10 employees, 11-50 employees, 51-200 employees, 201-500 employees, 501-1,000 employees, 1,001-5,000 employees, 5,001-10,000 employees, 10,001+ employees

### Lead Generation

| Library Key | Description | Count |
|-------------|-------------|-------|
| `lead_sources` | "How did you hear about us" | 23 |
| `project_types` | Project categories | 15 |
| `timelines` | Timeline options | 10 |
| `service_categories` | Service types | 29 |

**Lead Sources:** Google Search, Bing Search, Facebook, Instagram, Twitter/X, LinkedIn, YouTube, TikTok, Pinterest, Reddit, Referral/Word of mouth, Email marketing, Blog/Article, Podcast, Webinar, Conference/Event, Print advertisement, TV/Radio, Billboard/Outdoor, Direct mail, Cold call, Partner/Affiliate, Other

**Timelines:** As soon as possible, Within 1 week, Within 2 weeks, Within 1 month, 2-3 months, 3-6 months, 6-12 months, More than 1 year, Flexible, Not sure yet

---

## E-commerce & Product Libraries

### Clothing Sizes

| Library Key | Description | Count |
|-------------|-------------|-------|
| `tshirt_sizes` | T-shirt sizes | 9 |
| `shoe_sizes_us` | US shoe sizes | 21 |
| `shoe_sizes_eu` | EU shoe sizes | 14 |

**T-Shirt Sizes:** XS (Extra Small), S (Small), M (Medium), L (Large), XL (Extra Large), 2XL, 3XL, 4XL, 5XL

**US Shoe Sizes:** 4, 4.5, 5, 5.5, 6, 6.5, 7, 7.5, 8, 8.5, 9, 9.5, 10, 10.5, 11, 11.5, 12, 12.5, 13, 14, 15

**EU Shoe Sizes:** 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48

### Product Options

| Library Key | Description | Count |
|-------------|-------------|-------|
| `product_conditions` | Condition options | 6 |
| `colors` | Common colors | 20 |
| `quantities` | Quantity selector | 100 |

**Product Conditions:** New, Like New, Very Good, Good, Acceptable, For Parts

**Colors:** Black, White, Gray, Silver, Red, Blue, Green, Yellow, Orange, Purple, Pink, Brown, Beige, Navy, Teal, Maroon, Gold, Ivory, Multicolor, Other

### Real Estate

| Library Key | Description | Count |
|-------------|-------------|-------|
| `property_types` | Property types | 14 |
| `bedrooms` | Number of bedrooms | 7 |
| `bathrooms` | Number of bathrooms | 8 |

**Property Types:** House, Apartment, Condo/Condominium, Townhouse, Duplex, Multi-family, Mobile/Manufactured home, Land/Lot, Commercial, Industrial, Office, Retail, Farm/Ranch, Other

### Vehicles

| Library Key | Description | Count |
|-------------|-------------|-------|
| `vehicle_types` | Vehicle types | 17 |
| `fuel_types` | Fuel types | 9 |

**Vehicle Types:** Sedan, SUV, Crossover, Truck, Van/Minivan, Coupe, Convertible, Hatchback, Station wagon, Sports car, Luxury, Electric, Hybrid, Motorcycle, RV/Camper, Boat, Other

**Fuel Types:** Gasoline, Diesel, Electric, Hybrid, Plug-in hybrid, Flex fuel, Hydrogen, Natural gas, Propane

### Subscriptions

| Library Key | Description | Count |
|-------------|-------------|-------|
| `subscription_frequencies` | Subscription intervals | 8 |

**Frequencies:** Weekly, Every 2 weeks, Monthly, Every 2 months, Quarterly, Every 6 months, Annually, One-time (no subscription)

---

## Rating & Feedback Libraries

### Numeric Ratings

| Library Key | Description | Count |
|-------------|-------------|-------|
| `rating_5_star` | 1-5 star rating | 5 |
| `rating_10` | 1-10 scale | 10 |
| `nps` | Net Promoter Score (0-10) | 11 |

### Satisfaction & Agreement

| Library Key | Description | Count |
|-------------|-------------|-------|
| `satisfaction` | Satisfaction scale | 5 |
| `agreement` | Agreement scale | 5 |
| `agreement_7` | 7-point agreement | 7 |

**Satisfaction:** Very Unsatisfied, Unsatisfied, Neutral, Satisfied, Very Satisfied

**Agreement (5):** Strongly Disagree, Disagree, Neutral, Agree, Strongly Agree

**Agreement (7):** Strongly Disagree, Disagree, Somewhat Disagree, Neutral, Somewhat Agree, Agree, Strongly Agree

### Other Scales

| Library Key | Description | Count |
|-------------|-------------|-------|
| `likelihood` | Likelihood scale | 5 |
| `frequency` | Frequency scale | 5 |
| `quality` | Quality scale | 5 |
| `importance` | Importance scale | 5 |
| `priority_levels` | Priority levels | 5 |
| `statuses` | Status options | 7 |

**Likelihood:** Very Unlikely, Unlikely, Neutral, Likely, Very Likely

**Frequency:** Never, Rarely, Sometimes, Often, Always

**Quality:** Very Poor, Poor, Average, Good, Excellent

**Importance:** Not Important, Slightly Important, Moderately Important, Important, Very Important

**Priority Levels:** Low, Medium, High, Urgent, Critical

**Statuses:** Pending, Open, In Progress, On Hold, Resolved, Closed, Cancelled

---

## Health & Medical Libraries

### Medical Information

| Library Key | Description | Count |
|-------------|-------------|-------|
| `blood_types` | Blood types | 9 |
| `medical_specialties` | Medical specialties | 31 |
| `appointment_types` | Appointment types | 16 |

**Blood Types:** A+, A-, B+, B-, AB+, AB-, O+, O-, Unknown

**Medical Specialties:** Allergy & Immunology, Anesthesiology, Cardiology, Dermatology, Emergency Medicine, Endocrinology, Family Medicine, Gastroenterology, General Practice, Geriatrics, Hematology, Infectious Disease, Internal Medicine, Nephrology, Neurology, Obstetrics & Gynecology, Oncology, Ophthalmology, Orthopedics, Otolaryngology (ENT), Pathology, Pediatrics, Physical Medicine, Plastic Surgery, Psychiatry, Pulmonology, Radiology, Rheumatology, Surgery, Urology, Other

**Appointment Types:** Consultation, Follow-up, Initial Visit, Check-up, Emergency, Procedure, Screening, Vaccination, Lab Work, Imaging/X-Ray, Telehealth/Virtual, Therapy Session, Dental, Eye Exam, Physical Exam, Other

### Diet & Allergies

| Library Key | Description | Count |
|-------------|-------------|-------|
| `dietary_restrictions` | Dietary restrictions | 15 |
| `allergens` | Common allergens | 17 |
| `meal_preferences` | Meal preferences | 6 |

**Dietary Restrictions:** None, Vegetarian, Vegan, Pescatarian, Gluten-free, Dairy-free, Nut-free, Kosher, Halal, Keto, Paleo, Low sodium, Low sugar/Diabetic, Raw food, Other

**Allergens:** None, Peanuts, Tree nuts, Milk/Dairy, Eggs, Wheat, Soy, Fish, Shellfish, Sesame, Gluten, Sulfites, Mustard, Celery, Lupin, Mollusks, Other

### Accessibility

| Library Key | Description | Count |
|-------------|-------------|-------|
| `accessibility_needs` | Accessibility requirements | 13 |

**Accessibility Needs:** None, Wheelchair access, Mobility assistance, Visual impairment, Hearing impairment, Sign language interpreter, Service animal, Large print materials, Braille materials, Captioning/Subtitles, Audio description, Quiet/Low-stimulation space, Other

### Pets

| Library Key | Description | Count |
|-------------|-------------|-------|
| `pet_types` | Pet types | 13 |

**Pet Types:** Dog, Cat, Bird, Fish, Rabbit, Hamster, Guinea pig, Reptile, Turtle/Tortoise, Horse, Ferret, Other, No pets

---

## Technology Libraries

### Platforms & Devices

| Library Key | Description | Count |
|-------------|-------------|-------|
| `platforms` | Operating systems | 9 |
| `device_types` | Device types | 11 |
| `browsers` | Web browsers | 10 |

**Platforms:** Windows, macOS, Linux, iOS, Android, Web/Browser, ChromeOS, Cross-platform, Other

**Device Types:** Desktop computer, Laptop, Tablet, Smartphone, Smartwatch, Smart TV, Gaming console, Smart speaker, E-reader, VR headset, Other

**Browsers:** Google Chrome, Mozilla Firefox, Safari, Microsoft Edge, Opera, Brave, Vivaldi, Samsung Internet, Internet Explorer, Other

### Skills

| Library Key | Description | Count |
|-------------|-------------|-------|
| `skill_levels` | Skill/proficiency levels | 7 |

**Skill Levels:** Beginner, Elementary, Intermediate, Upper Intermediate, Advanced, Expert, Native/Fluent

---

## Boolean & Simple Libraries

| Library Key | Description | Values |
|-------------|-------------|--------|
| `yes_no` | Yes/No | Yes, No |
| `yes_no_unsure` | Yes/No/Unsure | Yes, No, Not sure |
| `true_false` | True/False | True, False |
| `agree_disagree` | Agree/Disagree | Agree, Disagree |
| `salutations` | Titles/Salutations | Mr., Mrs., Ms., Miss, Dr., Prof. |

---

## Events & Support Libraries

### Events

| Library Key | Description | Count |
|-------------|-------------|-------|
| `event_types` | Event types | 23 |
| `ticket_types` | Ticket types | 14 |

**Event Types:** Conference, Seminar, Workshop, Webinar, Meeting, Networking event, Training, Presentation, Product launch, Trade show, Exhibition, Fundraiser, Gala, Concert, Festival, Party/Celebration, Wedding, Corporate event, Sports event, Community event, Virtual event, Hybrid event, Other

**Ticket Types:** Free, General admission, VIP, Early bird, Student, Senior, Military, Group, Member, Press/Media, Sponsor, Exhibitor, Speaker, Other

### Support

| Library Key | Description | Count |
|-------------|-------------|-------|
| `support_issue_types` | Support ticket types | 14 |

**Issue Types:** Bug/Error, Feature request, Question, Account issue, Billing/Payment, Order issue, Shipping/Delivery, Return/Refund, Technical support, Feedback, Complaint, Partnership inquiry, Press/Media inquiry, Other

---

## Units of Measurement

| Library Key | Description | Count |
|-------------|-------------|-------|
| `units_length` | Length units | 8 |
| `units_weight` | Weight units | 7 |
| `units_volume` | Volume units | 9 |
| `units_temperature` | Temperature units | 3 |

**Length:** Millimeters (mm), Centimeters (cm), Meters (m), Kilometers (km), Inches (in), Feet (ft), Yards (yd), Miles (mi)

**Weight:** Milligrams (mg), Grams (g), Kilograms (kg), Ounces (oz), Pounds (lb), Stone (st), Tons

**Volume:** Milliliters (ml), Liters (l), Teaspoons (tsp), Tablespoons (tbsp), Fluid ounces (fl oz), Cups, Pints (pt), Quarts (qt), Gallons (gal)

**Temperature:** Celsius (°C), Fahrenheit (°F), Kelvin (K)

---

## Cascading Relationships

BAF supports intelligent cascading between related libraries. When a parent field value changes, the child field options automatically update.

### Built-in Cascades

| Parent Library | Child Library | Description |
|----------------|---------------|-------------|
| `continents` | `countries` | Filter countries by continent |
| `countries` | `states` | Auto-detect correct state/province library |
| `timezone_regions` | `timezones` | Filter timezones by region |

### Using Cascades

```bfl
select continent {
    label "Continent"
    library continents
}

select country {
    label "Country"
    cascade_from continent
    library countries
    empty_message "Please select a continent first"
}

select state {
    label "State/Province"
    cascade_from country
    library states
    empty_message "Please select a country first"
}
```

### Country-to-State Mapping

When using the `states` library with cascading from `countries`, BAF automatically selects the correct state/province library:

| Country Code | Library Used |
|--------------|--------------|
| US | `us_states` |
| CA | `ca_provinces` |
| GB | `uk_counties` |
| AU | `au_states` |
| DE | `de_states` |
| FR | `fr_regions` |
| IT | `it_regions` |
| ES | `es_regions` |
| NL | `nl_provinces` |
| BE | `be_provinces` |
| CH | `ch_cantons` |
| AT | `at_states` |
| MX | `mx_states` |
| BR | `br_states` |
| AR | `ar_provinces` |
| IN | `in_states` |
| CN | `cn_provinces` |
| JP | `jp_prefectures` |
| KR | `kr_provinces` |
| NZ | `nz_regions` |
| ZA | `za_provinces` |
| IE | `ie_counties` |
| PL | `pl_voivodeships` |
| RU | `ru_regions` |

---

## Custom Libraries

### Registering a Custom Library

```php
add_action( 'baf_register_choice_libraries', function() {
    BAF_Choice_Libraries::register( 'my_departments', array(
        'label'   => __( 'My Departments', 'my-plugin' ),
        'choices' => array(
            array( 'value' => 'sales', 'label' => __( 'Sales', 'my-plugin' ) ),
            array( 'value' => 'support', 'label' => __( 'Support', 'my-plugin' ) ),
            array( 'value' => 'engineering', 'label' => __( 'Engineering', 'my-plugin' ) ),
        ),
    ));
});
```

### Registering a Callback Library

For dynamic data:

```php
add_action( 'baf_register_choice_libraries', function() {
    BAF_Choice_Libraries::register( 'user_roles', array(
        'label'    => __( 'User Roles', 'my-plugin' ),
        'callback' => 'my_get_user_roles',
    ));
});

function my_get_user_roles() {
    $roles = wp_roles()->get_names();
    $choices = array();
    foreach ( $roles as $value => $label ) {
        $choices[] = array( 'value' => $value, 'label' => $label );
    }
    return $choices;
}
```

### Registering a Cascade

```php
add_action( 'baf_register_choice_libraries', function() {
    BAF_Choice_Libraries::register_cascade( 'regions', 'cities', array(
        'source' => 'callback',
        'callback' => 'my_get_cities_for_region',
    ));
});

function my_get_cities_for_region( $region_value ) {
    // Return cities filtered by region
    return array(
        array( 'value' => 'city1', 'label' => 'City 1' ),
        array( 'value' => 'city2', 'label' => 'City 2' ),
    );
}
```

---

## API Reference

### PHP Methods

#### `BAF_Choice_Libraries::register( $key, $config )`

Register a new choice library.

**Parameters:**
- `$key` (string) - Unique library identifier
- `$config` (array) - Library configuration
  - `label` (string) - Human-readable name
  - `choices` (array) - Static array of choices
  - `callback` (callable) - Function returning choices

#### `BAF_Choice_Libraries::get( $key )`

Get choices from a library.

**Parameters:**
- `$key` (string) - Library identifier

**Returns:** Array of choices

#### `BAF_Choice_Libraries::get_all()`

Get all registered libraries.

**Returns:** Array of all library configurations

#### `BAF_Choice_Libraries::register_cascade( $parent, $child, $config )`

Register a cascading relationship.

**Parameters:**
- `$parent` (string) - Parent library key
- `$child` (string) - Child library key
- `$config` (array) - Cascade configuration

#### `BAF_Choice_Libraries::get_cascaded( $parent_key, $parent_value, $child_key )`

Get cascaded choices based on parent value.

**Parameters:**
- `$parent_key` (string) - Parent library key
- `$parent_value` (string) - Selected parent value
- `$child_key` (string) - Child library key

**Returns:** Filtered array of choices

### REST API Endpoints

#### Get Library Choices

```
GET /baf/v1/libraries/{library_key}
```

**Response:**
```json
{
  "success": true,
  "data": [
    { "value": "US", "label": "United States" },
    { "value": "CA", "label": "Canada" }
  ]
}
```

#### Get Cascaded Choices

```
GET /baf/v1/libraries/{child_key}?parent={parent_key}&value={parent_value}
```

**Response:**
```json
{
  "success": true,
  "data": [
    { "value": "CA", "label": "California" },
    { "value": "NY", "label": "New York" }
  ]
}
```

#### List All Libraries

```
GET /baf/v1/libraries
```

**Response:**
```json
{
  "success": true,
  "data": {
    "countries": { "label": "Countries", "count": 250 },
    "us_states": { "label": "US States", "count": 51 }
  }
}
```

---

## Changelog

### Version 1.0 (January 2026)

- Initial release with 125+ libraries
- Full cascading support for geographic data
- 25+ country-specific state/province libraries
- Comprehensive demographic, professional, and e-commerce options
- REST API for dynamic library access

---

**End of Reference**
