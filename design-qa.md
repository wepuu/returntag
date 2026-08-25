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

---

# RT-320 global shell, metadata, Search, and 404 Product Design QA

## Comparison target

- Frozen product contract: `docs/design/RT-319-STAGE-0-AUDIT.md`.
- Baseline actual-page evidence:
  `docs/design/qa/rt-319/01-home-1440.png`,
  `docs/design/qa/rt-319/04-home-390.png`,
  `docs/design/qa/rt-319/16-search-1440.png`, and
  `docs/design/qa/rt-319/17-404-1440.png`.
- Visual-language references: `docs/design/homepage.png`,
  `docs/design/dashboard.png`, `docs/design/tanchuang.png`, and
  `docs/design/html/`. The references do not override PRD, ADR, privacy, or
  Theme/TagCore boundaries and were not modified by RT-320.
- Implementation: `http://localhost:8893/` in the isolated RT-320 WordPress
  environment with ForgeTag Theme `0.1.0`, TagCore `0.5.0`, Schema `14`,
  WordPress `7.0.2`, and WooCommerce `10.9.4`.
- Browser: the user's connected Chrome session. Requested CSS viewports were
  `1440 x 900`, `1024 x 768`, `816 x 900`, `720 x 450` as the 200-percent
  equivalent, `390 x 844`, and `320 x 568`. Captured content widths exclude the
  visible scrollbar where present.
- States: Home, generic Page, Search results, Search empty, 404, mobile menu
  open/close, and keyboard skip-link focus.

## Final visual evidence

- Home: `docs/design/qa/rt-320/home-1440.png` (`1425 x 891`),
  `home-1024.png` (`1009 x 768`), `home-816.png` (`801 x 900`),
  `home-720-200pct.png` (`705 x 450`), `home-390.png` (`375 x 844`), and
  `home-320.png` (`305 x 568`).
- Content states: `page-1440.png`, `search-results-1440.png`,
  `search-empty-1440.png`, `search-empty-390.png`, `404-1440.png`, and
  `404-320.png` under `docs/design/qa/rt-320/`.
- Interaction and focus: `home-390-menu-open.png` and
  `404-320-keyboard-focus.png` under the same directory.
- Same-input comparison canvases:
  `comparison-home-1440.png` (`2850 x 943`),
  `comparison-home-390.png` (`750 x 864`),
  `comparison-search-1440.png` (`2865 x 943`), and
  `comparison-404-1440.png` (`2865 x 943`).
- A separate focused crop was not required: the desktop comparisons preserve
  each complete first viewport, while the `390px` Home comparison is already a
  focused header, Hero, CTA, and product-image review at matched state and size.

## Fidelity, behavior, and privacy review

- Home preserves the frozen ForgeTag header, typography, Hero, CTA hierarchy,
  product imagery, spacing, radii, and responsive rhythm at desktop and mobile.
  The combined captures show no unintended redesign relative to RT-319.
- Search now has a semantic, branded result heading, responsive result cards,
  and a labelled empty-state search form. The RT-319 attempted Search evidence
  rendered the Cart fallback instead; the combined comparison records the
  corrected page completeness rather than treating the Cart surface as visual
  truth.
- 404 now returns the actual HTTP `404 Not Found` status and renders a semantic
  H1, recovery explanation, Home, Activate, and Report actions. The RT-319
  baseline had no main 404 content.
- Generic Page, Search, and 404 titles use the `ForgeTag` consumer suffix;
  Home uses `ForgeTag`. Consumer copy remains translatable US English.
- Theme code controls presentation only. Activate and Report links use the
  existing TagCore entry routes; no Tag ID parsing, state, ownership,
  authentication, privacy, Schema, API, or database logic moved into Theme.
- Ratings, sales figures, tenure statements, and testimonials remain available
  only in the explicit local/development Demo presentation. Production paths
  omit testimonials and use evidence-safe confidence copy without inventing a
  recovery rate, location, shipping, certification, or supported-network claim.
- A repeated server-rendered dual-state check on 2026-08-24 confirmed that the
  local environment includes the visible `Demo content · development
  environment`, `Customer stories`, and `Millions` markers. With
  `WP_ENVIRONMENT_TYPE=production`, those markers were absent while the safe
  `A clear route back, without public contact details`, recovery-path, and
  privacy-confidence copy remained. The isolated environment was restored to
  `local` immediately after the check.
- Chrome confirmed semantic H1s, labelled Search, the mobile navigation dialog,
  focus restoration to `Open menu`, and a visible keyboard-accessible skip
  link. The reviewed tabs recorded no console warning or error. Loaded local
  images reported complete resources with non-zero natural dimensions.
- The 2026-08-24 Playwright acceptance rerun passed all RT-320-adjacent
  Chromium checks (`7/7`) and all Homepage Mobile Safari checks (`6/6`). The
  complete PR suite initially reported `38` passed, `2` skipped, and `7`
  environment-related failures: WooCommerce Coming Soon intercepted six
  Commerce states, and a duplicate same-origin Inter face made one Foundation
  network assertion order-dependent. After opening the isolated store, the
  Commerce subset passed `6/6` with a local-only extended fixture timeout; the
  revised Foundation contract passed `2/2` while retaining computed-font,
  Theme-Manrope, allowlist, and same-origin checks.

## Findings and resolution history

- [Resolved P1] A long user-supplied Search term expanded the mobile document
  from a `375px` content viewport to `519px`. The Search H1 now uses
  `overflow-wrap: anywhere`; the repeated `390px` Chrome capture measured both
  document and H1 scroll widths at `375px` or less. An E2E assertion protects
  this state.
- [Resolved QA interpretation] PowerShell initially summarized the custom 404
  response as `200`; a direct header check returned the authoritative
  `HTTP/1.1 404 Not Found`. No Theme status-code fix was needed.
- [Resolved completeness gaps] Search and 404 no longer fall through to Cart or
  a header/footer-only shell. Both surfaces now have explicit, recoverable
  states and real CTAs.
- [Resolved acceptance assertions] Homepage external-resource checks now bind
  to Playwright's configured base URL instead of hard-coding port `8888`.
  Mobile Header alignment compares the visible Brand and Activate controls
  within the existing `16px` tolerance rather than assuming their top-edge
  order. The Foundation font assertion permits WooCommerce to satisfy its
  duplicate same-origin Inter face while still requiring the Theme-owned
  Manrope request and the computed Inter body family.
- [Tooling note] A fresh Chrome-extension control session could not be acquired
  on 2026-08-24 because the control component timed out during initialization,
  despite the user-visible side panel reporting connected. No Theme rendering
  code changed after the preserved Chrome captures; those combined captures
  remain the visual evidence for this pass, supplemented by the fresh
  Chromium and Mobile Safari interaction results above.
- No actionable P0, P1, or P2 screenshot, interaction, responsive,
  accessibility, privacy, console, or resource finding remains in the reviewed
  RT-320 scope.

final result: passed

## RT-329 Stage 0 and governance-console contract

Stage 0 used the connected user Chrome at `1440 × 1000` against the local
WordPress Admin. Batches, Tags, Finder Reports, and Users screenshots establish
the existing visual contract: native WordPress shell, one compact horizontal
operations navigation, restrained status pill, white bordered cards, semantic
tables, and responsive stacking below the WordPress mobile breakpoint.

RT-329 extends that contract without a new framework: Role Profiles uses
responsibility cards and capability chips; Audit Log uses one exact-filter card
and chronological stream; Retention uses policy/health cards and an explicit
Task ID confirmation modal. All copy is translatable US English. No Theme logic,
custom icon asset, Finder identity, message, media reference, or private item
field is introduced.

Evidence captured before implementation:

- `tmp/rt329-chrome/01-batches-1440.png`
- `tmp/rt329-chrome/02-tags-1440.png`
- `tmp/rt329-chrome/03-finder-reports-1440.png`
- `tmp/rt329-chrome/04-users-1440.png`

The implementation-pass fixture is available locally from
`tmp/rt329-preview/` and uses the built TagCore Admin CSS plus the same
responsive card, stream, and confirmation states. Connected-Chrome control
timed out after the implementation build, so the final `1440`, `1024`, `816`,
`390`, `320`, 200-percent, keyboard, permission-matrix, console, and resource
checks remain an explicit release-gate item; this document does not mark them
as passed without Chrome evidence.

## RT-325 Secure Reply accessibility release gate

- Date: 2026-08-13
- Surface: TagCore `/secure-reply/`
- Baseline findings: message submission redirected without visible outcome;
  Owner/Finder messages had no persistent visual distinction; the terminal
  action inherited the normal blue submit style; the desktop composition left
  excessive empty space above a long thread; unavailable and terminal states
  had no same-site recovery action.
- Resolution: added one-use generic `sent`/`failed` feedback, role-aware message
  rails, a bounded session-status strip, a correctly ordered danger-action
  style, a denser Secure Reply layout, semantic status/alert regions, linked
  message help, and a ForgeTag home recovery link.
- Privacy contract: feedback describes local acceptance for background delivery
  and never claims provider delivery. No participant email, private item name,
  Tag ID, Token, challenge, Message queue state, media reference, or filename is
  rendered.
- Chrome evidence and responsive/accessibility results are frozen in
  `docs/design/RT-325-SECURE-REPLY-RELEASE-GATE.md`.

final result: passed

---

# RT-322 Design QA

## Evidence contract

- Source visual truth:
  - `C:/Users/admin/.codex/worktrees/74dc/ForgeTag/docs/design/tanchuang.png` (visual language only; its item categories, description, consent, commerce claims, and other unsupported fields are not product requirements).
  - `C:/Users/admin/.codex/visualizations/2026/08/11/019fefbe-8165-78d0-803d-288cb446e20d/rt-319/31-activate-dialog-1440.png` (`1425 x 891`, accepted RT-319 desktop baseline).
  - `C:/Users/admin/.codex/visualizations/2026/08/11/019fefbe-8165-78d0-803d-288cb446e20d/rt-319/26-activate-390.png` (`390 x 900`, accepted RT-319 mobile baseline).
- Implementation: local TagCore `/tag/activate/`, `/tag/report/`, and the real ForgeTag Header `tagcore/tag-entry-link` blocks.
- Browser: the user's connected Chrome, device scale `1`; no in-app browser or Figma was used.
- State: Activate/Report standalone ready pages, Activate/Report open desktop dialogs, client-invalid Activate dialog, Escape close/focus restoration, and mobile Header fallback navigation.

## Findings

- No actionable P0, P1, or P2 difference remains.
- [P3] At 390 and 320 CSS px, the dark Tag ID guidance region continues below the first viewport and requires a short vertical scroll. The primary input and Continue action remain above it, the document does not overflow horizontally, and the guidance is supplemental rather than a hidden required action.
- [P3] WordPress Core emits one Interactivity API deprecation warning for a generated `data-wp-init` directive. Chrome reported no page console error, and RT-322 does not own that runtime directive.

## Required fidelity surfaces

- Fonts and typography: the implementation keeps the established ForgeTag display/body pairing, heavy sentence-case heading, uppercase tracked eyebrow, and readable utility copy. The dialog retains the RT-319 hierarchy while the standalone page adds a distinct but token-compatible guidance heading.
- Spacing and layout rhythm: `09-dialog-rt319-vs-rt322.jpg` shows the dialog remains centered and bounded while gaining one clearly separated orientation block. `10-mobile-rt319-vs-rt322.jpg` shows the prior sparse full-height canvas replaced by a deliberate card and guidance rail without moving the core action below the fold.
- Colors and visual tokens: warm cloud, white surface, near-black ink, graphite copy, restrained borders, and Forge red are preserved. The dark guidance rail spends the ticket's visual emphasis on locating the physical ID and does not encode business state.
- Image quality and assets: the entry surfaces require no new raster or icon asset. No reference image, custom SVG, CSS drawing, placeholder, remote image, or third-party tracker was added.
- Copy and content: all new strings are translatable US English and explain only where to find the public Tag ID and that TagCore resolves the next step. They make no location, pairing, shipping, sales, subscription, certification, recovery-rate, or identity claim.

## Full-view and focused comparison evidence

- Desktop comparison: `09-dialog-rt319-vs-rt322.jpg` combines the same 1440 CSS viewport and open Activate state. Source and implementation are both `1425 x 891` captured pixels at device scale `1`.
- Mobile comparison: `10-mobile-rt319-vs-rt322.jpg` combines the 390 CSS-pixel standalone Activate state. The RT-319 source is `390 x 900`; the implementation viewport capture is `390 x 844`, aligned at the top without rescaling, on a `780 x 900` comparison canvas.
- Focused evidence:
  - `06-activate-dialog-1440.jpg`: ready Activate dialog.
  - `07-activate-dialog-error-1440.jpg`: localized visible client error associated with the focused Tag ID input.
  - `08-report-dialog-1440.jpg`: intent-specific Report dialog and private-recovery orientation.
  - `02-activate-standalone-390.jpg`, `03-activate-standalone-320.jpg`, and `04-activate-200pct-equivalent.jpg`: responsive standalone presentation.
  - `05-report-standalone-390.jpg`: Report standalone parity.

## Interaction and responsive verification

- Desktop Header links open native TagCore dialogs only at or above 768 px. Initial focus reaches the Tag ID input after the dialog animation frame; the native modal provides the focus trap and inert background.
- Invalid client input keeps the dialog open, focuses the input, sets `aria-invalid`, and exposes `Enter a valid six-character Tag ID.` in a `role="alert"` region using server-translated data.
- Escape closes the dialog and restores focus to the exact invoking Header link.
- At 390 px, the real Header Activate link navigates to `/tag/activate/` without opening a dialog. The committed E2E contract retains explicit JavaScript-disabled and failed-Script-Module fallback coverage.
- Chrome measurements found no horizontal overflow: 1440 dialog `scrollWidth = clientWidth = 1425`; 390 standalone `scrollWidth = clientWidth = 390`; 320 standalone `scrollWidth = clientWidth = 305`; 720 CSS-pixel 200%-equivalent `scrollWidth = clientWidth = 705`.
- The dialog measured `736 x 612.39` CSS px within the `1440 x 900` viewport, leaving safe space on every edge.
- GET entry responses returned `200`, `Cache-Control: no-store, private`, `Referrer-Policy: no-referrer`, and `X-Robots-Tag: noindex, nofollow, noarchive`.

## Comparison history

- RT-319 baseline: the dialog was accessible but intentionally minimal; standalone pages were usable yet visually sparse with little orientation or secondary guidance.
- RT-322 implementation pass: added shared intent-safe next-step copy, localized client-invalid feedback, a bounded dialog orientation block, and a standalone two-region card explaining where to find the printed public ID.
- Final visual pass: combined same-state reference/implementation captures were inspected together. The new guidance does not import forbidden fields from `tanchuang.png`, the core input/action remains primary, and no P0/P1/P2 mismatch remains.

## Follow-up polish

- A future shared-shell polish ticket may replace the text Close control with an approved icon-library asset if the Theme and TagCore adopt one consistent close treatment; RT-322 keeps the explicit accessible label and introduces no new asset dependency.

final result: passed

---

# RT-314 Homepage Detail Pass - Chrome QA

- Brand proof strip: at `1440px`, `Millions`, `Trusted`, and `Travel Brand`
  each fit their own text column with no wrapping or scroll overflow. The
  tablet composition switches the proof items to icon-above-text while
  preserving three columns.
- Customer stories: three static, equal-height cards use five local red Lucide
  stars, unchanged sentence excerpts, and local non-identifying illustrated
  avatars. No carousel controls, pagination dots, scripts, or remote assets are
  present.
- Responsive review: Chrome verified `1440px`, `1024px`, `816px`, `720px`
  effective-width, `390px`, and `320px`; document overflow and metadata
  overflow were zero at every measured width.
- Accessibility/runtime: review star rows have an accessible five-out-of-five
  label while individual icons are hidden; avatar images have empty alternative
  text because adjacent customer names identify the content. Chrome recorded no
  page console errors.

final result: passed

---

# RT-314 Brand Story Effect Match - Product Design QA

## Evidence

- Visual source: `C:\Users\admin\AppData\Local\Temp\codex-clipboard-2af81d9e-a3e6-4d1c-86bd-76090a394fbf.jpg`
  (`6505 x 2579`, RGB), supplied and approved by the brand owner.
- Implementation: `http://localhost:8888/`, reviewed in the user's connected
  Chrome session.
- Desktop capture: `brand-story-redesign-1440-final.png` at a `1440 x 900`
  viewport. The signed-in WordPress admin toolbar is excluded from normalized
  section comparison.
- Side-by-side comparison: `brand-story-redesign-comparison-view.png`, with the
  source on the left and the implementation on the right at matching section
  proportions.
- Responsive captures: `brand-story-redesign-816.png`,
  `brand-story-redesign-390.png`, `brand-story-redesign-390-image.png`, and
  `brand-story-redesign-320.png`.

## Final visual review

- Composition: the implementation uses the reference's flat, full-width light
  gray banner, approximately balanced text and product-image columns, generous
  whitespace, and bottom/right-aligned travel-lock artwork.
- Typography: the eyebrow, two-line heading, body copy, and proof hierarchy
  reproduce the source's scale and rhythm using the Theme's established local
  Manrope and Inter fonts.
- Content: the approved heading, paragraph, and all three proof values match the
  supplied source. The proof strip is a semantic three-item list.
- Assets: the product image is the approved local transparent lock-family
  asset at its natural `3377 x 2424` dimensions. The proof icons are pinned
  local Lucide assets with empty alternative text because their labels carry
  the meaning.
- Responsive behavior: the banner remains a two-column layout at `1440px` and
  `816px`, changes to text-first single-column layout below `768px`, and changes
  the proof list to divided rows below `600px`.
- Overflow: Chrome reported zero document overflow at `1440px`, `816px`,
  `720px`, `390px`, and `320px`. The `720px` effective CSS viewport represents
  the `1440px` layout at 200-percent scaling; the stricter `320px` case also
  passed without clipping.
- Runtime and accessibility: the homepage recorded no console errors; heading
  order, descriptive product alternative text, local asset delivery, and the
  primary Activate URL were confirmed in the rendered DOM.

## Findings and resolution history

- Initial mismatch: the heading wrapped to three lines and constrained child
  blocks did not share a common left edge. Resolved by restoring full content
  alignment and matching the source's text-column scale.
- Initial mismatch: the proof strip was visually compact relative to the source.
  Resolved with larger proof values, red outline icons, and vertical desktop
  separators that become horizontal mobile separators.
- Initial mismatch: the travel-lock artwork was undersized in the right column.
  Resolved with a bounded desktop scale and bottom/right alignment while
  disabling overflow on mobile.
- No actionable P0, P1, or P2 visual findings remain.

## Deferred P3 refinements

- The closest pinned Lucide icons intentionally differ in small glyph details
  from the source's custom calendar, chart, and shield artwork.
- Chrome automation did not expose a reliable browser-chrome zoom percentage;
  the review therefore used the equivalent `720px` CSS viewport plus the
  stricter `320px` viewport rather than claiming a measured toolbar zoom state.

final result: passed

---

# RT-315 Stage 3 Finder Report - Product Design QA

## Evidence

- Source visual truth: `D:\Codex\ForgeTag\docs\design\html\finder-report.html`.
- Captured reference raster:
  `C:\Users\admin\.codex\visualizations\2026\08\01\019fbc50-b84a-75b3-9b63-9b1b0fd6ae42\flow-plan-audit\finder-reference.png`
  at `1018 x 568` pixels.
- Implementation: `http://localhost:8888/t/A7R2W9` in the local WordPress
  environment, using an active owned `classic_tag` QA fixture.
- Final implementation captures:
  `rt315-stage3c-finder-320-final.png`,
  `rt315-stage3c-finder-390-final.png`, and
  `rt315-stage3c-finder-1024-final.png` under the visualization directory above.
- Completed-flow captures:
  `rt315-stage3c-finder-review-390.png` and
  `rt315-stage3c-finder-accepted-390.png` under the same directory.
- Combined review input:
  `rt315-stage3c-finder-comparison.png` under the same directory.
- Chrome viewports: `1024 x 768`, `768 x 1024`, `720 x 900` as the 200-percent
  equivalent, `390 x 844`, and `320 x 720`, all at device scale factor 1.

## Findings and resolution history

- [Resolved P0] The public composition root checked Action Scheduler before its
  `plugins_loaded` initialization, so the Finder form remained fail-closed even
  though the queue became available later. Queue availability is now checked
  dynamically at the form boundary before rendering or submission.
- [Resolved P1] The sensitive route CSP used `default-src 'none'` without an
  explicit script policy, so Chrome blocked the same-origin two-step enhancer.
  The policy now permits only `script-src 'self'`; it still excludes inline
  script, `unsafe-eval`, and third-party script origins.
- [Resolved P2] Initial enhancement focused the step legend and displayed an
  unintended browser outline on first paint. Initial setup no longer steals
  focus; transitions still move focus to the destination legend.
- [Resolved QA environment] The first real POST failed closed because the
  local wp-env Apache worker runs as `admin` while the private-media root was
  owned by `www-data` with mode `0700`. The local root was reassigned to the
  actual worker account without changing application permissions or storage
  policy. The repeated Chrome POST then completed successfully.
- No remaining screenshot-based P0, P1, or P2 layout finding was observed in
  the report-details, review, or accepted states.

## Required fidelity surfaces

- Typography, spacing, rounded cards, progress treatment, Forge red accents,
  neutral surfaces, and primary/secondary action hierarchy follow the reference
  language while retaining the current TagCore design system.
- The approved contract intentionally replaces the reference's Finder identity
  fields with an optional 10–500 character message and one required evidence
  photo. Chrome confirmed that no email or name input is rendered.
- Chrome confirmed one visible step at a time after enhancement, required photo
  semantics, native invalid-message and invalid-photo feedback, a visible
  `3px` keyboard focus outline, and no console warnings or errors.
- Chrome confirmed the approved local image and optional message on the review
  step, preserved both values after Back, and rendered the privacy-safe
  `Report received` receipt after a real POST.
- Document `scrollWidth` equalled `clientWidth` at every reviewed viewport;
  320-pixel and 200-percent-equivalent layouts had no horizontal overflow.

## Runtime evidence

- The accepted local report reached `report_status=ready` and
  `evidence_status=ready`; its Action Scheduler processing action completed.
- The source was detected as a `1000 x 1000` JPEG. Processing produced a
  `1000 x 1000` review derivative and an `800 x 800` email derivative.
- Stored private objects were owned by the local web worker with mode `0600`.
  No owner notification or two-way conversation was started.

final result: passed
## RT-316 Stage 7A participant safety controls

- Date: 2026-08-10
- Surface: TagCore `/secure-reply/`
- Change: added role-specific Finder `End conversation` and Owner `Report and
  block` controls with an explicit confirmation checkbox and generic terminal
  feedback.
- Automated result: PHP template semantics, local-only assets, labels,
  translatable copy, focus-visible styling, no private form identifiers,
  Stylelint, TypeScript, Node contracts, and production build passed.
- Chrome visual result: pending. Chrome automation was unavailable in the
  current session, and the in-app browser was intentionally not used because
  the project requires Chrome for visual acceptance.
- Required manual states: Owner thread, Finder thread, unchecked confirmation,
  keyboard focus on checkbox and terminal button, generic terminal state,
  1440px, 390px, 320px, and 200% zoom.
- Final result: automated checks passed; Chrome visual acceptance pending.

## RT-317 Stage 2 Owner Tag editing

- Date: 2026-08-10
- Surface: TagCore `/account/tags/{tag_id}/`
- Change: added active/current-Owner metadata, Lost Mode, Lost Message, and
  Smart Setup acknowledgement forms.
- Chrome desktop result: the `1920 × 945` local Classic Tag page rendered one
  H1, two semantic H2 edit cards, equal desktop columns, labelled controls,
  visible local actions, no console warnings or errors, and no horizontal
  overflow.
- Chrome mobile result: the `390 × 844` viewport collapsed to one `335px`
  content column; all text inputs and the textarea stayed within their card,
  help copy remained readable, and document `scrollWidth` equalled
  `clientWidth`.
- Runtime result: a local synthetic current Owner successfully enabled Lost
  Mode with approved guidance and received `Your Tag settings were updated.`;
  the rendered detail immediately showed the persisted state.
- Smart Tag result: a local synthetic Smart Tag displayed the non-pairing
  disclaimer, accepted the acknowledgement once, replaced the action with
  `Setup guide acknowledged. This is not pairing verification.`, and showed
  no horizontal overflow.
- Privacy review: no Owner email, browser-supplied Owner ID, cross-party
  identifier, remote asset, or pairing/location/device claim appeared in the
  reviewed Account pages.

final result: passed

## RT-317 Stage 3 Owner Conversation browser

- Date: 2026-08-11
- Surface: TagCore `/account/conversations/` to `/secure-reply/`
- Change: added bounded current-Owner status/activity cards and an explicit
  nonce-protected `Continue securely` POST using the existing Secure Reply
  session contract.
- Chrome desktop result: at `1440 × 900`, the page rendered one H1, one
  `552px` summary card, one explicit POST action, balanced status/activity
  hierarchy, and no horizontal overflow.
- Chrome responsive result: `720px` 200%-equivalent, `390 × 844`, and
  `320 × 720` layouts retained one readable column, full-width mobile action,
  and `scrollWidth === clientWidth`.
- Keyboard result: Tab reached ForgeTag, My Tags, Conversations, and Continue
  securely in order; every target exposed a visible `2px` focus outline.
- Runtime result: Continue securely redirected to same-site `/secure-reply/`
  without a bearer query and rendered the existing Owner relay workspace.
- Privacy result: no participant email, Message body, Access Token, evidence
  or media reference, filename, console warning, or console error appeared on
  either reviewed surface.
- Automated result: Stage 3 unit, WordPress integration, persistence, PHP
  static analysis, PHP coding standards, documentation, CI Node, Stylelint,
  and TypeScript checks passed before the final full regression.

final result: passed
