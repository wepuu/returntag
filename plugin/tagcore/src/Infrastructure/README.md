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
does not add a repository or batch business behavior. No product provider
adapter is implemented.
