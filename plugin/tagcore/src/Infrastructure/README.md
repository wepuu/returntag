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
No product provider adapter is implemented.
