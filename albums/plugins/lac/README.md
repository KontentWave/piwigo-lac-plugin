# Piwigo Legal Age Consent (LAC)

A production-ready age verification (18+) gate for Piwigo. It blocks access to gallery content until users (guests and optionally logged‑in non-admins) explicitly confirm legal age. It supports configurable consent duration, graceful fallback when the plugin is inactive, and secure decline handling.

## Key Features

- Init hook enforcement (early redirect before content exposure)
- Explicit consent page outside gallery root (`/index.php`)
- Admin settings: enable/disable, external decline URL, consent duration (minutes, 0 = session only), apply-to-logged-in users
- Consent persistence (session + optional timestamp cookie for reconstruction when active)
- Expiration handling with strict time window
- Return-to-last-page after consent (deep link preservation)
- Session-only automatic fallback if plugin inactive/missing (no fatals, no duration/cookie logic)
- Secure headers & hardened session handling (HttpOnly, SameSite, optional Secure)
- User role awareness (admin/webmaster always exempt)

## Folder / File Structure (Expected)

```
<web root>/
  index.php                 # Root consent page (age form + logic)
  ageconsent.css            # Stylesheet (customizable)
  legal_clause.html         # Legal / policy text referenced from form
  .gallerydir               # (Optional) Contains ONLY the gallery directory name if not 'albums'
  albums/                   # Piwigo gallery root (default if .gallerydir absent)
    include/common.inc.php  # (Piwigo core)
    plugins/
      lac/                  # THIS plugin directory (must remain named 'lac')
        main.inc.php
        include/*.inc.php
        README.md
```

Do NOT rename the `lac` directory; Piwigo references the folder name as the plugin ID.

## Installation

1. Backup First (Strongly Recommended)

   - Backup database (mysqldump) and gallery root (tar/zip) before deploying.
   - Verify restore procedure on staging (dry run) before production change.

2. Copy Files

   a. New Gallery Installation

   - Install Piwigo into a directory (recommended default: `albums`).
   - If you choose another name (e.g. `gallery`), create a file `.gallerydir` in the web root containing exactly that name (no slashes, no trailing newline issues).

   b. Existing Gallery

   - Identify the current gallery root directory name.
   - If it is not `albums`, create `.gallerydir` with the exact directory name.
   - Do NOT relocate a working gallery unless you have a tested rollback plan.

   c. Plugin Files

   - Copy the `lac` folder into `<galleryDir>/plugins/`.
   - Ensure the path is: `<web root>/<galleryDir>/plugins/lac/main.inc.php`.

   d. Root Consent Assets

   - Place `index.php`, `ageconsent.css`, and `legal_clause.html` in the web root (same level as `<galleryDir>`).
   - If a root `index.php` already exists, back it up first and manually merge any custom logic—do not overwrite blindly.

   e. Optional Customization

   - Adjust `ageconsent.css` for styling; edit `legal_clause.html` for jurisdiction-specific wording after confirming base flow works.

3. Permissions

   - Ensure the web server user can read plugin files. Standard Piwigo writable directories (upload, \_data, etc.) remain unchanged.

4. Activate Plugin

   - Log into Piwigo admin → Plugins → Installed → Activate “LAC”.

5. Configure

   - Open the plugin settings page:
     - Enable Gate (verify it is ON).
     - Set an external Fallback URL (HTTPS, different host) for declines.
     - Set Consent Duration (minutes). 0 = session-only (no time-based expiry).
     - (Optional) Apply to Logged-in Users (non-admins). Admin/webmaster always exempt.

6. Test
   - In an incognito/private window open a gallery URL (e.g. `/<galleryDir>/index.php`) → expect redirect to `/index.php`.
   - Accept → redirected back (or original deep link if captured). Reload root URL → no prompt (same session) unless duration expired.
   - Decline → redirected to configured external fallback.

## Upgrade

- Backup first (DB + files).
- Replace plugin directory contents with new version (preserve folder name `lac`).
- Root `index.php`: reapply any local customizations (diff & merge). Avoid editing plugin internals if not necessary.
- Clear Piwigo template/cache if needed and test guest flow.

## Uninstall / Deactivate

- Deactivating plugin: root page automatically operates in session-only fallback (no duration, no cookie reconstruction, all users gated except existing session consents).
- To fully remove: deactivate in admin, delete `albums/plugins/lac/`, optionally restore original root `index.php` (or remove age form code if integrated).

## Configuration Keys (Stored in DB)

| Key                      | Purpose                                  |
| ------------------------ | ---------------------------------------- |
| `lac_enabled`            | Master toggle                            |
| `lac_fallback_url`       | Decline redirect (must be external)      |
| `lac_consent_duration`   | Minutes (0 = session-only)               |
| `lac_apply_to_logged_in` | Gate logged-in non-admin users when true |

## Fallback Behavior (Important)

If the plugin is inactive or its files are missing, the root consent page automatically:

- Skips cookie reconstruction & duration checks
- Uses only current PHP session consent markers (`lac_consent` or legacy `lac_consent_granted`)
- Continues to protect content (no crash) until plugin reactivated

## Precautions & Best Practices

- Always back up before upgrades or root `index.php` changes.
- Use HTTPS so Secure cookies are enforced (prevents interception).
- Keep consent duration modest; extremely long windows reduce compliance value.
- Avoid internal fallback URLs (enforced) to prevent redirect loops.
- Monitor logs with `?lac_debug=1` only temporarily (avoid noise in production).

## Troubleshooting Quick Table

| Symptom                             | Likely Cause / Fix                              |
| ----------------------------------- | ----------------------------------------------- |
| Always prompted even same session   | Session not persisting (check cookie path/name) |
| Decline goes to Google              | No valid external fallback configured           |
| Logged-in user gated unexpectedly   | `Apply to Logged-in Users` enabled              |
| Cookie reconstruction not happening | Plugin inactive OR duration expired             |
| Admin sees form                     | Plugin inactive (fallback) or mis-detected role |

## Minimal Flow Summary

1. Guest hits gallery → redirected to root consent page.
2. Accept → session consent stored (+ cookie if plugin active) → redirect back (original target if captured).
3. Within duration (or same session if duration=0) → access allowed silently; else re-prompt.
4. Decline → external safe fallback.

## References & Extended Docs

For deeper architectural rationale and decisions:

- Project Sheet: `.github/lac_project_sheet.md`
- Root Logic ADR: `.github/adr/lac_root_page_logic.md`
- Expiration & Reconstruction ADR: `.github/adr/consent-expiration-and-reconstruction.md`
- Feature BDD specs: `.github/features/*.feature`

## License

See `LICENSE.txt` (Piwigo compatible open-source license).
