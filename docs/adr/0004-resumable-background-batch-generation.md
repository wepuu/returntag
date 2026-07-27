# ADR 0004: Resumable background Batch generation

**Status:** Accepted for RT-204
**Date:** 2026-07-27
**Schema before/after:** `8 -> 8`
**Plugin before/after:** `0.2.0 -> 0.2.0`

## Context

Production Batches may request more Tag IDs than can be generated safely during
one administrative HTTP request. Generation must be asynchronous, resumable,
bounded, collision-safe, auditable, and compatible with the existing batches,
tags, and events tables. A failure must not erase committed IDs or require
reusing an ID.

RT-203 already provides insert-first generation with a maximum of ten
duplicate-key retries. RT-204 must coordinate that operation with Batch
progress without moving queue or WordPress behavior into Domain or
Application.

## Decision

An authorized administrator starts or resumes generation through:

```text
POST /wp-json/tagcore/v1/batches/{batch_id}/generation
```

The REST adapter accepts no client-controlled generation fields and requires
`manage_returntag_batches`. A pristine disabled `draft` Batch moves to
`generating` and records one metadata-free `batch_generation_started` Event in
the same transaction. A `generating` Batch resumes from its committed
`generated_quantity`; a `generated` Batch returns an idempotent completed
response.

Application depends on a provider-neutral scheduler port. Infrastructure uses
Action Scheduler with:

```text
Hook:     returntag_generate_batch_chunk
Group:    returntag-tag-generation
Priority: 20
Chunk:    100 Tags
Retries:  60, 300, 900, 3600, 21600 seconds
```

Queue arguments contain only the numeric Batch ID, committed checkpoint, and
retry attempt. Exact duplicate pending actions are unique.

Every Tag is committed in its own transaction:

1. Lock the Batch row.
2. Recheck that its state and counter permit generation.
3. Generate and insert one Tag through the RT-203 collision-safe service.
4. Conditionally increment `generated_quantity`.
5. For the final requested Tag, set status to `generated` and append one
   metadata-free `batch_generation_completed` Event.

The initial chunk inspection also verifies that the materialized counter equals
the number of committed Tag rows. A checkpoint ahead of committed storage
fails closed. A stale checkpoint may resume from current committed progress.
Actions stop without new writes when the Batch is no longer `generating`.

## Consequences

- Public requests do not perform long-running generation.
- Each action is bounded to 100 Tags and can resume after partial success.
- Batch row locks are short because there is no whole-chunk transaction.
- A Tag insert and its counter update either commit together or roll back
  together.
- Queue failure after the start transaction leaves a visible `generating`
  Batch; repeating the POST safely schedules its current checkpoint.
- No per-Tag or per-chunk Event is emitted, avoiding high-volume audit noise.
- Action Scheduler must be driven by a real Cron or WP-CLI runner in
  production, and the generation group must be monitored for failed actions.

## Rejected alternatives

- Generating all IDs inside the REST request: unbounded request time and unsafe
  retries.
- One transaction for an entire Batch or chunk: longer locks and loss of all
  chunk progress after one failure.
- Reserving candidate IDs before insert: adds a reusable pool and weakens the
  database uniqueness authority.
- Passing Tag IDs in queue arguments: exposes sensitive production identifiers
  in queue storage and logs.
- Reusing an activation feature flag as a generation flag: changes the meaning
  of an approved incident control.

## Rollback and operations

RT-204 adds no DDL, Option, or dependency. Code rollback preserves Schema
version `8`, every committed Tag, Batch progress, and audit Event. Disable the
plugin or pause the `returntag-tag-generation` Action Scheduler group to stop
new worker execution while investigating. Do not delete Tags, decrement
`generated_quantity`, or reuse IDs. Resume by restoring compatible code and
reposting the generation command for the affected Batch.
