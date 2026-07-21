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
