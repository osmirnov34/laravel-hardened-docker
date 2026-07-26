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
