# ADR 0015: Activation three-way routing and concurrency convergence

**Status:** Accepted for RT-307 through RT-309

**Date:** 2026-07-31

**Schema before/after:** `8 -> 8`

**Plugin before/after:** `0.3.0 -> 0.3.0`

## Context

The public Tag route already distinguishes an unregistered Tag, an active Tag,
and an invalid or unavailable Tag. Activation adds a concurrent state change:
after OTP authentication but before the ownership write completes, another
request may activate the same Tag.

That database race does not create a separate product state. Presenting an
"activation conflict" page would duplicate routing logic, reveal that a
specific activation race occurred, and introduce a support or dispute path
that is not part of the approved scan journey.

## Decision

The canonical scan experience remains a three-way product flow:

```text
unregistered Tag -> activation
active Tag       -> current Owner page or Finder return flow
invalid/blocked  -> invalid or state explanation page
```

RT-307 assigns first ownership with one atomic conditional database update.
The authoritative predicate includes the canonical Tag ID, no existing Owner,
`unregistered` Tag status, no prior activation timestamp, a `released` Batch,
and enabled Batch activation. The global activation incident control is also
checked by the Application service. Only one affected Tag row is success.

The successful Tag mutation and its metadata-free `tag_activated` audit Event
share one database transaction. A retry by the same committed Owner is
idempotent and does not append a duplicate activation Event.

RT-308 treats every zero-row activation outcome as a request to re-resolve the
committed public route state. It does not render a conflict page:

- the committed Owner receives the Owner state;
- another authenticated or anonymous user receives the Finder return state
  for an active Tag;
- invalid, suspended, retired, disabled, or otherwise unavailable state uses
  the existing privacy-safe state page.

No path overwrites a committed Owner or exposes Owner identity. No support or
ownership-dispute call to action is added to the activation state machine.

RT-309 applies durable attempt budgets before the activation mutation. Limits
use privacy-safe keyed signals and generic responses; they do not add a fourth
route state or disclose whether a Tag or account exists.

## Consequences

- Concurrency is a persistence outcome followed by state convergence, not a
  user-visible business state.
- Public templates continue to render only the existing approved product
  states and reuse the RT-301 design system.
- A same-Owner retry is safe; a different actor cannot replace ownership.
- Schema remains version 8 because the existing Tag, Batch, Event, and durable
  request-budget storage are sufficient.
- Customer support remains available through the website independently of
  this flow, but activation does not advertise or automate a dispute path.

## Rejected alternatives

- **Activation conflict page:** introduces an unnecessary fourth flow and
  exposes race-specific information.
- **Support or dispute CTA after a zero-row update:** couples customer support
  to a normal state-resolution outcome.
- **Read then write ownership:** permits two requests to believe they won.
- **Last write wins:** allows ownership theft and destroys audit integrity.
- **Order or shipment evidence:** violates the phase-one separation between
  commerce, logistics, and Tag ownership.

## Rollback

Disable `returntag_global_activation_enabled` first to stop new activation.
Code rollback removes the activation mutation, convergence, and attempt-limit
composition while preserving committed Owner relationships and audit Events.
Rollback must not clear `owner_id`, revert `active` status, delete activation
Events, or reuse any Tag ID.
