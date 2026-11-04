# The BAF expression language

One small language does four jobs in a BAF form:

- **conditions**: when a field, section, page, rule, confirmation or quiz outcome applies;
- **calculations**: the value of a calculation, total or price field;
- **quiz scoring**: outcomes such as `score >= 8`;
- **smart defaults**: starting values such as `today() + 30` or `user("display_name")`.

The form model stores every expression as **canonical text** (below). BFL writes
the same text, and BAF and QUILL show it as a sentence: `#type is "business" or
#type is "charity"` reads as "Type is Business or Type is Charity".

The PHP implementation is `includes/model/class-baf-expression.php`. The cases
every implementation must agree on are in `tests/conformance/expressions/cases.json`.

## Values

| Write | Meaning |
| --- | --- |
| `#company` | the answer to the field whose ID is `company` |
| `{company}` | the same; the older calculation spelling, rewritten as `#company` |
| `"Business"` or `'Business'` | text. Escapes: `\n`, `\t`, `\r`, `\\`, `\"`, `\'`, `\/`, `\uXXXX` |
| `42`, `2.5` | numbers. A minus sign is an operator, so `-5` is minus 5 |
| `true`, `false`, `null` | yes, no, nothing |
| `["a", "b"]` | a list |
| `score` | the quiz score: the total points of every scored field |
| `today` | the same as `today()` |
| a bare word such as `Email` | text. Older BFL allowed `if #pay is Email`; canonical text always quotes it |

These words are reserved and are never read as bare text: and, or, not, is,
contains, does, contain, starts, ends, with, empty, filled, before, after,
within, days, of, greater, less, than, at, least, most, true, false, null,
score, today.

## Grammar

From loosest to tightest:

```
expression  := and ( "or" and )*
and         := not ( "and" not )*
not         := "not" not | comparison
comparison  := sum [ test ]
test        := ( "is" | "=" | "==" ) sum
             | ( "is not" | "!=" | "<>" ) sum
             | ( "<" | "<=" | ">" | ">=" ) sum
             | "is" ["not"] "one of" list
             | "is" ["not"] "empty"          | "empty"
             | "is" ["not"] "filled" ["in"]  | "filled"
             | "contains" sum                | "does not contain" sum
             | "starts" ["with"] sum         | "ends" ["with"] sum
             | ["is"] ("greater" | "less") ["than"] sum
             | "is at least" sum             | "is at most" sum
             | ["is"] ("before" | "after") sum
             | ["is"] "within" sum "days" [ "of" sum ]
sum         := product ( ("+" | "-") product )*
product     := unary ( ("*" | "/" | "%") unary )*
unary       := "-" unary | primary
primary     := number | text | #id | {id} | list | "(" expression ")"
             | name "(" [ expression ( "," expression )* ] ")"
             | true | false | null | score | today | bare word
list        := "[" [ sum ( "," sum )* ] "]"
```

Keywords are not case-sensitive. A comparison takes one test: `#a is 1 is 2` is an error.

## Functions

| Function | Result |
| --- | --- |
| `today()` | today's date, `YYYY-MM-DD` |
| `date(x)` | `x` as a date, or null when it is not one |
| `days_between(a, b)` | days from `a` to `b` (negative when `b` is earlier) |
| `age(d)` | whole years from date `d` to today |
| `abs(x)`, `floor(x)`, `ceil(x)` | as in arithmetic |
| `round(x)`, `round(x, places)` | halves round away from zero: `round(2.5)` is 3, `round(-2.5)` is -3 |
| `min(...)`, `max(...)`, `sum(...)`, `avg(...)` | over every argument, lists opened up, empty answers skipped. `sum` of nothing is 0; the others give null |
| `count(x)` | how many non-empty answers a list holds; 1 or 0 for a single answer |
| `length(x)` | characters in the text (not bytes) |
| `if(condition, a, b)` | `a` when the condition holds, else `b` |
| `score(#field)` | the points one field earned |
| `user("key")` | a fact about the signed-in user, such as `display_name` or `email` |
| `query("key")` | a value from the page address (`?ref=newsletter`) |
| `geo()` | the visitor's country, when the site knows it |

Any other name followed by `(` is an error.

## Canonical text

The model stores the canonical spelling; BAF and QUILL both write it, so a form
written by one is byte for byte what the other writes.

- Field references are `#id`. Text is in double quotes, JSON-escaped, with
  characters outside ASCII and the slash `/` written as themselves.
- Numbers are written as JSON writes them (`2.50` becomes `2.5`, and a
  number written with a decimal point keeps it: `1.0`), but always in plain
  digits, never in exponent form, because the grammar has no exponents:
  `100000000000000000` is written `100000000000000000.0` and `0.00001`
  stays `0.00001`. Canonical text always reads back to itself.
- `is`, `is not`, `<`, `<=`, `>`, `>=`, `is one of`, `is not one of`,
  `is empty`, `is not empty`, `contains`, `does not contain`, `starts with`,
  `ends with`, `is before`, `is after`, `is within N days [of X]`.
- Functions are written with their parentheses: `today()`.
- `and` and `or` chains are flat: `(a or b) or c` is written `a or b or c`.
- Parentheses appear only where needed: around an `or` inside an `and`, an
  `and` or `or` inside `not`, a comparison used as a value, a sum inside a
  product, and the right side of any arithmetic operator that binds equally
  (`#a - (#b - #c)`, `#a + (#b + #c)`), because arithmetic reads left to right.
- One space around every operator and after every comma.

## The tree (AST)

Implementations that want to share parsed expressions use this JSON shape:

- `{"type": "literal", "value": ...}`: text, number, true, false or null.
  `-5` folds into the literal -5.
- `{"type": "ref", "field": "id"}`
- `{"type": "list", "items": [...]}`
- `{"type": "var", "name": "score"}`
- `{"type": "call", "name": "round", "args": [...]}`
- `{"type": "op", "op": "...", "args": [...]}` where op is one of: `or`, `and`
  (two or more args), `not`, `neg`, `empty`, `not_empty` (one arg), `is`,
  `is_not`, `lt`, `le`, `gt`, `ge`, `one_of`, `not_one_of`, `contains`,
  `not_contains`, `starts_with`, `ends_with`, `before`, `after`, `add`, `sub`,
  `mul`, `div`, `mod` (two args), `within` (two or three args).

## Evaluation

Answers are text, numbers, true or false, lists (several choices) or nothing.
An unanswered field is nothing (null).

- **Empty** means nothing, text that is only spaces, or a list of empty
  answers. The number 0 and `false` are not empty.
- **True or false** (for and, or, not and conditions): false, nothing, the
  number 0, empty text and the empty list are false. Everything else,
  including the text `"0"`, is true. `and` and `or` stop as soon as the answer is known.
- **is / is not**: two numbers, or text that is a number, compare as numbers
  (`"18.0"` is 18). Otherwise text must match exactly, including case. With a
  list on the left, `is` means the list includes the value; with a list on
  each side, they must hold the same values.
- **is one of**: any value on the left matches any in the list.
- **contains, starts with, ends with**: on text, ignore case (A to Z only).
  `contains` on a list means "includes".
- **Less than and greater than**: numbers as numbers, dates as dates, other
  text by its characters. If either side is empty the test is false.
- **Dates** are text in the form `YYYY-MM-DD`; anything after the tenth
  character, such as a time, is ignored. A date that does not exist (the 29th
  of February 2023) is not a date. `before`, `after` and `within` are false
  when either side is not a date. `within N days` means no more than N days
  apart, in either direction; without `of`, the other date is today.
- **Arithmetic**: empty is 0, numeric text is its number, true is 1, a list is
  the total of its members, other text is 0. A date plus or minus a number is
  a date that many days later or earlier; a date minus a date is a number of
  days. Division or remainder by 0 gives nothing. A whole-number result is a
  whole number (6 / 2 is 3, not 3.0).
- `today()` is the server's date in UTC unless the caller supplies one.
- **Text is compared as bytes**, as PHP does: only A to Z change case
  (`"É" contains "é"` is false), and ordering is by code point (`"é" > "z"`).
- **A date may be followed by a newline** and still be a date
  (`"2026-11-20
"`), because PHP's `$` matches before a final newline.
- **Text that is a number** is digits with an optional minus sign and one
  decimal point (`"3."`, `".5"`, `"-0"`), with spaces, tabs, line breaks or
  form feeds around it. `"+5"`, `"1e3"` and `"0x1A"` are not numbers.
- **Rounding** is half away from zero, after PHP's pre-rounding of the
  value to 15 significant digits: `round(1.005, 2)` is 1.01 and
  `round(0.49999999999999994)` is 1.

## Other implementations

BAF evaluates expressions in PHP (`includes/model/class-baf-expression.php`)
and, on a published form, in JavaScript (`public/js/baf-expression.js`).
QUILL evaluates them in Python. All three run the cases in
`tests/conformance/expressions/`; `php-edges.json` holds the cases where
another language's defaults differ from PHP's (text folding and ordering,
number formatting, rounding, numeric text, error offsets in UTF-8 bytes).
JavaScript has one number type, so an integral value is treated as PHP
treats an integer; the only place that shows is a PHP float of 1e17 or
more written as text, which no form reaches.

## Sentences

A sentence replaces each `#id` with the field's label and, when a value is
compared with a choice field, the choice's label instead of its stored value.
Operators read as words: "is less than", "is at least", "is filled in",
"is within 30 days of today", "times", "divided by". A negated test reads as
its opposite ("not ... is filled in" reads "is empty"), and a group of `and`
or `or` inside another is set in parentheses.
