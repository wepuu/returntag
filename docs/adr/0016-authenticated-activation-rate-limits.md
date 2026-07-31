# ADR 0016: Authenticated activation rate limits

**Status:** Accepted for RT-309

**Date:** 2026-07-31

**Schema before/after:** `8 -> 8`

**Plugin before/after:** `0.3.0 -> 0.3.0`

## Context

RT-307 provides the atomic ownership write and RT-308 converges every
non-exceptional outcome to committed public state. Exposing that mutation
without a separate activation-attempt budget would allow an authenticated
account, reused email, shared source network, or automated distributed client
to exercise the ownership boundary at excessive volume.

OTP request and verification already have durable limits, but those budgets
protect different actions. Reusing them would let prior authentication traffic
unexpectedly block activation or let activation bypass a dedicated incident
signal.

## Decision

Before an eligible authenticated activation mutation, RT-309 reserves all of
these fixed-window budgets:

| Scope | Limit |
|---|---|
| Server-derived WordPress User | 5/hour and 10/day |
| Keyed authenticated User email | 5/hour and 10/day |
| Keyed direct-peer IP | 30/hour and 100/day |
| Canonical Tag ID | 10/hour |
| Site-wide global | 100/minute and 2,000/hour |

The User ID and email come only from the authenticated WordPress session and
User record. The browser submits neither value. Email and direct-peer IP are
converted to independent keyed lookup digests before the limiter boundary.
Forwarding headers are not trusted.

All nine buckets are checked and serialized under one site-scoped MySQL
advisory lock. Durable, non-autoloaded WordPress Options use the
`returntag_activation_rate_` prefix plus expiry and a SHA-256 bucket-name hash.
Option names and values contain no raw email, IP, Tag ID, OTP, cookie, or
Session identifier. Expired buckets use the existing bounded daily maintenance
job.

Eligibility is resolved before budget reservation. A Tag that has already
become active, invalid, suspended, retired, or unavailable returns its existing
committed page without consuming an activation budget. After a denied
reservation, committed state is resolved once more. If it remains eligible,
the UI shows one generic retryable failure without identifying the limiting
scope. If state changed, normal Owner, Finder, invalid, or explanation routing
applies.

The authenticated browser mutation is a same-site nonce-protected `POST` to
the canonical Tag URL. A completed or changed-state attempt uses `303` back to
the canonical `GET`. The public form contains only the action and nonce.

## Consequences

- Activation budgets are independent of OTP request and verification budgets.
- A storage or lock failure fails closed and performs no Tag mutation.
- A partially reserved budget after a storage failure may reduce capacity but
  cannot grant activation authority.
- Shared networks receive a higher limit than a single User or email.
- Schema remains version 8; no high-cardinality personal value is added to a
  product table.
- The UI reuses the existing mobile-first activation card and adds no theme
  dependency, conflict page, or support action.

## Rejected alternatives

- **Only per-IP limits:** shared networks create false positives and distributed
  clients bypass them.
- **Only per-account limits:** multiple accounts or reused infrastructure can
  distribute attempts.
- **Trust proxy forwarding headers by default:** permits spoofing without an
  approved trusted-proxy policy.
- **Store raw email, IP, or Tag ID in Option names:** leaks sensitive or
  enumerable values into operational storage.
- **Reuse OTP budgets:** couples distinct security actions and makes incident
  interpretation unreliable.

## Rollback

Disable `returntag_global_activation_enabled` first. Code rollback removes the
public activation POST and ignores the dedicated limiter Options; expired
Options may be removed by bounded cleanup. Rollback preserves every committed
Owner relationship, activation timestamp, active Tag state, and audit Event.
Never clear ownership or delete audit evidence to simulate rollback.
