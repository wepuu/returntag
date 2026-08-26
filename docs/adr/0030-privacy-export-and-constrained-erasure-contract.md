# ADR 0030: Privacy export and constrained-erasure contract

**Status:** Proposed — BLOCKED for acceptance

**Date:** 2026-08-26

**Scope:** RT-339 contract only; no runtime or Schema change

**Schema before/after:** `15 -> 15`

**Plugin before/after:** `0.5.0 -> 0.5.0`

## Blocking policy binding

The external privacy and retention policy is reported as approved, but its
stable version identifier and accountable owner are not present in the
repository. They are therefore `UNVERIFIED`. This ADR cannot become Accepted,
and RT-340 runtime must remain disabled, until both values are recorded here
and the policy-to-engineering mapping is signed by the privacy owner.

The contract deliberately does not invent retention periods or response-time
SLA values. Those values must come from the versioned external policy. Existing
short-lived technical expiry and Finder evidence retention controls remain in
force until that binding is approved.

## Context

TagCore stores WordPress user references, Tag ownership and item fields,
encrypted Finder identities and messages, hash-only security material,
private Finder evidence, administrative actor identifiers, operational queue
arguments, and metadata-only email delivery state. A generic WordPress erase
callback cannot safely delete all rows associated with an email address:

- an active physical Tag must not silently lose its Owner or become retired;
- public Tag IDs, manufacturing exports, ownership decisions, accepted
  messages, disputes, and security Events have integrity and anti-reuse value;
- one conversation contains data and rights belonging to two private parties;
- evidence may be subject to an active abuse or dispute Hold; and
- tokens, OTP hashes, IP/risk keys, provider identifiers, and private evidence
  are not appropriate export material even when they relate to the requester.

RT-339 therefore freezes the engineering boundary before RT-340 adds a privacy
request table, WordPress exporter/eraser callbacks, Account UI, Admin status,
or cleanup workers. The evidence-backed storage map is
[RT-339 Privacy Data Map](../privacy/RT-339-DATA-MAP.md).

## Decision

### Identity and authorization

- Owner requests bind to the authenticated WordPress User ID. A submitted user
  ID or email address is never authorization evidence.
- Finder requests use the WordPress privacy-request confirmation flow and an
  exact keyed email lookup. TagCore must not create a WordPress account merely
  to service a Finder request.
- A previous Owner may export only their own historical participation. They
  receive no current Owner identity, private current Tag fields, Finder
  identity, evidence, or current Conversation access.
- Administrator processing requires a dedicated approved capability and a
  metadata-free Event. Administrator access does not broaden export contents.

### Request contract for RT-340

The only request types are:

```text
export
erasure
```

The only request states are:

```text
queued
processing
action_required
completed
failed
```

`action_required` uses a fixed reason code, initially `active_tag` or
`retention_hold`; it stores no free-text explanation. An external dependency
failure is retryable processing failure, not user action.

The future Schema 16 request row may contain an internal request ID, requester
WordPress User ID when one exists, request type, state, policy version,
idempotency key, bounded checkpoint, attempt count, fixed reason/error code,
and UTC lifecycle timestamps. It must not duplicate an email address, IP
address, evidence reference, message content, token, provider payload, or
administrator notes.

Only one unfinished request of the same type and requester identity may exist.
Every step must be idempotent and resume from a durable checkpoint. A repeated
request returns the existing request rather than starting concurrent export or
erasure work.

### Export

- Exporters are paginated and bounded according to the WordPress personal-data
  exporter contract; no request may load an unbounded history into memory.
- Export contains only fields the requester is already authorized to see, plus
  a plain-language history of their own TagCore actions.
- Conversation content may be exported only for a verified participant and
  only from content that participant was entitled to receive. The other
  party's address and internal identity remain absent.
- Evidence images, source/review/email derivatives, object references,
  encryption key IDs, OTP/token hashes, IP/risk keys, provider identifiers,
  idempotency keys, queue internals, raw Event metadata, and ordinary logs are
  always excluded.
- A successful export records only a metadata-free completion Event. Export
  archives must use the WordPress protected privacy-export lifecycle and must
  not become a permanent TagCore file store.

### Constrained erasure

- Active owned Tag causes `action_required` before the first identity mutation.
  TagCore does not remove the Owner, transfer ownership, disable Lost Mode, or
  retire the Tag.
- The requester must complete an approved lifecycle action outside the privacy
  worker. A later retry rechecks committed ownership and Tag state.
- An active evidence, dispute, abuse, or legal/operational Hold prevents the
  affected cleanup and moves the request to `action_required` without exposing
  the other party or the Hold evidence.
- Erasure is constrained anonymization, not broad physical deletion. It removes
  or irreversibly disconnects address ciphertext, equality lookups, private
  item text, and identity-bearing content only when ownership, retention, and
  Hold rules permit.
- Generated Tag IDs, Batch and export history, completed claims, transfer and
  dispute outcomes, accepted-message audit facts, and security Events remain.
  Identity references retained for integrity must be replaced by an approved
  non-reversible anonymous subject reference where the versioned policy allows
  it; they must not be replaced with another real WordPress User ID.
- Tokens and sessions are revoked before related identity material is
  anonymized. A partial failure leaves a retryable checkpoint and never reports
  completion.

### Hold and retention precedence

The order of authority is:

```text
active approved Hold
-> frozen product and security invariants
-> versioned external retention policy
-> ordinary expiry and cleanup schedule
```

A Hold delays only the affected cleanup. It does not grant access, expose
evidence, or silently extend unrelated data. Hold placement and release remain
separate authorized business actions; a privacy request cannot create, release,
or bypass a Hold.

### Observability and notification

- Events contain request ID, fixed type/result, actor type/ID when permitted,
  and timestamps only; no email, body, evidence, IP, provider response, or
  free-text reason is allowed.
- Ordinary logs use fixed error codes and correlation IDs. They must not log
  exporter rows, erased values, or provider/private-media payloads.
- Transactional status notifications depend on the RT-337 outbox and resolve
  the current address only at dispatch time. The privacy request table does not
  become an address source.

## Consequences

- RT-340 cannot be a generic delete-by-email implementation.
- Active Tag and Hold checks are committed-state release gates, not UI-only
  warnings.
- Some privacy requests legitimately require user action or delayed completion.
- Audit and anti-reuse history survive anonymization without preserving an
  unnecessary direct identity link.
- Exact retention and SLA values remain blocked until the external policy has
  a stable identifier and accountable owner.

## Rejected alternatives

### Delete every TagCore row related to the email

Rejected because it would destroy physical Tag ownership, non-reuse history,
two-party records, evidence Holds, and security audit integrity.

### Automatically retire or unassign active Tags

Rejected because a privacy request is not authorization for a lifecycle or
ownership mutation and could make a physical Tag unsafe or reusable.

### Export encrypted blobs, hashes, or private evidence as "all data"

Rejected because it discloses security/provider internals, another party's
rights, and evidence that the approved product decision explicitly excludes.

### Hard-code a provisional retention or SLA schedule

Rejected because the approved external policy is not versioned in the
repository. Engineering convenience cannot substitute for policy authority.

## Rollout and rollback

This proposed ADR changes documentation only. RT-340 must introduce an
independent default-disabled runtime, additive Schema 16 migration, fresh and
15-to-16 upgrade tests, retry checkpoints, and an operational kill switch.
Rollback disables new request intake and workers while preserving request
state and audit evidence; it must not reverse completed anonymization or delete
business records.
