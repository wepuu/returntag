# ADR 0005: Administrative Batch generation progress

**Status:** Accepted for RT-205
**Date:** 2026-07-27
**Schema before/after:** `8 -> 8`
**Plugin before/after:** `0.2.0 -> 0.2.0`

## Context

RT-204 persists committed Batch counters and runs generation through resumable
Action Scheduler actions, but it intentionally supplies no operator-facing
confirmation or progress view. Production administrators need to understand
that generation creates permanent public activation IDs, observe committed
progress, and safely recover a generating Batch whose worker is no longer
scheduled.

The Schema has no failed-Tag record or failure counter. A candidate either
commits with its Batch counter update or rolls back. Queue attempts are
operational failures and must not be presented as failed physical Tags.

## Decision

The existing Batch detail screen owns a WordPress-native confirmation modal and
progress panel. The first-generation POST remains the RT-204 idempotent command:

```text
POST /wp-json/tagcore/v1/batches/{batch_id}/generation
```

RT-205 adds an authorized, no-store aggregate query:

```text
GET /wp-json/tagcore/v1/batches/{batch_id}/generation
```

The Application query combines a narrow `$wpdb` projection with a
provider-neutral queue monitor. It returns only Batch state, target and
committed counters, derived remaining and percentage values, audited start and
completion times, a stable queue state, action availability, and a bounded
polling hint. It returns no Tag IDs, Action Scheduler arguments, SQL, metadata,
or provider error text.

Queue state is normalized to:

```text
idle
scheduled
running
needs_attention
complete
unavailable
```

A `generating` Batch with no pending or running action becomes
`needs_attention`; reposting the RT-204 command resumes from its committed
checkpoint. A queue inspection failure becomes `unavailable` and offers no
action until state can be verified.

`failed_quantity` is zero because the accepted storage model never persists a
failed Tag. The UI displays `remaining_quantity` separately and explains that
remaining work is not failure. A future cumulative failure metric requires a
separate approved contract.

The detail screen polls only while generation is scheduled or running, at no
less than three seconds, and pauses while the document is hidden. The Batch
list refreshes every ten seconds only while a visible row is generating.

## Consequences

- Administrators must confirm the permanent, non-reusable ID effect before the
  first POST.
- Progress represents committed database state rather than speculative queue
  work.
- Queue recovery reuses the existing idempotent RT-204 command.
- The UI stops polling on completion, attention, unavailable, and non-generating
  states.
- Activation remains disabled and is not repurposed as a generation control.
- No Migration, Option, dependency, Tag exposure, export, or public route is
  added.

## Rejected alternatives

- Counting remaining IDs as failures: misrepresents pending work.
- Returning Action Scheduler records or errors: exposes provider internals and
  potentially sensitive operational data.
- Running generation directly from the create request: removes the required
  second confirmation and blocks the administrative request.
- Polling continuously on every Batch page: creates unnecessary authenticated
  REST and database load.
- Adding a new failure counter in RT-205: requires an approved Schema and metric
  definition.

## Rollback and operations

RT-205 keeps Schema version `8` and plugin version `0.2.0`. Code rollback removes
the progress interface but preserves every Batch, Tag, counter, Event, and
Action Scheduler record. Pause the `returntag-tag-generation` group or disable
TagCore to stop new work while investigating. Do not delete Tags, decrement
counters, or reuse generated IDs.
