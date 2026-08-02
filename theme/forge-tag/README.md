# ForgeTag Theme

**Status:** RT-314 Stage 3B product-media and visual-acceptance baseline

**Version:** `0.1.0`

**Directory and slug:** `theme/forge-tag/`, `forge-tag`
**Text Domain:** `forge-tag`

ForgeTag is the replaceable consumer-presentation Theme for the ReturnTag
platform. TagCore remains the independent product application and owns Tag
entry, normalization, state resolution, authentication, authorization,
privacy controls, and business mutations.

Stages 1 and 2 contain the minimum Block Theme structure plus the approved Global
Styles tokens, scoped frontend/editor foundation CSS, production-approved
ForgeTag logo, local Manrope and Inter variable fonts, and exact Lucide icon
allowlist. `asset-manifest.json` records source versions, licenses,
transformations, and runtime SHA-256 values.

Stage 3A adds the source-controlled front-page Template and reusable Header,
Footer, Hero, Return Route, product-family, recovery-path, use-case, and privacy
Patterns. Activate and Report actions remain TagCore-owned dynamic blocks;
Report uses the plugin-owned `secondary` Block Style. The Theme does not copy
Tag ID forms, infer product state, or hard-code plugin entry routes.

Run `npm run sync:theme-icons` only when intentionally regenerating the pinned
Lucide files, then run `npm run check:theme` to verify Theme identity, token
contracts, local assets, licenses, hashes, icon scope, and presentation-layer
boundaries.

This stage does not implement WooCommerce Shop/Product/Cart/Checkout
Templates, business logic, packaging automation, or final page-level visual
acceptance. Stage 3B adds the user-confirmed official Sticker, Classic Tag, and
Smart Tag sources through three SHA-256-pinned runtime images. Sticker and
Classic Tag use documented safety derivatives that remove or exclude every
supplied QR code, obsolete domain, and Tag ID; Smart Tag uses an exact runtime
copy. Source-design files are never loaded by WordPress, and visible model
artwork in the Smart Tag image is not approved as public copy.

Production Site Editor changes must be exported to this directory, reviewed,
tested, and committed. Database-only templates or Global Styles are not a
release source of truth.

Review the repository PRD, the approved Theme boundary and brand-entry ADRs,
the design guide and asset manifest, and the release procedure before
extending the Theme.
