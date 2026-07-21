# Infrastructure

WordPress, `$wpdb`, migration, Action Scheduler, transactional email,
cryptography, logging, metrics, clock, and random-source adapters belong here.
RT-007 implements `WordPressOptionFeatureFlagReader` as the first WordPress
adapter. It is read-only, fail-closed, and adds no cache beyond the WordPress
Options API. RT-008 adds `SensitiveLogContextSanitizer` and the default-disabled
`WordPressErrorLogLogger`; neither is registered by the plugin bootstrap and no
product workflow emits logs yet. No product provider adapter is implemented.
