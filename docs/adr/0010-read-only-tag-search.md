# ADR 0010: Read-only Tag Search

**Status:** Accepted for RT-209
**Date:** 2026-07-29
**Schema before/after:** `8 -> 8`

## Context

Manufacturing operators need to locate an individual Tag without exposing the
complete Tags table or private owner-facing fields. The Batch detail inventory
from RT-206 is intentionally contextual and cannot replace a dedicated,
cross-Batch lookup.

## Decision

RT-209 adds a read-only Tags administration page and:

```text
GET /tagcore/v1/tags
```

Every request selects exactly one mode: an exact normalized Tag ID, or an exact
case-sensitive Batch Code with an optional canonical Tag Status. Unfiltered,
partial, wildcard, owner, and free-text searches are rejected.

Batch results use `tag_id ASC`, a default page size of 50, a maximum of 100,
and a versioned opaque cursor bound to the normalized filters. The response is
limited to persisted facts plus one server-derived, non-persisted activation
decision:

```text
tag_id
batch_id
batch_code
batch_status
batch_activation_enabled
activation_availability
tag_type
model_code
tag_status
lost_mode
activated_at
created_at
updated_at
```

`tag_status` continues to describe the Tag itself. It is intentionally
independent of the Batch lifecycle: a Tag in a suspended or voided Batch
remains searchable and keeps its stored status because generated IDs are
permanent audit records and must never be reused. The server derives
`activation_availability` from Tag status, activation history, Batch status,
the Batch activation control, and the global activation flag. It does not
persist or mutate a second status.

The derived values distinguish eligible activation, waiting for release,
global or Batch controls, suspended or voided Batches, suspended or retired
Tags, retained existing activations, and inconsistent stored facts. Existing
active Tags remain active when a Batch is later suspended or voided.

The collection response also includes
`context.global_activation_enabled`. This is a read-only operational snapshot,
not a browser-controlled eligibility decision.

Owner identifiers, item names, public labels, Lost Mode messages, scan history,
order or logistics data, Claim data, credentials, messages, device data, and
location data are excluded.

The route requires current Schema state, WordPress REST cookie authentication
and nonce handling, `manage_returntag_tags`, and `no-store, private` responses.
Capability contract version 2 adds the capability without downgrading a future
stored contract version.

## Consequences

- Schema remains 8 and the plugin remains 0.2.0.
- The reader names approved columns and never hydrates the complete Tag record.
- The feature flag is read only; no Event, mutation, queue, export, ownership,
  or feature-flag change occurs.
- Search results explicitly explain that audit visibility is not activation
  permission and clear stale rows while a new request is pending.
- RT-210 owns capacity evidence and any future numbered index Migration.

## Rejected alternatives

- Unfiltered all-Tag browsing creates unnecessary exposure and capacity risk.
- Reusing the complete Tag DTO selects private and wide fields.
- Offset pagination is unstable under concurrent inserts.
- Adding an index here bypasses the RT-210 capacity review.

## Rollback

Disable TagCore or roll back code to remove the page and route. Capability
contract version 2 and the inert granted capability may remain. No Tag, Batch,
Export, Event, or Schema record requires repair.
