# ForgeTag Homepage Trust Sections — Design QA

## Evidence

- Source visual truth: `docs/design/homepage.png` (`816 x 1927` pixels).
- Implementation: `http://localhost:8888/`, public homepage, logged-out state.
- Browser-rendered full view: `C:/Users/admin/.codex/visualizations/2026/08/01/019fbc50-b84a-75b3-9b63-9b1b0fd6ae42/homepage-trust-816-final-full.png` (`801 x 4877` pixels).
- Focused comparison: `C:/Users/admin/.codex/visualizations/2026/08/01/019fbc50-b84a-75b3-9b63-9b1b0fd6ae42/homepage-trust-comparison-final.png` (`1642 x 1260` pixels).
- Viewport: `816 x 900` CSS pixels; the full-page screenshot records the `801`-pixel content width after the browser scrollbar. No density resampling was applied to the implementation capture.
- Responsive checks: `390 x 844`, `320 x 720`, and browser zoom at `200%`.

## Findings

- No remaining P0, P1, or P2 findings.
- Fonts and typography: the existing local Manrope display and Inter body system is preserved. Heading weight, review body line height, and utility-label tracking remain consistent with the established homepage.
- Spacing and layout rhythm: the brand story keeps the reference's text/image split. The review grid presents three equal-height cards at `816px` and above, then stacks into one column on mobile. The longer approved reviews make the cards taller than the reference; this is an accepted content-driven difference and no text is truncated.
- Colors and visual tokens: the sections use only the approved cloud, surface, ink, graphite, line, and Forge red tokens. Shadows, borders, and radii reuse the existing Theme contracts.
- Image quality and asset fidelity: the supplied `3377 x 2424` RGBA travel-lock image is rendered as a local, contained product image without stretching, external loading, or replacement artwork.
- Copy and content: the implementation deliberately omits the reference's unverified founding year, sales volume, TSA, and trust claims. It uses three user-supplied reviews and does not publish the Smart Tag review containing tracking and battery language.
- Accessibility and behavior: the sections expose one H2 per section, three `figure`/`blockquote`/`figcaption` review structures, descriptive image alternative text, no horizontal overflow at the checked mobile sizes, and no new interactive controls. The existing Activate entry opened its TagCore dialog, focused the Tag ID field, and closed normally. Chrome reported no page console errors.

## Comparison History

1. Initial `816px` comparison found two P2 differences: the reviews formed a `2 + 1` tablet layout instead of the reference's three-card row, and a large invented review headline repeated the preceding “Made for travel” message.
2. The review breakpoint was changed to retain three columns at `816px`, and the redundant headline was reduced to the compact semantic heading `Customer stories`.
3. The final focused comparison shows the intended brand split and three-card review rhythm. Remaining differences are required by verified-content and accessibility constraints rather than unresolved design drift.

## Implementation Checklist

- Brand and testimonial Patterns appear between use cases and privacy.
- Travel-lock asset is local, manifest-pinned, and release-allowlisted.
- Three approved reviews render with rating, display name, product, buyer status, and source.
- Desktop, tablet, mobile, zoom, overflow, primary entry behavior, and console state were checked in Chrome.

## Follow-up Polish

- P3: reconsider card density only if shorter, customer-approved review excerpts become available; do not truncate or rewrite the current quotes automatically.

final result: passed

