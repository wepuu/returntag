# ADR 0009: Batch Release and Incident Controls

**Status:** Accepted for RT-208
**Date:** 2026-07-28
**Schema before/after:** `8 -> 8`

## Context

RT-207 produces an immutable, audited manufacturer export and changes the
first complete Batch from `generated` to `exported`. Production operators still
need an explicit release decision before unregistered Tags may activate, plus
incident controls that retain all generated identifiers and audit evidence.

The existing Batches, Tags, Batch Exports, and Events tables contain every
required field and index. A Schema change is unnecessary.

## Decision

RT-208 defines these state transitions:

```text
exported  -> released
suspended -> released, only with a complete audited export

generated -> suspended
exported  -> suspended
released  -> suspended

generated -> voided
exported  -> voided
released  -> voided
suspended -> voided
```

`voided` is terminal. `draft` and `generating` reject all RT-208 actions.
Repeating an action that has already reached its target is idempotent and does
not append another Event.

Release requires `generated_quantity = requested_quantity`, an equal committed
Tag count, and a latest audited export whose row count matches the requested
quantity. Release atomically sets `batch_status=released` and
`activation_enabled=1`.

Suspend and Void atomically set `activation_enabled=0`. They never update,
delete, retire, suspend, or reassign Tag rows. Existing active owners remain
active; the controls stop only future Batch activation. Generated, exported,
suspended, and voided Tag IDs remain permanently non-reusable.

Every changed state appends exactly one privacy-safe Event in the same
transaction:

```text
batch_released
batch_suspended
batch_voided
```

The Event actor is the authorized WordPress User ID, the target is the numeric
Batch ID, and metadata and correlation ID remain empty.

The site-scoped `returntag_global_activation_enabled` Option remains
authoritative. Release is permitted while the global control is disabled so
operators can complete manufacturing state deliberately, but effective
activation remains false. RT-208 does not write the global Option.

The capability-protected REST surface is:

```text
GET  /tagcore/v1/batches/{batch_id}/lifecycle
POST /tagcore/v1/batches/{batch_id}/release
POST /tagcore/v1/batches/{batch_id}/suspend
POST /tagcore/v1/batches/{batch_id}/void
```

Commands require a client-observed `expected_status`. Void additionally
requires an exact, case-sensitive `batch_code_confirmation`. Routes require
current Schema state, WordPress REST cookie authentication and nonce handling,
`manage_returntag_batches`, and `no-store, private` responses.
The lifecycle read model exposes a derived `release_ready` decision so the
interface does not offer Release before complete counts and an audited export
exist. The command revalidates the same evidence inside its transaction.

## Consequences

- Lifecycle writes are serialized by `SELECT ... FOR UPDATE` and a conditional
  status update.
- State and Event append commit or roll back together.
- No queue work is canceled; `draft` and `generating` cannot transition.
- No owner, email, message, item, order, Claim, device, pairing, or location
  data is selected or returned.
- The current Schema remains `8`, and project/plugin version remains `0.2.0`.
- RT-301 through RT-307 must enforce global enabled, Batch `released`, and
  Batch `activation_enabled=1` before activation.

## Rejected alternatives

- **Release automatically after export:** removes the required operator
  decision between manufacturing issuance and customer activation.
- **Suspend or retire every Tag row:** silently disrupts existing owners and
  conflates Batch containment with Tag-level incident state.
- **Delete or regenerate voided IDs:** violates permanent non-reuse and export
  auditability.
- **Let the UI decide transitions:** creates a race and bypass path; the server
  owns policy, integrity checks, and conditional writes.

## Rollback

Disable TagCore to remove lifecycle routes and administration controls. Code
rollback preserves Batch status, activation controls, every Tag ID, Batch
Export row, and Event. A released Batch remains released in storage, but the
previous code has no activation workflow. Do not roll back by deleting Events,
changing generated counters, deleting Tags, or reusing identifiers.
