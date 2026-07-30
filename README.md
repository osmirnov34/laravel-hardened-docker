# laravel-hardened-docker

A reference Docker build for running Laravel in production with a security-hardened configuration.

The goal is an **exemplary, auditable baseline**: every security-relevant setting is documented with its
rationale and a link back to the standard it comes from. Where this build deliberately diverges from a
recommendation, the reason is stated explicitly — divergences exist only where the strict value would break
a normal Laravel application.

## Standards & references

This build is guided by [OWASP](https://owasp.org/) — not a single document but the project as a whole.
Each layer of the stack is hardened against the most relevant OWASP resource, and every section of this
README cites the exact source it follows so the reasoning is verifiable.

| Area | Source |
|------|--------|
| PHP runtime (`php.ini`) | [OWASP PHP Configuration Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html) |

## Legend

Each directive below is tagged so you can tell hardening from pragmatism at a glance:

| Tag | Meaning |
|-----|---------|
| ✅ **OWASP** | Matches the OWASP recommended value. |
| ⚠️ **Deviation** | Intentionally differs from OWASP — rationale given. Review before copying into your own project. |
| ➕ **Extra** | Not in the OWASP cheat sheet, added as an established hardening or operational best practice. |
| ⬜ **Omitted** | Present in the OWASP example but intentionally left out — rationale given. |

---

## `php.ini` (production)

File: [`docker/production/php/production/php.ini`](docker/production/php/production/php.ini)

### Error handling & logging

[OWASP · PHP error handling](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html#php-error-handling)

| Directive | Value | Tag | Rationale |
|-----------|-------|-----|-----------|
| `expose_php` | `Off` | ✅ | Removes the PHP version from the `X-Powered-By` header, reducing stack fingerprinting. |
| `error_reporting` | `E_ALL` | ✅ | Report every error level; never silently drop signal. |
| `display_errors` | `Off` | ✅ | Rendering errors into the HTTP response leaks paths, code structure and data. Logs only. |
| `display_startup_errors` | `Off` | ✅ | Same, for PHP/extension initialization errors. |
| `log_errors` | `On` | ✅ | Since errors aren't shown to users, they must be captured in logs. |
| `ignore_repeated_errors` | `Off` | ✅ | Don't collapse repeated errors — collapsing can hide a burst indicating an attack or bug. |
| `error_log` | `/proc/self/fd/2` | ⚠️ | OWASP writes to a file path; in a container, logs go to stdout/stderr and are collected by the runtime/orchestrator rather than a file inside the container. |
| `html_errors` | `Off` | ✅ | Error output isn't wrapped in HTML — avoids XSS-via-error-output and keeps logs clean. |
| `zend.exception_ignore_args` | `On` | ✅ | Keeps function arguments out of exception stack traces, where secrets and PII often appear. |
