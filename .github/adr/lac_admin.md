# ADR: Phase 2 Administrative Controls – Deviations from Initial Draft

Date: 2025-09-13
Status: Accepted

## Context

Phase 2 introduced an administration UI for the Legal Age Consent plugin. An initial draft (in `lac_project_sheet.md`) outlined a plan that evolved during implementation. This ADR records the key deviations and the rationale so future contributors understand why the final approach differs from the draft.

## Summary of Deviations

| Draft Expectation                                                                                  | Implemented Reality                                                                         | Rationale                                                                                                                                  |
| -------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Keep legacy demo hooks temporarily; add config UI alongside them.                                  | Removed demo/public/menu/webservice hooks and templates.                                    | Reduce maintenance surface & startup overhead; demo code unrelated to age gating.                                                          |
| Use `pwg_get_conf` / `pwg_set_conf` helpers for persistence.                                       | Direct use of `$conf[...]` plus `conf_update_param()`; no `pwg_*` wrappers.                 | Original helper functions were not available in minimal test/bootstrap context; using core `conf_update_param()` is explicit and reliable. |
| Fallback URL persistence could use a filesystem `.lac_fallback_url` file if DB write not feasible. | Eliminated file-based persistence entirely; DB-only (`config` table) source of truth.       | File writes proved brittle (permissions / path variance). DB guarantees consistency & atomicity; simplifies logic.                         |
| No explicit restriction on internal (same-host) URLs for fallback.                                 | Internal (same-host) fallback URLs rejected.                                                | Prevent accidental self-referential or gated redirect loops; enforces clear “external exit” semantics.                                     |
| Basic URL validation (scheme only).                                                                | Added max length (2048 chars) + scheme + external host checks.                              | Defensive programming against extremely long inputs, header splitting attempts, or host-based loops.                                       |
| Leave sanitizer minimal; XSS covered by output escaping only.                                      | Centralized sanitizer (`lac_sanitize_fallback_url`) extended and reused in admin save path. | Single canonical validation point lowers future security audit burden.                                                                     |
| Potential future: keep legacy webservice sample.                                                   | Removed sample `pwg.PHPinfo` API method.                                                    | Avoid exposing irrelevant introspection surface; principle of least privilege.                                                             |
| Age gate enable flag optional; might always stay “on”.                                             | Implemented `lac_enabled` toggle controlling guard early-exit.                              | Operational flexibility (debugging, phased rollout, emergency disable) with negligible complexity.                                         |
| Decline flow may rely on file fallback or referer only.                                            | Decline flow queries DB directly (lightweight mysqli) + referer + Google ultimate fallback. | Ensures admin-updated value is immediately used without additional bootstrap cost.                                                         |
| Keep deviation documentation inline in project sheet.                                              | Moved deviations to dedicated ADR (`adr/lac_admin.md`).                                     | Keeps project sheet concise; formal ADR improves traceability.                                                                             |

## Detailed Rationale Highlights

1. **Removal of Demo Code** – The initial skeleton carried menu blocks, batch manager hooks, and a webservice sample. Retaining them created noise in event registration and complicated minimal test harnesses. Eliminating them narrowed plugin responsibility strictly to age gating and its configuration.

2. **DB-Only Fallback URL** – Repeated issues encountered with file-based approaches (path ambiguity, permissions). The configuration table already exists, supports transactional updates, and is environment-agnostic. This also removed the need for multi-path probing logic.

3. **Enhanced Validation** – Introducing length and same-host checks mitigates two classes of potential issues: resource abuse (very long inputs) and redirect loops / internal leakage back into protected space.

4. **Central Sanitizer** – Consolidating URL sanitation ensures future changes (e.g., adding allowed TLD filters) occur in one place.

5. **Toggle Philosophy** – A lightweight on/off switch grants operational safety (e.g., temporarily disable gate during emergency maintenance) without resorting to code edits.

6. **Direct `conf_update_param` Usage** – Simplifies testing and removes dependency on helper wrappers that weren’t loaded in the stripped-down test environment. Keeps intent explicit: mutate stored configuration.

7. **Direct DB Read in Root Decline Flow** – Bypasses full Piwigo bootstrap for performance and resilience. The minimal one-query approach balances speed with clarity; failures gracefully fall back to referrer or a neutral external site.

## Consequences

Positive:

- Leaner codebase, fewer moving parts.
- Reduced attack surface (removed extraneous webservice & menu points).
- Clear single source of truth for fallback URL.
- Stronger validation reduces risk of misconfiguration or abuse.

Negative / Trade-offs:

- Lost example hooks that might have served as future development references.
- Direct mysqli usage creates a tiny duplication of DB config parsing (acceptable for now; could be refactored behind a helper if more queries appear).

## Alternatives Considered

- Retain demo code but disable via feature flag (added complexity for little benefit).
- Keep file fallback as a secondary cache layer (unnecessary after reliability of DB confirmed; would reintroduce invalidation edge cases).
- Permit internal fallback URLs with stricter path checks (adds complexity, limited practical use; can be revisited if a real internal non-gated landing page is introduced).

## Future Revisions

If later requirements introduce multi-tenant fallback logic, we may refactor the direct DB read into an abstraction and potentially reintroduce selective internal URLs with an explicit allowlist.

## Decision

Adopt the implemented deviations as the canonical Phase 2 design; treat this ADR as authoritative until a superseding ADR revises scope or constraints.

---

End of ADR.
