# ReturnTag Project Status



## Project identity



* Repository: `wepuu/returntag`

* Consumer brand: ForgeTag

* Internal project: ReturnTag

* WordPress plugin: TagCore

* Plugin directory: `plugin/tagcore`

* Current baseline version: `0.4.0`

* Current completed milestone: Milestone 3 - Scan, OTP, and activation

* Current workstream: RT-316 Stage 7A participant Conversation safety controls implemented locally; Schema target remains `12`



## Completed work



The following tickets are considered implemented in the current baseline:



* RT-001 — Repository and documentation structure

* RT-002 — TagCore plugin skeleton

* RT-003 — Composer autoloading

* RT-004 — PHPCS, PHPStan, and PHPUnit

* RT-005 — GitHub Actions CI

* RT-006 — ZIP build script

* RT-007 — Feature flag infrastructure

* RT-008 — Base logging interface

* RT-009 — Risk-based GitHub CI routing and two-level Playwright regression

* RT-101 — Numbered Migration Runner and WordPress lifecycle integration

* RT-102 — Batches table Migration (`0001`)

* RT-103 — Tags table Migration (`0002`)

* RT-104 — Batch Exports table Migration (`0003`)

* RT-105 — Authentication Challenges table Migration (`0004`)

* RT-106 — Conversations and Messages table Migrations (`0005`, `0006`)

* RT-107 — Access Tokens table Migration (`0007`)

* RT-108 — Events table Migration (`0008`)

* RT-109 — Typed Repository interfaces, sensitive-value and Event policy hardening, `$wpdb` adapters, and transaction boundary

* RT-110 — Fresh installation, partial upgrade, uninstall, query-plan, and database-engine acceptance

* RT-201 — Batch administration create/list experience, capability boundary, and audited draft creation

* RT-202 — Canonical six-character Tag ID value and cryptographically secure candidate generator

* RT-203 — Insert-first Tag ID collision handling with duplicate-only bounded retry



* RT-204 — Resumable 100-Tag Action Scheduler generation with atomic Batch progress

* RT-205 — Administrative confirmation, committed generation progress, queue health, and safe retry

* RT-206 — Complete Batch Tag ID inventory projection and paginated admin list

* RT-207 — Audited deterministic CSV export and export history

* RT-208 — Batch release, suspension, and permanent void controls

* RT-209 — Exact Tag ID and Batch Code search with read-only Tag administration

* RT-210 — Million-row capacity acceptance and Milestone 2 version `0.3.0`

* RT-301 — Theme-independent public Tag route and fail-closed scan response

* RT-302 — Bounded public Tag ID normalization and canonical URL handling



* RT-303 - Privacy-minimized Tag and Batch state pages

* RT-304 - Worker-issued activation OTP request

* RT-305 - Atomic activation OTP verification

* RT-306 - Passwordless WordPress login and account provisioning

* RT-307 - Atomic first-owner activation

* RT-308 - Committed activation-state convergence

* RT-309 - Authenticated activation-attempt limits

* RT-310 - Static Smart Tag parallel-system activation guide and Milestone 3 version `0.4.0`

* RT-311 - ForgeTag consumer-brand convergence and TagCore theme-entry contract

* RT-312 - TagCore manual-entry routes, dynamic link block, and modal/full-screen adapter

* RT-313 - ForgeTag V1 design-asset baseline and production/reference asset governance

Do not reimplement or redesign these items without first inspecting the existing implementation and receiving explicit approval.



## Current development environment



The project uses Docker and `@wordpress/env`.



Known environment for Milestone 3 acceptance:



* Required Node.js major version: 24

* Node.js constraint: `>=24.0.0 <25`

* `@wordpress/env`: `11.11.0`

* PHP in the primary wp-env CLI container: `8.4.23`

* WordPress: `7.0.2`

* WooCommerce: `10.9.4`

* TagCore: `0.4.0`

* TagCore status: active

* Development site port: `8888`

* Test site port: `8889`



The required Node.js version is also recorded in `.nvmrc`.



## Important environment warning



The Windows system environment does not provide `node`, `npm`, or `npx` on
`PATH`. Codex Desktop supplies a workspace-scoped runtime instead. It was
verified on 2026-07-28 from workspace bundle `26.727.11326` as:



```text

Node.js: v24.14.0

pnpm: 11.9.0

TypeScript: 6.0.3

@wordpress/env: 11.11.0

```



The current workspace paths are:



```text

Node.js:
C:\Users\admin\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe

pnpm:
C:\Users\admin\.cache\codex-runtimes\codex-primary-runtime\dependencies\bin\fallback\pnpm.cmd

```



The workspace runtime does not include npm. Although it exposes pnpm, the
repository remains npm-oriented and contains an npm-installed `node_modules`.
Do not invoke `pnpm run` or `pnpm install` in this repository: pnpm may try to
reconcile or replace the existing modules tree and access the package registry.
Do not install dependencies, regenerate locks, or change package-manager policy
as part of ordinary ticket work.



For the existing dependency tree, prepend only the workspace Node directory to
the current PowerShell process and call project-owned Node entry points or
local `.cmd` tools directly. These commands were verified:



```powershell

$workspaceDeps = 'C:\Users\admin\.cache\codex-runtimes\codex-primary-runtime\dependencies'

$env:Path = "$workspaceDeps\node\bin;$env:Path"

node scripts/check-release-version.mjs

& '.\node_modules\.bin\tsc.cmd' --noEmit

```



The aggregate `npm run check` command is unavailable until npm is deliberately
provided. Other checks may be translated from `package.json` into direct calls
to the existing `node_modules\.bin` tools, without running an install.



Do not destroy the current Docker environment merely to fix PATH or package
manager availability.



## Package management warning



The repository currently contains both npm- and pnpm-related files, including:



* `package-lock.json`

* `pnpm-lock.yaml`

* `pnpm-workspace.yaml`



The current `package.json` scripts use npm.



Do not update dependencies, regenerate lock files, switch package managers, or delete either lock file until this situation has been audited in a dedicated task.



## Existing project commands



Primary environment commands:



```powershell

npm run env:start

npm run env:stop

npm run env:start:minimum

npm run env:start:woocommerce-9

npm run env:start:minimum-woocommerce-9

```



Primary validation command:



```powershell

npm run check

```



PHP integration test:



```powershell

npm run test:php:integration

```



End-to-end test:



```powershell

npm run test:e2e

```



The `check` script runs JavaScript linting, CSS linting, TypeScript checking, JavaScript unit tests, and frontend builds.



## Destructive commands



Do not execute any of the following without explicit user approval:



```text

wp-env destroy

wp-env reset all

docker compose down -v

docker volume rm

docker system prune --volumes

git clean -fd

git reset --hard

```



The local wp-env database and Docker volumes may contain useful test state.



## Git workflow



* `main` is the stable baseline branch.

* New work must use a dedicated feature branch.

* Handle one RT ticket at a time.

* Run applicable validation before declaring a ticket complete.

* Do not commit secrets, local credentials, databases, caches, dependency directories, or Codex authentication files.

* Do not continue to another ticket without explicit approval.



Recommended feature branch format:



```text

feature/rt-XXX-short-description

```



## Required takeover procedure



Before modifying files, a new Codex session or account must:



1. Read `AGENTS.md`.

2. Read this file.

3. Read `README.md` and the relevant product requirements.

4. Run `git status`.

5. Inspect the current branch and recent commits.

6. Inspect the implementation of RT-001 through RT-008 and RT-101 through RT-110.

7. Inspect `package.json`, `.wp-env.json`, and GitHub Actions workflows.

8. Check the running Docker containers.

9. Report the verified project state before making changes.



The new session must not assume that account-level chat history is available.



## Next work



RT-201 (Batch administration), RT-202 (secure candidate Tag ID generation),
RT-203 (bounded duplicate-key collision retry), RT-204 (resumable background
generation), RT-205 (administrative generation progress), RT-206 (complete
Batch Tag inventory projection), RT-207 (audited deterministic CSV export),
RT-208 (Batch release and incident controls), RT-209 (read-only Tag search),
and RT-210 (large-Batch capacity acceptance) have been implemented. Milestone
2 closes at Schema version `8` and project/plugin version `0.3.0`.

The PRD milestone allocation was reconciled on 2026-07-28: RT-206 is the Batch
Tag ID inventory and deterministic export-source foundation; RT-207 is the
audited CSV export including version, row count, format, operator, timestamp,
and SHA-256 checksum.

RT-207 adds:

- capability-protected CSV creation and bounded export-history REST routes;
- UTF-8, BOM-free, CRLF CSV ordered by `tag_id ASC`;
- exact row-count and SHA-256 verification;
- append-only export versions and `batch_exported` Events;
- atomic first-export `generated -> exported`;
- exact-byte re-export checks without regenerating Tag IDs;
- private temporary artifact streaming and cleanup;
- WordPress-native confirmation, download feedback, and responsive audit
  history in Batch detail.

RT-208 adds:

- capability-protected lifecycle read, Release, Suspend, and Void routes;
- complete-inventory and audited-export release gates;
- expected-status concurrency and idempotent repeats;
- Batch-level activation control with the global flag still authoritative;
- transactionally appended `batch_released`, `batch_suspended`, and
  `batch_voided` Events;
- exact Batch Code confirmation for permanent Void;
- WordPress-native responsive controls that do not change active Tag owners;
- no Schema, dependency, lock-file, plugin-version, or Tag-row change.

RT-209 adds:

- the dedicated `manage_returntag_tags` capability and capability contract
  version `2`;
- a separate WordPress-native Tags submenu;
- exact normalized Tag ID search;
- exact Batch Code search with optional canonical Tag Status;
- bounded, filter-bound keyset pagination;
- a narrow no-store projection that excludes owner and private Tag fields;
- separate Tag status, Batch status, and server-derived activation availability
  semantics, including retained suspended and voided IDs;
- no Schema, dependency, lock-file, plugin-version, Event, or Tag-row change.

RT-210 adds:

- a supported maximum of `100,000` requested Tag IDs per Batch;
- field-specific REST and WordPress-native form validation before persistence;
- a dedicated million-row performance suite for generation, inventory,
  search, progress, lifecycle counts, and deterministic CSV export;
- reproducible budgets and measured results in `docs/PERFORMANCE.md`;
- no Migration or index change because existing Schema 8 query shapes met the
  accepted budgets.

RT-301 adds the first public scan transport boundary:

- a theme-independent `GET /t/{tag_id}` WordPress rewrite route;
- exactly one raw path segment captured for later RT-302 normalization;
- a fail-closed `503` plugin-owned page while state resolution is not yet
  implemented;
- `405` rejection for mutation methods with `Allow: GET, HEAD`;
- no-store, no-referrer, no-index, and content-type hardening headers;
- activation, update, deactivation, and authorized-admin rewrite refresh
  lifecycle handling;
- a mobile-first, keyboard-accessible, translatable fallback matching the
  approved Product Design direction;
- no Tag or Batch query, normalization, activation, finder workflow, theme,
  Schema, Option, dependency, Event, or plugin-version change.

RT-302 adds the canonical public Tag ID input boundary:

- one shared Application normalizer that removes whitespace and hyphens,
  converts ASCII letters to uppercase, and validates the exact six-character
  alphabet through the Domain `TagId` value;
- a `64`-byte raw-input limit before normalization;
- one same-site permanent redirect for normalizable `GET` and `HEAD` route
  segments, including URL-encoded spaces;
- no redirect for mutation methods or invalid input;
- the same generic `503` body for canonical and invalid input until RT-303 owns
  Tag and Batch state resolution;
- no Tag or Batch query, existence response, state page, activation, finder
  workflow, theme, Schema, Option, dependency, Event, or plugin-version change.

RT-303 adds the first public Tag and Batch state resolution:

- one exact primary-key Tag lookup with a narrow left-joined Batch projection;
- server-derived invalid, activation, owner, finder, suspended, retired, and
  fail-closed service pages;
- current WordPress identity used only to distinguish the active Owner from a
  Finder, without sending `owner_id` to the renderer;
- Finder output limited to product type, `public_label`, and Lost Mode content
  only when the approved state permits it;
- global activation, Batch activation, Batch lifecycle, and Finder-contact
  controls enforced before selecting an entry experience;
- intentional `404` for malformed or unknown IDs, `503` for Schema,
  persistence, or data-integrity failures, `200` for known product states, and
  unchanged `405` mutation rejection;
- mobile-first, translatable standalone pages extending the approved RT-301
  Product Design system without a theme dependency or non-working workflow
  controls;
- no activation, OTP, Finder message, email, queue, Event, write, Schema,
  dependency, plugin-version, or WooCommerce change.

RT-304 adds:

- a labelled, keyboard-accessible email form on eligible activation pages;
- same-site and nonce checks plus privacy-safe success and error responses;
- encrypted email and keyed email/IP lookup storage in Schema 8 challenges;
- durable atomic email, Tag, direct-peer IP, and global request budgets;
- Action Scheduler work containing only `challenge_id`;
- Worker-memory six-digit OTP generation with domain-separated peppered
  password hashing and at-most-once dispatch;
- seven-day post-expiry challenge retention and bounded daily cleanup;
- no OTP verification, login, user creation, ownership change, activation,
  Event, Migration, dependency, plugin-version, or WooCommerce behavior.

RT-305 adds:

- exact six-ASCII-digit OTP validation and keyed adaptive-hash comparison;
- latest Tag-and-email challenge selection under a database row lock;
- pre-comparison rejection for unissued, expired, verified, consumed, and
  five-attempt-exhausted challenges;
- atomic mismatch counting and one-time `verified_at` plus `consumed_at`
  completion;
- separate durable email, Tag, direct-peer IP, and global verification limits,
  with email buckets gated by latest-challenge eligibility;
- generic privacy-safe failure feedback and no browser challenge identifier;
- no login, user creation, ownership change, Tag activation, Event, token,
  Migration, dependency, plugin-version, or WooCommerce behavior.

RT-306 adds:

- same-POST composition of one-time OTP verification, WordPress account
  provisioning, and native Session establishment without a browser handoff
  token;
- an authenticated-session short circuit before OTP handling so stale forms
  cannot consume a code or switch accounts;
- a 100-byte WordPress account email boundary before OTP request and
  verification;
- network-scoped HMAC-derived advisory locking, exact duplicate detection,
  existing-user reuse, opaque least-privilege account creation, and multisite
  membership preservation;
- metadata-free at-least-once account-creation audit with retry repair before
  Session issuance;
- non-persistent WordPress authentication values emitted with HttpOnly and
  SameSite=Lax plus a same-site 303 return to the canonical Tag route;
- a signed-in mobile activation state with no non-working activation control;
- no ownership assignment, Tag status change, activation Event, Finder
  workflow, WooCommerce order behavior, Migration, dependency, or
  plugin-version change.

RT-307 adds:

- a server-identity-only Application use case for first-owner activation;
- one atomic Tag/Batch conditional update that requires an unowned,
  unregistered Tag in a released, activation-enabled Batch;
- same-Owner retry idempotency and privacy-safe changed-state outcomes without
  replacing a committed Owner;
- one metadata-free `tag_activated` audit Event in the same transaction as the
  ownership write;
- no public POST until RT-309 supplies activation-attempt limits;
- no Migration, index, Schema, dependency, plugin-version, theme, email,
  queue, Finder, or WooCommerce change.

RT-308 adds:

- an Application composition that always resolves committed public state after
  a non-exceptional activation outcome;
- Owner convergence for the committed Owner, Finder convergence for another
  actor, and existing invalid or state-explanation pages for absent or blocked
  Tags;
- no activation-conflict route, page, copy, Event, support action, or identity
  disclosure;
- no public POST until RT-309 reserves durable activation-attempt limits;
- no Migration, index, Schema, dependency, plugin-version, asset, email,
  queue, theme, or WooCommerce change.

RT-309 adds:

- a working authenticated `Activate my tag` POST in the existing mobile public
  activation card;
- same-site and nonce validation with WordPress Session-derived User and email
  identity plus direct-peer IP;
- dedicated durable User 5/hour and 10/day, keyed email 5/hour and 10/day,
  keyed IP 30/hour and 100/day, Tag 10/hour, and global 100/minute and
  2,000/hour activation-attempt budgets;
- generic throttling and failure feedback followed by existing committed-state
  convergence and canonical `303` GET routing;
- bounded cleanup through the existing daily maintenance action;
- no Migration, index, Schema, dependency, plugin-version, theme, success
  email, new route, conflict page, support action, or WooCommerce change.

RT-310 adds:

- a static, translatable two-system guide only for eligible Smart Tag
  activation entries;
- explicit separation between the external smart finding network and
  ReturnTag QR recovery;
- privacy-safe copy stating that ReturnTag does not verify pairing or access
  Apple, Google, device, battery, or location data;
- mobile-first semantic markup and plugin-scoped styling that reuse the
  existing theme-independent public page;
- regression coverage proving the guide is absent from Sticker, Classic Tag,
  unavailable, Owner, Finder, invalid, suspended, retired, and fail-closed
  states;
- project and plugin version `0.4.0`, closing Milestone 3 while Schema remains
  version `8`;
- no external link, account connection, acknowledgement write, query,
  Migration, Option, Event, queue, email, dependency, theme, WooCommerce
  behavior, or smart-network integration.

## Approved frontend architecture baseline

ADR 0017 records the approved boundary for future frontend work:

- one WordPress site will contain the brand site, WooCommerce shop, TagCore
  public flows, and authenticated Owner account;
- a future ForgeTag block theme at `theme/forge-tag/` with Text Domain
  `forge-tag` may own brand, content, navigation, support,
  and WooCommerce presentation, but no TagCore business behavior;
- desktop website visitors select Activate or Report before a TagCore-owned
  modal requests the Tag ID;
- mobile website visitors use a TagCore-owned full-screen manual-entry surface;
- QR scans navigate directly to `/t/{tag_id}` without Tag ID re-entry;
- the selected Activate or Report intent never overrides server-resolved Tag
  state;
- `/t/{tag_id}` registration, normalization, state resolution, access control,
  privacy controls, and business processing remain exclusively in TagCore;
- the canonical route remains standalone and theme-independent;
- PHP server rendering, optional Interactivity API progressive enhancement,
  and plugin-scoped CSS remain the phase-one frontend baseline; Next.js and
  Tailwind are not approved for phase one.

The generated low-fidelity wireframes are non-normative references only.
Future user-supplied page-effect designs require review before they become an
implementation target. This documentation decision starts no new RT ticket,
adds no theme or code, and changes no route, Schema, Option, dependency,
plugin version, or stored data.



## RT-311 documentation baseline

RT-311 establishes the documentation-only prerequisite for the first ForgeTag
block theme:

- `ForgeTag` as the consumer brand while retaining `ReturnTag` as the internal
  project and technical identifier family;
- `theme/forge-tag/` and the `forge-tag` Text Domain as the future theme
  identity;
- WooCommerce compatibility without making WooCommerce an activation
  dependency;
- Theme V1 baseline Block Theme templates for Shop Archive, Single Product,
  Cart, and Checkout without adding commerce business logic or claiming final
  visual approval;
- source-controlled Site Editor governance: production editor changes must be
  exported, reviewed, and committed rather than existing only in the database;
- a TagCore-owned manual-entry contract with desktop-modal progressive
  enhancement, mobile full-screen presentation, and a server-rendered no-JS
  fallback;
- the future `tagcore/tag-entry-link` dynamic block with only `activate` or
  `report` presentation intent, with TagCore-generated same-site URLs for
  `/tag/activate/` and `/tag/report/`;
- no Theme file, public route, plugin runtime, Schema, Option, dependency,
  stored data, Finder workflow, Account UI, or WooCommerce behavior change.

RT-311 is accepted. Theme integration remains blocked until the TagCore-owned
entry adapter receives a separate implementation ticket. Finder Report and
Owner Account presentation must not be represented as implemented before their
product components exist.

## RT-312 implementation

RT-312 implements the TagCore-owned manual-entry adapter required by the
approved ForgeTag Theme contract:

- plugin-owned `/tag/activate/` and `/tag/report/` GET, HEAD, and POST routes;
- one server-rendered, no-JavaScript form with canonical six-character Tag ID
  normalization and a same-site `303` redirect to `/t/{tag_id}`;
- the dynamic `tagcore/tag-entry-link` block with only a closed `activate` or
  `report` presentation intent and TagCore-generated same-site URLs;
- desktop native-dialog progressive enhancement and mobile full-screen link
  navigation using the same form contract;
- nonce, Fetch Metadata, Origin, response-header, enumeration-resistance, and
  direct-peer/global fixed-window rate-limit controls;
- ForgeTag V1 modal and entry styling with 44-pixel targets, dual focus rings,
  reduced-motion and forced-colors support;
- no Tag/Batch state read before the canonical route, Theme, WooCommerce hard
  dependency, Migration, Schema, Event, email, queue, lock-file, dependency,
  or plugin-version change.

RT-312 unblocks future ForgeTag Theme integration. It does not create the Theme
and does not implement Finder messaging or Owner Account presentation.

## RT-313 design-asset baseline

RT-313 converts the supplied ForgeTag V1 design material into an auditable,
version-controlled source baseline before Theme implementation:

- `docs/design/UI-STYLE-GUIDE-V1.md` remains the normative visual guide and now
  distinguishes production-approved assets from reference-only source images;
- `docs/design/ASSET-MANIFEST-V1.md` records source-image dimensions, formats,
  Alpha state, byte counts, SHA-256 values, allowed uses, prohibited uses, Alt
  principles, and confirmed commercial-use status;
- `forge-logo.png` is the only image currently approved for direct production
  use, subject to the documented light-surface and display-size limits;
- `homepage.png`, `tanchuang.png`, `forge-logo-light.png`, `tag1.jpg` through
  `tag4.jpg`, and `forge-smarttag.png` are reference-only and cannot enter
  Theme runtime assets, templates, Patterns, CSS, or WooCommerce pages;
- the local `a1.jpg` and `ForgeTag文案设计.docx` files are excluded through
  exact ignore rules because they contain unapproved claims, but remain on the
  user's machine;
- no Theme, font, Lucide package, TagCore runtime, public contract, Schema,
  Option, Hook, route, database, dependency, or product-state change is made.

RT-313 establishes the design-asset prerequisite for RT-314 ForgeTag FSE Theme
V1. Theme development may start only after explicit authorization. Final brand
homepage visual acceptance still requires new Classic Tag, Sticker Tag, and
Smart Tag production photography without an old domain, old ID, or real
routable QR code. Manrope, Inter, Lucide, and their licenses remain RT-314
deliverables.

RT-314 was subsequently authorized through Issue #52 with explicit staged
acceptance criteria. Do not infer authorization for later stages, release, or
deployment from the Stage 2 implementation.

## RT-314 Stage 1 implementation

RT-314 is explicitly authorized through Issue #52. Stage 1 establishes:

- the independently versioned `theme/forge-tag/` Block Theme skeleton with
  Text Domain `forge-tag` and Theme version `0.1.0`;
- the required `style.css` and `templates/index.html`, a WordPress
  `6.9`-schema `theme.json` version 3 baseline, minimal translation bootstrap,
  and source-controlled Header and Footer Template Parts;
- an independent Theme release contract using Git tag
  `forge-tag-v{version}`, artifact `forge-tag-v{version}.zip`, archive root
  `forge-tag/`, and a matching SHA-256 checksum;
- automated release-metadata validation that keeps Theme `0.1.0` independent
  from TagCore `0.4.0` and Schema `8`.

Stage 1 adds no design Tokens, fonts, icons, runtime design image, homepage
Pattern, TagCore entry block placement, WooCommerce template, packaging
workflow, release tag, ZIP, deployment, route, API, Option, Migration, Schema,
database behavior, product-state change, email, queue, or personal-data
processing. Those Theme implementation items remain later RT-314 stages.

## RT-314 Stage 2 implementation

Stage 2 establishes the source-controlled visual foundation:

- approved Global Styles tokens for color, local typography, spacing, radius,
  shadow, motion, and layout width;
- scoped frontend and editor foundation CSS for minimum control sizes, visible
  focus, reduced motion, and forced-colors support;
- the production-approved ForgeTag logo, self-hosted Manrope and Inter variable
  fonts, and the exact 17-icon Lucide allowlist;
- a machine-readable asset manifest containing pinned sources, licenses,
  transformations, and SHA-256 evidence;
- automated Theme boundary, asset-integrity, identity, WordPress activation,
  WooCommerce-disabled shell, responsive, local-asset, and accessibility
  regression checks.

Stage 2 does not add a homepage Pattern, TagCore entry placement, WooCommerce
Shop/Product/Cart/Checkout Templates, business logic, product copy approval,
Schema, database write, Option, route, API, email, queue, Theme artifact,
release tag, GitHub Release, or deployment. Theme version remains `0.1.0`,
TagCore remains `0.4.0`, and Schema remains `8`.

## RT-314 Stage 3A implementation

Stage 3A establishes the source-controlled homepage engineering baseline:

- a `front-page.html` Template composed from reusable Header, Footer, Hero,
  Return Route, product-family, recovery-path, use-case, and privacy Patterns;
- TagCore-owned Activate and Report entry rendering through four
  `tagcore/tag-entry-link` dynamic blocks, with a plugin-owned `secondary`
  Block Style for Report actions;
- a page-scoped, mobile-first stylesheet with keyboard focus, reduced-motion,
  forced-colors, narrow-screen, and 200-percent zoom protections;
- static contract tests that reject copied forms, hard-coded TagCore entry
  paths, unsupported product families, and deep Theme selectors into TagCore;
- WordPress integration and browser regression coverage for block-style
  registration, dialog behavior, responsive hierarchy, local requests, and
  accessibility.

Stage 3A uses only the approved ForgeTag logo, local Manrope and Inter fonts,
and recorded Lucide icons. It does not copy RT-313 reference-only imagery or
substitute generated placeholders. Approved production product photography is
still required for Stage 3B visual acceptance. This stage adds no WooCommerce
Shop/Product/Cart/Checkout Template, Schema, Migration, database write, Option,
route, API, Tag state change, email, queue, Theme artifact, release tag,
GitHub Release, or deployment. Theme version remains `0.1.0`, TagCore remains
`0.4.0`, and Schema remains `8`.

## RT-314 Stage 3B implementation

Stage 3B integrates the user-confirmed official source photographs for the
three canonical product families without exposing obsolete identifiers:

- `tag2.png` supplies the Sticker source; the Theme uses a safety derivative
  with every QR code, obsolete domain, and Tag ID removed;
- `tag1.jpg`, `tag3.jpg`, and `tag4.jpg` supply the Classic Tag sources; the
  Theme uses a front-only family composition that excludes reverse-side QR,
  domain, and Tag ID content;
- `forge-smarttag.png` supplies the Smart Tag source and is copied byte-for-byte
  into the Theme; the visible model artwork is not used as public product copy
  or Alt text;
- runtime paths, transformations, source hashes, and output hashes are pinned
  in `theme/forge-tag/asset-manifest.json` and enforced by Theme checks;
- the Hero and product-family cards use responsive local images with explicit
  dimensions, descriptive translatable Alt text, and no remote request.

The source files under `docs/design/` remain unchanged and are not loaded by
WordPress. Stage 3B changes no WooCommerce Template, Schema, Migration,
database write, Option, route, API, Tag state, email, queue, Theme artifact,
release tag, GitHub Release, or deployment. Theme version remains `0.1.0`,
TagCore remains `0.4.0`, and Schema remains `8`.

## RT-314 Stage 4 implementation

Stage 4 establishes the WooCommerce and Theme artifact engineering baseline:

- source-controlled Shop Archive, Single Product, Cart, and Checkout Block
  Templates using WooCommerce public blocks and Theme-owned wrappers;
- inherited Product Collection behavior for the catalog and assigned Page
  Content Wrapper behavior for Cart and Checkout, preserving WooCommerce as
  the source of truth for commerce state and forms;
- responsive commerce presentation, shared brand-shell styling, local-only
  asset loading, main-content accessibility checks, and 320px/200-percent
  text regression coverage;
- static checks that reject copied forms, direct Cart or Checkout block
  ownership, TagCore/business identifiers, and WooCommerce internal selectors;
- WordPress/WooCommerce matrix verification plus browser coverage for catalog,
  product, add-to-cart, Cart, and Checkout rendering;
- tag-triggered Theme artifact automation that validates the Theme version and
  exact runtime allowlist, creates a `forge-tag/`-rooted ZIP and SHA-256
  checksum, and uploads Actions artifacts without publishing a GitHub Release
  or deploying them.

Stage 4 creates no Git tag, ZIP in the repository, GitHub Release, deployment,
Schema, Migration, database write, product-state rule, TagCore route, API,
email, queue, or personal-data flow. Theme version remains `0.1.0`, TagCore
remains `0.4.0`, and Schema remains `8`. WooCommerce templates remain an
engineering/responsive baseline and do not imply final commercial copy or
page-level visual approval.

## RT-314 Stage 5 implementation

Stage 5 closes the Theme-to-TagCore production integration baseline:

- the Header and Hero retain exactly two Activate and two Report placements
  through the closed `tagcore/tag-entry-link` dynamic-block contract;
- static Theme checks reject hard-coded manual-entry or canonical Tag paths,
  copied Tag ID forms, and CSS or markup dependencies on TagCore DOM internals
  across every runtime Theme file;
- TagCore integration coverage verifies invalid-intent fail-closed behavior and
  unique dialog relationships for repeated block instances;
- browser coverage verifies all four homepage entry instances, exact 767px and
  768px behavior, desktop focus restoration, mobile full-screen navigation,
  no-JavaScript operation, and Script Module failure fallback;
- the WordPress compatibility matrix verifies TagCore entry with WooCommerce
  disabled, TagCore routes under a replacement Theme, and safe brand-shell
  rendering when TagCore is disabled.

Stage 5 changes no TagCore business service, public product API, route, Schema,
Migration, database data, Option, dependency, lock file, email, queue, feature
flag, release tag, GitHub Release, or deployment. Theme version remains
`0.1.0`, TagCore remains `0.4.0`, and Schema remains `8`. Finder messaging and
Owner Account presentation are not represented as complete by this integration
baseline.

## RT-009 risk-based CI and E2E optimization

RT-009 reduces pull-request feedback time without removing accepted coverage:

- a repository-owned, fail-closed path classifier routes documentation,
  runtime, database-sensitive, and full-check changes without a third-party
  path-filter Action;
- the stable `Quality Gate` result verifies that every applicable matrix job
  ran successfully and rejects failed or ambiguous classification;
- documentation-only changes avoid npm, Composer, wp-env, and Playwright setup
  while retaining relative-link, RT-313 asset-manifest, exclusion, and tracked
  text secret checks;
- runtime pull requests use a bounded desktop-Chromium profile plus selected
  public-route, manual-entry, commerce, and homepage tests in Mobile Safari;
- the complete five-project browser suite remains intact for daily
  03:00 Asia/Shanghai regression, manual dispatch, and CI/E2E infrastructure
  changes;
- administrator browser state is created once per Playwright run and reused
  only by an explicit admin fixture; public route pages remain anonymous;
- superseded runs on the same pull request are cancelled, while `main` pushes
  remain independently auditable;
- failed browser runs retain screenshots and first-retry trace/video artifacts
  for seven days.

RT-009 changes CI, tests, and documentation only. It adds no TagCore runtime
behavior, dependency, public route, Hook, Option, Schema, Migration, product
state, WooCommerce mapping, email, queue, or personal-data processing.

The first `Quality Gate` on `main` completed successfully for commit
`a56ea74046634cabcc387e533ffa3150a158e25a`. The protected `main` branch now
requires pull requests and the exact `Quality Gate` check, applies protection
to administrators, and disallows force pushes and deletion. RT-314 is now
explicitly authorized through Stage 5; release, publication, and deployment
remain separate approvals.

## RT-315 Stage 0 contract freeze

RT-315 approves a documentation-only change to the phase-one Finder contract:

* `Message for the owner` is optional and, when present, contains 10–500
  characters;
* `Item photo` is required and is exactly one Finder-supplied evidence image;
* Finder email is optional for initial one-way reporting and is not a gate for
  the first Owner alert;
* only a processed, metadata-stripped, safety-approved inline derivative may
  reach the Owner email;
* the Owner has no reply path until the Finder optionally verifies an email and
  the report is linked to a canonical Conversation;
* Finder Report and private-media persistence are future expand contracts and
  do not change canonical Conversation states.

Stage 0 adds ADR 0019 and aligns the PRD, repository instructions,
architecture, database, security, release, and documentation checks. It does
not implement a route, form, upload, storage object, Migration, repository,
queue, email, template, Theme behavior, dependency, release, or deployment.
TagCore remains `0.4.0`, Theme remains `0.1.0`, and Schema remains `8`.

## RT-315 Stage 1 persistence foundation

Stage 1 advances Schema `8 -> 10` with separate Finder Report and private-media
expand tables. It adds canonical report, evidence, and MIME enums; typed
encrypted-message, encrypted-reference, digest, and derivative metadata values;
and narrow insert/read Repository ports with `$wpdb` adapters. The media table
enforces exactly one evidence row per report, and neither table stores public
URLs, filenames, email addresses, location, EXIF, or image bytes.

Fresh-install, Schema-8 upgrade, idempotent retry, missing-predecessor,
cardinality, privacy-field, and Repository round-trip coverage are included.
The new adapters are not registered by the production bootstrap. Public forms,
multipart upload handling, private object storage, image processing, content
safety, rate limits, cleanup, queues, Owner notification, Finder verification,
Conversation linkage, UI, and Theme behavior remain outside Stage 1. TagCore
remains `0.4.0`, Theme remains `0.1.0`, and Schema is `10`.

## RT-315 Stage 2 private-media safety foundation

Stage 2 adds an uncomposed, independently testable media kernel inside TagCore:

* bounded source bytes and server-derived JPEG, PNG, or WebP validation;
* exact container-length checks with animated PNG/WebP and appended-content
  rejection;
* GD decode, JPEG orientation handling, metadata-removing re-encoding, and
  controlled 1600-pixel review plus 800-pixel/200-KiB email derivatives;
* an explicit content-safety port where only `approved` creates an approved
  marker and the shipped default always fails unavailable;
* XChaCha20-Poly1305 encrypted filesystem objects and separately encrypted,
  purpose-bound opaque references using independent external keys;
* private-root, traversal, symlink, overwrite, key-reuse, purpose-confusion,
  ciphertext-tamper, digest, and idempotent-delete regression coverage.

The production bootstrap does not register these classes. No public form,
multipart boundary, object write, database mutation, rate limit, retention
Worker, queue, email, Owner notification, Finder verification, Conversation,
Theme behavior, dependency, or lock-file change is included. TagCore remains
`0.4.0`, Theme remains `0.1.0`, and Schema remains `10`.

## RT-315 Stage 3 Finder evidence intake runtime

Stage 3 connects the frozen one-way Finder Report contract to the TagCore
public Tag route. Eligible Finder pages can render a two-step, progressively
enhanced form with one required JPEG/PNG/WebP photo and an optional 10–500
character plain-text message. The boundary collects no Finder name, email, or
location, uses same-site and WordPress nonce checks, atomically claims a signed
submission token, applies per-Tag/peer/risk/global budgets, encrypts private
message and object data, persists before enqueue, and returns only generic
privacy-safe states.

Processing is asynchronous and report-ID-only. It validates persisted source
facts, strips metadata through controlled derivatives, requires an explicit
approved safety decision, converges state transitions transactionally,
recovers missing or stale work, and deletes expired objects through bounded
cleanup. The runtime and `returntag_finder_evidence_enabled` flag default off;
missing private configuration or the shipped unavailable reviewer prevents the
form from opening. Owner notification, Finder email verification,
Conversation linkage, Schema changes, dependencies, Theme business logic,
release, and deployment remain outside Stage 3. TagCore remains `0.4.0`, Theme
remains `0.1.0`, and Schema remains `10`.

## RT-315 Stage 4 current-Owner notification

Stage 4 adds an asynchronous, report-ID-only Owner notification after the
Stage 3 evidence pipeline reaches `ready`. The Worker rechecks Finder contact,
Finder evidence, and email-dispatch controls, atomically claims the report,
resolves the current active Owner at send time, and embeds only the controlled
metadata-free email JPEG as a local CID part. Finder email verification is not
required for this initial one-way notification.

The ForgeTag email includes the optional Finder message when present and makes
the one-way limitation explicit. It contains no Secure Reply control, public
media URL, original filename, private item name, Tag ID, access Token, or
cross-party email address. Mailer acceptance records `sent`, not confirmed
delivery; failures become bounded terminal states and stale ambiguous claims
fail closed instead of automatically resending. Successfully notified report
and media rows receive a 30-day retention boundary. No Conversation or Owner
reply path is opened. TagCore remains `0.4.0`, Theme remains `0.1.0`, Schema
remains `10`, and no dependency or lock-file changes are introduced.

## RT-315 Stage 5 optional Finder email verification

Stage 5 adds the optional `Continue privately` path after a Finder Report is
accepted. A six-digit email OTP is dispatched asynchronously, verified through
the existing TagCore public-page visual language, and consumed atomically with
creation and one-to-one linkage of an `open` Conversation. Skipping the step
does not affect evidence processing or the one-way Owner notification.

The flow uses a short-lived opaque continuation claim rather than exposing an
internal report ID. It resolves the current active Owner at verification time,
does not expose either email, and adds no Secure Reply or message delivery.
TagCore remains `0.4.0`, Theme remains `0.1.0`, and Schema advances `10 -> 11`.

## RT-315 Stage 6 Secure Reply and bounded relay

Stage 6 implements role-bound Secure Reply links, explicit POST exchange into
30-minute sessions, and 24-hour Owner/Finder email links. Human messages are
required 10–500 character plain text, limited to 10 per role and 20 per
Conversation, encrypted at rest, and dispatched through Message-ID-only
Workers. Schema advances `11 -> 12` with additive dispatch-claim metadata so
mailer acceptance, failure, and stale ambiguous work converge without an
automatic duplicate send.

The flow re-resolves current active Owner authorization, never exposes either
email, and supports no Conversation attachments, HTML, precise location,
administrative moderation, close/report/block UI, release, or deployment.
The public route uses no-cache, no-referrer, no-index, same-site, nonce, and
secure-cookie controls; Stage 7 remains responsible for close, report, block,
dispute, and administrative moderation behavior. TagCore remains `0.4.0`,
Theme remains `0.1.0`, and no dependency or lock-file changes are introduced.

Stage 6 acceptance coverage verifies one-time link exchange and session
rotation, current-Owner invalidation, role and Conversation limits, one-claim
dispatch convergence, stale-claim terminal failure, canonical lifecycle
Events, privacy-safe mail headers, secure template structure, and the absence
of remote assets or cross-party identifiers.

## RT-316 Stage 7A participant safety controls

Stage 7A freezes Finder termination and current-Owner report-block as the two
participant terminal actions for an existing private Conversation. The
implementation keeps Schema `12`, revokes all Conversation access, fails
queued Messages, records metadata-free Events, and renders only generic
terminal feedback. Administrative moderation outcomes, evidence holds,
reopening, ownership disputes, release, and deployment remain later work.
