# ReturnTag Database Baseline

**Status:** Milestone 2 complete at Schema version 8 with RT-210 million-row capacity evidence

**Schema created through RT-108:** `returntag_batches`, `returntag_tags`, `returntag_batch_exports`, `returntag_auth_challenges`, `returntag_conversations`, `returntag_messages`, `returntag_access_tokens`, `returntag_events`; current target version `8`

## 1. Purpose

This document defines database naming, ownership, integrity, migration,
retention, and rollback rules for ReturnTag schema tickets. RT-007 reads four
site-scoped operational options but does not create or write them. RT-008 adds
non-persistent operational logging. RT-101 implements the numbered Migration
runtime. RT-102 registers Migration `0001` for batches. RT-103 registers
Migration `0002` for tags after verifying the required batches contract. Each
version advances only after postcondition verification. RT-104 registers
Migration `0003` for immutable export audit metadata. RT-105 registers
Migration `0004` for privacy-oriented authentication challenge state. RT-106
registers Migrations `0005` and `0006` for privacy-preserving conversations and
encrypted messages. RT-107 registers Migration `0007` for hash-only access
token lifecycle state. RT-108 registers Migration `0008` for privacy-safe
business audit events. RT-109 adds typed persistence contracts and `$wpdb`
adapters for the eight existing tables without changing the schema.

## 2. Table naming

Every table name must combine the active WordPress prefix with the ReturnTag
suffix:

```php
$table = $wpdb->prefix . 'returntag_tags';
```

The WordPress prefix is installation-specific. Code must never hard-code
`wp_`, interpolate an untrusted prefix, or assume a single-site prefix applies
to every supported WordPress context.

All WordPress options use the `returntag_` prefix. The Migration runtime uses
the non-autoloaded, site-scoped option `returntag_schema_version`. It advances
only after a numbered Migration passes its postcondition checks. Missing or
invalid values fail closed to `0`.

The RT-007 global feature flag reader uses the four option names frozen in the
PRD. Missing or noncanonical values are disabled. The adapter adds no cache
beyond the WordPress Options API and performs no automatic writes during plugin
load, activation, deactivation, or uninstall.

## 3. Planned phase-one tables

| Logical table | Responsibility |
|---|---|
| `returntag_batches` | Manufacturing batch metadata, generation progress, state, and activation control |
| `returntag_tags` | Immutable Tag ID, batch membership, owner, lifecycle state, and item fields |
| `returntag_batch_exports` | Auditable export versions, row counts, checksums, operators, and timestamps |
| `returntag_auth_challenges` | Hashed OTP and one-time verification challenge state |
| `returntag_conversations` | Finder/owner relay session state and encrypted finder address data |
| `returntag_messages` | Encrypted message content and provider delivery state |
| `returntag_access_tokens` | Hashed secure-link tokens, purpose, expiry, exchange, and revocation state |
| `returntag_events` | Privacy-safe business audit events |

The eight phase-one table contracts are implemented by RT-102 through RT-108
and remain governed by the PRD and ADR 0003.

### 3.1 Schema version 1: `returntag_batches`

Migration `0001` creates the dynamically prefixed batches table with InnoDB
and the active WordPress character set and collation. Codes and canonical state
values use ASCII with `ascii_bin` collation.

| Column | Contract |
|---|---|
| `batch_id` | Unsigned auto-increment bigint primary key |
| `batch_code` | Required, case-sensitive ASCII `varchar(191)`; unique |
| `tag_type` | Required, case-sensitive ASCII `varchar(32)` |
| `model_code` | Nullable, case-sensitive ASCII `varchar(191)` |
| `smart_network` | Required ASCII `varchar(32)`; default `none` |
| `manufacturer` | Nullable WordPress-charset `varchar(191)` |
| `sales_channel` | Nullable, case-sensitive ASCII `varchar(64)` |
| `requested_quantity` | Required unsigned integer |
| `generated_quantity` | Required unsigned integer; default `0` |
| `batch_status` | Required ASCII `varchar(32)`; default `draft` |
| `activation_enabled` | Required unsigned boolean storage; default `0` |
| `notes` | Nullable WordPress-charset text |
| `created_by` | Required unsigned WordPress user ID storage; no foreign key |
| `created_at`, `updated_at` | Required UTC datetimes supplied by the application |

Indexes are `batch_code_unique`, `batch_status_created_at`,
`tag_type_model_code`, and `activation_enabled_status`, in addition to the
primary key. RT-102 creates no foreign key, SQL enum, check constraint,
trigger, database-managed timestamp, soft-delete field, or business workflow.

### 3.2 Schema version 2: `returntag_tags`

Migration `0002` creates the dynamically prefixed tags table with InnoDB and
the active WordPress character set and collation. Tag IDs and canonical code
values use ASCII with `ascii_bin` collation.

| Column | Contract |
|---|---|
| `tag_id` | Required, case-sensitive ASCII `char(6)` primary key |
| `batch_id` | Required unsigned batch ID storage; no foreign key |
| `owner_id` | Nullable unsigned WordPress user ID storage; no foreign key |
| `tag_type` | Required, case-sensitive ASCII `varchar(32)` |
| `model_code` | Nullable, case-sensitive ASCII `varchar(191)` |
| `item_name` | Nullable private WordPress-charset `varchar(191)` |
| `public_label` | Nullable public-target WordPress-charset `varchar(191)` |
| `tag_status` | Required ASCII `varchar(32)`; default `unregistered` |
| `lost_mode` | Required unsigned boolean storage; default `0` |
| `lost_message` | Nullable public-target WordPress-charset text |
| `owner_pairing_ack_at` | Nullable UTC acknowledgement datetime; not pairing proof |
| `activated_at`, `owner_changed_at`, `last_scanned_at` | Nullable UTC event datetimes |
| `created_at`, `updated_at` | Required UTC datetimes supplied by the application |

Indexes are `batch_id_status`, `owner_id_status`, and
`tag_status_updated_at`, in addition to the primary key. The primary key makes
the public six-character Tag ID unique. RT-103 creates no foreign key, SQL
enum, check constraint, trigger, database-managed timestamp, soft-delete
field, repository, ID generator, or business workflow.

The data model intentionally duplicates `tag_type` and `model_code` from the
batch snapshot needed by each physical tag. A later Repository/Application
boundary must verify that `batch_id` exists and that these values match the
referenced batch before insertion. RT-103 does not weaken that integrity rule
by accepting arbitrary writes, because it exposes no write API.

### 3.3 Schema version 3: `returntag_batch_exports`

Migration `0003` creates the dynamically prefixed export audit table with
InnoDB and the active WordPress character set and collation. File format and
SHA-256 checksum values use ASCII with `ascii_bin` collation.

| Column | Contract |
|---|---|
| `export_id` | Unsigned auto-increment bigint primary key |
| `batch_id` | Required unsigned Batch ID storage; no foreign key |
| `export_version` | Required unsigned integer |
| `row_count` | Required unsigned integer |
| `file_format` | Required, case-sensitive ASCII `varchar(32)` |
| `file_checksum` | Required, case-sensitive ASCII `char(64)` |
| `created_by` | Required unsigned WordPress user ID storage; no foreign key |
| `created_at` | Required UTC datetime supplied by the application |

The unique `batch_export_version_unique` index covers
`(batch_id, export_version)`. The non-unique `batch_file_checksum` index covers
`(batch_id, file_checksum)` so repeated delivery of the same immutable export
can retain a distinct audit version. The table stores no CSV content, file
path, Tag ID list, order identifier, personal data, or secret.

RT-104 supplies no Repository or append operation. Later Application and
Repository code must require an existing Batch, allocate a positive version
concurrency-safely, validate `csv` and canonical SHA-256 syntax, verify that
`row_count` matches the exported immutable Tag set, and expose no update or
delete operation. Append-only behavior is therefore an application contract,
not a trigger-enforced database property.

### 3.4 Schema version 4: `returntag_auth_challenges`

Migration `0004` creates the dynamically prefixed one-time authentication
challenge table with InnoDB and the active WordPress table character set and
collation. Purpose, subject, lookup, and hash values use case-sensitive ASCII
storage. Ciphertext is an opaque binary value.

| Column | Contract |
|---|---|
| `challenge_id` | Unsigned auto-increment bigint primary key |
| `purpose` | Required case-sensitive ASCII `varchar(32)` |
| `subject_type` | Required case-sensitive ASCII `varchar(32)` |
| `subject_id` | Required case-sensitive ASCII `varchar(191)` polymorphic identifier |
| `email_ciphertext` | Required opaque `longblob`; plaintext email is forbidden |
| `email_lookup` | Required case-sensitive ASCII `char(64)` for a keyed HMAC lookup |
| `code_hash` | Required case-sensitive ASCII `varchar(255)` for a secure code hash |
| `attempt_count` | Unsigned integer, default `0` |
| `send_count` | Unsigned integer, default `0` |
| `ip_hash` | Optional case-sensitive ASCII `char(64)` for a privacy-safe keyed IP lookup |
| `expires_at` | Required UTC datetime supplied by the application |
| `verified_at` | Optional UTC verification time |
| `consumed_at` | Optional UTC consumption time |
| `created_at` | Required UTC creation time supplied by the application |

Indexes are `(purpose, email_lookup, created_at)`,
`(subject_type, subject_id, created_at)`, and `(expires_at, consumed_at)`.
They support later challenge lookup, throttling, and cleanup without creating a
false uniqueness rule: multiple historical challenges for the same purpose
and lookup are valid. No index contains ciphertext or a plaintext identity.

RT-105 supplies no Repository, encryption service, HMAC service, OTP generator,
sender, verifier, limiter, login flow, or cleanup task. Later code must treat
`subject_id` as a typed reference rather than authorization evidence, use a
self-describing authenticated-encryption envelope, keep keys outside the
database, compare secrets safely, enforce the PRD expiry/attempt/resend limits,
and define retention before writes are enabled.

### 3.5 Schema version 5: `returntag_conversations`

Migration `0005` creates the dynamically prefixed finder/owner conversation
table with InnoDB and the active WordPress table character set and collation.
Identifiers, lookup values, and status use case-sensitive ASCII storage;
finder email ciphertext is an opaque binary envelope.

| Column | Contract |
|---|---|
| `conversation_id` | Unsigned auto-increment bigint primary key |
| `tag_id` | Required, case-sensitive ASCII `char(6)` Tag reference; no foreign key |
| `owner_id_snapshot` | Required unsigned WordPress user ID snapshot; no foreign key |
| `finder_email_ciphertext` | Required opaque `longblob`; plaintext finder email is forbidden |
| `finder_email_lookup` | Required case-sensitive ASCII `char(64)` keyed-HMAC lookup |
| `finder_verified_at` | Optional UTC verification time |
| `conversation_status` | Required case-sensitive ASCII `varchar(32)` with no database default |
| `expires_at` | Required UTC expiry supplied by the application |
| `last_activity_at` | Required UTC activity time supplied by the application |
| `created_at` | Required UTC creation time supplied by the application |

Indexes are `(tag_id, conversation_status, last_activity_at)`,
`(owner_id_snapshot, conversation_status, last_activity_at)`,
`(finder_email_lookup, created_at)`, and `(conversation_status, expires_at)`.
There is no uniqueness constraint on finder lookup or Tag ID because multiple
historical conversations are valid. Future application code must explicitly
write one of the canonical conversation states and verify the Tag and owner
snapshot references.

### 3.6 Schema version 6: `returntag_messages`

Migration `0006` creates the dynamically prefixed encrypted conversation
message table. Message bodies are opaque binary envelopes. Roles, delivery
states, and provider identifiers use case-sensitive ASCII storage.

| Column | Contract |
|---|---|
| `message_id` | Unsigned auto-increment bigint primary key |
| `conversation_id` | Required unsigned Conversation ID storage; no foreign key |
| `sender_role` | Required case-sensitive ASCII `varchar(32)` with no database default |
| `body_ciphertext` | Required opaque `longblob`; plaintext message bodies are forbidden |
| `delivery_status` | Required case-sensitive ASCII `varchar(32)`; default `queued` |
| `provider_message_id` | Optional case-sensitive ASCII `varchar(191)`; non-unique |
| `delivered_at` | Optional UTC confirmed-delivery time |
| `created_at` | Required UTC creation time supplied by the application |

Indexes are `(conversation_id, message_id)`, `(delivery_status, created_at)`,
and the non-unique `provider_message_id`. Provider acceptance is not confirmed
delivery. The current schema stores the latest provider identifier and delivery
projection; it does not model provider namespaces or delivery-attempt history.
A future expand Migration is required before multi-provider webhook ambiguity
or attempt-history requirements are enabled.

RT-106 supplies no Repository, encryption or HMAC service, Finder form, email
verification, access token, queue handler, provider adapter, webhook, retention
job, or conversation state machine. No production write path is enabled.

### 3.7 Schema version 7: `returntag_access_tokens`

Migration `0007` creates the dynamically prefixed access token table with
InnoDB and the active WordPress table character set and collation. Purpose,
actor role, and the digest use case-sensitive ASCII storage. The schema stores
no plaintext Token.

| Column | Contract |
|---|---|
| `token_id` | Unsigned auto-increment bigint primary key |
| `conversation_id` | Required unsigned Conversation ID storage; no foreign key |
| `purpose` | Required case-sensitive ASCII `varchar(32)` |
| `actor_role` | Required case-sensitive ASCII `varchar(32)` |
| `token_hash` | Required case-sensitive ASCII `char(64)` digest; unique |
| `expires_at` | Required UTC expiry supplied by the application |
| `exchanged_at` | Optional UTC successful-exchange time |
| `revoked_at` | Optional UTC revocation time |
| `created_at` | Required UTC creation time supplied by the application |

The unique `token_hash_unique` index covers `token_hash`. Non-unique indexes
cover `(conversation_id, purpose, actor_role)` and `(expires_at, revoked_at)`.
Multiple historical tokens for the same Conversation, purpose, and actor are
valid; future Application code must enforce issuance, rotation, exchange,
revocation, replay, and concurrency policy.

The fixed 64-character column supports a normalized 256-bit digest encoded as
hexadecimal text. RT-107 does not select or implement the hashing adapter. Any
future adapter must use one deterministic canonical encoding so equivalent
digests cannot differ only by representation. A different digest format
requires a forward-compatible expand Migration.

RT-107 supplies no Token generator, hashing service, Repository, secure-link
route, GET or POST handler, session, exchange, revocation workflow, logger,
audit event, or retention job. No production write path is enabled.

### 3.8 Schema version 8: `returntag_events`

Migration `0008` creates the dynamically prefixed business events table with
InnoDB and the active WordPress table character set and collation. Event,
actor, target, result, and correlation codes use case-sensitive ASCII storage.
Optional metadata uses the WordPress table character set as portable
`longtext`; the RT-109 Repository validates approved JSON before writing it.

| Column | Contract |
|---|---|
| `event_id` | Unsigned auto-increment bigint primary key |
| `event_type` | Required case-sensitive ASCII `varchar(64)` |
| `actor_type` | Required case-sensitive ASCII `varchar(32)` |
| `actor_id` | Optional unsigned WordPress or internal actor ID; no foreign key |
| `target_type` | Required case-sensitive ASCII `varchar(32)` |
| `target_id` | Required case-sensitive ASCII `varchar(191)` supporting numeric and public Tag identifiers |
| `event_result` | Required case-sensitive ASCII `varchar(32)` |
| `correlation_id` | Optional case-sensitive ASCII `varchar(64)` operation correlation value |
| `metadata_json` | Optional WordPress-charset `longtext`; future write paths must validate bounded privacy-safe JSON |
| `created_at` | Required UTC creation time supplied by the application |

Non-unique indexes cover `(event_type, created_at)`,
`(target_type, target_id, created_at)`,
`(actor_type, actor_id, created_at)`, and `correlation_id`. The
`(created_at, event_id)` index supports stable global audit pagination and
time-based retention scans. Correlation is intentionally non-unique because one
business operation may append multiple related events.

The PRD event names are examples, not a closed SQL enum. RT-108 adds no event
writer, Repository, admin query, export, retention job, logger bridge, trigger,
or product workflow. Append-only behavior is not implemented by database
triggers. RT-109 exposes Event persistence only through append and bounded
query contracts without update or delete methods. No production event writer
is registered by the plugin bootstrap.

### 3.9 RT-109 persistence contract

RT-109 adds one typed Repository port and `$wpdb` adapter for each phase-one
table. Create records and stored records are separate immutable DTOs, database
timestamps map to UTC `DateTimeImmutable`, unknown persisted enum values fail
with a mapping exception, and ciphertext remains an opaque byte string.
Encrypted email/message payloads, keyed lookup digests, OTP password hashes,
and access-token digests use distinct non-interchangeable value objects.
Hydration revalidates their storage shape; an approved cryptographic adapter,
not the value object itself, must create the encrypted or keyed value.

Repository methods are deliberately narrow:

- Batches insert, resolve by ID or code, and return a bounded newest-first
  summary projection that excludes `notes`.
- Tags insert, resolve by public Tag ID, and list by Batch or owner.
- Batch Exports append, resolve by Batch/version, and list by Batch.
- Auth Challenges insert, resolve by ID, and locate the most recent structural
  match; application code must still decide expiry, attempts, and consumption.
- Conversations insert and resolve by ID.
- Messages append and list by Conversation.
- Access Tokens insert and resolve by hash; lookup does not authenticate or
  exchange a Token.
- Events append and query by target or correlation. Correlation pagination
  orders by descending `event_id` through a dedicated cursor so it follows the
  existing Schema version 8 `correlation_id` index.

All lists use stable cursors with a default page size of `50` and a maximum of
`100`. Adapters validate logical parent references before insert, while unique
constraints remain the final protection against duplicate Batch codes, Tag
IDs, Batch export versions, and Token hashes. They provide no generic CRUD,
physical delete, ordinary update, state transition, or unbounded query.

Event metadata is limited to a flat scalar object of at most 4096 encoded
bytes. Keys require an event-specific allowlist, sensitive key names and full
email-shaped values are rejected, and the default policy allows no non-empty
metadata. Event actor/target/correlation values separately require an explicit
event identity allowlist; the default denies every event. A generic guard
rejects email, IP, digest/token-shaped, credential, device, session, and
location-like identifiers. Persisted Event identity and JSON are revalidated
on read.

`TransactionManager::transactional()` starts one database transaction, commits
only after the callback returns, and rolls back when the callback throws.
Nested transactions and implicit retries are rejected; a future application
service remains responsible for idempotency and retry decisions.

### 3.10 RT-201 Batch creation write path

RT-201 leaves Schema version `8` unchanged and creates no table or index.
`CreateBatch` writes one `returntag_batches` row and one append-only
`batch.created` Event in the same non-nested transaction. The server always
sets `generated_quantity=0`, `batch_status=draft`,
`activation_enabled=0`, the authenticated WordPress User ID, and explicit UTC
timestamps. A preflight code lookup improves the authorized operator error,
but the existing `batch_code_unique` database constraint remains the final
concurrency control.

Batch list queries use `batch_id DESC` keyset pagination with a maximum page
size of `100` and select only summary columns. Detail reads remain explicit
authorized operations. RT-201 provides no update or delete method and does not
generate Tag IDs.

### 3.11 RT-202 candidate Tag ID generation

RT-202 leaves Schema version `8` unchanged and performs no database operation.
The Domain `TagId` value object accepts only six uppercase characters from
`23456789ABCDEFGHJKLMNPQRSTUVWXYZ`. The Application generator requests six
uniform indexes across the full 32-character alphabet, and the production
Infrastructure source uses PHP `random_int()`.

The result is an in-memory candidate, not a reserved or generated database
record. RT-202 does not call `TagRepository`, inspect uniqueness, retry a
collision, insert a Tag, increment `generated_quantity`, transition a Batch,
append an Event, or schedule Action Scheduler work. The existing `tag_id`
primary key remains the final uniqueness constraint. RT-203 must implement
bounded collision retry around insertion without deleting, replacing, or
reusing any existing ID.

### 3.12 RT-203 bounded collision retry

RT-203 leaves Schema version `8` unchanged. `InsertGeneratedTag` generates one
candidate, constructs an unowned `unregistered` Tag record with server-controlled
defaults, and immediately attempts `TagRepository::insert()`. It does not issue
a uniqueness pre-query. The Tags primary key is the authority for uniqueness.

The wpdb gateway reads the numeric connection error code after a failed
application-supplied-key insert. Only MySQL/MariaDB error `1062` becomes
`PersistenceDuplicateKeyException`; the database message is discarded. The
Application service retries that exception only and makes at most ten total
insert attempts. Batch-snapshot, mapping, connection, and other persistence
errors fail immediately. Retry exhaustion inserts no replacement and never
updates, deletes, or reuses existing rows.

The operation intentionally does not open a transaction. RT-204 places each
insertion inside its own transaction with the Batch progress update. RT-203
does not update those values independently.

### 3.13 RT-204 resumable background generation

RT-204 leaves Schema version `8` unchanged. Starting a pristine disabled
`draft` Batch locks its row, verifies `generated_quantity` against the committed
Tag count, changes status to `generating`, and appends one
`batch_generation_started` Event in one transaction. Repeating the command for
a `generating` Batch schedules its current checkpoint without another start
Event. A `generated` Batch is an idempotent no-op.

Each Action Scheduler execution processes at most `100` Tags. Before work, it
locks and verifies the Batch and rejects a checkpoint ahead of committed
progress. Each Tag then uses a separate transaction:

```text
SELECT Batch FOR UPDATE
-> RT-203 insert-first Tag generation
-> conditional generated_quantity increment
-> final status and completion Event when target is reached
-> COMMIT
```

The conditional update requires the expected prior counter and `generating`
status. A failed update rolls back the Tag insert. The final Tag changes status
to `generated` and appends one metadata-free
`batch_generation_completed` Event in that same transaction. The worker also
checks that `COUNT(tags)` equals the materialized counter at chunk boundaries;
any mismatch fails closed for investigation.

Action arguments contain only numeric Batch ID, checkpoint, and retry attempt.
Exact duplicate actions collapse. Retries use fixed delays of `60`, `300`,
`900`, `3600`, and `21600` seconds. No migration, foreign key, trigger, new
Option, temporary ID reservation, or reusable ID pool is introduced.

### 3.14 RT-205 administrative progress projection

RT-205 leaves Schema version `8` unchanged and performs no write. The
administrative detail query reads the Batch primary key projection:

```text
batch_id
requested_quantity
generated_quantity
batch_status
activation_enabled
updated_at
```

It then reads at most three matching lifecycle rows from
`returntag_events`, constrained by `target_type=batch`, the numeric Batch target
ID, and the `batch_generation_started` and `batch_generation_completed` Event
types. The existing `target_type_target_id_created_at` index supports this
bounded query. More than one start or completion Event, an active Batch without
a start Event, or a generated Batch without a completion Event fails closed.

`remaining_quantity` and whole-number percentage are derived from committed
counters. `failed_quantity` remains zero because failed candidates are rolled
back and have no persisted Tag record. Queue health is operational state, not a
database failure count.

### 3.15 RT-206 Batch Tag inventory projection

RT-206 leaves Schema version `8` unchanged and performs no write. A Batch must
exist, must no longer be `draft` or `generating`, and must have
`generated_quantity = requested_quantity` before its manufacturing inventory
can be read.

The dedicated reader selects exactly:

```text
tag_id
tag_status
created_at
```

It constrains `batch_id`, applies `tag_id > cursor` after the first page, orders
by `tag_id ASC`, and reads one extra row to determine whether a next page
exists. Page size defaults to `50` and is limited to `100`. The reader neither
hydrates the complete Tag record nor returns owner, item, Lost Mode, scan, or
smart-network data.

The stable ordering is the approved source order for future export work, but
RT-206 creates no CSV, export version, checksum, file, or audit record. Query
plans are recorded in the Query Catalog without asserting optimizer-specific
costs. A new compound index, if capacity evidence requires one, must use a new
numbered Migration rather than modifying Migration `0002`.

### 3.16 RT-207 audited CSV export workflow

RT-207 leaves Schema version `8` unchanged and uses the existing
`returntag_batch_exports` append-only contract. It adds no table, column,
index, Foreign Key, trigger, stored CSV, or file path.

The deterministic source selects `tag_id`, `tag_type`, and `model_code` in
bounded `tag_id ASC` pages. A private temporary artifact is built before the
write transaction. The commit transaction then:

1. locks the parent Batch with `FOR UPDATE`;
2. revalidates Batch status, requested/generated quantity, and manufacturing
   snapshot;
3. verifies the stored Tag count equals the CSV data-row count;
4. reads the latest export version under the Batch lock;
5. requires re-export format, row count, and SHA-256 to match the latest
   record;
6. appends the next positive export version;
7. atomically changes the first export from `generated` to `exported`;
8. appends the corresponding business Event.

The parent Batch lock serializes version allocation, while
`batch_export_version_unique` remains the final duplicate protection. Repeated
exports intentionally retain the same Batch/checksum pair under different
versions, which is why `batch_file_checksum` remains non-unique.

CSV `row_count` excludes the header. `file_checksum` is the lowercase SHA-256
of the exact UTF-8, BOM-free, CRLF-delimited bytes delivered to the REST
stream. A failed integrity check, transaction, or state transition does not
advance Batch status or append an export record.

## 4. Forbidden relationships and fields

Tag and Batch storage must not contain or infer mappings to:

```text
WooCommerce order IDs
WooCommerce order item IDs
Amazon order IDs
shipment IDs
tracking numbers
logistics record IDs
Claim IDs or claim secrets
Apple or Google account IDs
Apple or Google device IDs
pairing state
latitude, longitude, or location history
```

The public six-character Tag ID is the activation ID. A second claim or
activation identifier must not be added.

## 5. Identifier integrity

- `tag_id` must have a database uniqueness constraint.
- Tag IDs are uppercase and exactly six characters after normalization.
- Candidate IDs are generated through the RT-202 cryptographically secure
  random source and RT-203 persists each candidate against the database primary
  key for a trusted production Batch snapshot.
- A duplicate-key collision is retried up to ten total attempts without a
  uniqueness pre-query and without modifying or deleting an existing ID.
- RT-204 commits the generated Tag and its Batch counter atomically; partial
  chunks resume from committed quantity and never regenerate committed rows.
- Generated, exported, voided, suspended, and retired IDs are never reused.
- Re-export reads the original immutable ID set and never regenerates it.

No rollback, uninstall, cleanup job, or repair operation may physically delete
an ID so it can return to the available pool.

## 6. State and concurrency

State transitions must occur through application services. Important mutations
must create audit events in the same reliable unit of work or through an
approved outbox-style mechanism.

Activation must use an atomic conditional update or an equivalent transaction.
A read followed by an unconditional write is not sufficient because concurrent
claim attempts must produce exactly one owner.

Queue handlers, migrations, imports, and background jobs must be idempotent and
safe to retry. Long-running work must expose progress and resume without
duplicating IDs, exports, email, or events.

## 7. Time and data representation

- Store timestamps in UTC.
- Use nullable timestamps to distinguish events that have not occurred.
- Format dates for users through WordPress locale and timezone APIs.
- Store canonical state strings exactly as documented in the PRD and
  `AGENTS.md`; do not introduce near-duplicate states.
- Keep Lost Mode separate from the Tag lifecycle state.

## 8. Security and privacy

- OTPs and access tokens are stored only as secure hashes.
- Finder email is encrypted at rest; equality lookup may use a keyed HMAC.
- Encryption keys are not stored in the same database.
- Private message content follows the approved encryption design.
- Ordinary logs and audit metadata must not contain plaintext OTPs, complete
  tokens, full private messages, or unnecessary full email addresses.
- SQL uses `$wpdb->prepare()` or a safe repository abstraction.
- Persistence failures expose fixed exception messages. The `$wpdb` gateway
  suppresses raw database error output during its operation and restores the
  caller's previous error-reporting state, so SQL and bound sensitive values
  are not copied into ordinary logs.

## 9. Migration policy

Every schema change requires a numbered, version-controlled migration. A
migration must:

1. Detect or safely tolerate completed work.
2. Support fresh installation.
3. Support upgrade from the immediately previous supported schema.
4. Be observable and safe to retry.
5. Avoid running on every normal request.
6. Use forward-compatible expand, migrate, and later contract phases.
7. Preserve a compatibility window for the previous stable application.

Production rollback must not depend on destructive `down()` migrations.
Dropping or renaming data used by the previous stable release cannot occur in
the same release that stops using it.

The RT-101 runtime validates a contiguous registry, acquires a MariaDB/MySQL
advisory lock derived from the current site ID and hashed active table prefix,
re-reads the stored version, then applies and verifies one pending Migration at
a time. Only a verified version is persisted. The lock is always released,
and a failed version remains safe to retry.

Migration execution is limited to single-site plugin activation,
`upgrader_process_complete` for TagCore, and an `admin_init` compensation check
requiring `activate_plugins`. Public requests do not run DDL. Milestone 1
rejects network-wide activation; multisite installations activate per site.

RT-102 advances Schema version `0` to `1`. A complete table is idempotent; a
safely repairable missing index can be restored by `dbDelta()` and reverified.
An incompatible engine, column, collation, or index contract fails without
advancing the stored version. The table is retained for diagnosis and a later
safe retry; no automatic drop, rebuild, or destructive down Migration occurs.

Before calling `dbDelta()` on an existing table, the Schema Inspector classifies
the table as exact, missing only expected indexes, or incompatible. Only a
missing table or missing expected indexes may reach `dbDelta()`; conflicting
columns, defaults, engines, collations, primary keys, extra indexes, or changed
index definitions fail before DDL can rewrite the existing structure.

RT-103 advances Schema version `1` to `2`; a fresh installation runs the
contiguous `0 -> 1 -> 2` chain. Before creating or verifying the tags table,
Migration `0002` independently verifies the complete batches contract. Missing
or incompatible predecessor schema fails closed without creating the tags
table or advancing version `1`. A complete tags table is idempotent, and a
safely repairable missing index can be restored by `dbDelta()` and reverified.
An incompatible tags engine, column, collation, primary key, or index leaves
the stored version at `1` for diagnosis and safe retry.

RT-104 advances Schema version `2` to `3`; a fresh installation runs
`0 -> 1 -> 2 -> 3`. Migration `0003` verifies the complete Tags predecessor,
which also verifies Batches. Missing or incompatible predecessor schema blocks
creation and leaves version `2`. A complete exports table is idempotent, a
missing expected index is repairable, and incompatible existing definitions
fail before DDL mutation. Routine rollback preserves the table and its audit
history.

RT-105 advances Schema version `3` to `4`; a fresh installation runs
`0 -> 1 -> 2 -> 3 -> 4`. Migration `0004` verifies the complete Batch Exports
predecessor chain before creating authentication challenge storage. Missing or
incompatible predecessors leave version `3`. A complete table is idempotent,
a missing expected index is repairable, and incompatible sensitive columns,
engine, collation, or index definitions fail before `dbDelta()` mutation.
Rollback preserves all four tables and the Schema option; version `0.1.0` code
does not read the challenge table.

RT-106 advances Schema version `4` through independently verified versions `5`
and `6`; a fresh installation runs `0 -> 1 -> 2 -> 3 -> 4 -> 5 -> 6`.
Migration `0005` verifies the complete authentication challenge predecessor
chain before creating conversations. Migration `0006` verifies conversations
before creating messages. Failure in `0005` leaves version `4`; failure in
`0006` leaves the verified conversations table and version `5`, so retry resumes
at Messages. Complete tables are idempotent, missing expected indexes are
repairable before the version advances, and incompatible columns, engines,
collations, or index definitions fail before `dbDelta()` mutation. Rollback
preserves all six tables and the Schema option; version `0.1.0` has no business
read or write path for the two new tables.

RT-107 advances Schema version `6` to `7`; a fresh installation runs
`0 -> 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 7`. Migration `0007` verifies the complete
Messages predecessor chain before creating access token storage. Missing or
incompatible predecessors leave version `6`. A complete table is idempotent,
a missing expected index is repairable, and incompatible hash storage, engine,
collation, column, or index definitions fail before `dbDelta()` mutation.
Rollback preserves all seven tables and the Schema option; version `0.1.0` has
no access token business read or write path.

RT-108 advances Schema version `7` to `8`; a fresh installation runs the
contiguous `0 -> 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 7 -> 8` chain. Migration `0008`
verifies the complete Access Tokens predecessor before creating Events.
Missing or incompatible predecessors leave version `7`. A complete table is
idempotent, missing expected indexes are repairable, and incompatible
metadata, identifier, engine, collation, column, or index definitions fail
before `dbDelta()` mutation. Rollback preserves all eight tables, business
events, and the Schema option; version `0.1.0` has no event business read or
write path.

RT-109 changes Schema version `8` to `8`: it adds no Migration, table, column,
index, Option, or DDL trigger. Fresh installs and upgrades continue to use the
existing `0001` through `0008` chain. Code rollback removes only the unused
Repository classes and preserves all eight tables, the Schema option, and
stored data. The previous `0.1.0` baseline remains database-compatible because
the plugin bootstrap does not register or call the new adapters. Schema
inspection now distinguishes a successful no-row result from query failure or
malformed metadata; inspection failure stops migration before `dbDelta()` and
does not advance the Schema version.

RT-110 also changes Schema version `8` to `8`. Production-composition tests
cover fresh activation from no Option or tables, upgrade from verified version
`4` while preserving predecessor data, reconciliation of complete tables when
the Option is missing, and non-destructive uninstall. MariaDB 10.11 and MySQL
8.0 run the integration suite in independent CI jobs; the normal local
acceptance environment remains WordPress 7.0.2, PHP 8.4, WooCommerce 10.9.4,
and the existing wp-env MariaDB image.

Repository query shapes, cursors, projections, and candidate indexes are
recorded in `docs/QUERY_CATALOG.md`. EXPLAIN tests require the expected index to
remain available in `possible_keys` but do not freeze optimizer-specific plans
or cost estimates.

RT-208 changes Schema version `8` to `8`: no Migration, table, column, index,
Option, or DDL trigger is added. Lifecycle commands lock one Batch by primary
key, aggregate its Tags by `(batch_id, tag_status)`, and read the latest export
through `batch_export_version_unique`. A conditional primary-key update changes
only `batch_status`, `activation_enabled`, and `updated_at`.

The update and append-only Event commit in one transaction. Suspend and Void
never update or delete Tag rows. Rollback preserves every Batch state, Tag ID,
Batch Export record, and Event; previous code does not perform activation.

## 10. Retention and uninstall

Routine code rollback and plugin uninstall do not delete business data. In
particular, retain:

- generated and exported Tag IDs;
- batch export history and checksums;
- completed owner claims and ownership events;
- audit events;
- accepted conversation messages for their approved retention period.

Any future personal-data erasure or retention job must distinguish privacy
obligations from immutable anti-reuse records and must have explicit product,
security, and legal approval.

## 11. Database change checklist

A database pull request must report schema versions before and after, fresh
install results, previous-version upgrade results, retry behavior, locking or
batch impact, previous-release compatibility, retention impact, and the
operational rollback or feature-disable plan.

## 12. RT-209 Tag search projection

RT-209 leaves Schema version `8` unchanged and performs no write. Exact Tag ID
mode uses the Tags primary key. Exact Batch Code mode joins the unique Batch
Code to Tags, optionally constrains canonical `tag_status`, orders by
`tag_id ASC`, and uses strict keyset continuation with a maximum page size of
100.

The projection selects only `tag_id`, `batch_id`, `batch_code`,
`batch_status`, `activation_enabled`, `tag_type`, `model_code`, `tag_status`,
`lost_mode`, `activated_at`, `created_at`, and `updated_at`. A presentation-only
activation availability value is derived after hydration; it is not a column,
status migration, or stored duplicate of these facts.

It does not hydrate or return owner, private item, Lost Mode message, scan,
order, credential, device, or location data. Suspended and voided Batch rows
remain searchable, and no Tag row is rewritten, deleted, or made reusable.
RT-210 owns capacity evidence and any future numbered Migration for an
additional index.

## 13. RT-210 capacity acceptance

RT-210 changes Schema version `8` to `8`: no Migration, table, column, index,
Option, or DDL trigger is added. The accepted test profile retains ten Batches
of `100,000` synthetic Tags each and exercises the inventory, exact Tag,
Batch-search, progress, lifecycle-count, and deterministic export read shapes.

Representative inventory and Batch-search statements continue to expose an
indexed candidate through `EXPLAIN`. The existing primary key and
`batch_id_status` index met the budgets recorded in `docs/PERFORMANCE.md`;
therefore a `(batch_id, tag_id)` index is not justified at the approved
capacity.

Fresh installation and upgrade behavior remain the contiguous `0001` through
`0008` Migration chain. Retry, idempotency, lock, uninstall, and rollback
behavior are unchanged. Code rollback to `0.2.0` retains all Schema version 8
tables and data.
