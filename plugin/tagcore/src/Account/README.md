# Account

Authenticated Owner views and request adapters belong here. ADR 0022 freezes
the future surfaces at `/account/sign-in/`, `/account/`,
`/account/tags/{tag_id}/`, and `/account/conversations/`.

Every read and mutation derives the current WordPress user server-side,
rechecks ownership, and keeps private `item_name` separate from Finder-visible
`public_label` and approved Lost Mode content. Account login is not Secure
Reply authorization; Conversation messages continue to require the existing
role-bound 30-minute session issued after an explicit eligible POST.

RT-317 Stage 1 implements Account-specific passwordless entry and My Tags/Tag
Detail here. The implementation uses the default-off
`returntag_owner_account_enabled` control, the distinct `account_otp`
challenge purpose, current-session Owner queries, generic unavailable states,
and TagCore-owned server-rendered templates.

RT-317 Stage 2 adds separate Tag-bound nonce POST actions for bounded metadata,
Lost Mode, and Smart Setup acknowledgement changes. The Application service
derives the Owner from the session, rate-limits before work, and coordinates an
active/current-Owner conditional write plus a fixed metadata-free Event in one
transaction. Smart Setup acknowledgement records only completion of a static
guide and is not pairing evidence.

RT-317 Stage 3 implements a bounded privacy-minimized Conversation summary
projection and an explicit same-site nonce POST into the existing Secure Reply
runtime. The browser Conversation ID remains a selector. Persistence rechecks
current active ownership and the complete relay eligibility graph atomically,
revokes prior Owner sessions, and issues the existing role-bound 30-minute
session. Account GET and the WordPress session never authorize Message reads or
writes directly, and no cross-party email, Message content, Token, evidence,
media reference, or filename enters the Account projection.

RT-324 refines only the server-rendered presentation contract. My Tags remains
the primary overview task, while the test-email action is a secondary account
utility. Tag Detail marks private fields as `Only you` and approved public
recovery fields as `Finder-visible`; these labels explain the existing data
boundary and do not change authorization or persistence. Active, suspended,
retired, unavailable, and empty states continue to come from the Application
projection. The responsive header moves Account navigation onto its own row at
small widths, and Transfer/Retire remain distinct nonce-protected forms inside
an isolated high-risk section.
