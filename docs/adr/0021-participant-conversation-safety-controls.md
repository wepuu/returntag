# ADR 0021: Participant Conversation safety controls

**Status:** Accepted

**Date:** 2026-08-10

**Scope:** RT-316 / RT-315 Stage 7A participant close and report-block actions

**Schema before/after:** `12 -> 12`

**Plugin before/after:** `0.4.0 -> 0.4.0`

## Context

Stage 6 provides a bounded two-way private relay, but an authenticated
participant cannot yet stop unwanted contact. Phase one requires the Owner to
block and report abuse and the Finder to terminate a Conversation. The
canonical `closed` and `blocked` Conversation states already exist, while the
later moderation outcome, evidence hold, appeal, and ownership-dispute
contracts remain unspecified.

## Decision

- A verified Finder session may explicitly transition only its authorized
  `open` Conversation to `closed`.
- A current active Owner session may explicitly choose `Report and block` and
  transition only its authorized `open` Conversation to `blocked`.
- Both actions require a same-site, nonce-protected POST, an explicit
  confirmation field, and a valid role-bound 30-minute session. Browser input
  cannot select a Conversation, role, Owner, Finder, Tag, or recipient.
- The transition locks the Conversation, rechecks the complete Stage 6
  eligibility graph, updates the status, revokes every unrevoked link and
  session Token for that Conversation, and changes every still-queued Message
  to terminal `failed` in one database transaction.
- Finder termination records the existing metadata-free
  `conversation_closed` Event. Owner report-block records the metadata-free
  `conversation_reported` Event with result `blocked`.
- Neither action accepts a reason, free text, attachment, location, email, Tag
  ID, message body, evidence reference, or other report payload.
- After success, the route clears both relay cookies and renders only the
  generic unavailable state. Repeating the action is safe and cannot reopen or
  further mutate the Conversation.

## Dispatch boundary

Closing or blocking prevents new links and new message claims, fails queued
Messages, and makes Workers perform a final eligibility check against the exact
continuation Token immediately before calling the provider. An external
provider call that already passed that check and started before the terminal
transaction cannot be recalled. Any continuation Token created before the
transaction is revoked, and provider acceptance cannot restore the
Conversation or issue an active session.

## Consequences

- `blocked` Conversations form the privacy-minimized input for a later
  privileged moderation queue, but Stage 7A provides no administrator UI or
  moderation decision.
- Stage 7A adds no report-reason taxonomy, evidence hold, reopen, unblock,
  appeal, recovery confirmation, or ownership-dispute behavior.
- No Migration, table, column, dependency, lock-file, Theme, WooCommerce, or
  Smart Network change is required.

## Rollback

Remove the Stage 7A controls while preserving terminal Conversation states,
revoked Tokens, failed queued Messages, accepted Messages, Finder Reports,
evidence, ownership, and Events. Rollback must not reopen a Conversation,
restore a Token, or requeue a Message automatically.
