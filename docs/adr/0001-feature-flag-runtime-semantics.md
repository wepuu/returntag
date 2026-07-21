# ADR 0001: Feature Flag Runtime Semantics

**Status:** Accepted

**Date:** 2026-07-20

**Ticket:** RT-007

## Context

The PRD requires four global incident controls but does not prescribe their
initial stored values, WordPress scope, cache behavior, or precedence against
environment configuration. These semantics affect incident containment and
must remain stable before product workflows begin consuming the controls.

The Events schema, audited administrator controls, and product consumers belong
to later tickets. RT-007 therefore needs a narrow read boundary that does not
silently introduce unaudited mutations or product behavior.

## Decision

The canonical global controls are exactly:

```text
returntag_global_activation_enabled
returntag_finder_contact_enabled
returntag_email_dispatch_enabled
returntag_woocommerce_account_enabled
```

They are site-scoped WordPress options read through `get_option()`. RT-007 does
not use network options, environment overrides, a second storage source, or a
plugin-owned static cache.

Missing values fail closed. Boolean `true`, integer `1`, and string `"1"` are
enabled; every other value is disabled. Plugin bootstrap, activation,
deactivation, and uninstall do not create, enable, reset, or delete the options.

Application depends on a read-only `FeatureFlagReader` interface.
Infrastructure supplies the WordPress option adapter. No writer, hook, REST
route, admin UI, or product workflow is part of RT-007.

An approved operator may set an option to `0` through WP-CLI for incident
containment before an audited administration use case exists. The operator must
record that external action. Future application consumers must still enforce
authorization, validation, idempotency, and domain policy; a flag is never a
substitute for those controls.

## Consequences

- A fresh or partially configured site cannot accidentally enable side effects.
- WordPress handles option cache invalidation; emergency changes do not wait for
  a plugin-owned cache lifetime.
- Multisite networks configure each site independently unless a later approved
  ADR changes the scope.
- RT-007 has no schema migration and no automatic database write.
- The controls have no behavioral effect until their assigned workflows consume
  the reader in later tickets.
