# ReturnTag Database Baseline

**Status:** RT-106 conversations and messages table Migrations implemented

**Schema created through RT-106:** `returntag_batches`, `returntag_tags`, `returntag_batch_exports`, `returntag_auth_challenges`, `returntag_conversations`, `returntag_messages`; current target version `6`

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
encrypted messages.

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

The batches, tags, batch exports, authentication challenges, conversations, and
messages table contracts are implemented by RT-102 through RT-106. The
remaining table definitions and Migrations belong to RT-107 and RT-108 and must
remain consistent with the PRD and ADR 0003.

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
- IDs are generated by production batch using a cryptographically secure random
  source in a later ticket.
- A collision is retried without modifying or deleting an existing ID.
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
