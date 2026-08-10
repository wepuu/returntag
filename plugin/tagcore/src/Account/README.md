# Account

Authenticated Owner views and request adapters belong here. ADR 0022 freezes
the future surfaces at `/account/sign-in/`, `/account/`,
`/account/tags/{tag_id}/`, and `/account/conversations/`.

Every read and mutation derives the current WordPress user server-side,
rechecks ownership, and keeps private `item_name` separate from Finder-visible
`public_label` and approved Lost Mode content. Account login is not Secure
Reply authorization; Conversation messages continue to require the existing
role-bound 30-minute session issued after an explicit eligible POST.

RT-317 Stage 0 adds no runtime implementation in this directory.
