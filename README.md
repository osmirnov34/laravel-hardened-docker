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
