\# ReturnTag Project Status



\## Project identity



\* Repository: `wepuu/returntag`

\* Product: ReturnTag

\* WordPress plugin: TagCore

\* Plugin directory: `plugin/tagcore`

\* Current baseline version: `0.1.0`

\* Current completed milestone: Milestone 0 — Engineering Foundation

\* Current workstream: Milestone 1 — Database and Migration (RT-105 implemented; schema target version `4`)



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



Do not reimplement or redesign these items without first inspecting the existing implementation and receiving explicit approval.



\## Current development environment



The project uses Docker and `@wordpress/env`.



Known environment at the Milestone 0 handoff:



\* Required Node.js major version: 24

\* Node.js constraint: `>=24.0.0 <25`

\* `@wordpress/env`: `11.11.0`

\* PHP in the primary wp-env CLI container: `8.4.23`

\* WordPress: `7.0.2`

\* WooCommerce: `10.9.4`

\* TagCore: `0.1.0`

\* TagCore status: active

\* Development site port: `8888`

\* Test site port: `8889`



The required Node.js version is also recorded in `.nvmrc`.



\## Important environment warning



At handoff time, the wp-env Docker containers were running, but a normal PowerShell session previously could not resolve `node`, `npm`, or `npx`.



Before destroying, resetting, or recreating wp-env, verify that Node.js 24 and npm are available:



```powershell

node --version

npm --version

npx --version

```



The Node.js version must begin with `v24.`.



Do not destroy the current Docker environment merely to fix a missing PATH entry.



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

6\. Inspect the implementation of RT-001 through RT-008 and RT-101 through RT-105.

7\. Inspect `package.json`, `.wp-env.json`, and GitHub Actions workflows.

8\. Check the running Docker containers.

9\. Report the verified project state before making changes.



The new session must not assume that account-level chat history is available.



\## Next work



The next planned ticket after RT-105 is RT-106 (`0005` conversations and
`0006` messages table Migrations). It has not been authorized by this status
file.



Do not infer or begin the next milestone automatically. Wait for the user to provide or approve the next RT ticket and acceptance criteria.



