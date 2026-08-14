# laravel-hardened-docker

A reference production build of **Laravel 13 on PHP 8.5**, hardened along the
[OWASP](https://owasp.org/) recommendations — from the container up to the application itself.

## Layout

```
docker/
└── php/
    └── production/
        └── conf.d/
            ├── 20-opcache.ini
            └── zz-owasp-hardening.ini
```