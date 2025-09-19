# ADR: User Exclusion Rule for Legal Age Consent

Status: Accepted
Date: 2025-09-16

## Context

Regulatory environments may require applying age gating not only to anonymous visitors (guests) but also to logged-in users who are not administrators. At the same time, many deployments prefer to avoid interrupting authenticated sessions and keep the age gate limited to guests. We needed a flexible, admin-controlled option that satisfies both cases without breaking existing behavior.

Prior phases delivered:

- A root consent page at `/index.php` and a plugin guard (`init` hook) that gates guest users.
- Admin configuration (enable/disable, fallback URL).
- Consent duration with session structure and cookie reconstruction.
- Security hardening: standardized helpers, constants, session management, and error handling.

## Decision

Introduce a configuration flag `lac_apply_to_logged_in` (default false) with an Admin UI checkbox “Apply to Logged-in Users”. When enabled, the age gate applies to logged-in non-admin users under the same consent and expiration rules as guests. Admins and webmasters always bypass.

Summary of rules:

- Admin/webmaster: bypass regardless of setting.
- Guest: always gated (unchanged).
- Logged-in non-admin:
  - If `lac_apply_to_logged_in` is false → bypass.
  - If `true` → gated (consent required/validated).

Guest detection prioritizes `$user['status'] === 'guest'`, falling back to comparing `$user['id']` with `$conf['guest_id']`, and then to `$user['is_guest']` if needed. Admin detection uses `$user['is_admin']` or `$user['status']` in {`admin`, `webmaster`}.

Consent semantics:

- Duration = 0: legacy flag honored and upgraded to structured.
- Duration > 0: legacy ignored; structured timestamp required and subject to expiry checks.
- Cookie reconstruction: if `LAC` cookie timestamp is within the cookie window and within duration, structured consent is reconstructed for a seamless experience.

## Alternatives Considered

1. Always apply to logged-in users.

- Pros: Simpler mental model, single rule for all non-admins.
- Cons: Breaks backward compatibility; unnecessary friction for many galleries.

2. Never apply to logged-in users.

- Pros: Preserves classic Piwigo UX for authenticated users.
- Cons: Fails compliance needs where all non-admins must be validated.

3. Role-based matrix with granular controls (e.g., per-group).

- Pros: Maximum flexibility.
- Cons: Higher complexity, additional UI and testing burden; deferred until a concrete need arises.

We chose a single boolean flag as the pragmatic balance of flexibility and simplicity.

## Consequences

- Backward compatibility: Default (`false`) preserves existing behavior; enabling the flag expands gating to logged-in non-admin users.
- UX: Registered users may be redirected to `/index.php` when the flag is on and consent isn’t present/valid; cookie-based restoration minimizes repeated friction.
- Testing: Added unit tests covering admin save of the flag, admin bypass, logged-in behavior for both flag states, and guest behavior invariance.
- Documentation: Project sheet updated with detailed behavior and examples.

## Implementation Notes

- Guard hook `lac_age_gate_guard` now reads `lac_apply_to_logged_in` and applies gating to logged-in non-admins accordingly.
- Improved user classification to avoid misidentifying registered users as guests.
- The root consent page now restores consent using the `LAC` cookie alone (no dependency on PHP session cookie), improving revisit flow.

## References

- Project sheet: `.github/lac_project_sheet.md` (Phase 5 section)
- Feature spec (Gherkin): `.github/features/lac_user_exclusion.feature`
- Code: `albums/plugins/lac/include/age_gate.inc.php`, `albums/plugins/lac/include/functions.inc.php`, `albums/plugins/lac/admin/config.php`
