# PublicSite

Public scan, activation, finder, and secure-link presentation adapters belong
here. Public routes use server rendering and progressive enhancement, with
validation, rate limits, safe errors, and privacy controls at the boundary.

RT-303 composes the canonical route with current Schema state, one read-only
Application resolver, server-derived WordPress identity, privacy-safe HTTP
semantics, and a standalone translatable renderer. Templates receive only a
pre-decided render view and never query or authorize.

RT-312 exposes the Theme-facing manual-entry adapter. The dynamic
`tagcore/tag-entry-link` block accepts only an `activate` or `report` display
intent and renders a normal same-site link as the no-JavaScript baseline.
Desktop JavaScript may progressively enhance that link into a native dialog;
mobile browsers continue to the plugin-owned full-screen route. Both surfaces
submit the same bounded, nonce-protected form, and a valid Tag ID receives a
`303` redirect to the canonical `/t/{tag_id}` authority. Entry templates never
query Tag or Batch state and never decide the resulting activation or Finder
experience.
