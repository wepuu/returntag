# ADR 0011: Milestone 2 capacity baseline

**Status:** Accepted

**Date:** 2026-07-29

**Ticket:** RT-210

**Plugin before/after:** `0.2.0 -> 0.3.0`

**Schema before/after:** `8 -> 8`

## Context

Milestone 2 introduced asynchronous generation, deterministic Batch inventory,
audited CSV export, lifecycle controls, and exact Tag search. Schema version 8
does not have a dedicated `(batch_id, tag_id)` index. Earlier tickets preserved
that schema intentionally and assigned the production-scale decision to
RT-210.

The project needs a measurable Batch limit and evidence that existing query,
queue, and export paths remain usable before enabling larger manufacturing
runs. It must not add speculative indexes or expose a new product flow merely
to close the milestone.

## Decision

The supported Milestone 2 Batch request limit is `100,000` Tag IDs. The
Application input contract and administrative REST boundary enforce the same
limit before persistence, and the WordPress admin form communicates it.

The dedicated capacity suite validates:

- actual Action Scheduler generation of `10,000` Tags in 100-Tag chunks;
- inventory, exact Tag search, Batch search, progress, and lifecycle count
  queries against `1,000,000` synthetic Tags;
- deterministic CSV construction for one complete `100,000`-Tag Batch;
- indexed candidates for representative inventory and Batch-search query
  shapes.

The measured default-environment results are recorded in
`docs/PERFORMANCE.md`.

Schema remains version `8`. Existing indexes satisfy the approved budgets, so
RT-210 does not add a Migration or a speculative `(batch_id, tag_id)` index.
Optimizer-specific complete plans are not frozen.

The expensive capacity suite is a dedicated command rather than part of every
ordinary quality run. It is required when capacity-sensitive queries,
generation, export, indexes, database engines, or infrastructure change and
before the Milestone 2 release candidate.

## Consequences

- Requests greater than `100,000` fail validation without creating a Batch or
  audit Event.
- Existing Batch generation remains resumable in chunks of `100`; no large
  synchronous public or administrative request is introduced.
- The existing admin workflow, layout, permissions, no-store behavior, privacy
  projection, and feature controls remain unchanged.
- A future higher limit requires new capacity evidence, operational review,
  and an explicit contract change.
- A future index change requires a new numbered Migration with fresh-install,
  upgrade, retry, compatibility, and rollback coverage.

## Security and privacy

Capacity fixtures use generated operational identifiers only. They contain no
owner, finder, email, message, token, order, shipment, device, pairing, or
location data. Performance output contains aggregate timings and counts, never
Tag IDs, CSV content, SQL parameters, credentials, or database errors.

The `100,000` limit reduces accidental resource exhaustion but is not an
authorization control. Existing capability, nonce, Schema-current, queue,
transaction, and global or Batch incident controls remain authoritative.

## Rollback

Code rollback to `0.2.0` removes the new input limit, dedicated test harness,
and documentation only. Schema and stored records remain compatible because
no table, index, Option, or Migration changed.

Operators should disable or suspend risky Batch generation through existing
controls before code rollback. Never delete or reuse generated, exported,
suspended, voided, or retired Tag IDs.
