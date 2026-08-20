# RT-319 Stage 0 frontend audit and visual contract

- **Issue:** [RT-319](https://github.com/wepuu/returntag/issues/65)
- **Audit status:** Stage 0 complete; visual contract frozen with named fixture blockers
- **Original screenshot run:** 2026-08-11
- **Closure review:** 2026-08-20
- **Current Git baseline:** `origin/main` at `8c774dc`
- **Runtime:** isolated local `wp-env` at `http://localhost:8888/`; ForgeTag active; TagCore `0.5.0` at Schema `14`; WooCommerce active
- **Browser:** the user's Chrome through the Codex Chrome extension; no Figma or in-app browser was used

## Scope and evidence rule

RT-319 is a documentation-only Stage 0 audit. It freezes the cross-surface
visual contract and records gaps for RT-320 through RT-325. It does not redesign
or implement pages, alter Theme or TagCore runtime behavior, change Schema or
public APIs, add dependencies, modify lock files, enable production options,
deploy, or create a release.

The original Chrome audit produced 38 historical screenshots outside the
repository under:

`C:/Users/admin/.codex/visualizations/2026/08/11/019fefbe-8165-78d0-803d-288cb446e20d/rt-319/`

The 2026-08-20 closure run produced 23 new screenshots under:

`C:/Users/admin/.codex/visualizations/2026/08/11/019fefbe-8165-78d0-803d-288cb446e20d/rt-319-current/`

A privacy-reviewed selection is stored in `docs/design/qa/rt-319/`. Only the
2026-08-20 screenshots are used as closure evidence. No email, user identity,
message, token, OTP, private item name, location, or uploaded media appears in
the selected evidence.

The user-supplied `dashboard.png` and `docs/design/html/` remain untracked in the
original worktree and were not copied, rewritten, or staged. The tracked
`homepage.png` and `tanchuang.png` were also left unchanged.

### Durable evidence selection

- [Home desktop](qa/rt-319/01-home-1440.png) and
  [Home mobile](qa/rt-319/04-home-390.png)
- [Shop empty state](qa/rt-319/07-shop-1440.png)
- [Activate standalone](qa/rt-319/10-activate-1440.png),
  [desktop dialog](qa/rt-319/18-activate-dialog-1440.png), and
  [mobile path](qa/rt-319/19-activate-mobile-390.png)
- [Report mobile path](qa/rt-319/21-report-mobile-390.png)
- [Invalid Public Tag](qa/rt-319/12-public-invalid-1440.png)
- [Account fail-closed](qa/rt-319/13-account-signin-1440.png)
- [Search](qa/rt-319/16-search-1440.png) and
  [404](qa/rt-319/17-404-1440.png)
- [Keyboard focus](qa/rt-319/23-home-keyboard-focus-1440.png)

## Reproduction

1. Start Docker Desktop and the repository WordPress environment.
2. Confirm the ForgeTag Theme and TagCore plugin are active.
3. Confirm pretty permalinks are initialized with the CI-equivalent WordPress
   setup; do not flush rewrite rules on normal requests.
4. In the user's Chrome, open `http://localhost:8888/`.
5. Audit 1440, 1024, 816, 390, and 320 CSS-pixel widths, plus 720 CSS pixels as
   the 200% equivalent of a 1440-wide desktop viewport.
6. Follow the real Header Activate and Report links. At 768px and above they may
   progressively enhance to a TagCore dialog; below 768px they navigate to the
   standalone TagCore entry page. QR scans always navigate directly to
   `/t/{tag_id}`.
7. Exercise only approved synthetic fixtures. Do not create production-like
   identities, submit real evidence, exchange real tokens, or alter user-owned
   data for visual coverage.
8. Record page console errors, failed resources, focus order, announcements,
   overflow, image crop, back/refresh behavior, and response security headers.

## Executive result

Chrome confirms a coherent ForgeTag shell, responsive Home layout, desktop
TagCore dialog, mobile full-page Tag entry, invalid Public Tag state, Account
fail-closed state, and expired/unavailable Secure Reply state. Home reflowed at
1440, 1024, 816, 720, 390, and 320 CSS pixels without horizontal overflow.
Header and hero entry links are real links, JavaScript progressively enhances
the desktop Header action, and mobile Header/hero actions navigate to the
standalone TagCore pages. Keyboard focus is strongly visible and begins with the
Skip link.

The frontend is not release-ready. The Home page contains unsupported history,
sales, marketplace, rating, and recovery-story claims. Public Tag presentation
uses the internal ReturnTag name. Search has no query/result-page H1 and the 404
main region is empty. The default generic page exposes WordPress sample copy and
an administrator link. Shop renders a usable empty state and Cart redirects
Checkout to its empty state, but there is no approved Product fixture for a
Single Product or transactional Checkout review. Account and Public Tag state
coverage remain blocked by intentionally absent approved identities and
fixtures.

RT-320 through RT-324 remain the implementation and acceptance boundary for
these gaps. RT-325 is already merged and owns the valid/terminal Secure Reply
regression matrix.

## Page and state matrix

| Surface or state | 2026-08-20 actual-page result | Required closure or named blocker |
|---|---|---|
| Home, Header, Footer | Audited at 1440, 1024, 816, 390, and 320; no horizontal overflow; real Activate/Report links present | RT-320 must remove unsupported claims, fix metadata, and repeat Chrome acceptance. |
| 200% equivalent | Audited at 720 CSS pixels; content and actions reflowed | Repeat after RT-320. |
| Shop archive | WooCommerce empty state rendered with H1 and recovery links | RT-321 needs an approved Product fixture. |
| Single Product | No approved active product fixture | RT-321 blocker. |
| Cart and Checkout | Empty Cart rendered; empty Checkout redirected to Cart | RT-321 needs populated synthetic commerce fixtures for transactional states. |
| Activate entry | Desktop dialog and standalone 1440 page audited; Header action navigated to the 390 mobile page | RT-322 owns final dialog focus/restoration, no-JavaScript, and canonical 303 validation. |
| Report entry | Standalone 1440 page and real 390 hero-link path audited | RT-322 owns final parity and routing validation. |
| Public Tag: invalid | Privacy-safe generic failure rendered | RT-323 must retain non-enumerating feedback and use ForgeTag consumer naming. |
| Public Tag: active/contact paused | Not recreated in the fresh closure environment | Historical evidence exists; RT-323 must cover it with a controlled fixture. |
| Public Tag: unregistered, suspended, retired, Lost Mode | No approved non-mutating fixtures | RT-323 named fixture blocker. |
| OTP and activation steps | No approved challenge fixture or submission | RT-323 named fixture blocker. |
| Finder Report and evidence | Contact/evidence path disabled; no upload attempted | RT-323 named feature-flag and fixture blocker. |
| Account sign-in | `Account unavailable` fail-closed page rendered | RT-324 needs approved local Account configuration. |
| Account Overview, My Tags, Tag Detail, Conversations | No authenticated owner fixture | RT-324 named identity/fixture blocker. |
| Secure Reply: missing or expired token | Privacy-safe unavailable state rendered | RT-325 merged; use its dedicated release-gate evidence. |
| Secure Reply: valid and terminal states | Not available in the original RT-319 run | Covered by RT-325 rather than recreated here. |
| Generic page | WordPress Sample Page rendered in the ForgeTag shell | RT-320 must remove sample/admin-facing content and define the generic-page contract. |
| Search | A Cart result rendered, but no search-query/result H1 was present | RT-320 must provide query, results, and empty states. |
| 404 | Header/Footer shell with empty `main` | RT-320 must provide an H1, explanation, and safe recovery actions. |

## Findings

### P0 - release and acceptance blockers

1. **Unsupported claims.** Founding/history, sales-volume, retailer affiliation,
   certification, recovery success, rating, and testimonial claims require
   evidence and approved copy. Demo/reference content does not establish truth.
2. **Commerce cannot be accepted.** WooCommerce and an approved product fixture
   were unavailable, so Shop, Product, Cart, and Checkout were not real states.
3. **Owner and Finder state coverage is incomplete.** Account, activation, OTP,
   Finder evidence, conversations, and valid role-bound Secure Reply states need
   controlled synthetic fixtures and actual-page evidence.
4. **Fallback pages are incomplete.** The 404 has an empty `main`; Search lacks
   a query/result-page H1; the generic page exposes WordPress sample and admin
   guidance rather than consumer-safe ForgeTag content.

### P1 - contract and completeness gaps

1. **Consumer brand leakage.** Public Tag pages must use ForgeTag in navigation,
   titles, and consumer copy. ReturnTag remains a technical/internal name.
2. **Incomplete fallbacks.** Search and 404 require visible, semantic main
   content and safe recovery actions.
3. **Default metadata.** The Home title is the environment name `RT-319-final`,
   not a consumer-facing ForgeTag title.
4. **Desktop dialog focus.** The first DOM observation after opening the
   Activate dialog showed the Close button as active. RT-322 must confirm and
   freeze the intended initial focus and focus-restoration behavior.
5. **Environment readiness.** A fresh worktree initially activated TagCore
   without its ignored `vendor/` and `build/` artifacts, yielding a false Active
   state with no Bootstrap, Schema, routes, or styling. Release/runbook checks
   must build the artifact before activation; request-time repair is forbidden.

### P2 - visual refinement

1. Standalone TagCore pages use a sparse full-height canvas. Tighten vertical
   rhythm and add a safe orientation/back action where the product contract
   permits it.
2. Keep the one-field Tag entry workflow. Do not copy item-category selection,
   marketing consent, location/tracking, or Theme-owned content from
   `tanchuang.png` or the HTML demos.
3. At 320px the Header Activate CTA consumes its own row while Report remains in
   the hero rather than the mobile menu. This is usable, but RT-320 should review
   entry-action discoverability while retaining both paths.

## Frozen cross-surface visual contract

### Brand and copy

- Consumer-facing identity is ForgeTag; ReturnTag remains internal/technical.
- Consumer-visible copy is translatable US English.
- Reviews, sales, experience, certification, shipping, retailer, location,
  recovery-rate, or testimonial claims may use explicit demo data during local
  development but must not be represented as verified production facts.
- Smart-network copy describes a separate compatible system. ForgeTag must not
  imply verified pairing, active tracking, access to device/account/location
  data, battery state, or proactive network alerts.

### Visual language

- Continue the warm-white/cloud canvas, white surfaces, near-black ink,
  graphite secondary text, restrained borders, and Forge red for primary actions.
- Continue the existing Manrope-style display and Inter/system body hierarchy,
  sentence-case headings, and uppercase tracked eyebrows.
- Use restrained radii and subtle elevation. Primary controls are solid red;
  secondary controls are bordered or text actions. Color is never the sole
  status carrier.
- Use real, approved assets with stable aspect ratios and intentional object
  positioning. Do not stretch or invent product representations.

### Responsive and interaction

- Required acceptance widths are 1440, 1024, 816, 390, 320, and 200% equivalent.
- Pages must have no horizontal overflow, clipped text, or hidden actions.
- At 768px and above Theme links may enhance to TagCore-owned dialogs; below
  768px they navigate to TagCore-owned pages. JavaScript failure preserves link
  navigation.
- Dialogs have a name, initial focus, focus trap, inert background, Escape
  dismissal, and focus restoration. Controls remain at least 44 CSS pixels.
- Every page has landmarks, one clear H1, logical headings, labels, visible
  focus, keyboard access, accessible error association, and live status feedback.
- Sensitive pages retain no-store, no-referrer, no-index, framing, and approved
  CSP controls, without advertising, replay, or unnecessary third-party tracking.

### Ownership and privacy

- The ForgeTag Theme owns shell, navigation, footer, editorial presentation,
  approved tokens, and WooCommerce presentation.
- TagCore owns Tag ID normalization and validation, status resolution, OTP,
  authentication, authorization, Finder/Owner privacy, Secure Reply, state
  mutations, and state-specific pages.
- Theme code must not duplicate TagCore forms, decide intent or ownership, query
  TagCore data, or deep-style plugin internals.
- Finder pages expose only approved public fields such as `public_label`, product
  type, and safe Lost Mode messaging. They never expose private `item_name`,
  either party's email, location, token data, or internal processing state.
- Account may reuse the density and hierarchy of `dashboard.png`, but not Last
  seen/location, Billing/subscriptions, active tracking alerts, invented activity,
  or unsupported data.
- Secure Reply remains role-bound and privacy-safe. Missing/expired links reveal
  no conversation existence; GET never consumes a token.
- Commerce presentation uses WooCommerce public APIs and configured catalog data;
  it never creates an Order, Shipment, or Tracking Number to Tag ID mapping.

## Follow-up boundaries

- **RT-320:** global shell, metadata, claims, Search, generic page, and 404.
- **RT-321:** Shop, Product, Cart, and Checkout presentation using isolated
  WooCommerce fixtures.
- **RT-322:** desktop Tag entry dialogs, mobile pages, no-JavaScript fallback,
  focus behavior, and canonical redirects.
- **RT-323:** Public Tag, OTP, activation, Owner/Finder resolution, Finder Report,
  evidence, and unavailable states.
- **RT-324:** Account sign-in, Overview, My Tags, Tag Detail, Conversations, and
  responsive authenticated navigation.
- **RT-325:** Secure Reply and cross-surface accessibility regression gate;
  already merged.

## Validation record

- Chrome evidence: 23 current-run PNG files captured and visually inspected; 12
  privacy-reviewed files are retained in `docs/design/qa/rt-319/`.
- Responsive metrics: document `scrollWidth` did not exceed the effective
  content viewport at 1440, 1024, 816, 720, 390, or 320 CSS pixels.
- Keyboard: the first seven Home stops were Skip to content, ForgeTag home, How
  it works, Products, Recovery, Report a found tag, and Activate my tag. Visible
  focus was confirmed in `23-home-keyboard-focus-1440.png`.
- Console: no page warning or error was recorded on the final Home load. A
  separate Statsig timeout came from the Chrome control client and was not a
  page console or local resource error.
- Assets: the accepted Home screenshot shows all hero images rendered; no blank
  or broken image was accepted as evidence.
- Environment: TagCore `0.5.0` activation created Schema `14` and registered the
  canonical Public Tag, Account, and Secure Reply rewrites. Existing locked
  Composer dependencies and public assets were installed/built locally so the
  fresh worktree matched an installable plugin artifact.
- No Theme or TagCore source, public API, migration, persisted contract,
  dependency version, lock file, production configuration, user-owned data, or
  reference asset was changed by RT-319. Local schema creation was standard
  isolated plugin activation only.
