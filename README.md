# laravel-hardened-docker

A reference Docker build for running Laravel in production with a security-hardened configuration.

The goal is an **exemplary, auditable baseline**: every security-relevant setting is documented with its
rationale and a link back to the standard it comes from. Where this build deliberately diverges from a
recommendation, the reason is stated explicitly — divergences exist only where the strict value would break
a normal Laravel application.
