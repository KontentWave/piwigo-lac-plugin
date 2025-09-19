# ADR: Phase 1 Age Gate Implementation

Date: 2025-09-13
Status: Accepted

## Context

The initial objective was to introduce a minimal age verification mechanism preventing anonymous (guest) users from accessing Piwigo gallery pages until confirming age on a root-level consent page. Early drafts envisioned a very small root page and hardcoded redirect target. Implementation evolved to improve UX, security, and flexibility while preserving simplicity.

## Core Decisions

1. **Two-Layer Model**: Keep the consent UX outside the gallery (`/index.php`) and a lightweight guard inside the plugin (`lac_age_gate_guard` on `init`) that only _reads_ session state.
2. **Session Flag Contract**: Plugin never sets the flag; only the root page writes `$_SESSION['lac_consent_granted']` ensuring a single authority.
3. **Redirect Memory**: Support `?redirect=` parameter (sanitized) so deep links after consent land on intended gallery content.
4. **Loop Protection**: Guard skips redirect when already on consent page path to avoid infinite loops.
5. **Cookie Optimization**: `LAC` timestamp cookie speeds re-entry (sets session flag when still fresh) reducing repeat form friction.
6. **Gallery Directory Flexibility**: Default `./albums` with optional override via `.gallerydir` file for deployments using a different subdirectory.

## Deviations from Initial Idea

| Draft Idea                                 | Implemented                                                          | Rationale                                                         |
| ------------------------------------------ | -------------------------------------------------------------------- | ----------------------------------------------------------------- |
| Hardcode redirect to `./gallery/index.php` | Dynamic gallery dir (`./albums` default, override via `.gallerydir`) | Align with actual Piwigo layout; configurable without code edits. |
| No redirect memory (always gallery index)  | Added `?redirect=` capture + session storage                         | Preserves user intent & improves UX on shared links.              |
| Only in-session check (no cookie)          | Added bounded-lifetime `LAC` cookie (24h)                            | Faster repeat access; reduces unnecessary form submissions.       |
| Minimal 5-test set                         | Added 2 more tests (redirect target + loop prevention)               | Increases safety and regression confidence.                       |
| Immediately remove skeleton/demo code      | Deferred removal to later phase (then removed in Phase 2)            | Focus early effort on core gating; postpone cleanup risk.         |

## Security Considerations

- Strips `sid` parameter from redirect target to avoid session fixation.
- Restricts stored redirect to paths under gallery directory (regex anchored) preventing open redirect to arbitrary hosts.
- Maintains short cookie validation logic (numeric timestamp; age checked against lifetime) to avoid misuse as general token.

## Alternatives Considered

- **Single-Layer (plugin-only) gate**: Rejected; root page allows clearer isolation + friendlier themed messaging outside gallery bootstrap.
- **AJAX overlay modal within gallery**: Higher complexity; delayed gating until after potentially sensitive content loads.
- **Signed JWT cookie**: Overkill for MVP; can be introduced later for multi-day persistence.

## Consequences

Positive:

- Simple mental model: root page grants, plugin enforces.
- Low performance overhead (single early guard check and optional redirect).
- Extensible foundation for admin and analytics features (added later).

Negative / Trade-offs:

- Direct DB config not yet integrated in Phase 1 (handled later).
- Root page duplication of some path logic (cleanup possible if unified helper introduced).

## Future Enhancements (some realized in Phase 2)

- Administrative enable/disable toggle (implemented Phase 2).
- Configurable external fallback for declines (implemented Phase 2 DB-only).
- Internationalization improvements for consent copy.
- Stronger persistence (signed token, multi-day timeout) if compliance requires.

## Decision

Adopt the implemented multi-layer approach with session flag ownership at the root page and a passive plugin guard. Treat deviations as intentional improvements for user experience and safety.

---

End of ADR.
