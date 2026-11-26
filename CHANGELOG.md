# Changelog

All notable changes to this project will be documented here.

## Unreleased

- Load standard `lang/{locale}.json` catalogs through the explicit `@json` selector or wildcard; preserve literal keys and make PHP/grouped-JSON precedence deterministic.
- Add Laravel-configured fallback catalogs and sparse source-locale metadata for fallback translations. Fallback can be disabled with the package `fallback` option.
- Resolve locale and shared groups when Inertia props are evaluated, and keep catalog loading lazy for partial responses.
- Correct the no-shared-groups contract: the translations prop is `null` rather than omitted.
- Harden locale/group path validation and JSON error handling.
- Make the frontend helper read replaceable page props, preserve literal translation keys and interpolation values, and support Laravel-style plural selectors and locale families.
- Expand compatibility CI and document the supported conditional framework combinations and frontend verification commands.
