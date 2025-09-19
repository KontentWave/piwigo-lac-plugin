# ADR: Return to Last Page After Consent

Status: Accepted
Date: 2025-09-17

## Context

The initial implementation redirected consenting users to the gallery index, which could be disruptive when the user originally attempted to open a specific photo or album page. We want a seamless flow where, after providing age consent, users return to the exact page they intended to view. A complication is that the gallery and the root consent page may use different session cookies, so the destination must be preserved reliably across that boundary.

## Decision

Implement a destination capture and one-time redirect mechanism:

1. When the guard triggers (guest or logged-in non-admin when applicable), capture the current request URI and save it in `$_SESSION['LAC_REDIRECT']`.
2. Redirect to the root consent page `/index.php` with `?redirect=<encoded-uri>` in production to bridge potential session differences.
3. The root consent page captures and validates the `redirect` parameter:
   - Same-origin (or relative) only, http/https scheme only
   - Path must be within the gallery subtree (e.g., `/albums/...`)
   - Strip `sid` from the query while preserving PATH_INFO style
   - Save the sanitized path to `$_SESSION['LAC_REDIRECT']`
4. On consent acceptance, redirect to the saved destination and immediately unset it (one-time use). If none is available or it’s invalid, fall back to the gallery index.

## Consequences

- UX improvement: Users land on the specific content they intended to view.
- Predictable behavior: Latest destination overwrites the previous one; direct consent page visits still land on the gallery index.
- Compatibility: Test mode avoids adding `?redirect=` to keep legacy tests stable.

## Implementation

- Guard: `albums/plugins/lac/include/age_gate.inc.php`

  - Saves intended URI (current `REQUEST_URI`) in session.
  - Appends `?redirect=<encoded-uri>` to `/index.php` only when not in test mode and URI is not `/`.

- Root consent page: `/index.php`
  - GET handler for `redirect`: validate and store into `$_SESSION['LAC_REDIRECT']`.
  - POST accept (“Yes”): prefer the saved destination, unset it immediately, else fall back to `/<galleryDir>/index.php`.
  - Ordering fix: compute `$galleryDir` before accept handling.

## Security

- Same-origin enforcement for absolute URLs; relative URLs accepted.
- Restrict redirect path to the gallery subtree; otherwise fallback prevents open redirects.
- Remove `sid` query parameter to avoid session fixation.
- No change to admin bypass or consent validation semantics.

## Alternatives Considered

1. Store absolute URL and always trust it.

- Rejected due to open redirect risk and cross-origin concerns.

2. Use only session storage without query param bridging.

- Rejected because gallery/root may use different session cookies; the destination could be lost.

## References

- Project sheet Phase 6: `albums/plugins/lac/tools/lac_project_sheet.md`
- Feature spec (BDD): `albums/plugins/lac/tools/features/age_gate.feature`
- Code: `albums/plugins/lac/include/age_gate.inc.php`, `/var/www/piwigo/index.php`
