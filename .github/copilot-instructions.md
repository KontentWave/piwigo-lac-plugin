# AI Coding Agent Instructions for This Piwigo Fork / Legal Age Consent Plugin

These instructions capture project-specific knowledge so an AI agent can be productive quickly.

## Project Context & Environment

- **Piwigo Version:** `15.6.0`
- **PHP Version (Target):** `8.1+`

## CONTEXT: Files under webroot/ are deployed to the Piwigo root:

- webroot/index.php → /var/www/piwigo/index.php
- webroot/ageconsent.css → /var/www/piwigo/ageconsent.css
- webroot/legal_clause.css → /var/www/piwigo/legal_clause.css
- webroot/legal_clause.html → /var/www/piwigo/legal_clause.html
- webroot/.gallerydir → /var/www/piwigo/.gallerydir

## CONTEXT: GitHub metadata

- The root `.github/` is always a symlink to the active plugin’s `.github` folder.
  Example: `.github → albums/plugins/lac/.github`
  This means issue templates, workflows, etc. come from the active plugin repo.

## Symlinks note

- Files above appear at Piwigo root via symlinks; treat them as if they were real files in root.
- `.github` is always symlinked to the active plugin’s `.github/`.

## Scope & Architecture

- This workspace contains a Piwigo gallery plus a custom plugin `lac` (Legal Age Consent) under `albums/plugins/lac/`.
- Piwigo loads plugins via their `main.inc.php` files very early (see comment in that file). Only lightweight setup & `add_event_handler` calls should happen there.
- The `lac` plugin is currently a skeleton demonstrating: admin tab injection, menu injection, batch manager integration, public section creation, template prefilters, and adding a webservice method. **For the MVP, this boilerplate code can be ignored or removed to focus solely on the age gate functionality.**
- Public vs Admin context: many handlers are conditionally registered based on `defined('IN_ADMIN')`.

## Key Directories / Files

- Core plugin entry: `albums/plugins/lac/main.inc.php` – defines constants, registers hook handlers.
- Public event handlers: `albums/plugins/lac/include/public_events.inc.php` – defines functions for section routing (`lac_loc_end_section_init`), page inclusion (`lac_loc_end_page`), UI button injection, and template prefilter logic.
- Admin event handlers: `albums/plugins/lac/include/admin_events.inc.php` – adds menu links, tabs, batch manager prefilters/actions.
- Menu class handlers: `albums/plugins/lac/include/menu_events.class.php` – demonstrates class-based handler registration patterns.
- Web service methods: `albums/plugins/lac/include/ws_functions.inc.php` – adds `pwg.PHPinfo` method via `ws_add_methods` hook.
- Additional includes: `functions.inc.php` (currently empty for future shared helpers).
- Plugin templates: under `albums/plugins/lac/template/` and `albums/plugins/lac/admin/template/`.
- Plugin Directory Guard: `albums/plugins/lac/index.php` just redirects one level up to avoid directory listing / direct access.

## Plugin Constants (set in `main.inc.php`)

- `LAC_PATH`, `LAC_PUBLIC`, `LAC_ADMIN`, `LAC_DIR` – reuse these instead of recomputing paths/URLs.

## Event / Hook Patterns

- Register handlers with `add_event_handler(<event>, <callable>, EVENT_HANDLER_PRIORITY_NEUTRAL, <file>)` referencing the file for deferred load.
- Separation: Only register handlers in `main.inc.php`; implementation lives in `include/*.inc.php` or class files to keep bootstrap lean.
- Public section detection uses `loc_end_section_init` to set `$page['section']` then `loc_end_index` to include content.
- UI additions (buttons, menu items) call template methods like `$template->add_index_button` and `$template->add_picture_button` depending on `script_basename()`.

## Localization

- Language strings loaded with `load_language('plugin.lang', LAC_PATH);`. Add translations under a `language/` subfolder matching Piwigo conventions if expanding strings.

## Webservice Extension

- Add new API endpoints by hooking `ws_add_methods` and calling `$service->addMethod(...)`. Use flags/types (`WS_TYPE_INT`, etc.) as in `ws_functions.inc.php`.

## Planned MVP (Age Gate)

- See project sheet at `albums/plugins/lac/tools/lac_project_sheet.md`.
- See BDD specifications in `albums/plugins/lac/tools/features/age_gate.feature`.
- Core requirement: On every request for guest users, check a `$_SESSION['lac_consent_granted']` flag; if absent/false redirect to root `index.php` (to be placed outside gallery root). The implementation MUST hook the `init` event in `main.inc.php`.
- The plugin's responsibility is only to read the $\_SESSION['lac_consent_granted'] variable. The setting of this variable is handled externally by the Web Root Gate (/index.php).
- Future edits should add a new handler (e.g. `lac_age_gate_guard`) registered on `init` before other public page logic.

## Conventions & Practices

- Avoid heavy logic directly in handlers; delegate to small pure functions placed in `functions.inc.php` for testability.
- When adding hooks ensure admin/public separation to prevent unnecessary execution.
- Use existing constant names and mimic existing event naming scheme `lac_<event_description>`.
- Keep redirect logic fast: no template rendering before header redirects.

## Testing Strategy (Planned)

- TDD list (from project sheet):
  - it_correctly_hooks_into_the_piwigo_init_event
  - it_does_nothing_if_the_user_is_logged_in
  - it_does_nothing_if_a_guest_has_the_correct_consent_session_variable
  - it_redirects_a_guest_to_the_root_index_if_the_consent_session_is_missing
  - it_redirects_a_guest_to_the_root_index_if_the_consent_session_is_false
- No current test harness present: before implementing tests, decide between Pest or PHPUnit and add config (e.g. `phpunit.xml`) plus bootstrap to load minimal Piwigo environment or mock needed globals (`$user`, `$conf`, session state).
- To begin, create a tests/bootstrap.php file. This file should define the minimum required Piwigo constants and mock the necessary global variables (e.g., $user, $conf) to allow the unit tests to run in isolation.

## Safe Change Guidelines

- Do not rename `lac` directory (bootstrap check enforces name).
- Maintain early-load minimalism; adding large includes or side effects in `main.inc.php` can break Piwigo init.
- Use `defined('LAC_PATH') or die('Hacking attempt!');` guard in new include files for consistency.

## Quick Example: Adding a New Public Hook

1. In `main.inc.php` register: `add_event_handler('init', 'lac_age_gate_guard', EVENT_HANDLER_PRIORITY_NEUTRAL, LAC_PATH.'include/age_gate.inc.php');`
2. Create `include/age_gate.inc.php` implementing function; use `global $user;` and `$_SESSION` checks.

Provide feedback if test harness or deployment workflow should be documented here.
