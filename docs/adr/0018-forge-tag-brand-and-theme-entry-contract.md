# ADR 0018: ForgeTag consumer brand and TagCore theme-entry contract

**Status:** Accepted

**Date:** 2026-08-01

**Scope:** Consumer naming and the future WordPress theme-to-TagCore entry seam

**Schema before/after:** `8 -> 8`

**Plugin before/after:** `0.4.0 -> 0.4.0`

## Context

The repository, plugin namespace, database prefixes, hooks, Options, and
existing internal architecture use the ReturnTag project name. The approved
consumer design baseline uses ForgeTag. Those identities need an explicit
boundary so a presentation change does not trigger unsafe renaming of stable
technical contracts.

The future block theme also needs stable Activate and Report entry points.
ADR 0017 assigns manual entry, state resolution, the desktop modal, the mobile
full-screen surface, and all product processing to TagCore, but it does not
define how a replaceable theme reaches those surfaces or falls back when
JavaScript is unavailable.

This ADR defines the contract only. The current TagCore runtime does not yet
implement the manual-entry endpoints or progressive-enhancement adapter, and
the current Finder Report and Owner Account screens remain incomplete.

## Decision

### Consumer and technical naming

- `ForgeTag` is the consumer-facing brand for navigation, page titles, CTA
  copy, product names, help content, Logo alternative text, transactional copy,
  and the WordPress theme.
- `ReturnTag` remains the internal project name and the stable technical name
  for the repository, PHP namespace, Composer package, database tables,
  Options, hooks, events, capabilities, release artifacts, and TagCore code.
- The theme directory and slug are `theme/forge-tag/` and `forge-tag`.
- The theme Text Domain is `forge-tag`.
- The obsolete `forgetag` spelling is not an alias and must not be introduced.
- Existing persisted technical identifiers are not renamed or migrated.

Consumer-copy migration in existing TagCore templates and email content is a
future implementation change. Until that change is reviewed and tested, this
documentation decision must not be represented as already changing runtime
copy.

### Stable manual-entry locations

TagCore will own these same-site, public manual-entry locations:

```text
GET /tag/activate/
GET /tag/report/
```

They are presentation entry points, not separate business workflows. Each
location displays a server-rendered six-character Tag ID form and carries only
an untrusted initial intent of `activate` or `report`. After TagCore normalizes
the submitted ID, it redirects to the canonical `/t/{tag_id}` location and the
existing server-side state resolver remains authoritative.

The implementation ticket may choose a safe internal submission method, but
it must define validation, rate limiting, CSRF reasoning, privacy-safe errors,
canonical redirect behavior, and automated tests before the endpoints are
released. It must not place an Owner ID, email address, access token, or other
private value in the entry URL.

TagCore also registers one server-rendered dynamic block as the stable theme
integration point:

```text
tagcore/tag-entry-link
```

The block requires one closed `intent` attribute with the value `activate` or
`report`. It may accept translatable presentation copy and standard block style
attributes, but it accepts no Tag ID, User ID, email, state, permission, or
redirect target. TagCore generates the same-site URL through WordPress APIs so
the theme does not assume a domain, site path, permalink prefix, or Multisite
layout.

### Theme contract

The ForgeTag theme places `tagcore/tag-entry-link` blocks, which render ordinary
same-origin links to the two TagCore locations.
The initial V1 brand page does not render a Tag ID input before a visitor
selects one of those links. The theme may control the surrounding layout and
approved brand design tokens, but it must not:

- register or emulate either manual-entry location;
- hard-code the manual-entry origin or route path;
- normalize, validate, query, or persist a Tag ID;
- query TagCore tables or resolve Tag, Batch, Owner, or feature-flag state;
- turn the selected intent into authorization or a forced workflow;
- reproduce TagCore forms, messages, secure links, or account operations;
- depend on undocumented TagCore markup or JavaScript internals.

WooCommerce is presentation-compatible with the theme but is not required for
TagCore activation, Finder entry, QR routing, or account authorization. The
theme must remain usable for brand content when WooCommerce is unavailable.
Theme V1 includes basic Block Theme presentation templates for the Shop
archive, single Product, Cart, and Checkout. They establish design-system and
responsive baselines only; they do not implement commerce business rules,
alter WooCommerce data, or constitute final visual approval without the
relevant page designs.

### Progressive enhancement and fallback

Without JavaScript, the block-rendered links navigate to standalone TagCore
pages and both manual-entry locations remain fully usable. With the approved
TagCore Script Module:

- desktop activation and reporting links may be progressively enhanced into a
  TagCore-owned modal;
- viewports below the approved `768px` product-flow breakpoint navigate to the
  TagCore-owned full-screen page rather than opening a centered modal;
- QR scans continue to navigate directly to `/t/{tag_id}` and never pass
  through a manual-entry page.

The modal must use semantic dialog behavior, associate its accessible title,
trap focus, support Escape, restore focus to the invoking link, and make the
background inert. It must not use an iframe. Closing the modal is not a
business mutation. A failed enhancement must leave the original link usable.

The selected Activate or Report entry may change only the initial heading and
guidance. An active Tag entered from Activate still resolves to Owner or
Finder according to server identity; an unregistered Tag entered from Report
still resolves to activation. Invalid, unavailable, suspended, retired, and
fail-closed states retain their existing policies.

### Styling and Site Editor governance

The theme may publish approved design tokens for TagCore to consume through a
documented CSS custom-property contract. It must not style TagCore through deep
DOM selectors. TagCore retains plugin-scoped defaults so its standalone pages
remain usable under another theme.

The source-controlled block theme is the production design baseline. Site
Editor changes intended for production must be exported, reviewed, tested, and
committed. Untracked database-only template or Global Styles changes are not a
release source of truth.

## Security and privacy requirements

- Manual entry is public input and requires bounded validation and abuse
  controls before runtime release.
- Entry responses and failures must not provide a bulk or differential Tag ID
  enumeration surface.
- Sensitive TagCore responses retain the approved no-store, no-referrer,
  no-index, framing, local-asset, and tracking restrictions.
- Intent, Tag IDs, actor identifiers, email addresses, and tokens must not be
  exposed to advertising pixels, session replay, or unnecessary analytics.
- Theme code and editor content are never authorization evidence.

## Consequences

- Theme work can use stable links before relying on modal enhancement.
- TagCore remains functional when the theme changes or JavaScript fails.
- The future TagCore adapter requires its own ticket, tests, version impact,
  and release review; this ADR does not implement it.
- Existing TagCore consumer copy may temporarily differ from the approved
  ForgeTag brand until a scoped runtime-copy migration is completed.
- Finder Report and Owner Account cannot be represented as complete merely
  because their entry links or theme shells exist.

## Rejected alternatives

- **Rename ReturnTag technical identifiers:** creates migration and backward-
  compatibility risk without consumer value.
- **Use `forgetag` as the theme slug or Text Domain:** reintroduces an obsolete
  name and conflicts with repository naming policy.
- **Theme-owned Tag forms or state lookup:** couples product security and
  business behavior to replaceable presentation code.
- **JavaScript-only modal entry:** removes the required standalone fallback.
- **Iframe the canonical Tag page:** conflicts with the framing policy and
  sensitive-page boundary.
- **Let Activate or Report select the final workflow:** bypasses the
  authoritative TagCore state resolver.

## Rollback

This decision changes documentation only. It creates no route, theme, block,
Script Module, Schema, Option, dependency, stored data, email, or external side
effect. Reverting the documentation does not rename or delete any technical
identifier. Future adapter behavior can be disabled while preserving the
canonical `/t/{tag_id}` route and standalone TagCore pages.
