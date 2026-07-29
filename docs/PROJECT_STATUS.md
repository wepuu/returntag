\# ReturnTag Project Status



\## Project identity



\* Repository: `wepuu/returntag`

\* Product: ReturnTag

\* WordPress plugin: TagCore

\* Plugin directory: `plugin/tagcore`

\* Current baseline version: `0.3.0`

\* Current completed milestone: Milestone 2 - Batch and ID production

\* Current workstream: Awaiting explicit authorization for the next milestone (RT-201 through RT-210 implemented; schema remains `8`)



\## Completed work



The following tickets are considered implemented in the current baseline:



\* RT-001 — Repository and documentation structure

\* RT-002 — TagCore plugin skeleton

\* RT-003 — Composer autoloading

\* RT-004 — PHPCS, PHPStan, and PHPUnit

\* RT-005 — GitHub Actions CI

\* RT-006 — ZIP build script

\* RT-007 — Feature flag infrastructure

\* RT-008 — Base logging interface

\* RT-101 — Numbered Migration Runner and WordPress lifecycle integration

\* RT-102 — Batches table Migration (`0001`)

\* RT-103 — Tags table Migration (`0002`)

\* RT-104 — Batch Exports table Migration (`0003`)

\* RT-105 — Authentication Challenges table Migration (`0004`)

\* RT-106 — Conversations and Messages table Migrations (`0005`, `0006`)

\* RT-107 — Access Tokens table Migration (`0007`)

\* RT-108 — Events table Migration (`0008`)

\* RT-109 — Typed Repository interfaces, sensitive-value and Event policy hardening, `$wpdb` adapters, and transaction boundary

\* RT-110 — Fresh installation, partial upgrade, uninstall, query-plan, and database-engine acceptance

\* RT-201 — Batch administration create/list experience, capability boundary, and audited draft creation

\* RT-202 — Canonical six-character Tag ID value and cryptographically secure candidate generator

\* RT-203 — Insert-first Tag ID collision handling with duplicate-only bounded retry



\* RT-204 — Resumable 100-Tag Action Scheduler generation with atomic Batch progress

\* RT-205 — Administrative confirmation, committed generation progress, queue health, and safe retry

\* RT-206 — Complete Batch Tag ID inventory projection and paginated admin list

\* RT-207 — Audited deterministic CSV export and export history

\* RT-208 — Batch release, suspension, and permanent void controls

\* RT-209 — Exact Tag ID and Batch Code search with read-only Tag administration

\* RT-210 — Million-row capacity acceptance and Milestone 2 version `0.3.0`



Do not reimplement or redesign these items without first inspecting the existing implementation and receiving explicit approval.



\## Current development environment



The project uses Docker and `@wordpress/env`.



Known environment for Milestone 2 acceptance:



\* Required Node.js major version: 24

\* Node.js constraint: `>=24.0.0 <25`

\* `@wordpress/env`: `11.11.0`

\* PHP in the primary wp-env CLI container: `8.4.23`

\* WordPress: `7.0.2`

\* WooCommerce: `10.9.4`

\* TagCore: `0.3.0`

\* TagCore status: active

\* Development site port: `8888`

\* Test site port: `8889`



The required Node.js version is also recorded in `.nvmrc`.



\## Important environment warning



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



\## Package management warning



The repository currently contains both npm- and pnpm-related files, including:



\* `package-lock.json`

\* `pnpm-lock.yaml`

\* `pnpm-workspace.yaml`



The current `package.json` scripts use npm.



Do not update dependencies, regenerate lock files, switch package managers, or delete either lock file until this situation has been audited in a dedicated task.



\## Existing project commands



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



\## Destructive commands



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



\## Git workflow



\* `main` is the stable baseline branch.

\* New work must use a dedicated feature branch.

\* Handle one RT ticket at a time.

\* Run applicable validation before declaring a ticket complete.

\* Do not commit secrets, local credentials, databases, caches, dependency directories, or Codex authentication files.

\* Do not continue to another ticket without explicit approval.



Recommended feature branch format:



```text

feature/rt-XXX-short-description

```



\## Required takeover procedure



Before modifying files, a new Codex session or account must:



1\. Read `AGENTS.md`.

2\. Read this file.

3\. Read `README.md` and the relevant product requirements.

4\. Run `git status`.

5\. Inspect the current branch and recent commits.

6\. Inspect the implementation of RT-001 through RT-008 and RT-101 through RT-110.

7\. Inspect `package.json`, `.wp-env.json`, and GitHub Actions workflows.

8\. Check the running Docker containers.

9\. Report the verified project state before making changes.



The new session must not assume that account-level chat history is available.



\## Next work



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



Do not infer or begin the next milestone automatically. Wait for the user to provide or approve the next RT ticket and acceptance criteria.
