# ADR: Root Consent Page Logic (Phase 6 Part 2)

Status: Accepted
Date: 2025-09-17

## Context

The Legal Age Consent (LAC) plugin enforces an age gate across Piwigo. The root consent page (`/index.php`) operates outside the Piwigo gallery path and must coordinate with the plugin when present, but also remain safe and functional if the plugin is inactive or unavailable. Earlier versions read duration and reconstructed consent from a cookie regardless of plugin state, which could cause inconsistent behavior when the plugin was deactivated.

## Decision

Implement a “plugin in use” detection and branch logic in the root page:

- Plugin in use = Piwigo booted + LAC files available + LAC active (state='active' in `plugins` table).
- When plugin is in use:
  - Include the plugin bootstrap and call `lac_is_user_exempt()`.
  - If exempt, redirect to the gallery index and exit.
  - Load `lac_consent_duration` from DB and allow cookie-based auto-recognition using the LAC cookie.
  - Validate session consent against duration; accept legacy flag only when duration = 0.
- When plugin is not in use (fallback mode):
  - Do not include plugin or call exemption logic.
  - Enforce session-only gating (ignore duration and cookie reconstruction):
    - If session has structured (`$_SESSION['lac_consent']`) or legacy (`$_SESSION['lac_consent_granted']`) consent, redirect immediately.
    - Otherwise show the consent form.

Additionally, add a session-consent short-circuit early in the root flow:

- If consent is already in the session, redirect immediately.
- Respect duration only when plugin is in use; ignore duration in fallback mode.

## Rationale

- Consistency: Root behavior aligns with plugin’s rules only when the plugin is truly in use.
- Safety: Deactivating or removing the plugin won’t break access; root continues with simple, session-only gating.
- UX: Returning users within the same browser session are not re-prompted.

## Consequences

- The root page now queries the `plugins` table to confirm activation status when Piwigo is booted.
- Cookie-based reconstruction and duration checks are completely bypassed in fallback mode.
- Session-consent short-circuit reduces unnecessary prompts and page loads.

## Security Considerations

- Same-origin and gallery-subtree checks are enforced for redirect targets.
- The `sid` parameter is stripped while preserving PATH_INFO-style routing.
- Security headers (HSTS, X-Frame-Options, X-Content-Type-Options, XSS-Protection, Referrer-Policy) remain applied.
- Session cookies are configured with `httponly`, `secure` (when HTTPS), and `samesite=Lax`; session IDs are periodically regenerated.

## Implementation Notes

- Root determines plugin-in-use with:
  - Files available: `file_exists('albums/plugins/lac/include/bootstrap.inc.php')`
  - Piwigo booted: includes `albums/include/common.inc.php` after defining `PHPWG_ROOT_PATH`.
  - Active in DB: `SELECT state FROM <prefix>plugins WHERE id='lac'` equals `'active'`.
  - Gate: `$lacUsePlugin = booted && available && active`.
- When `$lacUsePlugin` is true:
  - `include bootstrap.inc.php` and call `lac_is_user_exempt()`.
  - Load duration from `<prefix>config` and allow cookie reconstruction (LAC cookie) using original timestamp.
- When `$lacUsePlugin` is false:
  - Skip duration DB lookup and cookie reconstruction.
  - Session-only gating and immediate redirect if session already holds consent.
- File references:
  - Root page: `/index.php`
  - Plugin bootstrap: `/albums/plugins/lac/include/bootstrap.inc.php`
  - Piwigo bootstrap: `/albums/include/common.inc.php`

## Alternatives Considered

- Always use cookie reconstruction regardless of plugin state: rejected for inconsistency and surprising behavior when deactivated.
- Require plugin for root to operate: rejected; root must function safely during deployment or outages.
- Store activation state in a separate flag file: rejected; DB is the canonical source of plugin activation.

## Testing

- Admin/logged-in users exempt when plugin is in use.
- Logged-in users gated when apply-to-logged-in is enabled (plugin in use).
- Guests continue to see the form when consent missing.
- Fallback mode: session-only gating and no cookie reconstruction or duration checks.
- Session-consent present: immediate redirect from root without prompting.

## References

- `/var/www/piwigo/index.php`
- `/var/www/piwigo/albums/plugins/lac/include/bootstrap.inc.php`
- `/var/www/piwigo/albums/plugins/lac/include/functions.inc.php`
- `/var/www/piwigo/albums/include/common.inc.php`
