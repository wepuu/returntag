# ForgeTag Theme

**Status:** RT-314 stage 2 design-system foundation

**Version:** `0.1.0`

**Directory and slug:** `theme/forge-tag/`, `forge-tag`
**Text Domain:** `forge-tag`

ForgeTag is the replaceable consumer-presentation Theme for the ReturnTag
platform. TagCore remains the independent product application and owns Tag
entry, normalization, state resolution, authentication, authorization,
privacy controls, and business mutations.

Stage 2 contains the minimum Block Theme structure plus the approved Global
Styles tokens, scoped frontend/editor foundation CSS, production-approved
ForgeTag logo, local Manrope and Inter variable fonts, and exact Lucide icon
allowlist. `asset-manifest.json` records source versions, licenses,
transformations, and runtime SHA-256 values.

Run `npm run sync:theme-icons` only when intentionally regenerating the pinned
Lucide files, then run `npm run check:theme` to verify Theme identity, token
contracts, local assets, licenses, hashes, icon scope, and presentation-layer
boundaries.

This stage does not implement homepage Patterns, TagCore entry blocks,
WooCommerce Shop/Product/Cart/Checkout Templates, business logic, packaging
automation, or final page-level visual acceptance.

Production Site Editor changes must be exported to this directory, reviewed,
tested, and committed. Database-only templates or Global Styles are not a
release source of truth.

Review the repository PRD, the approved Theme boundary and brand-entry ADRs,
the design guide and asset manifest, and the release procedure before
extending the Theme.
