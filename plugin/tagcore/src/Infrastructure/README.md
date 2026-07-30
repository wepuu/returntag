# Infrastructure

WordPress, `$wpdb`, migration, Action Scheduler, transactional email,
cryptography, logging, metrics, clock, and random-source adapters belong here.
RT-007 implements `WordPressOptionFeatureFlagReader` as the first WordPress
adapter. It is read-only, fail-closed, and adds no cache beyond the WordPress
Options API. RT-008 adds `SensitiveLogContextSanitizer` and the default-disabled
`WordPressErrorLogLogger`; neither is registered by the plugin bootstrap and no
product workflow emits logs yet.

RT-101 adds the `Migration` contracts, ordered registry, site-scoped version
store, MariaDB/MySQL advisory lock, retry-safe runner, schema readiness state,
and WordPress administrative lifecycle adapter under `Migration/`. RT-102
registers version `0001`, a trusted dynamic table-name mapping, an
`information_schema` postcondition verifier, and the InnoDB batches table. It
does not add a repository or batch business behavior. RT-103 registers version
`0002` and the InnoDB tags table, with an explicit predecessor-contract check
before creation or verification. It adds no Repository, ID generation,
activation, ownership, Lost Mode, or finder behavior. RT-104 registers version
`0003` and the InnoDB batch export audit table. It adds no CSV generation,
checksum calculation, export version allocator, file storage, Repository, or
Batch state transition. RT-105 registers version `0004` and the InnoDB
authentication challenges table. It defines only opaque ciphertext, keyed
lookup, code-hash, counter, and lifecycle-time storage; it adds no encryption,
OTP, Finder verification, login, rate-limit, cleanup, or Repository behavior.
RT-106 registers versions `0005` and `0006` for the InnoDB conversations and
messages tables. It defines opaque finder-email and message ciphertext,
case-sensitive lookup/status metadata, UTC lifecycle fields, and provider
delivery projection only. It adds no encryption, Finder relay, email sending,
token exchange, webhook, cleanup, state transition, or Repository behavior.
RT-107 registers version `0007` for hash-only access token lifecycle storage.
It adds a unique digest, purpose, actor role, Conversation reference, and UTC
expiry/exchange/revocation fields without adding generation, hashing, secure
links, GET/POST exchange, sessions, revocation workflows, cleanup, or a
Repository.
RT-108 registers version `0008` for privacy-safe business event storage. It
adds actor, target, result, correlation, optional metadata, and UTC creation
fields plus stable audit-query indexes without emitting events, bridging the
operational logger, enforcing metadata policy, or adding a Repository, admin
query, export, retention job, update, or delete path.

RT-109 adds `$wpdb` adapters for the eight tables and a non-nesting transaction
manager under `Persistence/`. The adapters use the trusted Migration table-name
mapping, parameterized values, strict stored-row mapping, bounded stable
cursors, logical-reference checks, fixed persistence errors, and per-operation
suppression of raw database error output. The Event adapter is append/query
only, requires explicit identity and metadata policies, and uses a dedicated
descending `event_id` correlation cursor that matches the existing index.
Sensitive stored values are mapped through distinct types and revalidated on
read. Migration inspection distinguishes a successful absent-table result from
query failure or malformed metadata and stops before DDL on failure. These
adapters are not registered by the plugin bootstrap and implement no product
workflow.

RT-202 adds `PhpSecureRandomIntegerSource` under `Random/`. It uses PHP
`random_int()` and has no WordPress, database, queue, HTTP, or logging side
effect. It is not composed into the RT-201 administration workflow.

RT-203 hardens `WpdbGateway::insert_without_id()` to classify only numeric
MySQL/MariaDB error `1062` as a duplicate key. It reads the fixed connection
error stack while wpdb output remains suppressed and never exposes the
database message, SQL, or rejected value. All other failures retain the generic
privacy-safe persistence exception.

RT-204 adds a locking Batch-generation Repository, Action Scheduler adapter,
bounded retry handler, and worker composition root. Each worker action commits
at most 100 Tags, with one short transaction per Tag so insertion and
conditional Batch progress succeed or roll back together. Queue arguments are
integer-only, duplicate pending actions are unique, and retry delays are
bounded. Production must drive Action Scheduler with real Cron or WP-CLI and
monitor the `returntag-tag-generation` group.

RT-205 adds a bounded `$wpdb` projection for Batch counters and the two
metadata-free lifecycle Events, plus an Action Scheduler monitor that reports
only pending/running availability. Database failures and provider internals
remain behind fixed Application contracts; no queue argument, Tag ID, SQL, or
raw failure is returned to the administrator.

RT-206 adds a dedicated `$wpdb` Batch Tag inventory reader. It names only
`tag_id`, `tag_status`, and `created_at`, constrains the trusted dynamic Batch
ID, and uses bounded `tag_id ASC` keyset pagination. It does not use
`SELECT *`, hydrate private Tag columns, add an index or Migration, generate a
file, or write an export audit record.

RT-303 adds one exact, parameterized public Tag state reader. It uses the Tag
and Batch primary keys, names only the state fields required by Application,
and preserves a missing Batch as a fail-closed integrity condition. It never
selects private item, email, message, order, device, pairing, or location data.

No product provider adapter is implemented.
