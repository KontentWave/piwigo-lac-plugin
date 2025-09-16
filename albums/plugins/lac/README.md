# Piwigo Legal Age Consent (LAC)

Legal age gate enforcing explicit confirmation for guest (anonymous) users before any gallery content is shown. Includes admin enable/disable, external decline fallback, configurable consent duration, and resilient session/cookie reconstruction.

## What It Does

On each public request the plugin runs early (`init` hook) and:

1. Skips logic if plugin disabled (`lac_enabled = false`).
2. Allows immediately if visitor is an authenticated (non-guest) Piwigo user.
3. Loads consent state from session (structured) or upgrades legacy flag if present.
4. If structured consent exists and not expired -> allow.
5. If missing but a valid timestamp cookie exists (within both configured duration and max window) -> reconstruct session and allow.
6. Otherwise -> redirect guest to root consent page (`/index.php` outside gallery root) unless already on that page (prevent loop), or to configured external fallback after decline.

## Consent Flow

1. Guest hits gallery (`/albums/index.php`).
2. Guard sees no valid consent; redirects to `/index.php` (parent of gallery root).
3. Consent page renders Yes / No form (+ legal clause markup you customize).
4. Yes:
   - Sets `$_SESSION['lac_consent'] = ['granted'=>true,'timestamp'=>time()]` (also keeps legacy boolean for compatibility).
   - Drops timestamp cookie (name via constant `LAC_COOKIE_NAME`, default `LAC`).
   - Redirects back to gallery landing page (or original target soon – roadmap).
5. No:
   - Redirects to configured external fallback URL (validated: http/https, external host, <=2048 chars) or safe default.
6. Gallery access allowed until consent expires (if duration > 0) or session ends (duration = 0).

## Consent Storage Model

Structured session payload (primary):

```php
$_SESSION['lac_consent'] = [
	'granted'   => true,
	'timestamp' => 1730000000 // unix seconds
];
```

Legacy flag (temporary compatibility): `$_SESSION['lac_consent_granted'] = true;` — auto-upgraded on first guard pass.

Timestamp Cookie (reconstruction aid): value is original acceptance unix timestamp; reused only if still within admin duration AND within a hard cap window (`LAC_COOKIE_MAX_WINDOW`, 24h) to avoid stale resurrection.

Expiration Rule: consent valid while `time() < timestamp + (duration_minutes * 60)`. Duration `0` means session-only (no time comparison).

## Important Files

| File                                                     | Purpose                                                                |
| -------------------------------------------------------- | ---------------------------------------------------------------------- |
| `main.inc.php`                                           | Defines constants, registers guard on `init` (deferred includes).      |
| `include/age_gate.inc.php`                               | Guard logic: guest checks, consent reconstruction, redirects.          |
| `include/functions.inc.php`                              | Helpers: guest detection, expiration, duration fetch, legacy upgrade.  |
| `/index.php` (web root)                                  | Consent UI & sets session/cookie state (not part of plugin directory). |
| `./.github/adr/consent-expiration-and-reconstruction.md` | Architectural decision record for expiration strategy.                 |

## Configuration (Admin UI)

Settings stored in Piwigo `config` table:

| Key                      | Description                                                         |
| ------------------------ | ------------------------------------------------------------------- |
| `lac_enabled`            | Master toggle (true/false).                                         |
| `lac_fallback_url`       | External URL for decline action (validated).                        |
| `lac_consent_duration`   | Minutes consent remains valid (0 = session-only).                   |
| `lac_apply_to_logged_in` | Apply gate to logged-in non-admin users (admins/webmasters bypass). |

Changing duration does not retroactively modify existing timestamps; they expire naturally.

## Debugging

Append `?lac_debug=1` to any gallery URL to emit minimal guard diagnostics (bootstrap, guest detection, duration, expiration event). Add `&lac_debug_verbose=1` for extra lines (reconstruction, branching decisions). Debug output is lightweight and safe to leave enabled only when needed.

## Testing

Current PHPUnit tests (roadmap to expand) cover:

- Hook registration (`init`).
- Guest/consent allow path.
- Expiration enforcement (non-expired vs expired).
- Admin validation for fallback URL & duration.

Planned additional tests:

- Cookie reconstruction path.
- Legacy flag upgrade.
- Signed cookie (future) once implemented.

Run tests from repository root once test harness is installed:

```bash
composer install
vendor/bin/phpunit
```

## Logged-in User Application Matrix

| User Type          | Setting Off | Setting On |
| ------------------ | ----------- | ---------- |
| Guest              | Gated       | Gated      |
| Logged-in (normal) | Allowed     | Gated      |
| Admin/Webmaster    | Allowed     | Allowed    |

## Roadmap / Extension Ideas

- Signed (HMAC) consent cookie.
- Allow-list of non-gated informational pages (About / Terms).
- Expiration warning banner prior to redirect.
- Localized consent text + translations.
- Analytics (opt-in) for acceptance vs decline.
- Admin debug panel: remaining seconds until expiration.

## Internal Name / Legacy Info

- Directory name fixed: `lac` (must remain unchanged).
- Historic plugin listing: http://piwigo.org/ext/extension_view.php?eid=543
- Legacy session flag still recognized (auto-upgraded); slated for removal in a future major iteration.

## License

See `LICENSE.txt`.
