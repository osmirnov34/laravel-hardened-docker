# laravel-hardened-docker

A reference production build of **Laravel 13 on PHP 8.5**, hardened along the
[OWASP](https://owasp.org/) recommendations — from the container up to the application itself.

## Layout

```
docker/
└── php/
    ├── Dockerfile
    └── production/
        └── conf.d/
            ├── 20-opcache.ini
            └── zz-owasp-hardening.ini
```

## Container image

Built by [`docker/php/Dockerfile`](docker/php/Dockerfile), following the
[OWASP Docker Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html).
Most of its 14 rules govern the *runtime*, not the build file; the table below maps only the rules
this Dockerfile actually carries, filled in as each part of the image is built.

✅ carried here · ⚠️ deviation · ➕ beyond the cheat sheet

| Rule | Lands in | |
|---|---|---|
| #13 Enhance supply chain security | **Dockerfile** — pinned base images (tag + digest); refreshing the digest is a repo-level concern, out of scope for this file | ✅ |

Both `FROM` lines pin a tag *and* an `@sha256:` digest. Docker resolves the digest and ignores the
tag, so builds are reproducible; the tag documents the intended version and bounds a future refresh
to the same minor line.

A pinned digest never picks up upstream patches on its own. Pinning gives integrity, not freshness —
the update process that supplies the latter (Renovate, Dependabot) is repository-level and is not
configured here.

## PHP configuration

Applied on top of [`php.ini-production` (PHP 8.5)](https://github.com/php/php-src/blob/PHP-8.5/php.ini-production),
following the [OWASP PHP Configuration Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html).

✅ per the cheat sheet · ⚠️ deviation · ➕ beyond the cheat sheet · ⬜ deliberately skipped

| Area | Options | |
|---|---|---|
| Error handling | `expose_php=Off`, `display_errors=Off`, `display_startup_errors=Off`, `log_errors=On`, `error_reporting=E_ALL`, `ignore_repeated_errors=Off` | ✅ |
| Error log | `error_log=/proc/self/fd/2` — stderr instead of a file, per container convention | ➕ |
| File uploads | `file_uploads=On`, `upload_tmp_dir=/tmp/laravel-uploads`, `max_file_uploads=10` | ✅ |
| Upload size | `upload_max_filesize=20M`, `post_max_size=25M` | ⚠️ |
| Executables | `enable_dl=Off`, `disable_functions=` system, exec, shell_exec, passthru, phpinfo, popen, proc_open, chdir, … | ⚠️ |
| Sessions | `use_strict_mode=1`, `use_only_cookies=1`, `cookie_secure=1`, `cookie_httponly=1`, `cookie_samesite=Strict`, `cookie_lifetime=14400`, `name=sessid`, `save_path`, `cache_expire=30` | ✅ |
| Session IDs | `session.sid_length`, `session.sid_bits_per_character` | ⬜ |
| Referer check | `session.referer_check` | ⬜ |
| Resource limits | `memory_limit=512M`, `max_execution_time=30` | ⚠️ |
| Misc | `html_errors=Off`, `report_memleaks=On`, `zend.exception_ignore_args=On` | ✅ |
| Operational | `date.timezone=Europe/Moscow` | ➕ |

The session block is defence in depth. Laravel runs its own session layer through `config/session.php`
and does not touch the native engine on the file, database, redis, cookie or array drivers; these
options cover code that calls `session_start()` directly.

`session.cookie_domain` ships as `full.qualified.domain.name`. Replace it before deploying.

### Deviations

| Option | Cheat sheet | Here | Reason |
|---|---|---|---|
| `disable_functions` | also disables `move_uploaded_file`, `mkdir`, `rmdir`, `chmod`, `rename`, `putenv` | those stay enabled | Laravel depends on them for uploads (`store()`), `Illuminate\Filesystem` (storage, atomic config and route cache) and phpdotenv. `chdir` has no such dependency and stays disabled. |
| `upload_max_filesize` / `post_max_size` | 2M / 8M | 20M / 25M | The cheat sheet values are illustrative minimums. Size these to what the application actually accepts. |
| `memory_limit` | 128M | 512M | Composer autoloading, Eloquent and queue workers regularly exceed 128M. |
| `session.sid_length`, `session.sid_bits_per_character` | set explicitly | left at default | PHP 8.4 deprecated changing either one. The defaults already yield 128 bits of entropy, which meets the session ID requirement. |
| `session.referer_check` | enabled | left off | PHP 8.4 deprecated any non-empty value. Cross-origin protection comes from Laravel's `VerifyCsrfToken` and `samesite=Strict`. |

## Performance

Kept in a separate overlay: no security standard covers OPcache. Sizing comes from
`opcache_get_status()` on a built image, everything else from the
[PHP manual](https://www.php.net/manual/en/opcache.configuration.php).

✅ per the manual · ⚠️ deviation · ➕ beyond the manual · ⬜ deliberately skipped

| Area | Options | |
|---|---|---|
| Activation | `opcache.enable=1`, `opcache.enable_cli=0` — CLI pays off only for long-running workers | ✅ |
| Sizing | `memory_consumption=256`, `interned_strings_buffer=16` (drawn from that 256), `max_accelerated_files=20000` (rounds up to 32531) | ⚠️ |
| Invalidation | `validate_timestamps=0` — code cannot change inside an immutable image. `revalidate_freq`, `max_wasted_percentage` kept explicit but inert | ✅ |
| Bytecode | `save_comments=1` — annotation-driven frameworks and PHPUnit break without it | ✅ |
| JIT | `opcache.jit=disable`, `jit_buffer_size=0` | ⚠️ |
| Platform | `huge_code_pages=0` — host transparent huge pages cannot be assumed | ✅ |
| OPcache API | `restrict_api=/nonexistent/opcache-api` | ➕ |
| On-disk cache | `opcache.file_cache` | ⬜ |
| Preloading | `opcache.preload`, `opcache.preload_user` | ⬜ |
| Tenancy checks | `validate_permission=0`, `validate_root=0` — one user per container, no chroot | ✅ |

### Deviations

| Option | Manual | Here | Reason |
|---|---|---|---|
| `opcache.jit` | tracing JIT, "recommended for most users" | `disable` | Laravel requests are I/O- and bootstrap-bound, so JIT buys little while adding runtime-generated native code to the attack surface. `disable` rather than `off`, which stays switchable through `ini_set()`. |
| Sizing values | 128M / 8M / 10000 | 256M / 16M / 20000 | Laravel plus `vendor/` outgrows the defaults. Placeholders pending a real reading. |
| `opcache.restrict_api` | unrestricted | path no application script matches | Blocks `opcache_reset()` from injected or uploaded files. Free here: with `validate_timestamps=0` a deploy is a new container, never a cache reset. |
| `opcache.file_cache` | available | unset | Read back at startup, so a writable cache directory becomes code execution. An immutable image gains nothing from it. |
| `opcache.preload` | available | unset | The largest win available, but preloaded code runs at FPM startup as `preload_user`, which the manual refuses to default to root. Opt in per project. |

The sizing values are marked `measured: TBD` and still need a reading from `opcache_get_status(false)`
on a warmed image. The overlay assumes `docker-php-ext-install opcache`; without it, nothing applies.

### Forced HTTPS scheme

`URL::forceScheme('https')` in [`AppServiceProvider`](app/Providers/AppServiceProvider.php), gated to
production, forces every generated URL — `route()`, `asset()`, signed links — to `https://`.

### Eloquent lazy loading

`Model::preventLazyLoading()` in [`AppServiceProvider`](app/Providers/AppServiceProvider.php) turns an
accidental N+1 into a reported fault instead of a silent one.

| Environment | Behaviour |
|---|---|
| Non-production | throws `LazyLoadingViolationException` — surfaces in development, fails the test suite |
| Production | logs at `warning` with model and relation, request proceeds |
| Model not yet persisted or just created | ignored, as in the framework default |

## Application

Configured in [`bootstrap/app.php`](bootstrap/app.php). Cheat sheet targets Laravel ≤10's
`App\Http\Kernel`; entries below are translated onto 11+'s middleware configuration.

✅ per the cheat sheet · ⚠️ deviation · ➕ beyond the cheat sheet · ⬜ deliberately skipped

| Area | Where | |
|---|---|---|
| Host header validation ([WSTG-INPV-17](https://owasp.org/www-project-web-security-testing-guide/latest/4-Web_Application_Security_Testing/07-Input_Validation_Testing/17-Testing_for_Host_Header_Injection)) | [`trustHosts()`](bootstrap/app.php#L16) — opt-in; unset, forged `Host` headers reach `url()`/`route()` | ➕ |
| Absolute session lifetime ([ASVS 5.0.0 §7.3.2](https://asvs.dev/v5.0.0/V7-Session-Management/)) | [`EnforceSessionAbsoluteTimeout`](app/Http/Middleware/EnforceSessionAbsoluteTimeout.php) appended to the `web` group ([`bootstrap/app.php`](bootstrap/app.php#L17)); sized by `session.absolute_lifetime` — Laravel has no built-in equivalent | ➕ |

## Environment

[`.env.prod.example`](.env.prod.example) is Laravel's stock `.env.example` with the lines below
changed; everything unlisted stays at the scaffold default.

| Option | Stock scaffold | Here | Reason |
|---|---|---|---|
| `APP_ENV` | `local` | `production` | Gates Laravel's own production guards: the artisan confirmation prompt on destructive commands, and providers that skip production. |
| `APP_DEBUG` | `true` | `false` | Debug output renders stack traces, environment values and absolute paths into error responses. Per [Error Handling CS](https://cheatsheetseries.owasp.org/cheatsheets/Error_Handling_Cheat_Sheet.html), the client gets a generic response and the details go to the log. |
| `LOG_CHANNEL` | `stack` | `stderr` | The stock stack writes inside the container, where the log dies with it. `stderr` hands the stream to the runtime, matching `error_log=/proc/self/fd/2`. |
| `LOG_LEVEL` | `debug` | `info` | `debug` records query bindings and request context — passwords, tokens and PII, all named under [Data to exclude](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html#data-to-exclude). Not `warning`, which would drop the authentication successes [OWASP requires](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html#which-events-to-log). |
| `DB_SSL*` | absent | commented | `sslmode` defaults to `prefer`, which encrypts opportunistically and verifies nothing; only `verify-full` with a pinned root certificate authenticates the server. Left commented: a database reached over the local socket gains nothing from it, one reached across the network cannot go without. |
| `SESSION_LIFETIME` | `120` | `15` | Inactivity timeout. [Laravel Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Laravel_Cheat_Sheet.html) puts it at 15 minutes; [ASVS 5.0.0 §7.3.1](https://asvs.dev/v5.0.0/V7-Session-Management/) requires the timeout but leaves the duration to your own risk analysis. The absolute cap of [§7.3.2](https://asvs.dev/v5.0.0/V7-Session-Management/), which Laravel has no equivalent for, is `session.absolute_lifetime`. |
| `SESSION_ENCRYPT` | `false` | `true` | [Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html#session-id-life-cycle) requires encrypting the session repository when sessions hold sensitive data. A reference build cannot know what a session will hold, so it encrypts unconditionally: a leaked dump, replica or backup then yields no `_token` or authenticated user. Rotating `APP_KEY` logs everyone out. |
| `SESSION_SECURE_COOKIE` | unset | `true` | `Secure` is mandatory per [Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html#secure-attribute), and [Laravel Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Laravel_Cheat_Sheet.html) puts `true` on HTTPS-only systems. Unset leaves it to the request scheme. |
| `SESSION_SAME_SITE` | `lax` | `strict` | [Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html#samesite-attribute): "must explicitly set `SameSite=Strict` (preferred) or `SameSite=Lax`". `lax` still sends the cookie on top-level cross-site GETs; `strict` withholds it entirely, layering on `VerifyCsrfToken`. |

## References

- [OWASP Docker Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html)
- [OWASP PHP Configuration Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [PHP: `php.ini-production`](https://github.com/php/php-src/blob/PHP-8.5/php.ini-production)
- [PHP: OPcache runtime configuration](https://www.php.net/manual/en/opcache.configuration.php)
- [OWASP Laravel Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Laravel_Cheat_Sheet.html)
- [OWASP Error Handling Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Error_Handling_Cheat_Sheet.html)
- [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html)
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [OWASP WSTG-INPV-17: Testing for Host Header Injection](https://owasp.org/www-project-web-security-testing-guide/latest/4-Web_Application_Security_Testing/07-Input_Validation_Testing/17-Testing_for_Host_Header_Injection)
- [OWASP ASVS 5.0.0 — V7 Session Management](https://asvs.dev/v5.0.0/V7-Session-Management/)

## License

[MIT](LICENSE)
