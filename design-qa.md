# RT-314 Stage 3.1 and desktop-width follow-up Product Design QA

## Comparison target

- Source visual truth: `docs/design/homepage.png`
- Source pixels: `816 x 1927`; the original CSS viewport and pixel density are
  unavailable, so exact CSS-density parity cannot be asserted.
- Implementation route: `http://localhost:8888/`
- Browser: the user's connected Chrome session; device scale factor `1`
- CSS viewports: `1440 x 900`, `816 x 900`, `390 x 844`, and `320 x 720`
- States: public homepage and the TagCore-owned Activate modal

The source is used for visual hierarchy and composition only. Its obsolete
claims, domains, QR codes, identifiers, and unapproved product statements are
not product facts and were not copied into the Theme.

## Final visual evidence

- Desktop homepage (`1440 x 900` CSS viewport):
  `artifacts/design-qa/rt-314-stage3-1/09-implementation-final-desktop-1440.png`
  (`1425 x 4256` captured pixels; the scrollbar occupies the remaining width).
- Tablet homepage (`816 x 900`):
  `artifacts/design-qa/rt-314-stage3-1/06-implementation-final-816.png`
  (`801 x 3786`).
- Mobile homepage (`390 x 844`):
  `artifacts/design-qa/rt-314-stage3-1/10-implementation-final-390.png`
  (`375 x 5960`).
- Narrow mobile homepage (`320 x 720`):
  `artifacts/design-qa/rt-314-stage3-1/05-implementation-320.png`
  (`305 x 6318`).
- Activate modal (`1440 x 900`):
  `artifacts/design-qa/rt-314-stage3-1/08-activate-modal-desktop.png`
  (`1425 x 660`).
- Full same-input comparison:
  `artifacts/design-qa/rt-314-stage3-1/11-reference-vs-final-816.png`
  (`1664 x 3842`).
- Focused Hero and products comparison:
  `artifacts/design-qa/rt-314-stage3-1/12-focused-hero-products-816.png`
  (`1664 x 1256`).
- Desktop-width follow-up (`1440 x 900`):
  `artifacts/design-qa/rt-314-stage3-2/homepage-1440.jpg`.
- Wide-desktop follow-up (`1920 x 1080`):
  `artifacts/design-qa/rt-314-stage3-2/homepage-1920.jpg`.

## Fidelity and interaction review

- Typography and tokens: existing local Manrope and Inter files, color tokens,
  radii, and component language remain unchanged.
- Hero and process: preserve the reference's two-column hierarchy at desktop
  and tablet sizes, with denser vertical rhythm and bounded official product
  imagery. Mobile retains a clear text-first reading order.
- Products: use the user-approved temporary official imagery in consistent
  `4:3` contain slots. Desktop cards now occupy the available content width;
  tablet uses two cards plus a full-width Smart Tag card.
- Use cases: compact four-column rail at tablet and desktop, changing to a
  balanced `2 x 2` grid on mobile.
- Header: one account control is rendered through the WooCommerce hook. At
  `390px` the brand, account, and Activate action remain on one row; at `320px`
  the Activate action moves to an orderly second row without overflow.
- Accessibility: primary and footer navigations have explicit accessible
  names; controls retain keyboard focus visibility and translatable strings.
- Activate interaction: opens the TagCore-owned modal, focuses the Tag ID
  input, closes with Escape, and restores focus. The Theme does not duplicate
  activation validation or domain behavior.
- Report interaction: remains a TagCore-owned full-screen entry below `768px`.
- Runtime: the final browser regression recorded no console errors. All product
  images are local Theme assets; no external runtime image request was added.
- Desktop-width follow-up: the process grid now uses the panel's full content
  width at `1440px` and a deliberate `76rem` readability cap at `1920px`. The
  privacy principles use three equal columns across the full wide container,
  and the privacy heading starts at the container edge instead of inheriting
  the constrained-content centering rule.
- Stylesheet delivery: the homepage stylesheet now uses its file modification
  time as the WordPress asset version, with the Theme version as a safe
  fallback. This prevents a previously cached Stage 3 stylesheet from masking
  a source-controlled layout update during acceptance.

## Findings and resolution history

- P1 tablet collapse and excessive page length: the earlier `816px` capture was
  `7079px` high. Resolved by retaining multi-column composition; the final
  capture is `3786px` high.
- P1 mobile header disorder: account and Activate controls previously separated
  unpredictably. Resolved with one-row `390px` and intentional two-row `320px`
  layouts.
- P1 desktop product grid too narrow: increased from about `768px` to `1310px`.
- P1 desktop process and privacy content too narrow: the process grid measured
  about `768px` inside a `1310px` panel, and the privacy title/grid inherited
  the `48rem` content constraint. Resolved with explicit wide block alignment,
  bounded process width, a full-width privacy grid, and start-aligned heading.
- P2 oversized product cards and media: resolved with bounded `4:3` slots;
  desktop cards are approximately `421 x 445px`, and tablet uses a `2 + 1`
  composition.
- P2 sparse use-case section: resolved with compact four-column and `2 x 2`
  responsive layouts.
- P2 footer navigation exposed the generic accessible name `2`: resolved with
  the explicit name `Footer navigation`.
- P2 Activate modal spacing: tightened helper-text spacing and increased the
  dialog surface padding.
- Intentional source deviations: the implementation retains approved,
  privacy-safe copy and official temporary product photography instead of the
  reference's obsolete claims and unapproved lifestyle imagery.

No actionable P0, P1, or P2 visual findings remain.

## Automated validation

- `node scripts/check-theme.mjs`: passed; 45 files, 17 pinned icons, two local
  fonts, and one approved logo.
- JavaScript lint, CSS lint, and TypeScript typecheck: passed after the final
  responsive and console-regression changes.
- `playwright test tests/e2e/theme-homepage.spec.ts --project=chromium`: passed,
  5 tests, including `1440px`/`1920px` width use, `816px`, `320px` at
  200-percent text, modal keyboard behavior, mobile Report routing, overflow,
  and console-error checks.
- `node --test tests/ci/*.test.mjs`: passed, 29 tests.
- Jest: passed, 8 suites and 37 tests.
- Admin, public, and entry-block production builds: passed.
- `node scripts/check-docs.mjs`: passed, 40 Markdown files, 12 links, 540 text
  files, and 9 assets.
- Container `composer check`: passed, including 257 PHPUnit tests and 2,325
  assertions.
- Container `composer test:integration`: passed, 196 tests and 2,146 assertions.

## Deferred P3 refinements

- Replace the temporary product photography when final release assets are
  supplied, harmonizing visible product marks such as FORGE, ForgeTag,
  SmartTag2, and the flag artwork.
- Consider a plugin-owned icon close control if TagCore later approves a
  bundled icon; the current text Close control is accessible and functional.
- Add lifestyle photography only when approved assets exist; the current
  compact text use-case rail avoids fabricated imagery.

final result: passed
