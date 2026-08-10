# ADR 0020: Secure Reply session and bounded private relay

**Status:** Accepted

**Date:** 2026-08-10

**Scope:** RT-315 Stage 6 secure-link exchange, short-lived Conversation
sessions, bounded two-way text messages, and idempotent email dispatch

**Schema before/after:** `11 -> 12`

**Plugin before/after:** `0.4.0 -> 0.4.0`

## Context

Stage 5 can link an approved Finder Report to an `open` canonical Conversation
only after the Finder verifies an email address. It deliberately provides no
Owner reply action, Access Token exchange, Message write path, or delivery
Worker. Phase one nevertheless requires both parties to communicate through a
ForgeTag page without exposing either email address.

The existing Access Token and Message tables provide hash-only Token storage
and encrypted Message storage, but their runtime policies were intentionally
left undefined. A public reply flow also needs an explicit GET/POST boundary
that is safe against email-link prefetch and an idempotent message-delivery
claim that does not turn an ambiguous mailer result into duplicate email.

## Decision

### Secure-link and session lifecycle

- Owner links use purpose `owner_secure_reply`; Finder links use purpose
  `finder_continue_conversation`.
- Link Tokens are generated from 32 cryptographically secure random bytes,
  stored only as a keyed SHA-256 digest, and expire after 24 hours.
- A first GET may validate structure and move the raw Token into a secure,
  HttpOnly, SameSite=Strict transient cookie, but it cannot exchange or consume
  the Token. The address bar is then cleaned with a `303` redirect.
- An explicit nonce-protected POST exchanges the link exactly once, revokes
  prior sessions for the same actor and Conversation, and issues a 30-minute
  `conversation_session` Token in a secure, HttpOnly, SameSite=Strict cookie.
- Every Owner exchange and request re-resolves the current active Owner. A
  transfer, suspension, retirement, closed, blocked, or expired Conversation
  invalidates access without trusting a browser-supplied user or Owner ID.
- Finder access remains bound to the verified Finder destination and the
  emailed Token; it never reveals the Owner address.

### Bounded message relay

- Human messages are required plain text of 10 to 500 Unicode characters.
- Each actor may submit at most 10 human messages and a Conversation may contain
  at most 20 human messages. `system` delivery records do not consume those
  limits.
- HTML, scripts, attachments, files, audio, video, and precise-location fields
  are unsupported. Output is escaped.
- Message bodies are encrypted at rest with an independent external key and
  are never written to URLs, queue payloads, Events, or ordinary logs.
- Queue payloads contain only an internal Message ID. A Worker rechecks
  Conversation state, current Owner, feature controls, actor role, limits, and
  recipient availability before delivery.
- Email bodies contain the escaped message and a new role-bound continuation
  link. Headers, subject, body, and URL never expose the other party's email,
  private item name, Tag ID, or evidence filename.

### Dispatch convergence and Schema 12

Migration `0012` adds nullable `dispatch_claimed_at` and required unsigned
`dispatch_attempt_count` fields to `returntag_messages`. A queued Message may
be claimed once. Mailer rejection becomes `failed`; acceptance becomes `sent`
and is not treated as confirmed delivery. A stale ambiguous claim also becomes
terminal `failed` rather than being sent again.

## Consequences

- Secure Reply is useful only after Finder email verification and approved
  Owner notification have both converged.
- Email prefetch cannot consume a link or establish a session.
- A mailer crash can prefer a missed message over a duplicate message; the
  failure remains visible and auditable.
- Stage 6 adds no general attachment support and never reuses Finder evidence
  as a Conversation attachment.
- Conversation close, report, block, dispute, administrative moderation, and
  release/deployment remain Stage 7 or later work.

## Rollback

Disable Finder Contact or Email Dispatch first. Remove the Stage 6 routes and
Workers while preserving Schema 12, hash-only Tokens, encrypted Messages,
Conversations, Finder Reports, audit Events, and ownership records. Do not
reset the Schema option, drop claim columns, or delete accepted messages.
