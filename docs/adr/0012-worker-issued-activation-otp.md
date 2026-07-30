# ADR 0012: Worker-issued activation OTP

**Status:** Accepted for RT-304

**Date:** 2026-07-30

**Schema before/after:** `8 -> 8`

**Plugin before/after:** `0.3.0 -> 0.3.0`

## Context

RT-304 introduces the first email OTP request for an eligible unregistered
Tag. The authentication-challenges table already stores encrypted email,
keyed lookup digests, an OTP password hash, counters, expiry, and terminal
timestamps. Its `code_hash` column is non-null.

Action Scheduler persists action arguments. Passing a plaintext or reversibly
encrypted OTP through those arguments would create another long-lived secret
store and would contradict the requirement that OTP values are stored only as
hashes.

## Decision

The public request never generates an OTP. It validates the canonical Tag and
email boundary, checks operational controls and rate limits, stores an
unissued challenge with `send_count=0`, and enqueues only:

```text
challenge_id
```

The non-null `code_hash` initially contains a high-entropy password hash from
the `activation-otp-unissued:v1` domain. It cannot be produced by the issued
six-digit verification domain. RT-305 must reject `send_count=0` before any
code comparison.

The Worker reloads the challenge, rechecks the activation and email-dispatch
controls and current Tag eligibility, then generates the six-digit OTP in
memory. It applies HMAC-SHA-256 with a dedicated external OTP pepper and an
`activation-otp-issued:v1` domain before `password_hash()`.

The Worker atomically changes only the latest, open, unexpired challenge from
`send_count=0` to `send_count=1`, replaces the placeholder hash, and starts a
new ten-minute expiry. It then decrypts the stored recipient address and calls
the transactional mail adapter. The plaintext code is never written to the
database, Action Scheduler, Events, URLs, HTTP responses, or logs.

Dispatch uses at-most-once semantics. A repeated Worker is a no-op after the
atomic claim. A crash after claim but before mail submission sacrifices that
attempt's availability; the user may request a replacement after the resend
window. This is preferred to duplicate or uncertain code delivery.

Email encryption uses XChaCha20-Poly1305 with Tag- and purpose-bound associated
data. Email/IP lookup HMAC, email encryption, and OTP pepper use three
independent versioned 32-byte keys supplied outside WordPress and its database:

```text
RETURNTAG_TAGCORE_EMAIL_ENCRYPTION_KEY_V1
RETURNTAG_TAGCORE_LOOKUP_HMAC_KEY_V1
RETURNTAG_TAGCORE_OTP_PEPPER_V1
```

Missing or malformed keys fail closed.

Persistent indexed challenge counts enforce email and Tag windows. Durable,
non-autoloaded, HMAC-keyed WordPress Option buckets atomically enforce email,
Tag, direct-peer IP, and global budgets under a site-scoped database lock.
Anonymous WordPress nonce validation is supplemented with browser
`Origin`/`Sec-Fetch-Site` checks. Proxy forwarding headers are not trusted.

Expired challenges are retained for seven additional days, then removed in
bounded daily chunks. Expired rate-limit buckets are also removed by bounded
daily maintenance.

## Consequences

- Schema remains version 8; no table or index changes are required.
- The public form remains plugin-owned, theme-independent, translatable,
  keyboard-accessible, and mobile-first.
- `wp_mail()` returning true means only that WordPress accepted the submission;
  it is not presented as confirmed delivery.
- RT-304 performs no OTP verification, WordPress login or registration, Tag
  ownership mutation, activation, Event append, or WooCommerce operation.
- Email and Tag count reads use existing Schema-8 indexes. IP and global
  limiter state does not require an unindexed `ip_hash` scan.

## Rejected alternatives

- **Generate OTP in the public request and encrypt it in queue arguments:**
  creates a reversible persisted OTP copy.
- **Store a plaintext or encrypted OTP in the challenge row:** violates
  hash-only storage.
- **Rely only on WordPress Transients:** eviction and non-atomic backends are
  insufficient for the challenge and formal limiter state.
- **Retry a claimed dispatch automatically:** can duplicate delivery after an
  uncertain provider handoff.
- **Add an `ip_hash` index in RT-304:** requires a new Migration and a separate
  rollback-compatibility design; the Option limiter avoids that change.

## Rollback

Disable `returntag_email_dispatch_enabled` first to stop new dispatches, then
disable global activation if necessary. Code rollback removes the form,
Worker, and maintenance hooks while leaving Schema 8 compatible. Historical
challenge rows and inert rate-limit Options may safely expire; rollback must
not expose or reconstruct their sensitive values.
