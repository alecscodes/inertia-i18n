# Changelog

All notable changes to this project will be documented here.

## Unreleased

### Fixed

- The `locale` prop is resolved when the response renders. Previously it was captured at boot, so a locale set by middleware never reached the frontend.
- `tc()` uses the locale's plural rule (same table as Laravel's `MessageSelector`). Languages with more than two forms (Romanian, Russian, Polish, Czech, Arabic…) previously got the wrong form.
- `tc()` no longer outputs condition markers such as `{1}` when no condition matches.
- Placeholders are replaced in a single pass, longest first, and values are inserted literally. Previously `:name` could corrupt `:names`, and values containing `$&` were mangled.
- `t('constructor')` and similar keys no longer return `Object.prototype` members.
- Locales that could escape the `lang/` directory (`..`, `/`, `\`, NUL) are rejected, matching Laravel's own rule.
- Integer-like keys are no longer renumbered when catalogs are merged.

### Added

- Fallback locale: missing keys are filled in from `app.fallback_locale`. Enabled by default; disable with `'fallback' => false`.
- `'@json'` group for Laravel's standard `lang/{locale}.json` catalog.
- `useTranslation()` accepts a getter (`() => page.props`) so long-lived components stay current, and an option for custom prop names.
- `:Name` / `:NAME` placeholder variants.

### Changed

- **Breaking:** Laravel 10 and 11 are no longer supported. Laravel 12 or 13 is required.

- Malformed JSON translation files throw a `RuntimeException` naming the file (as Laravel's own loader does) instead of being silently ignored.
- `withTranslations()` now sends `translations` as a JSON object (`{}` when empty) instead of `[]`.
- CI tests Laravel 12/13 × Inertia 2/3 on PHP 8.2–8.5, plus the TypeScript helper on Node 22/24.
