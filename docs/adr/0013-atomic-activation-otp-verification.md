# ADR 0013: Atomic activation OTP verification

**Status:** Accepted for RT-305

**Date:** 2026-07-30

**Schema before/after:** `8 -> 8`

**Plugin before/after:** `0.3.0 -> 0.3.0`

## Context

RT-305 verifies the Worker-issued activation OTP introduced by RT-304. The
browser must identify the intended challenge without receiving an internal
challenge identifier or retaining an email address in a URL, cookie, hidden
field, or rendered response. Concurrent requests must not bypass the five
attempt limit or reuse a successful code.

RT-306 owns passwordless WordPress login and user provisioning. RT-307 owns
the atomic Tag activation and ownership mutation. Neither responsibility may
be pulled into OTP verification.

## Decision

The verification form asks for the same email address and the six-digit code.
Application derives the keyed email lookup again and identifies the latest
challenge matching:

```text
purpose = activation_otp
subject_type = tag
subject_id = canonical Tag ID
email_lookup = keyed canonical email
```

Infrastructure locks that latest row before inspecting or changing it. It
rejects the challenge before code comparison when it was not issued
(`send_count=0`), is expired, is already verified or consumed, or has reached
five attempts. A wrong code increments `attempt_count` under the same lock. A
matching code atomically writes the same UTC instant to `verified_at` and
`consumed_at`; subsequent requests fail.

The issued OTP hash remains a domain-separated HMAC protected by the external
OTP pepper and an adaptive password hash. Verification never logs, returns, or
persists plaintext OTPs. Public failures use one generic response for unknown
email, missing challenge, malformed code, mismatch, expiry, replay, exhaustion,
throttling, and operational failure.

Verification attempts first reserve durable Tag, direct-peer IP, and global
budgets. A bounded eligibility read must find an issued, open, unexpired latest
challenge below the attempt ceiling before the keyed-email budget is reserved.
The authoritative row-locked verification repeats every eligibility predicate,
so the preliminary read grants no authority and a concurrent terminal
transition fails safely. This ordering prevents arbitrary unknown identities
from allocating durable email buckets. All buckets contain only hashed scopes
and expiry and use the `returntag_otp_verify_rate_` namespace. The
per-challenge five-attempt ceiling remains authoritative.

## Consequences

- Schema remains version 8; existing challenge columns and indexes are reused.
- Unknown Tag-and-email pairs consume bounded public budgets but create no
  attacker-selected persistent email scope.
- The browser stores no challenge ID, email, OTP, access token, or
  authentication state.
- A successful RT-305 response is terminal verification feedback only. It does
  not create or log in a WordPress user, assign ownership, activate the Tag,
  append an Event, or issue a reusable credential.
- RT-306 may compose verification and login in one future POST while the
  verified email remains in process memory; RT-305 deliberately creates no
  browser handoff token.
- Code rollback removes verification UI and behavior while leaving consumed
  challenge rows and expiring limiter Options intact.

## Rejected alternatives

- **Expose the numeric challenge ID in HTML or the URL:** creates an unnecessary
  client correlation handle and expands enumeration and log exposure.
- **Store the email in a cookie or hidden field:** retains personal data in the
  browser without need; re-entry is the narrower privacy boundary.
- **Read then update the attempt counter without a row lock:** concurrent
  requests could exceed the attempt ceiling or reuse a code.
- **Return distinct expiry, mismatch, or unknown-email errors:** enables
  differential account or challenge probing.
- **Create a post-verification access token in RT-305:** overlaps the
  authentication and token design owned by RT-306.

## Rollback

Disable `returntag_global_activation_enabled` to stop verification and new OTP
requests together. Code rollback requires no database rollback. Existing
verified/consumed rows remain valid audit state and verification limiter
Options expire independently; do not clear counters or resurrect consumed
codes.
