# ADR 0023: Owner lifecycle and WP Mail SMTP transport contract

**Status:** Accepted

**Date:** 2026-08-11

**Scope:** RT-318 through RT-320 Milestone 4 Test Email, Transfer, Retire, and v0.5.0 closure

**Schema before/after:** `12 -> 13`

**Plugin before/after:** `0.4.0 -> 0.5.0`

## Context

ADR 0022 deliberately left Test Email, Transfer, and Retire for separate
contracts. Milestone 4 cannot close until these high-risk Owner actions define
their authentication, privacy, queue, concurrency, and rollback boundaries.

## Decision

Owner Test Email is an explicit same-site, nonce-protected Account POST. The
recipient is the current WordPress user's server-resolved email; the browser
cannot submit an address. TagCore persists a metadata-free request Event and
queues only its Event and User identifiers. A Worker calls `wp_mail()`.
WP Mail SMTP is the supported operational transport and may intercept that
call, but TagCore does not use its internal APIs, tables, settings, logs, or
provider SDKs. Mailer acceptance is not confirmed delivery. Automated tests
intercept `pre_wp_mail` and never require live SMTP credentials.

Transfer requires an active current Owner, a fresh Account OTP, and a target
email. The target email is encrypted at rest with a keyed lookup digest. A
Worker generates a 32-byte invitation token, stores only its SHA-256 hash, and
sends the plaintext only in the invitation URL. GET moves a structurally valid
token into an HttpOnly SameSite cookie and cleans the URL; only an authenticated,
nonce-protected POST from the invited email may accept. Acceptance atomically
changes `owner_id`, sets `owner_changed_at`, closes old conversations, revokes
their Tokens, and records a metadata-free Event. Historic conversations do not
transfer.

Retire requires an active current Owner, a fresh Account OTP, and exact Tag ID
confirmation. It atomically changes `active -> retired`, cancels pending
transfers, closes conversations, revokes Tokens, and records a metadata-free
Event. It preserves ownership, Tag fields, Tag ID, accepted messages, evidence,
and audit history. Retirement is irreversible and the ID is never reusable.

Schema 13 adds only `returntag_tag_transfers`. The independent, missing-by-
default control `returntag_owner_lifecycle_enabled` gates Transfer and Retire.
Test Email remains governed by Owner Account and Email Dispatch controls.

## Operational email policy

WP Mail SMTP is installed and configured separately by an operator. Phase one
keeps detailed/content logging, attachment logging, and open/click tracking off.
No SMTP credentials or production recipient data belong in this repository.
The WP Mail SMTP Email Test remains the administrator transport diagnostic;
TagCore's Test Email verifies only the Owner product pipeline.

## Rollback

Disable `returntag_owner_lifecycle_enabled` to stop new Transfer and Retire
actions, and disable `returntag_email_dispatch_enabled` to stop new email work.
Previous `0.4.0` code ignores the additive Schema 13 table. Do not drop it or
reverse accepted transfers or retirement. Preserve Tags, ownership history,
revoked Tokens, closed Conversations, Events, and pending invitation audit data.
