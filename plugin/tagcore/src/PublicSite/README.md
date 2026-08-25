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

RT-322 refines only that presentation seam. The desktop dialog and standalone
page share the same intent-safe orientation, labelled Tag ID field, localized
client error, and ForgeTag token contract. Standalone pages add bounded help
for locating the printed ID and explaining that TagCore will resolve the next
step; the selected intent still grants no authority. The native dialog remains
the focus trap and inert-background boundary, while mobile and failed-script
requests retain the ordinary link fallback.

RT-323 keeps `/t/{tag_id}` as the only post-entry authority and extends only
its presentation contract. An unregistered Tag renders one server-driven
three-step journey: request an email OTP, verify the email and code, then make
an explicit activation POST. A logged-in visitor starts at the final explicit
activation step; no browser field carries an email, Owner ID, or internal
challenge identifier. After commit, state is resolved again so an Owner sees a
Tag-specific Account deep link while another visitor converges to the Finder
experience.

An active non-Owner receives the existing two-step Finder Report form with an
optional bounded message and exactly one required private evidence image. The
form does not request location or require identity. Optional Finder email
verification remains a separate continuation after report acceptance and is
required before a two-way Conversation. The Application resolver continues to
own all Tag, Batch, feature-control, identity, and privacy decisions; this
layer supplies ForgeTag consumer copy, semantic forms, safe state feedback,
and responsive presentation only.

RT-325 closes the Secure Reply presentation and accessibility release gate.
The controller remains the only adapter that exchanges one-time bearer links,
creates 30-minute role-bound sessions, submits messages, and performs terminal
participant safety actions. The template receives only the resolved thread and
one closed `sent` or `failed` feedback code. It never receives participant
email addresses, Token values, Owner IDs, Finder IDs, or delivery-provider
state. Successful submission means the encrypted Message was accepted for
background delivery; the page does not claim provider delivery.

Secure Reply remains server rendered and usable without JavaScript. GET strips
the bearer from the address bar, and explicit nonce-protected same-site POSTs
perform link exchange, message submission, and confirmed role-specific safety
actions. Unavailable, dependency-failure, and terminal states converge to
generic recovery copy. Sensitive responses retain no-store, no-referrer,
no-index, local-only CSP, secure-cookie, output-escaping, and role-separation
controls.
