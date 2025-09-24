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

You can deploy the root consent assets in one of two ways:

1. Physical files in the web root (simplest, zero indirection)
2. Symlinks in the web root pointing back into the plugin (centralizes maintenance)

Both approaches are fully supported. Choose one and keep it consistent to avoid confusion during upgrades.

### Option 1: Physical Files Layout

```
<web root>/
   index.php                 # (Physical) Root consent page (age form + logic)
   ageconsent.css            # (Physical) Stylesheet (customizable)
   legal_clause.html         # (Physical) Legal / policy text referenced from form
   .gallerydir               # (Optional) Contains ONLY the gallery directory name if not 'albums'
   albums/                   # Piwigo gallery root (default if .gallerydir absent)
      include/common.inc.php  # (Piwigo core)
      plugins/
         lac/                  # THIS plugin directory (must remain named 'lac')
            main.inc.php
            include/*.inc.php
            README.md
```

### Option 2: Symlink-Based Layout

Keep canonical versions of the root consent assets inside the plugin (for example under `albums/plugins/lac/webroot/`) and expose them in the actual web root via symbolic links. This makes upgrades and diffs easier (single source of truth) and avoids accidental drift.

```
<web root>/
   index.php      -> albums/plugins/lac/webroot/index.php
   ageconsent.css -> albums/plugins/lac/webroot/ageconsent.css
   legal_clause.html -> albums/plugins/lac/webroot/legal_clause.html
   .gallerydir    -> (optional plain file OR may remain physical; usually a tiny real file)
   albums/
      plugins/
         lac/
            webroot/
               index.php
               ageconsent.css
               legal_clause.html
            include/
            main.inc.php
```

Symlink Benefits:

- Centralized maintenance (edit once in plugin source).
- Cleaner version control (no manual copy-on-upgrade mistakes).
- Faster rollback (switch plugin folder / branch and links still target the right versions).

Symlink Considerations:

- Ensure the web server user and PHP have permission to traverse the plugin directory path.
- When packaging (zip/tar) for distribution, symlinks may dereference; document expected layout for downstream deployers.
- Local development on Windows may require enabling developer mode or using WSL for symlink creation.
- Some shared hosting panels disallow symlinks—use Option 1 in those environments.

Do NOT rename the `lac` directory; Piwigo references the folder name as the plugin ID.

Do NOT rename the `lac` directory; Piwigo references the folder name as the plugin ID.

## Installation

Pick ONE of the deployment models below for the root consent assets (`index.php`, `ageconsent.css`, `legal_clause.html`). The plugin code itself always lives inside `<galleryDir>/plugins/lac/`.

1. Backup First (Strongly Recommended)

   - Backup database (mysqldump) and gallery root (tar/zip) before deploying.
   - Verify restore procedure on staging (dry run) before production change.

2. Copy / Link Files

   ### Option 1: Physical Files

   a. New Gallery Installation

   - Install Piwigo into a directory (recommended default: `albums`).
   - If you choose another name (e.g. `gallery`), create `.gallerydir` in the web root containing exactly that name (no slashes).

   b. Existing Gallery

   - Determine gallery directory name; if not `albums`, create `.gallerydir` accordingly.
   - Avoid renaming an active gallery without a rollback plan.

   c. Plugin Files

   - Copy the `lac` folder into `<galleryDir>/plugins/`.
   - Verify: `<web root>/<galleryDir>/plugins/lac/main.inc.php` exists.

   d. Root Consent Assets (Physical)

   - Copy `index.php`, `ageconsent.css`, `legal_clause.html` into the web root.
   - Backup any pre-existing root `index.php` and manually merge needed custom logic.

   e. Optional Customization

   - Adjust `ageconsent.css` / `legal_clause.html` after verifying baseline flow.

   ### Option 2: Symlink-Based Deployment

   a. Prepare Plugin-Resident Sources

   - Ensure the plugin contains canonical files (e.g. `albums/plugins/lac/webroot/index.php`, `ageconsent.css`, `legal_clause.html`).

   b. Create Symlinks (from web root):

   ```bash
   ln -s albums/plugins/lac/webroot/index.php ./index.php
   ln -s albums/plugins/lac/webroot/ageconsent.css ./ageconsent.css
   ln -s albums/plugins/lac/webroot/legal_clause.html ./legal_clause.html
   ```

   - Adjust paths if your gallery dir ≠ `albums` (also update `.gallerydir`).

   c. Validation

   - `ls -l` should show links pointing to the plugin files.
   - Hitting `/index.php` in browser must render the consent form.

   d. Upgrades

   - Replace / update plugin directory → symlinks still valid (no copying needed).
   - If you switch branches or versions, confirm target files still exist.

   e. Fallback / Reversion

   - To revert to physical: remove the symlinks (`rm index.php ageconsent.css legal_clause.html`) and copy files physically.

   ### Symlink Warnings

   - On Windows native filesystems symlink creation may require admin rights / developer mode.
   - Some hosting providers block symlink creation; in that case use physical deployment.
   - Backup scripts or deployment pipelines must preserve symlink metadata (use `tar` or `rsync -a`).

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
   - If using symlinks, verify `ls -l` in web root still shows valid targets after each deployment.

## Upgrade

- Backup first (DB + files).
- Replace plugin directory contents with new version (preserve folder name `lac`).
- Root `index.php`: reapply any local customizations (diff & merge). Avoid editing plugin internals if not necessary.
- Clear Piwigo template/cache if needed and test guest flow.

## Uninstall / Deactivate

- Deactivating plugin: root page automatically operates in session-only fallback (no duration, no cookie reconstruction, all users gated except existing session consents).
- To fully remove: deactivate in admin, delete `albums/plugins/lac/`, optionally restore original root `index.php` (or remove age form code if integrated).
- If using symlinks, remove the symlinked files (they do NOT delete the originals inside the plugin) before restoring any previous root `index.php`.

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
- For symlink deployments, include a quick integrity check (ensure links resolve) in CI/CD post-deploy hooks.

## Troubleshooting Quick Table

| Symptom                             | Likely Cause / Fix                              |
| ----------------------------------- | ----------------------------------------------- |
| Always prompted even same session   | Session not persisting (check cookie path/name) |
| Decline goes to Google              | No valid external fallback configured           |
| Logged-in user gated unexpectedly   | `Apply to Logged-in Users` enabled              |
| Cookie reconstruction not happening | Plugin inactive OR duration expired             |
| Root page 404 after deployment      | Symlink targets moved/renamed – recreate links  |
| Admin sees form                     | Plugin inactive (fallback) or mis-detected role |

## Minimal Flow Summary

1. Guest hits gallery → redirected to root consent page.
2. Accept → session consent stored (+ cookie if plugin active) → redirect back (original target if captured).
3. Within duration (or same session if duration=0) → access allowed silently; else re-prompt.
4. Decline → external safe fallback.
5. (Symlink variant) Upgrading plugin does not require recopying files – links remain valid.

## References & Extended Docs

For deeper architectural rationale and decisions:

- Project Sheet: `.github/lac_project_sheet.md`
- Root Logic ADR: `.github/adr/lac_root_page_logic.md`
- Expiration & Reconstruction ADR: `.github/adr/consent-expiration-and-reconstruction.md`
- Feature BDD specs: `.github/features/*.feature`

## License

See `LICENSE.txt` (Piwigo compatible open-source license).
