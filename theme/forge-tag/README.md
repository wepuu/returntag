# ForgeTag Theme

**Status:** RT-314 Stage 5 TagCore integration baseline

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

Stage 3B adds the user-confirmed official Sticker, Classic Tag, and Smart Tag
sources through three SHA-256-pinned runtime images. Sticker and Classic Tag
use documented safety derivatives that remove or exclude every supplied QR
code, obsolete domain, and Tag ID; Smart Tag uses an exact runtime copy.
Source-design files are never loaded by WordPress, and visible model artwork
in the Smart Tag image is not approved as public copy.

RT-320 keeps the user-authorized Forge travel-lock family image and the supplied
commercial-history, sales-proof, marketplace-review, rating, and recovery-story
content as explicit development/local demo data. Production environments render
the PRD-backed brand-story facts and omit the testimonial demo Pattern. The
companion confidence Pattern explains browser-based QR recovery, private relay,
and the independent smart-network boundary. Generic Page, Search, and 404
Templates share the same responsive shell and give people clear recovery actions
without moving TagCore forms or state decisions into the Theme.

Stage 4 adds source-controlled Shop Archive, Single Product, Cart, and Checkout
Block Templates plus responsive and accessibility regression coverage. Cart
and Checkout continue to render their assigned page content, so WooCommerce
remains authoritative for those blocks. The Theme owns presentation only and
does not allocate Tag IDs, infer product state, or duplicate TagCore routes.
The independent release workflow validates the exact runtime allowlist and an
approved `forge-tag-v{version}` tag before assembling a `forge-tag/`-rooted ZIP
and SHA-256 checksum. It uploads workflow artifacts only; release approval and
deployment remain separate, explicit operations.

Stage 5 closes the tested integration seam without moving product behavior into
the Theme. Header and Hero keep two Activate and two Report placements through
`tagcore/tag-entry-link`; TagCore owns their generated URLs, desktop dialogs,
mobile and no-JavaScript pages, normalization, routing, state, and privacy
controls. Repository checks reject hard-coded TagCore paths, copied Tag ID
forms, and dependencies on plugin DOM internals across all Theme runtime files.
The compatibility matrix verifies entry without WooCommerce, TagCore routes
under a replacement Theme, and a safe brand shell when TagCore is disabled.

Production Site Editor changes must be exported to this directory, reviewed,
tested, and committed. Database-only templates or Global Styles are not a
release source of truth.

Review the repository PRD, the approved Theme boundary and brand-entry ADRs,
the design guide and asset manifest, and the release procedure before
extending the Theme.
