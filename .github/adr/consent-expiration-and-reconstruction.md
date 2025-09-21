# ADR 0001: Consent Expiration & Cookie Reconstruction Strategy

Date: 2025-09-14
Status: Accepted

## Context

The Legal Age Consent (LAC) plugin originally stored a simple boolean `$_SESSION['lac_consent_granted']` set by a root-level consent form outside the Piwigo gallery directory. We needed to introduce:

1. Configurable consent duration (minutes) requiring periodic re-confirmation.
2. Backward compatibility with existing deployments using the legacy boolean.
3. Robustness across session boundary inconsistencies (root vs gallery session name/path differences causing lost consent on first gallery hit).
4. Defense against redirect loops when state is partially present.

## Decision

We implemented a structured session payload:

```php
$_SESSION['lac_consent'] = [
  'granted' => true,
  'timestamp' => <int unix seconds>
];
```

We retain (temporarily) the legacy `lac_consent_granted` flag. On guard evaluation, if the structured array is missing but the legacy flag is set, we upgrade seamlessly by creating the structured form with a synthetic timestamp (current `time()`).

Expiration is enforced by comparing: `$_SESSION['lac_consent']['timestamp'] + (duration * 60)` to `time()`. Duration `0` means session-only validity (no time check).

To mitigate session loss between the root consent page and gallery pages, we added a lightweight unsigned timestamp cookie (name via `LAC_COOKIE_NAME`, default `LAC`). If the gallery receives a request with no consent session but a valid cookie within both:

1. The admin-configured duration window, and
2. A maximum absolute acceptance reuse window (`LAC_COOKIE_MAX_WINDOW`, 24h),

then the guard reconstructs the structured session using the original timestamp (preserved via cookie value) without extending expiry.

Guard logic is registered on the `init` hook and defensively includes helper functions to ensure expiration checks are always available during early bootstrap.

## Alternatives Considered

| Alternative                                                  | Pros                                   | Cons                                                  | Reason Rejected                                                                    |
| ------------------------------------------------------------ | -------------------------------------- | ----------------------------------------------------- | ---------------------------------------------------------------------------------- |
| Continue legacy boolean only                                 | Simple                                 | No expiration semantics                               | Fails requirement                                                                  |
| Refresh timestamp on every page view                         | Keeps sessions alive while user active | Allows indefinite avoidance of re-consent             | Violates periodic re-confirmation goal                                             |
| Signed / HMAC cookie (tamper-proof) now                      | Higher integrity                       | Higher complexity; key management ADR needed          | Deferred to keep velocity; current cookie only reconstructs existing valid session |
| Store acceptance in DB per anonymous browser via fingerprint | Auditable                              | Privacy complexity; unreliable fingerprint            | Overkill for MVP                                                                   |
| Regenerate session ID on consent accept to unify contexts    | Cleaner boundary                       | Risk of interfering with Piwigo core session handling | Avoid core coupling now                                                            |

## Consequences

Positive:

- Predictable, non-sliding expiration enforces re-consent cadence.
- Reduced redirect loops via reconstruction path.
- Backward compatible rollout (legacy flag auto-upgrade).
- Constants permit easy parameter changes.

Negative / Risks:

- Unsigned cookie could be user-modified to shorten or extend consent (extending possible only up to max window). Impact assessed as low severity; tampering does not elevate privileges, merely delays re-prompt.
- Additional branching in guard increases complexity; will warrant refactor into service class if more rules are added (e.g., allow-lists, analytics).

## Follow-Up / TODO

- Introduce signed (HMAC) cookie with rotation plan (future ADR).
- Add PHPUnit tests explicitly covering cookie reconstruction and legacy upgrade path.
- Provide admin UI indicator for remaining consent time (read-only debug panel).
- Localize consent text and admin field descriptions.

## Status Tracking

Implemented in: Phase 3 (Expiration & Reliability). See project sheet for roadmap.

---

End of ADR 0001.
