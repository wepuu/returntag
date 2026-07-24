# ReturnTag Architecture

**Status:** Milestone 1 data foundation complete at version 0.2.0 and Schema version 8; product workflows pending

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
batches, RT-103 tags, RT-104 batch export audit, RT-105 authentication
challenge, and RT-106 conversation and message schemas under
`Infrastructure/Migration`. RT-107 adds the hash-only access token schema and
RT-108 adds the privacy-safe business event schema under
`Infrastructure/Migration`. RT-109 adds canonical persistence enums under
`Domain`, typed records, sensitive-value objects, Event policies, and
Repository ports under `Application/Persistence`, and `$wpdb` adapters under
`Infrastructure/Persistence`. Future implementation must preserve this mapping
and dependency direction.

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
`information_schema`. RT-103 registers version `0002` for the tags table and
fails closed if its required version `0001` batches contract has drifted.
Existing tables are classified before `dbDelta()` runs; only table creation or
missing-index repair is allowed, while incompatible definitions fail before
DDL mutation. RT-109 hardens this boundary so a failed or malformed
`information_schema` query raises a fixed migration error; only a successful
query returning no table row is classified as absent.

RT-104 registers version `0003` for batch export audit metadata that later
Repository and Application contracts must treat as append-only. The database
schema itself does not use triggers to prevent direct updates or deletes.
RT-105 registers version `0004` for one-time authentication challenge state.
It provides only privacy-oriented storage for opaque email ciphertext, keyed
lookups, code hashes, counters, and UTC lifecycle times. RT-106 registers
version `0005` for finder/owner conversation state and version `0006` for
encrypted message and delivery-state storage. The two versions advance
independently so a failed Messages migration leaves a verified, retryable
version `5`. Neither table introduces a physical foreign key or business write
path. Public requests never invoke this runtime. RT-107 registers version
`0007` for
hash-only secure-link and conversation access token state. It verifies the
complete Messages predecessor before creation and adds no generator, hashing
adapter, route, token exchange, session, revocation workflow, or cleanup job.
RT-108 registers version `0008` for durable business event storage. It verifies
the complete Access Tokens predecessor, adds actor/target/result/correlation
and optional metadata fields, and provides stable query indexes without
emitting events or adding a Repository. The Migration itself does not enforce
append-only writes through triggers.

RT-109 implements narrow persistence contracts for all eight tables. Domain
backed enums freeze the canonical stored vocabulary without adding state
machines. Application owns immutable create/stored records, bounded page and
cursor types, Repository ports, Event identity and metadata policies, distinct
sensitive-value objects, and the transaction port. Infrastructure maps those
contracts to trusted dynamic table names and parameterized `$wpdb` operations.
It verifies logical references before inserts because the schema intentionally
has no physical foreign keys.

Repositories expose only ticket-approved insert/append, lookup, and bounded
query methods. They expose no generic array CRUD, update, delete, state
transition, or unbounded list operation. The Event Repository is append/query
only. Default policies deny every Event identity and every non-empty metadata
object; future tickets must supply event-specific identity and scalar-key
allowlists before writing events. A generic identifier guard additionally
rejects email, IP, digest/token-shaped, credential, device, session, and
location-like values. Correlation queries use a dedicated descending
`event_id` cursor to follow the existing `correlation_id` index without a
Schema change.

Encrypted payload, lookup digest, OTP hash, and access-token digest types are
not interchangeable. Stored values are revalidated during hydration, including
recognition of OTP password-hash formats. These types cannot prove that
encryption or keyed hashing occurred; future approved cryptographic adapters
must be their only source in product workflows. Transaction callbacks reject
nesting, commit on success, roll back on exceptions, and do not automatically
retry side effects.

RT-110 adds no runtime composition or product behavior. It closes the data
foundation with production-registry installation and upgrade tests,
non-destructive uninstall verification, public-request lifecycle isolation,
query-to-index documentation, and database-engine compatibility checks.
Milestone 1 remains a modular persistence foundation rather than a usable
activation, recovery, email, or commerce product.

External side effects must occur after durable state changes and be retry-safe.
Transactional email must be queued rather than sent synchronously from a public
request.

## 9. Public contracts

The engineering foundation introduces no REST route, product hook, emitted
business event, or business service; RT-102 through RT-108 add only the current
product tables and RT-109 adds internal persistence contracts and adapters.
RT-007 introduces only the four approved global option names and a read
contract; it neither creates nor writes those options. RT-008 adds
engineering-only logging contracts and a disabled adapter without emitting
product events. RT-101 adds the Migration engineering contracts and
administrative lifecycle hooks. RT-102 adds only the
version `0001` batches table contract. RT-103 adds only the version `0002` tags
table contract, without a repository, state transition, ownership operation,
activation, Tag ID generation, batch job, or export behavior. The RT-104
contract stores only export audit metadata and does not generate a CSV,
calculate a checksum, allocate an export version, or change Batch state. The
RT-105 contract stores authentication challenge state but does not generate,
send, verify, resend, consume, or rate-limit an OTP and does not authenticate a
user. The RT-106 contracts store opaque finder email and message ciphertext,
lookup metadata, lifecycle state, and provider delivery identifiers without
creating, verifying, sending, replying to, closing, reporting, or expiring a
conversation. The RT-107 contract stores only a unique digest, purpose, actor
role, conversation reference, and UTC expiry/exchange/revocation times. It does
not generate, deliver, consume, exchange, revoke, or authenticate with a
Token. The RT-108 contract stores only privacy-safe event classification,
opaque internal actor/target identifiers, results, correlation, optional
metadata text, and UTC creation time. It does not emit, update, delete, display,
export, or retain an event through a product workflow. RT-109 makes the eight
table adapters available to future composition roots but does not instantiate
them from the plugin bootstrap or expose them through a public API. RT-201 is
the first product-facing exception to the earlier foundation-only contract. It
registers the capability-protected `tagcore/v1/batches` collection and item
routes plus the WordPress-native TagCore Batch admin page. The REST controller
validates and normalizes input, invokes Application services, maps privacy-safe
responses, and applies no-store headers. The `CreateBatch` service owns fixed
draft defaults and atomically persists the Batch with a `batch.created` Event.
The Composer package, PSR-4 namespace, plugin headers, build entry points, and
quality commands are also engineering contracts. Product names documented in
the PRD remain future contracts and must not be implemented or changed outside
their assigned tickets.

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
operations configuration. RT-101 supplies schema orchestration; RT-102 through
RT-108 supply the batches, tags, batch export audit, authentication challenge,
conversation, message, access token, and business event tables only.
Transactional email, encryption, rate limiting, metrics, and repositories
still require their assigned tickets.

RT-201 composes only the Batch and Event repositories required by its
administrative workflow. Its React interface uses WordPress-provided packages,
the dedicated `manage_returntag_batches` capability, REST cookie
authentication with the WordPress REST nonce, and plugin-scoped CSS. It does
not compose Action Scheduler or add an ID generator, export, Batch transition,
email, public route, or WooCommerce integration.

### Runtime dependency rationale

| Package | Purpose and boundary | License and maintenance |
|---|---|---|
| `psr/log` | Defines the logging interface extended by `ApplicationLogger`; RT-008 supplies a default-disabled WordPress adapter without selecting production transport or retention. | MIT; interface-only dependency included in the release artifact. |
| `woocommerce/action-scheduler` | Supplies retryable WordPress queue infrastructure. TagCore loads the library for version negotiation but does not schedule product jobs in this foundation. | GPL-3.0-or-later, compatible with TagCore's GPL-2.0-or-later declaration; its distributed license files must remain in the artifact. |

Composer locks both packages and Dependabot proposes updates. Runtime updates
must pass the PHP, WordPress, WooCommerce, queue-idempotency, and release
artifact checks before adoption. Node packages are build and test inputs;
`node_modules` is never included in the TagCore ZIP.
