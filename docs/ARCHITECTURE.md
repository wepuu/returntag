# ReturnTag Architecture

**Status:** Milestone 2 complete at version 0.3.0 and Schema version 8

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
delegates Migration, Batch worker, and administration registration to
composition roots. It contains no schema SQL, request handling, queue logic, or
business workflow.

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

The original engineering foundation introduced no product route or workflow;
RT-102 through RT-108 add the current product tables and RT-109 adds internal
persistence contracts and adapters.
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
RT-204 adds the POST generation command and internal
`returntag_generate_batch_chunk` Action Scheduler hook. These are internal
product contracts: the command accepts only a route Batch ID, and the hook
accepts only numeric Batch ID, checkpoint, and retry-attempt arguments. The
worker emits only aggregate lifecycle Events and never returns Tag IDs.
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
not add an ID generator, export, email, public route, or WooCommerce
integration.

RT-202 adds one inward-facing Tag ID generation boundary without changing the
RT-201 interface. Domain owns the exact alphabet, length, and strict canonical
value object. Application owns the generator and inclusive random-integer
contracts plus the deterministic alphabet-mapping algorithm. Infrastructure
implements the production random source with PHP `random_int()`. The generator
returns one candidate and has no Repository, transaction, queue, WordPress,
HTTP, logging, or Batch-state dependency.

RT-203 adds the narrow Application orchestration boundary around
`TagIdGenerator` and `TagRepository`. It performs insert-first allocation and
retries only the explicit `PersistenceDuplicateKeyException`, up to ten total
candidates. Infrastructure classifies only MySQL/MariaDB error `1062` as that
exception and discards the database message so SQL and candidate values do not
cross the persistence boundary. Snapshot, mapping, connection, and all other
persistence failures stop immediately. The service owns no transaction.

RT-204 supplies the surrounding orchestration. Application owns start/resume,
100-Tag chunk limits, checkpoint validation, Batch transition requests, audit
Event requests, and the provider-neutral scheduler port. Infrastructure owns
the `$wpdb` row locks and conditional progress updates, short per-Tag
transactions, Action Scheduler adapter, delayed retry handler, and worker
composition root. Admin adds one capability-protected POST command and returns
only aggregate status. The React page is unchanged; RT-205 owns confirmation
and progress presentation.

The start transaction changes a pristine disabled `draft` Batch to
`generating` and appends one `batch_generation_started` Event. Every worker
transaction locks that Batch, runs RT-203 insertion, then conditionally advances
the materialized counter. The final transaction also sets `generated` and
appends one `batch_generation_completed` Event. Queue arguments contain no Tag
IDs, and public requests never perform generation.

RT-205 adds the read side of that workflow. Application combines a narrow
progress-reader port with a provider-neutral queue-monitor port and derives
stable `idle`, `scheduled`, `running`, `needs_attention`, `complete`, and
`unavailable` states. Infrastructure reads only Batch counters and the two
generation lifecycle Event timestamps, and inspects Action Scheduler without
returning action arguments or errors. Admin exposes one capability-protected
no-store GET endpoint and renders the existing WordPress-native React detail
screen with a second confirmation, committed progress, bounded visibility-aware
polling, and idempotent recovery.

RT-206 adds a separate read model rather than exposing the complete RT-109 Tag
Repository record. Application gates the inventory on a complete, non-active
Batch and defines narrow item/page/cursor contracts. Infrastructure selects
only Tag ID, status, and UTC creation time in deterministic `tag_id ASC`
keyset order. Admin encodes the internal keyset as a validated versioned opaque
cursor and maps only those three fields through the capability-protected
no-store route. React renders the list only after complete generation and does
not add CSV, search, edit, delete, copy, or state-transition behavior.

RT-207 builds on that ordering with an explicit export use case. Application
coordinates a narrow streaming source, an artifact-builder port, a Batch-row
locking Repository, the append-only Batch Export Repository, the Event
Repository, and a short transaction. Infrastructure writes exact CSV bytes to
a private temporary artifact in bounded chunks. Admin maps an authorized POST
to the export command and uses `rest_pre_serve_request` only for the resulting
internal download object so WordPress does not JSON-encode the file.

The file is prepared before the short write transaction. The transaction locks
the parent Batch, revalidates the immutable manufacturing snapshot and Tag
count, serializes version allocation, appends the audit row and
`batch_exported` Event, and performs the first `generated -> exported`
transition. A re-export must match the previous row count, format, and SHA-256
before another version can be committed. The temporary path never crosses the
Application or REST response contract and is deleted after streaming or
failure.

RT-208 adds a separate lifecycle adapter instead of extending export or
generation templates with state rules. Domain owns the allowed Release,
Suspend, and Void edges. Application owns expected-state concurrency,
idempotency, manufacturing-count and export-audit validation, effective
activation evaluation, and Event requests. Infrastructure owns the Batch row
lock, aggregate Tag counts, latest-export lookup, and conditional status write.

The command transaction locks the Batch, revalidates counts and release
evidence, conditionally writes status plus `activation_enabled`, and appends
one Event. UI controllers only validate request shape, capability, Schema
readiness, and exact Void confirmation. Suspend and Void do not call the Tag
Repository, and the site-scoped global activation flag is read but never
written.

The lifecycle read model derives `release_ready` from committed Batch and Tag
counts plus the latest audited export. Admin uses it only to present available
actions; the Release command revalidates the same evidence while holding the
Batch lock.

### Runtime dependency rationale

| Package | Purpose and boundary | License and maintenance |
|---|---|---|
| `psr/log` | Defines the logging interface extended by `ApplicationLogger`; RT-008 supplies a default-disabled WordPress adapter without selecting production transport or retention. | MIT; interface-only dependency included in the release artifact. |
| `woocommerce/action-scheduler` | Runs RT-204 resumable Batch chunks behind an Application scheduler port; production requires a real Cron or WP-CLI runner and failed-action monitoring. | GPL-3.0-or-later, compatible with TagCore's GPL-2.0-or-later declaration; its distributed license files must remain in the artifact. |

Composer locks both packages and Dependabot proposes updates. Runtime updates
must pass the PHP, WordPress, WooCommerce, queue-idempotency, and release
artifact checks before adoption. Node packages are build and test inputs;
`node_modules` is never included in the TagCore ZIP.

## RT-209 read-only Tag search boundary

RT-209 adds a separate Tag search read adapter rather than exposing the
complete Tag Repository record. Application models two exact search criteria
and bounded keyset pagination. Infrastructure names only the approved columns
and joins the trusted Batches table to return its identifier, code, lifecycle
status, and activation control. Application derives a non-persisted activation
availability reason from Tag status, activation history, Batch state, and the
global activation feature flag. Admin owns normalization, permission and
Schema checks, cursor encoding, response mapping, and the WordPress-native
read-only page.

The `tagcore/v1/tags` route requires `manage_returntag_tags`; it cannot list
without an exact Tag ID or Batch Code anchor. The Batch-mode cursor is bound to
the normalized filters. No write service, Event, queue, feature flag, or
business state transition is involved. Searchability is deliberately broader
than activation eligibility: retained IDs from suspended and voided Batches
remain visible, while active Tags keep their existing activation.

## RT-210 capacity boundary

RT-210 adds no product route, queue type, Repository, table, index, or state
transition. The existing `CreateBatchInput` is the single Application contract
for the supported `100,000`-Tag maximum. Admin maps that rule to a
field-specific REST validation error and the existing React form exposes the
same native input maximum and translatable guidance.

The dedicated performance harness remains test-only. It exercises the real
generation, inventory, search, progress, lifecycle-count, and export
composition against synthetic capacity fixtures without registering a
production service. Measurements and operational caveats live in
`docs/PERFORMANCE.md`.

Schema version 8 remains authoritative. Evidence from the million-row profile
does not justify an additional `(batch_id, tag_id)` index, so Infrastructure
and Migration composition are unchanged.

## RT-301 public Tag route boundary

RT-301 introduces a `PublicSite` transport adapter for `GET /t/{tag_id}`. The
WordPress rewrite captures exactly one non-empty raw path segment into the
internal `returntag_tag_id` query variable. It deliberately does not normalize,
validate, persist, or query that value; RT-302 owns canonical Tag ID input and
RT-303 owns Tag and Batch state resolution.

The route selects a standalone plugin template instead of a theme template.
This keeps the scan entry point available across theme changes and prevents
core product behavior from moving into `functions.php` or page builders. The
adapter returns a generic fail-closed `503` response until later tickets attach
approved application services. Unsupported methods receive `405`.

Rewrite rules are refreshed only on site-scoped activation, deactivation,
successful TagCore updates, or an authorized administrative compensation
request when the stored rule is missing. No rewrite flush runs on ordinary
public requests. RT-301 adds no Domain or Application workflow, database read
or write, Schema change, Option, queue, email, WooCommerce integration, Event,
dependency, or feature flag.
