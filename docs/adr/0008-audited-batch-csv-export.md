# ADR 0008: Audited Deterministic Batch CSV Export

**Status:** Accepted for RT-207
**Date:** 2026-07-28
**Schema before/after:** `8 -> 8`

## Context

RT-206 exposes a complete Batch inventory in deterministic `tag_id ASC`
order, but deliberately creates no manufacturer file, version, checksum, audit
record, or state transition. Production operators need an authorized export
whose exact bytes and issuance history can be verified without regenerating or
modifying Tag IDs.

The existing `returntag_batch_exports` table already stores Batch ID, export
version, row count, format, SHA-256 checksum, operator, and UTC time. A Schema
change is therefore unnecessary.

## Decision

RT-207 defines CSV format `csv` version one with these exact columns:

```text
sequence_no,batch_code,tag_id,tag_type,model_code,smart_network,qr_url
```

Files are UTF-8 without a BOM, comma-delimited, CRLF-terminated, and ordered by
`tag_id ASC`. The header participates in the SHA-256 digest and is excluded
from `row_count`. Formula-capable prefixes in operator-controlled cells are
neutralized before CSV encoding.

The export source selects only Tag ID, Tag Type, and Model Code. Owner, item,
Lost Mode, scan, order, credential, message, and location fields are never
read or exported. QR URLs use the trusted WordPress home URL and must be HTTPS
outside local or development environments.

Only complete `generated`, `exported`, and `released` Batches may export.
`draft`, `generating`, `suspended`, and `voided` Batches fail closed. The first
successful export atomically appends version `1`, records a `batch_exported`
Event, and changes `generated` to `exported`. Re-exports append a new version
without changing later states.

The file is built in a private temporary path using bounded `tag_id ASC`
reads. A short database transaction then locks the Batch, revalidates its
manufacturing snapshot and Tag count, allocates the next version, appends the
audit row and Event, and performs the first-export state transition.

Every re-export must reproduce the previous row count, format, and exact
SHA-256 digest. Drift stops the operation without a new audit record. A
successful audit row means the server prepared the artifact and began the
authorized download response; it does not prove that an operator saved or
delivered the file to a manufacturer.

The capability-protected REST surface is:

```text
POST /tagcore/v1/batches/{batch_id}/exports
GET  /tagcore/v1/batches/{batch_id}/exports
```

POST streams the newly audited CSV. GET returns bounded audit history through
a validated opaque cursor. Both require current Schema state, WordPress REST
cookie authentication, nonce handling, `manage_returntag_batches`, and
`no-store, private` responses.

## Consequences

- Generated and exported Tag IDs remain immutable and permanently
  non-reusable.
- No CSV body or temporary path is persisted in WordPress or the product
  tables.
- Repeated delivery creates a distinct audit version but identical CSV bytes.
- Batch export version allocation is serialized through a `FOR UPDATE` lock on
  the parent Batch and finalized by the existing unique Batch/version index.
- The current Schema remains version `8`; existing Migrations are unchanged.
- RT-208 owns release and incident-state transitions. RT-210 owns production
  capacity evidence and any proposed compound index or asynchronous export.

## Rejected alternatives

- **Generate a new Tag set during re-export:** violates permanent ID
  immutability and manufacturer auditability.
- **Expose complete Tag records:** unnecessarily reads future owner and private
  item data.
- **Persist files in the uploads directory:** creates a public-path and
  retention problem not approved for phase one.
- **Allow suspended or voided Batch exports:** risks sending incident-contained
  IDs back into manufacturing.
- **Record an audit after streaming:** a connection failure could leave an
  untracked file response. RT-207 instead records successful preparation and
  delivery initiation.

## Rollback

Code rollback removes export commands and UI but retains all Batch Export rows,
Events, `exported` states, and generated Tag IDs. RT-206 can still display the
complete inventory for an exported Batch. Rollback must not delete audit
records, change an exported Batch back to generated, remove Tags, or reuse any
identifier.
