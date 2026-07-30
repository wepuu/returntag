# Application

Use-case orchestration and the interfaces required by those use cases belong
here. Application code coordinates Domain behavior without depending on
concrete WordPress, database, queue, email-provider, or logging adapters.

RT-007 defines the `FeatureFlag` option-name contract and the read-only
`FeatureFlagReader` interface in this layer. It does not define a writer or a
product use case.

RT-008 defines `ApplicationLogger`, a project-owned PSR-3 marker port, and
`LogContextSanitizer`. Application code must use these abstractions and must
not call WordPress encoding or PHP error-log functions directly.

RT-109 adds typed immutable persistence records, bounded cursor/page types,
Repository ports for the eight Schema version 8 tables, distinct encrypted
payload/digest/hash value objects, default-deny Event identity and metadata
policies, and a transaction port. Sensitive value types prevent accidental
cross-use but do not perform or prove encryption or hashing; approved
cryptographic adapters remain responsible for producing them. The ports expose
no generic CRUD, delete, state transition, authentication, token exchange,
activation, export, relay, or WooCommerce behavior.

RT-202 adds the `TagIdGenerator` and `RandomIntegerSource` ports plus the pure
alphabet-mapping generator. It returns one candidate only and deliberately has
no Repository, transaction, queue, collision retry, Batch transition, or
logging dependency.

RT-203 adds `InsertGeneratedTag`, which combines `TagIdGenerator` with the
narrow `TagRepository` port. It retries only
`PersistenceDuplicateKeyException`, makes at most ten total attempts, returns
only the stored Tag and aggregate collision count, and fails closed for every
other error. It does not own a transaction, queue, progress update, Batch state,
counter, Event, log, route, or UI.

RT-205 adds `GetBatchGenerationProgress`, a narrow progress-reader port, and a
provider-neutral queue-monitor port. The query validates audited lifecycle
timestamps, derives committed percentage and remaining work, maps missing
active work to `needs_attention`, and keeps the persisted failed-ID count at
zero because RT-204 never stores a failed Tag. It performs no write, generation,
queue scheduling, or provider inspection directly.

RT-204 adds `StartBatchGeneration` and `GenerateBatchChunk` plus narrow Batch
progress and scheduler ports. Application owns state eligibility, checkpoint
and counter integrity, the 100-Tag action bound, aggregate lifecycle Event
requests, and resume decisions. It does not call Action Scheduler or WordPress
globals, expose generated IDs, perform DDL, export CSV, or add a visible UI.

RT-206 adds `ListBatchTagInventory` and a dedicated narrow reader contract.
The use case rejects missing, draft, generating, or counter-incomplete Batches
before reading a page. Items contain only a typed Tag ID, canonical Tag status,
and UTC generation time in deterministic Tag ID order. It performs no write,
export, search, state transition, authorization, or WordPress operation.

RT-303 adds the public Tag state reader port, immutable state projection,
privacy-minimized page model, pure page policy, and resolver use case. Owner
identity remains internal to the policy; Finder-only fields enter the page
model only for the approved Finder state. The use case performs no activation,
message, notification, write, or WordPress operation.
