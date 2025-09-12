# Piwigo Legal Age Consent (LAC)

Minimal age gate enforcing an explicit confirmation for guest users before any gallery content is shown.

## What It Does

On each request (public side) the plugin checks:

1. Is the current visitor a guest? If not (logged-in user) -> allow.
2. If guest: does `$_SESSION['lac_consent_granted']` exist and evaluate to `true`? -> allow.
3. Otherwise -> immediate HTTP redirect to the root `/index.php` (the standalone consent page you place one level above the Piwigo gallery directory).

## Consent Flow

1. Visitor lands on gallery (e.g. `/gallery/albums/index.php`).
2. Plugin detects missing consent and redirects to root `/index.php`.
3. Root page presents Yes / No form.
4. On "Yes": sets session flag `$_SESSION['lac_consent_granted'] = true;` (and optional cookie), then redirects back to gallery.
5. On "No": sends visitor to an external fallback (e.g. Google) or previous referrer.
6. For the remainder of the session the gallery is accessible (flag lives in `$_SESSION`).

## Key Session Variable

`$_SESSION['lac_consent_granted']` (boolean true = consent granted). The plugin only reads this value; setting it happens in the root `index.php` consent page.

## Important Files

| File                        | Purpose                                                                    |
| --------------------------- | -------------------------------------------------------------------------- |
| `main.inc.php`              | Registers `lac_age_gate_guard` on `init` (public side).                    |
| `include/age_gate.inc.php`  | Implements redirect logic.                                                 |
| `include/functions.inc.php` | Helper functions (`lac_is_guest`, `lac_has_consent`, `lac_gate_decision`). |
| `/index.php` (web root)     | Consent form + sets session flag.                                          |

## Testing

PHPUnit tests live in `/tests` (see `AgeGateTest.php`). They assert:

- Hook is attached to `init`.
- Logged-in user bypasses gate.
- Guest with consent bypasses gate.
- Guest without consent (missing or false flag) triggers redirect decision.

Run tests from project root:

```bash
composer install
vendor/bin/phpunit
```

## Extending (Future Ideas)

- Configurable whitelist of public pages.
- Admin UI to toggle age gate or customize messages.
- Persist consent across sessions (e.g. signed cookie) while retaining session flag for fast checks.

## Internal Name / Legacy Info

- Internal plugin directory name: `lac` (must remain unchanged).
- (Historic) Plugin page: http://piwigo.org/ext/extension_view.php?eid=543

## License

See `LICENSE.txt`.
