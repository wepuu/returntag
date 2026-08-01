# ADR 0017: WordPress theme and TagCore frontend delivery boundary

**Status:** Accepted

**Date:** 2026-07-31

**Scope:** Phase-one website and product-flow presentation

**Schema before/after:** `8 -> 8`

**Plugin before/after:** `0.3.0 -> 0.3.0`

**Terminology amendment:** ADR 0018 establishes `ForgeTag` as the consumer
brand and retains `ReturnTag` for the internal project and technical
identifiers. That terminology amendment does not change this ADR's ownership
or security decisions.

## Context

ForgeTag must present one coherent website containing the brand site,
WooCommerce shop, Tag activation, Finder reporting, and authenticated Owner
account. Visitors may begin activation or reporting from the website, while a
QR scan already supplies the public Tag ID through `/t/{tag_id}`.

The active theme is replaceable presentation infrastructure. Moving route
registration, Tag state decisions, access control, form processing, or
business actions into a theme would make the core recovery product dependent
on `functions.php`, page templates, or a page builder. Maintaining separate
desktop and mobile implementations would also create divergent security,
privacy, state, and accessibility behavior.

The existing public route is a standalone TagCore route, and the approved
frontend baseline is PHP server rendering with optional WordPress
Interactivity API progressive enhancement and plugin-scoped CSS.

## Decision

ForgeTag uses one WordPress site with two explicit frontend responsibilities:

- the ForgeTag WordPress block theme owns the brand shell, navigation, footer,
  editorial content, support content, and WooCommerce presentation;
- TagCore owns the product application, including public Tag entry, activation,
  Finder reporting, secure links, authenticated Owner views, and every related
  route, state, access-control, privacy, and business decision.

The website manual-entry interaction is:

```text
desktop brand page
-> visitor selects Activate or Report
-> TagCore-owned modal opens
-> visitor enters the six-character Tag ID
-> TagCore normalizes and resolves the canonical Tag state
-> TagCore presents activation, Finder, Owner, invalid, or explanation state
```

The brand page does not display a Tag ID field before the visitor selects
`Activate` or `Report`. The selected button records presentation intent only.
It never authorizes a workflow and never overrides the server-derived Tag
state. An active Tag entered through the activation modal may converge to the
Finder or Owner state; an unregistered Tag entered through the report modal
may converge to activation.

On mobile, selecting `Activate` or `Report` opens a TagCore-owned full-screen
manual-entry surface rather than a modal. A QR scan bypasses manual entry and
navigates directly to the canonical `/t/{tag_id}` route. The Tag ID must not be
requested again after a QR scan.

TagCore exclusively owns:

- `/t/{tag_id}` route registration and canonical URL generation;
- Tag ID normalization and validation;
- Tag and Batch lookup and state resolution;
- current-user adaptation and server-side ownership decisions;
- access control, nonces, rate limits, privacy headers, output escaping, and
  abuse controls;
- activation, Finder, secure-reply, and Owner-account business processing;
- the reusable modal and full-screen product-flow presentation adapters.

The theme may place TagCore blocks or integration points and provide approved
brand design tokens. It must not query TagCore tables, derive Tag state, submit
an Owner identifier as authority, reproduce product forms, or implement
business mutations.

The canonical `/t/{tag_id}` route remains independently usable when the active
theme changes or when JavaScript is unavailable. Desktop modal behavior is
progressive enhancement, not the only way to complete a flow. The standalone
route must not be embedded through an iframe; its `frame-ancestors 'none'`
control remains authoritative.

TagCore public and account UI continues to use PHP server rendering, semantic
HTML, plugin-scoped CSS, and optional Interactivity API Script Modules.
Tailwind, a global CSS reset, and a separate Next.js frontend are not part of
the phase-one production architecture.

Generated low-fidelity wireframes are non-normative references. They do not
freeze visual style, layout, copy, or component appearance. Future
user-supplied page-effect designs must be reviewed against this ADR and the
security, privacy, accessibility, and responsive-flow requirements before
becoming the approved visual target.

## Consequences

- Theme replacement cannot remove or alter the canonical Tag route or its
  business behavior.
- Desktop modal, mobile manual entry, and QR entry reuse one authoritative
  TagCore state model instead of separate state machines.
- The future theme can evolve brand and commerce presentation without owning
  activation, Finder, or Owner-account rules.
- TagCore must provide integration surfaces that fail safely back to the
  standalone route when progressive enhancement is unavailable.
- Sensitive TagCore pages retain no-store, no-referrer, no-index, local-asset,
  and no-unnecessary-tracking controls even when entered from the brand site.
- A future theme implementation requires its own versioning, compatibility,
  deployment, and rollback plan before release.
- This ADR changes no route, Schema, Option, data, dependency, runtime asset,
  plugin version, or existing behavior by itself.

## Rejected alternatives

- **Core workflows in a WordPress theme:** couples business behavior to a
  replaceable presentation layer and violates the plugin boundary.
- **Separate Next.js and Tailwind frontend in phase one:** duplicates routing,
  authentication, WooCommerce session, deployment, caching, and security
  responsibilities without an approved headless platform requirement.
- **Separate desktop and mobile business flows:** creates inconsistent state,
  authorization, validation, and recovery behavior.
- **Iframe embedding of `/t/{tag_id}`:** conflicts with the sensitive-page
  framing policy and weakens the independent-route boundary.
- **Always-visible Tag ID field on the brand page:** contradicts the approved
  intent-first website interaction.
- **CTA-selected workflow overrides Tag state:** allows presentation intent to
  bypass the authoritative server state machine.

## Rollback

This ADR is documentation-only. It creates no code, route, Schema, Option,
asset, theme, data, or external side effect. A future presentation integration
may be disabled while preserving the canonical TagCore route and standalone
pages. Rollback must never move business logic into a theme, weaken the public
route security controls, clear ownership, delete audit evidence, or reuse a
Tag ID.
