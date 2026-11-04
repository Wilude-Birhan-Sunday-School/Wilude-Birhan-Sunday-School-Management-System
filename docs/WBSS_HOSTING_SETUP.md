# WBSS hosting and environment setup

This guide is for the independent WBSS deployment at **wbss.felegekidusan.com**. It does not give permission to connect to the original school's database, import private records, deploy code, or run destructive setup. Do not paste secrets into chat, issues, Git or screenshots.

## 1. Confirm the document root and HTTPS

In cPanel → **Domains**, find the **Document Root** of `wbss.felegekidusan.com`; it must be the folder that contains this project's `config.php`. Check the full filesystem path, not just its folder name. Example paths below use `USER` as a placeholder.

Enable AutoSSL or a valid certificate covering the exact subdomain. Confirm `https://wbss.felegekidusan.com/` opens without a warning before entering credentials. The mobile app is configured to require HTTPS and will not use plain HTTP. From this development workspace the hostname did not resolve, so DNS, certificate, server and API are **not yet verified**.

## 2. Put `.wbss_env.php` outside all public document roots

The updated WBSS `config.php` looks for `.wbss_env.php` in this order: (1) the parent of the project, (2) the grandparent, (3) the project itself. The third location is for development only; do not use it for production secrets. Never place the file anywhere publicly served, even if it begins with a dot. For example:

```text
/home/USER/.wbss_env.php              ← private file
/home/USER/public_html/wbss/config.php ← subdomain document root
```

Here, the private file is **two** levels above the site folder. If your subdomain root is `/home/USER/wbss/`, the file `/home/USER/.wbss_env.php` is one level above it. Your hosting provider can confirm whether `/home/USER` is private and which PHP account can read it. If your file is currently in `public_html`, arrange to move it outside every web root; a parent of a subdomain folder can itself be public.

The updated loader searches `.wbss_env.php` before anything else and will not load the older `.fkss_env.php` or `.wbws_env.php` on a normal production request. Note the spelling: `.wbss_env.php` is different from `.wbws_env.php`. The legacy `.fkss_env.php` and `.wbws_env.php` fallbacks are enabled only in an explicitly configured `SSMS_AUDIT_TESTING=1` disposable test environment; **never set that flag on production**. The currently deployed older code may still prefer `.fkss_env.php`, so do not assume this protection is live until the updated code is deliberately deployed and verified. Do not alter or delete the original school's configuration.

Use cPanel File Manager → Settings → **Show Hidden Files**, and copy `env.example.php` to the safe private location as `.wbss_env.php`. Set the file to owner-only permissions such as `0600` if the PHP process runs as that account; ask the host about a secure equivalent if PHP cannot read it. Never commit the real file.

## 3. Configure the database and stable security keys

In cPanel → **MySQL Databases**, note the *full* database name and username (hosting accounts often prefix both). Create or identify a database user, set its password, and **add that user to the WBSS database** with the privileges required by the application. If using All Privileges, grant them to the WBSS database only, not to other schools' databases. `DB_HOST` is often `localhost`, but use the host's actual MySQL hostname if different.

Edit the private PHP file. These are placeholders, not real credentials; keep the `<?php` at the top and use valid PHP syntax:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'USER_wbss_database');
define('DB_USER', 'USER_wbss_user');
define('DB_PASS', 'YOUR_DATABASE_PASSWORD');

define('SITE_DOMAIN', 'wbss.felegekidusan.com'); // no https:// or slash

define('JWT_SECRET', 'A_UNIQUE_LONG_RANDOM_VALUE');
define('BACKUP_KEY', 'A_DIFFERENT_LONG_RANDOM_VALUE');
define('HEALTH_KEY', 'A_DIFFERENT_LONG_RANDOM_VALUE');
define('MONITOR_SECRET_KEY', 'A_DIFFERENT_LONG_RANDOM_VALUE');
define('TELEMETRY_HASH_SECRET', 'A_DIFFERENT_LONG_RANDOM_VALUE');
```

Use five different, cryptographically random values. If you have SSH/Terminal, `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` generates one value; run it independently for each key and paste each result directly into the private file, not into chat. Preserve existing valid keys if the site is already using them: changing token keys invalidates existing token signatures and changing backup keys can make backups unrecoverable. Prefer hexadecimal random keys, which need no PHP string escaping. Database passwords containing `'` or `\\` must be escaped correctly in a PHP single-quoted string. Never leave the placeholder values in use.

Optionally check syntax privately with `php -l /home/USER/.wbss_env.php`; this does not test DB connectivity. Avoid `cat` or diagnostics that print secrets into a shared terminal or ticket. Preserve the other template options if needed.

## 4. An empty database is not yet an installed application

Creating the database and user does **not** create the application's tables or initial administrator. This repository does not provide a verified, current single-file production schema: `sql/legacy/database_schema.sql` is explicitly stale and the numbered migrations cannot safely be applied blindly in sequence to an empty database. Do **not** import that file, run `tests/e2e/provision_env.sh`, or run test seeds on the real database. Those scripts are for disposable test systems and can reset or delete data.

Before importing anything, establish whether your database is truly empty and obtain a separately verified **structure-only** current schema and safe migration/initial-admin procedure from an authorized maintainer. A structure-only export must not include the other school's student records, users, password hashes, private documents or secrets. Back up any existing WBSS data first, and review the schema and import plan before execution.

## 5. Verify before accepting real data

After an authorized deployment, check that HTTPS works, that the application points to the WBSS database (not the original school's), and that the verified schema and admin account are in place. `/api/v1/ping` checks only a lightweight API route; it does **not** prove database connectivity, schema completeness, or the correct school identity. Check the host's private error log without publishing secrets if the page returns 500. Validate role access and a synthetic record in a safe environment before using real records.

The app ID in the local change is `com.felegekidusan.wbss` for Android and iOS. This is a separate installation and does not carry over the old app's local storage or offline work. Confirm the ID and store/signing ownership before release. The backend's new release configuration is `.wbss_app_release.php` and its private release directory is `wbss_releases`; do not point them to or delete the old school's artifacts. Old release settings are not migrated automatically. The current release loader and manager look for the private release file and `wbss_releases` directly in the project’s parent; the APK resolver also permits the project’s `releases` directory. If the parent is `public_html`, those paths are not outside every web root: do not publish an APK until the host layout and a safe private release-storage arrangement are reviewed. The example release path in `api/v1/app_release.example.php` is not automatically accepted when it is two levels above the project.
