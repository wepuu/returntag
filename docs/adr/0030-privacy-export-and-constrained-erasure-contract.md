# ADR 0030: Privacy export and constrained-erasure contract

**Status:** Accepted

**Date:** 2026-08-27

**Scope:** RT-339 contract only; no runtime or Schema change

**Schema before/after:** `15 -> 15`

**Plugin before/after:** `0.5.0 -> 0.5.0`

## Approved policy binding

| Field | Approved value |
|---|---|
| External policy ID | `FORGETAG-PRIVACY-RETENTION-v1.0-20260827` |
| Effective date | 2026-08-27 |
| Approver role | ForgeTag Product Owner and Privacy Owner |
| Accountable organization | Forge Life LLC |
| Approval date | 2026-08-27 |

Forge Life LLC approved the Owner, Finder, and previous-Owner export boundaries
in this ADR and the linked data map, together with constrained anonymization.
The approval authorizes RT-340 engineering implementation. Production
enablement remains a separate approval.

### Retention schedule

| Data class | Approved maximum or preservation rule |
|---|---|
| WordPress privacy export archive | 7 days |
| Privacy request audit record | 3 years |
| OTP challenge after expiry or consumption | 24 hours |
| Access-token hash after expiry or revocation | 30 days |
| Temporary rate-limit and submission state after expiry | 24 hours |
| Operational and security logs | 90 days |
| Finder Evidence after Owner notification | 30 days |
| Rejected or incomplete Finder Evidence | 7 days |
| Closed or expired Conversation message content | 12 months |
| Private Tag fields after ownership ends | 30 days |
| Email delivery and webhook metadata | 180 days |
| Ownership, transfer, dispute, and security audit facts | 7 years, with constrained anonymization |
| Tag IDs, Batch, and manufacturing export integrity records | Permanent; Tag IDs are never reused |
| Backup natural expiry | 35 days |

An active approved Hold overrides cleanup only for affected data. The backup
boundary is a natural-expiry requirement: privacy processing does not rewrite
historical backups, and deleted or anonymized live data must disappear as
protected backups rotate within 35 days.

### Response SLA

| Stage | Approved target or limit |
|---|---|
| Request acknowledgement | Immediate; no later than 24 hours |
| Normal export completion | Target 7 calendar days |
| Normal erasure completion | Target 7 calendar days |
| Internal completion limit | 30 calendar days |
| Retryable processing failure | Operational alert within 24 hours |
| Completion notification | Within 24 hours of completion |

`action_required` and a valid Hold pause only the affected completion clock.
They do not permit TagCore to report a request as completed.

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
- RT-340 has an accountable, versioned retention and SLA contract against
  which runtime behavior and operational evidence can be tested.

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

Rejected because engineering convenience cannot substitute for policy
authority. The schedule above is binding because it comes from the versioned
approval, not because it is convenient for the implementation.

## Rollout and rollback

This accepted ADR changes documentation only. RT-340 is authorized to introduce
an independent default-disabled runtime, additive Schema 16 migration, fresh
and 15-to-16 upgrade tests, retry checkpoints, and an operational kill switch.
Acceptance requires proof that every approved retention and SLA rule is mapped
and enforced. Rollback disables new request intake and workers while preserving
request state and audit evidence; it must not reverse completed anonymization
or delete business records. This ADR does not authorize production enablement.
