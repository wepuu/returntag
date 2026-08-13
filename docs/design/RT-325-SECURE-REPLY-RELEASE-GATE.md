# RT-325 Secure Reply Release Gate

**Status:** passed
**Audit date:** 2026-08-13
**Issue:** RT-325 / GitHub #72
**Branch:** `feat/RT-325-secure-reply-accessibility-gate`

## Scope and authority

This gate audits and freezes the cross-surface Secure Reply contract from an
emailed role-bound link or an authenticated Owner Conversation continuation to
`/secure-reply/`. PRD, ADR, Architecture, Security, and Release requirements
override visual references. The ForgeTag design language supplies typography,
spacing, cards, forms, focus treatment, and color hierarchy; TagCore continues
to own Token exchange, session identity, authorization, message acceptance,
terminal actions, privacy, and dependency failure.

The branch is based on `origin/main` at `9ebe744`. RT-320 through RT-324 were
not merged into that base during this audit, so their cross-surface pages are
recorded as upstream dependencies rather than copied or reimplemented here.
RT-325 adds no Theme business logic, dependency, lock-file change, Schema/API
change, database Migration, production configuration, deployment, or release
artifact.

## Frozen interaction contract

1. An emailed Secure Reply URL may contain one 43-character bearer. GET stores
   it briefly in an HttpOnly, Secure, SameSite=Strict cookie and returns `303`
   to the clean `/secure-reply/` URL. GET never consumes the Token.
2. The clean continuation page explains the one-time exchange. Only an
   explicit, same-site, nonce-protected POST creates a 30-minute role-bound
   session.
3. The thread labels the current participant as `You` and the opposite role as
   `Owner` or `Finder`; it never displays either email address. Plain-text
   messages remain 10-500 characters, with no HTML or attachments.
4. A successful submit means the encrypted Message was saved and background
   delivery may continue. It does not mean provider delivery. A failed submit
   produces one generic recovery message without revealing Conversation,
   participant, Token, queue, or provider state.
5. Finder `End conversation` and Owner `Report and block` require explicit
   confirmation. Successful terminal actions revoke access and converge to one
   generic ended state. The UI exposes no IDs or free-form report payload.
6. Invalid, expired, revoked, consumed, ineligible, or dependency-failed access
   converges to the same unavailable presentation and recovery guidance.

## Page-state matrix

| State | Required presentation | Mutation | Recovery |
|---|---|---|---|
| Bearer URL | Immediate clean-URL redirect; bearer never rendered | None | Continue page |
| Continue | Link-use explanation and `Continue securely` | Explicit POST exchange | Latest ForgeTag email if unavailable |
| Owner thread | `You`/`Finder` message roles; Owner safety action | Message POST; confirmed report/block POST | Latest email after session loss |
| Finder thread | `You`/`Owner` message roles; Finder safety action | Message POST; confirmed close POST | Latest email after session loss |
| Empty thread | Status text plus the same bounded message form | Message POST | Same session rules |
| Message accepted | One-use `role=status` feedback | No additional mutation | Continue in thread |
| Message rejected | One-use `role=alert` generic retry guidance | No additional mutation | Correct input or latest email |
| Terminal | Generic ended status; no thread or form | None | ForgeTag home |
| Unavailable | Generic expired/unavailable guidance; no existence detail | None | Most recent email or ForgeTag home |
| Runtime/read failure | Same unavailable state; no exception detail | None | Most recent email or ForgeTag home |

## Cross-surface contract

| Producer or consumer | Frozen handoff |
|---|---|
| Finder Report | Optional verified Finder email may enable a conversation; initial anonymous report remains one-way |
| Email | Role-specific opaque link only; no opposite-party address in headers, content, URL metadata, or `Reply-To` |
| Owner Account Conversations | Nonce-protected POST may create a current-Owner session; no bearer is added to the URL |
| Secure Reply | Role-bound read and writes only; no participant selector or browser-supplied Owner/Finder ID |
| Background delivery | Message ID only; UI reports acceptance, not confirmed delivery |
| Terminal safety | Current session derives actor and Conversation; accepted action revokes obsolete access |

## Accessibility and responsive contract

- Server rendering and all primary actions remain usable with JavaScript off.
- One H1 identifies the surface. Forms use visible labels; message help is
  referenced through `aria-describedby`; Conversation items are an ordered
  list with an accessible label.
- Submission feedback uses `role=status` for accepted work and `role=alert` for
  retryable failure. Terminal feedback is a status. Focus uses the established
  three-pixel ForgeTag focus ring.
- Keyboard order follows document order: continuation, message, confirmation,
  terminal action, and recovery links. No focus trap or scripted focus movement
  is introduced.
- The frozen viewport matrix is 1440 x 900, 1024 x 768, 816 x 1024,
  720 x 900 as the 200-percent equivalent, 390 x 844, and 320 x 720. At every
  size, `scrollWidth` must equal `clientWidth`, controls must remain visible,
  and long plain text must wrap without clipping.

## Security and privacy review

- Sensitive responses retain `Cache-Control: no-store, private, max-age=0`,
  `Pragma: no-cache`, `Referrer-Policy: no-referrer`, no-index/no-follow/no-
  archive, `nosniff`, and the local-only CSP.
- Link, session, terminal, and message-feedback cookies are scoped to
  `/secure-reply/`, HttpOnly, Secure, and SameSite=Strict. Feedback accepts only
  the closed `sent` and `failed` values and is cleared after one render.
- Browser POSTs require same-site validation and the existing nonce. Runtime
  services continue to enforce role, current ownership, eligibility, limits,
  rate controls, replay prevention, encryption, audit, and idempotency.
- HTML output remains escaped. No private address, private item name, Tag ID,
  Token, challenge, access row, queue state, evidence/media reference, original
  filename, provider identifier, location, scan history, or message body appears
  in ordinary logs or unrelated surfaces.

## Findings and disposition

| Priority | Baseline finding | Disposition |
|---|---|---|
| P1 | Message POST redirected silently, leaving the user unable to tell acceptance from rejection | Resolved with one-use, closed-code status/alert feedback |
| P1 | Terminal action inherited the normal blue submit rule because equal-specificity CSS appeared earlier | Resolved with a compound danger selector after the base rule |
| P2 | Owner and Finder messages used identical cards | Resolved with text labels plus asymmetric, color-independent rails |
| P2 | Large top spacing reduced thread context and pushed the safety action far below the fold | Resolved with a Secure Reply-specific responsive top rhythm |
| P2 | Unavailable and terminal states offered no same-site next action | Resolved with a ForgeTag home recovery link |

No visual change alters authorization, Message persistence, Token exchange,
Conversation state, or delivery semantics.

## Chrome evidence and reproduction

Evidence is captured from synthetic local data only and is stored outside the
repository under the RT-325 visualization directory. No screenshot contains a
real email address, production Token, customer Message, or production data.

Connected Chrome evidence captured before the final styling pass records the
baseline unavailable, continuation, desktop Owner thread, and 390-pixel Owner
thread states as `01-secure-reply-unavailable-1440.png` through
`04-owner-thread-390.png` in the external RT-325 evidence directory. Final
post-change evidence is recorded as:

- `14-owner-continue-final-1440.png` and
  `15-owner-thread-final-1440.png` for the clean continuation and Owner thread;
- `16-owner-thread-final-1024.png`, `16-owner-thread-final-816.png`,
  `16-owner-thread-final-720-200pct.png`, `16-owner-thread-final-390.png`, and
  `16-owner-thread-final-320.png` for the frozen viewport matrix;
- `18-owner-terminal-final-390.png`, `19-finder-thread-final-390.png`,
  `20-finder-terminal-final-390.png`, and
  `21-consumed-link-unavailable-final-390.png` for terminal, role, and replay
  convergence.

Chrome runtime verification confirmed:

- 1440 x 900 Owner and Finder threads had zero horizontal overflow, clean URLs,
  no remote page assets, local POST actions, explicit `You`/peer labels, and a
  computed danger-button background of `rgb(163, 19, 32)`;
- an accepted synthetic Owner message increased the rendered Message count and
  exposed `Message saved. Delivery continues in the background.` through a
  status region;
- temporarily disabling Finder Contact caused the same bounded message to
  remain unsaved and exposed the generic alert; the flag was restored before
  continuing;
- Owner `Report and block` removed both forms and exposed the generic terminal
  status and ForgeTag home recovery link;
- a fresh Finder link stripped the bearer before rendering, required explicit
  Continue, resolved peer labels as `You`/`Owner`, and exposed only `End
  conversation`; no synthetic email, Tag ID, or Token appeared in the DOM;
- a 390 x 844 Owner thread measured `scrollWidth === clientWidth`, with both
  actions contained within the viewport.
- every frozen viewport had zero horizontal overflow; at 390 and 320 pixels the
  primary and terminal controls remained full-width and inside the viewport;
- keyboard order was Message, Send private message, confirmation, and terminal
  action, with the established three-pixel solid focus ring and three-pixel
  offset visible on every control;
- Owner and Finder terminal actions both converged to the generic ended state,
  and reuse of a consumed Finder link converged to the generic unavailable
  state after the explicit exchange POST;
- the final Chrome console contained no warning or error entries, and the
  rendered Finder DOM contained no synthetic email, Tag ID, Token, or private
  identifier.

The final unavailable response returned the expected no-store, no-referrer,
no-index, nosniff, and local-only CSP headers, with no private fixture marker in
the response body. All acceptance used the user's connected Chrome session;
no non-Chrome browser or Playwright CLI was used. Reproduction uses the isolated
local WordPress environment on port 8891, TagCore and ForgeTag active, synthetic
Owner/Finder fixtures, and Chrome only.

## Release and rollback

Release requires PHP validation/lint/static analysis/unit and integration tests,
JavaScript and CSS lint, TypeScript, Node contract tests, production asset build,
Chrome state/viewport evidence, full diff review, and confirmation that no lock
file or unrelated asset changed. Playwright CLI is not part of this acceptance
unless separately authorized. In this implementation run, `composer check`
passed 341 tests / 3,356 assertions, WordPress integration passed 245 tests /
2,591 assertions, the Secure Reply contract test passed 6 tests / 64 assertions,
and Stylelint and documentation checks passed. JavaScript lint, CSS lint,
TypeScript, all 49 Node contract tests, all eight Jest suites / 37 tests, release
metadata checks, Theme checks, Theme artifact checks, and the admin, public, and
entry-block production builds also passed. The temporary dependency repair did
not add a dependency or retain a lock-file change.

Containment disables Finder Contact first and Email Dispatch when outbound work
must stop. Code rollback removes the presentation and feedback refinement only.
It must not delete or reopen Conversations, Messages, Access Tokens, Reports,
evidence, ownership, Events, or terminal states, and it requires no Schema down
Migration or data repair.
