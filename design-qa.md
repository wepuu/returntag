# RT-301 / RT-302 Product Design QA

## Comparison target

- Source visual truth:
  `C:\Users\admin\.codex\generated_images\019fad15-ddd5-7941-b775-911bd9a46455\call_w1y4o35K5FFEEy4ekAbVOoVv.png`
- Source pixel dimensions: `853 x 1844`
- Implementation route: `http://localhost:8888/t/A7R2W9`
- Final mobile implementation screenshot:
  `artifacts/design-qa/rt-301-mobile-final-v2.png`
- Final desktop implementation screenshot:
  `artifacts/design-qa/rt-301-desktop-final-v2.png`
- Mobile viewport and CSS size: `390 x 844`
- Mobile implementation pixel dimensions: `390 x 844`
- Density normalization: source downsampled from `853 x 1844` to
  `390 x 844`; implementation captured at `deviceScaleFactor: 1`
- State: generic fail-closed public Tag service response

## Full-view comparison evidence

- Initial combined comparison:
  `artifacts/design-qa/rt-301-comparison-initial.png`
- Final combined comparison:
  `artifacts/design-qa/rt-301-comparison-final-v2.png`
- The final implementation preserves the selected option's warm paper-white
  field, compact wordmark, large quiet interval, blue vertical accent,
  uppercase recovery eyebrow, three-line heading, three-line explanation, and
  understated text link.
- The final section origin, heading width, line wrapping, action position, and
  overall density align closely with the normalized source.
- The `1440 x 900` desktop capture preserves the hierarchy without overflow
  and gives the explanation an intentional wider measure.

## Focused region comparison evidence

- Initial focused comparison:
  `artifacts/design-qa/rt-301-focus-comparison-initial.png`
- Final focused comparison:
  `artifacts/design-qa/rt-301-focus-comparison-final-v2.png`
- The focused evidence confirms that the accent, eyebrow, heading, explanation,
  and homepage action share the source alignment and vertical rhythm.
- Browser-system font rasterization differs slightly from the generated source,
  but the final optical weight, width, hierarchy, and wrapping are equivalent.

## Required fidelity surfaces

- Fonts and typography: system sans-serif rendering, optical weight, heading
  width, three-line wrap, eyebrow tracking, and link scale match the source.
  The implementation uses available local system fonts and no remote font.
- Spacing and layout rhythm: the mobile section origin is within a few pixels
  of the normalized source; content alignment, gaps, accent height, and link
  position preserve the selected composition. Desktop and short-height
  fallbacks do not overflow.
- Colors and tokens: warm `#fbfaf7` background, near-black `#182126` text, and
  ReturnTag blue `#0b57d0` match the selected visual direction with accessible
  contrast.
- Image quality and asset fidelity: the selected target contains no
  illustration, icon, or product image. The implementation introduces no
  substitute asset, SVG, emoji, remote image, or third-party font.
- Copy and content: the US-English heading, explanation, and homepage action
  are coherent, translatable, privacy-safe, and visually match the reference.

## Functional, responsive, and accessibility evidence

- Chromium, Firefox, WebKit, mobile Chromium, and mobile WebKit E2E verify
  `503` for `GET`, `405` for `POST`, documented privacy headers, generic copy,
  and no raw Tag ID reflection.
- Keyboard Tab reaches the homepage link and exposes the intended 3-pixel
  focus outline.
- The homepage link navigates to the site root.
- Mobile and desktop checks show no horizontal overflow.
- All captured requests remain on the local WordPress origin; there is no
  third-party tracking or asset request.
- There are no page exceptions. Chromium reports only the expected main
  resource `503` console message for this deliberate fail-closed state.

## Findings and resolution

- Initial P2: the recovery section began about 44 pixels below the source,
  changing the above-the-fold composition. The mobile vertical clamp was
  reduced and recaptured.
- Initial P2: wordmark, eyebrow, homepage link, body measure, and heading
  density drifted from the selected option. Their size, tracking, line measure,
  optical weight, and line height were corrected.
- Initial P2: body copy wrapped to two lines instead of the source's three.
  The mobile measure was bounded and recaptured.
- Follow-up P3: the desktop body measure was too narrow. A desktop-only measure
  restored balanced responsive rhythm.
- Final comparison has no actionable P0, P1, or P2 finding.

## Comparison history

1. The initial same-input comparison identified the low section origin,
   oversized supporting type, mismatched heading density, and two-line body.
2. CSS tokens and responsive measures were corrected; the first final
   comparison showed correct section position and copy rhythm but an
   under-weight, slightly narrow heading.
3. Heading optical weight, scale, and line height were tuned and recaptured.
4. The final full-view and focused comparisons confirmed alignment with the
   source. Desktop line measure received one P3 polish correction.

## Follow-up polish

- The generated source has slightly softer raster antialiasing than native
  Chromium system-font rendering. Shipping a remote or bundled display font
  solely to imitate that artifact is not justified for this privacy-sensitive
  fallback.

## RT-302 regression extension

- RT-302 intentionally changes routing behavior without changing the selected
  visual target, template, stylesheet, asset bundle, copy, focus behavior, or
  responsive layout.
- Normalizable input redirects to the canonical URL and then renders the same
  approved fail-closed state. Invalid input renders the same state without a
  redirect or validation-detail disclosure.
- The final Chromium, Firefox, WebKit, mobile Chromium, and mobile WebKit run
  passed all 20 route, privacy-header, canonicalization, mutation-method,
  keyboard, overflow, navigation, and third-party-request checks.
- Rebuilding both public and administrative assets produced no tracked visual
  asset delta, so the existing same-viewport reference comparisons remain the
  material visual evidence.

final result: passed
