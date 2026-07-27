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
