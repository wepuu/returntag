# ADR 0006: Batch Tag ID Inventory Projection

**Status:** Accepted for RT-206
**Date:** 2026-07-27
**Schema before/after:** `8 -> 8`

## Context

RT-205 deliberately exposes only aggregate generation progress. After a Batch
finishes generation, an authorized production operator still needs to verify
the immutable Tag IDs assigned to that Batch before an audited manufacturer
export can be created.

The existing `TagRepository::list_by_batch()` hydrates complete Tag records,
including future owner and private item fields, and orders by status. Reusing
that surface would expose unnecessary data and would not establish the
deterministic Tag-ID order required by export work.

## Decision

RT-206 adds a separate read-only Batch Tag inventory projection:

```text
GET /tagcore/v1/batches/{batch_id}/tags
```

The route requires `manage_returntag_batches`, a current Schema, and WordPress
REST cookie authentication. Every response is marked `no-store, private`.

The projection selects only:

```text
tag_id
tag_status
created_at
```

Results are ordered by `tag_id ASC`, limited to 50 by default and 100 at most,
and use a strict keyset cursor. The REST cursor is a validated, versioned
Base64URL value. It is intentionally opaque but is not a secret,
authorization token, or integrity proof.

Inventory is available only when committed generated quantity equals requested
quantity and the Batch is no longer `draft` or `generating`. This allows a
complete historical inventory to remain visible after later terminal status
changes while preventing exposure of an in-progress manufacturing set.

The existing Schema version 8 remains unchanged. The current primary key and
Batch predicate support bounded reads, but the exact `batch_id + tag_id`
execution characteristics must be measured with production-scale data during
RT-210 before any new index is approved.

## Consequences

- Batch detail gains a paginated, keyboard-accessible manufacturing inventory.
- The UI reports loaded rows against the committed Batch total and retains
  deterministic ordering across pages.
- Full Tag records, owner identity, private item data, Lost Mode content,
  scanning data, order data, tokens, and location data never cross this route.
- RT-207 can reuse the same ordering contract as the source of an audited CSV,
  but RT-206 creates no file, checksum, export version, audit record, or
  download response.
- RT-209 remains responsible for cross-Batch Tag management and search.

## Rollback

Code rollback removes the route and UI only. It does not modify Schema version
8, delete Tag rows, regenerate IDs, change Batch state, or affect future export
history. Disabling TagCore removes access to the administrative projection
without changing stored manufacturing data.
