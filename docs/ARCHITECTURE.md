# ReturnTag Architecture

**Status:** Engineering foundation and RT-102 Schema version 1 implemented; product workflows pending

**Plugin:** TagCore (`plugin/tagcore`)

**Namespace root:** `ReturnTag\TagCore`

## 1. Purpose

This document defines the system boundaries and dependency rules for ReturnTag.
The repository implements the layer directories, Composer boundary, build
tooling, and quality gates described here. Product use cases remain assigned to
later tickets.

The product requirements in `docs/PRD.md` remain the source of truth for
product behavior. Architecture must support those requirements without
weakening frozen business, security, or privacy rules.

## 2. System boundary

All ReturnTag product functionality belongs in the independent WordPress plugin
under `plugin/tagcore`. A theme may style or host presentation integration, but
it must not own ReturnTag business rules, state transitions, persistence, email
orchestration, or WooCommerce workflows.

TagCore integrates with WordPress and, in later milestones, WooCommerce. These
platforms are external dependencies and must be accessed through public APIs
and adapters. WooCommerce order storage must never be queried directly.

Smart finding networks are outside the TagCore system boundary. TagCore may
render approved static setup guidance and store an owner acknowledgement, but
it does not connect to Apple or Google accounts, devices, pairing, or location
services.

## 3. Logical layers

### Domain

Contains entities, value objects, policies, validation, state rules, and frozen
business invariants. Domain code is framework-independent and must not directly
call WordPress globals, `$wpdb`, `wp_mail()`, option APIs, HTTP globals, or
WooCommerce objects.

### Application

Coordinates use cases through explicit interfaces. It owns orchestration,
authorization decisions, idempotency, state transitions, audit-event requests,
and transaction boundaries. It depends on Domain and on abstractions, not on
concrete WordPress or provider implementations.

### Infrastructure

Implements adapters for WordPress, `$wpdb`, queues, scheduled work, email
providers, cryptography, clocks, random sources, logging, and metrics. It maps
external data into application and domain contracts without moving provider
behavior into those layers.

### Admin

Adapts WordPress administration requests and responses. Admin controllers must
perform capability and nonce checks, validate input, invoke application use
cases, and escape output. They must not contain SQL or business state machines.

### PublicSite

Owns public scan, activation, finder, and secure-link HTTP presentation. It must
apply rate limits, privacy-safe errors, validation, output escaping, no-cache
controls, and CSRF decisions before or around application use cases.

### Account

Adapts authenticated owner views and actions. It must enforce server-side
ownership for every mutation and keep private fields separate from fields that
may be displayed to a finder.

### WooCommerce

Contains WooCommerce-specific hooks and adapters. It may use WooCommerce public
APIs to locate or create a WordPress user and enqueue activation guidance. It
must not generate, allocate, claim, release, suspend, transfer, or map Tag IDs.

## 4. Dependency direction

Dependencies point inward:

```text
Admin / PublicSite / Account / WooCommerce
                    |
                    v
               Application
                    |
                    v
                  Domain

Infrastructure implements interfaces required by Application and Domain.
WordPress, WooCommerce, databases, queues, and providers remain at the edge.
```

Domain must remain independently unit-testable. Application use cases must be
testable with in-memory or fake implementations. Infrastructure integration is
covered separately by integration tests.

## 5. Namespace and directory mapping

Composer maps the `ReturnTag\TagCore\` namespace to these paths below the
plugin directory:

```text
plugin/tagcore/src/Domain/         ReturnTag\TagCore\Domain
plugin/tagcore/src/Application/    ReturnTag\TagCore\Application
plugin/tagcore/src/Infrastructure/ ReturnTag\TagCore\Infrastructure
plugin/tagcore/src/Admin/          ReturnTag\TagCore\Admin
plugin/tagcore/src/PublicSite/     ReturnTag\TagCore\PublicSite
plugin/tagcore/src/Account/        ReturnTag\TagCore\Account
plugin/tagcore/src/WooCommerce/    ReturnTag\TagCore\WooCommerce
```

The directories contain layer guidance, the RT-007 feature-flag contracts and
adapter, the RT-008 logging boundary, and the Migration runtime plus RT-102
batches schema under `Infrastructure/Migration`. Future implementation must
preserve this mapping and dependency direction.

## 6. Bootstrap boundary

`plugin/tagcore/tagcore.php` remains small. It defines stable
`RETURNTAG_TAGCORE_` constants, loads Composer when available, makes the bundled
Action Scheduler runtime available early enough for version negotiation, and
delegates Migration lifecycle registration to its Infrastructure composition
root. It does not contain schema SQL or register product hooks, routes,
services, or workflows.

RT-101 lifecycle hooks are limited to activation, completed plugin upgrade, and
authorized admin compensation. Domain workflows, database queries, email
composition, request routing logic, and rendered pages stay outside the
bootstrap file.

## 7. Data and state rules

- Supported tag types are exactly `sticker`, `classic_tag`, and `smart_tag`.
- A physical tag has one public six-character Tag ID, also used for activation.
- There is no Claim ID, claim secret, or secondary activation credential.
- Generated or exported Tag IDs are immutable and never reusable.
- Batch, Tag, conversation, and ownership changes go through application
  services and create audit events where required.
- Activation requires an atomic conditional database write.
- Order, shipment, logistics, and tracking identifiers are not part of the Tag
  or Batch domain.

## 8. Cross-cutting concerns

Authentication, authorization, encryption, rate limiting, queues, logging,
metrics, and feature flags are exposed to Application through interfaces and
implemented at the infrastructure edge. Controllers and hooks may enforce
request-level controls but may not duplicate domain policy.

RT-007 implements the read-only feature flag boundary. It does not register a
controller, hook, writer, environment override, or product consumer. WordPress
option caching is the only cache used by the adapter.

RT-008 implements an Application-owned PSR-3 marker port and sanitizer
contract. Infrastructure provides a bounded sensitive-data sanitizer and a
default-disabled WordPress error-log adapter. Operational diagnostic records
remain separate from durable business audit events. No logger is registered or
used by a product workflow in RT-008.

RT-101 keeps schema orchestration inside Infrastructure. `MigrationRegistry`
validates the ordered sequence, `MigrationRunner` owns locking and version
progress, and `SchemaState` exposes readiness for future fail-closed startup.
RT-102 registers version `0001` through the Infrastructure composition root,
uses a trusted table-name mapping, and verifies the batches table through
`information_schema`. RT-103 through RT-108 will add the remaining table
Migrations. Public requests never invoke this runtime.

External side effects must occur after durable state changes and be retry-safe.
Transactional email must be queued rather than sent synchronously from a public
request.

## 9. Public contracts

The engineering foundation introduces no REST route, product hook, event, or
business service; RT-102 adds the only current product table. RT-007 introduces
only the four approved global option names and a read contract; it neither
creates nor writes those options. RT-008 adds engineering-only logging
contracts and a disabled adapter without emitting product events. RT-101 adds the Migration
engineering contracts and administrative lifecycle hooks. RT-102 adds only the
version `0001` batches table contract, without a repository, state transition,
batch job, ID generation, or export behavior. The Composer package, PSR-4
namespace, plugin headers, build entry points, and quality commands are also
engineering contracts. Product names documented in the PRD remain future
contracts and must not be implemented or changed outside their assigned
tickets.

New architectural decisions that alter a frozen requirement or introduce a
long-lived tradeoff require an approved ADR under `docs/adr` and any necessary
PRD update before implementation.

## 10. Technology baseline

- Minimum runtime: PHP 8.3 and WordPress 6.9.
- Intended production runtime: PHP 8.4 and the latest patched WordPress and
  WooCommerce releases approved through compatibility testing.
- Compatibility matrix: pinned WordPress 7.0.2 and security-backported 6.9.5
  releases against pinned current and previous-major WooCommerce releases,
  with HPOS enabled in isolated test databases. Previous-major WooCommerce is
  not approved for production use.
- Persistence: custom tables through `$wpdb` repositories and numbered
  migrations; no ORM and no schema changes on normal requests.
- Background work: Action Scheduler behind application-facing abstractions,
  with a real Cron or WP-CLI runner in production.
- Admin UI: TypeScript and React through WordPress-provided packages, built by
  `@wordpress/scripts`; WordPress React is externalized.
- Public and account UI: PHP server rendering with semantic HTML and optional
  Interactivity API Script Modules for progressive enhancement.
- Styling: separate plugin-scoped admin and public CSS roots, without Tailwind
  or a global CSS reset.
- Testing: pure PHPUnit unit tests, WordPress integration tests, Jest-based
  TypeScript tests, and Playwright browser projects.

RT-008 supplies the structured operational logging interface and initial
WordPress adapter, but leaves it disabled pending explicit composition and
operations configuration. RT-101 supplies schema orchestration, and RT-102
supplies the batches table only. Transactional email, encryption, rate
limiting, metrics, the remaining numbered schema changes, and repositories
still require their assigned tickets.

### Runtime dependency rationale

| Package | Purpose and boundary | License and maintenance |
|---|---|---|
| `psr/log` | Defines the logging interface extended by `ApplicationLogger`; RT-008 supplies a default-disabled WordPress adapter without selecting production transport or retention. | MIT; interface-only dependency included in the release artifact. |
| `woocommerce/action-scheduler` | Supplies retryable WordPress queue infrastructure. TagCore loads the library for version negotiation but does not schedule product jobs in this foundation. | GPL-3.0-or-later, compatible with TagCore's GPL-2.0-or-later declaration; its distributed license files must remain in the artifact. |

Composer locks both packages and Dependabot proposes updates. Runtime updates
must pass the PHP, WordPress, WooCommerce, queue-idempotency, and release
artifact checks before adoption. Node packages are build and test inputs;
`node_modules` is never included in the TagCore ZIP.
