# Copilot / AI Agent Instructions for baf

These brief instructions help AI coding agents be productive in this WordPress plugin repository.

## Accessibility First - Core Mission

**BITS Accessible Forms (BAF) is built by Blind Information Technology Solutions with one core mission: make form building fully accessible to blind, low-vision, and keyboard-dependent users.**

- **WCAG 2.2 AA compliance is non-negotiable** — every feature must meet or exceed WCAG 2.2 AA standards
- **Blind and low-vision users are primary personas**, not afterthoughts — design and test for screen readers (NVDA, JAWS, VoiceOver), keyboard-only navigation, and high-contrast modes
- **Accessibility is measured continuously** — the built-in Accessibility Coach scores forms in real-time with WCAG-based issue detection
- **When ANY code change is made**, ensure it maintains or improves accessibility. Run accessibility tests and validate ARIA patterns.

## Project Summary

- This repository is a WordPress plugin (entry: [bits-accessible-forms.php](bits-accessible-forms.php)) that implements accessible form building and rendering. Key areas: `admin/`, `includes/accessibility/` (core a11y logic), `public/`, and `tests/`.

## Code Style

- Primary language: PHP (minimum PHP 7.4+ idioms). Follow PSR-12 style where practical.
- File/class pattern: classes are `class-baf-*.php` and named `BAF_*`/`Baf*` style. Example: [admin/class-baf-admin.php](admin/class-baf-admin.php).
- Keep functions small, prefer classes for features (see [includes/class-baf-loader.php](includes/class-baf-loader.php)).

## Accessibility & WCAG 2.2 Compliance (Critical)

**Every code change must maintain WCAG 2.2 AA compliance.** Blind and low-vision users rely on our accessibility implementation.

### Key Accessibility Patterns

- **ARIA Attributes**: Use [includes/accessibility/class-baf-aria-generator.php](includes/accessibility/class-baf-aria-generator.php) to generate proper ARIA roles, labels, and states. Never hardcode ARIA.
- **Screen Reader Announcements**: Use ARIA live regions (`aria-live`, `aria-label`, `aria-describedby`) for all dynamic updates. See [includes/accessibility/class-baf-announcer.php](includes/accessibility/class-baf-announcer.php).
- **Keyboard Navigation**: Every UI control must be operable via keyboard. Test with Tab, Arrow keys, Enter, Escape, and form-specific shortcuts.
- **Focus Management**: Use [includes/accessibility/class-baf-focus-manager.php](includes/accessibility/class-baf-focus-manager.php) for intelligent focus handling. Never lose focus or trap users without escape.
- **High Contrast & Reduced Motion**: Check CSS for hardcoded colors; use CSS variables. Respect `prefers-reduced-motion` in animations.
- **Labels & Help Text**: Every form input must have an associated `<label>`, `aria-label`, or `aria-labelledby`. Never use placeholder as label.

### Testing for Accessibility (Required)

Before committing any UI changes:

1. **Screen Reader Test** (NVDA on Windows or Safari VoiceOver on macOS): Navigate with only keyboard and screen reader. Verify all labels, roles, and live announcements are correct.
2. **Keyboard Navigation**: Tab through interface, use arrow keys, test Escape and Enter. Ensure no keyboard traps.
3. **Focus Visible**: Verify high-contrast focus rings appear on all interactive elements.
4. **Accessibility Coach**: Run the built-in coach (Ctrl+Shift+A) on forms and verify score and issue feedback.
5. **WCAG Checklist**: Cross-reference [docs/PRD.md](docs/PRD.md) → **Accessibility Requirements (WCAG 2.2 AA)** to ensure the change doesn't violate any criterion.

### When Accessibility Breaks Tests

If a change causes accessibility test failures, it must be reverted or fixed before merge. Accessibility is non-negotiable—treating it as "nice-to-have" is not acceptable.

## Architecture

- High-level components:
  - Admin UI: `admin/` and `admin/partials/` (form builder, CSV import UI). All admin UIs must support keyboard-only navigation via Structure Mode.
  - Core Accessibility: `includes/accessibility/` — ARIA generation ([class-baf-aria-generator.php](includes/accessibility/class-baf-aria-generator.php)), focus management ([class-baf-focus-manager.php](includes/accessibility/class-baf-focus-manager.php)), screen reader announcements ([class-baf-announcer.php](includes/accessibility/class-baf-announcer.php)), and the Accessibility Coach ([class-baf-accessibility-coach.php](includes/accessibility/class-baf-accessibility-coach.php)).
  - Services & Logic: `includes/` (activator, loader, cache, autocomplete, BFL compiler, entry management).
  - Public Rendering: `public/` (form renderer and public hooks). All rendered forms must be fully accessible.
  - Tests: `tests/` (unit tests; include accessibility test cases).
- When changing behavior, prefer updating or extending the service classes in `includes/` rather than modifying many templates. Ensure all changes maintain ARIA integrity.

## Build & Test

- Install dependencies (if present) with `composer install` in repository root.
- Run unit tests with PHPUnit using repository config:
  - `vendor/bin/phpunit -c phpunit.xml` (or `phpunit -c phpunit.xml` if globally installed)

- Tests live under `tests/`; run only the affected test files during iterative work.

## Project Conventions

- Namespace / prefixing: code uses `baf`/`BAF` prefixes instead of PHP namespaces in many places. When introducing new classes, follow the existing filename/class-name pattern and add to autoload if composer is used.
- Templates and partials: admin UI markup lives under `admin/partials/` and is rendered by admin classes — prefer small, testable callbacks over large inline scripts.
- JavaScript: small modules live in `admin/js/` and `public/js/` — keep DOM interactions unobtrusive and prefixed to avoid collisions.

## Integration Points

- This is a WordPress plugin: use WP APIs (`add_action`, `add_filter`, nonces, capability checks). Refer to `bits-accessible-forms.php` and `includes/` loader for registration patterns.
- External services: autocomplete providers live under `includes/autocomplete/` — mock these in tests (see `tests/mocks/`).

## Security & Sensitive Areas

- Sanitize and escape all output (use `esc_html()`, `esc_attr()`, `wp_kses()` in admin/public templates).
- Verify nonces and capabilities on AJAX and REST endpoints (see `admin/class-baf-accessibility-coach-ajax.php` and `api/class-baf-rest-api.php`).
- Avoid logging secrets. Tests may include mock data under `tests/mocks/`.

## When Editing Code

- Prefer minimal, focused changes. Update or add unit tests in `tests/unit/` for any behavior change.
- Link PRs to specific files changed and include a short test plan: commands to run and expected results.

## Helpful Files

- Entry: [bits-accessible-forms.php](bits-accessible-forms.php)
- Admin examples: [admin/class-baf-admin.php](admin/class-baf-admin.php)
- Core services: `includes/` directory
- Public rendering: [public/class-baf-form-renderer.php](public/class-baf-form-renderer.php)
- Tests config: [phpunit.xml](phpunit.xml) and `tests/`

If any section is unclear or you want more detail (build steps, composer/autoload rules, or test fixtures), say which area and I'll expand.
