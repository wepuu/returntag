# ADR 0029: Direct Resend transactional-email transport

**Status:** Accepted

**Date:** 2026-08-25

**Scope:** RT-336 transport decision and RT-337 implementation contract

**Schema before/after:** `14 -> 14` (decision only)

**Plugin before/after:** `0.5.0 -> 0.5.0` (decision only)

## Context

TagCore currently queues business work and its Infrastructure email senders
call `wp_mail()`. ADR 0023 allows WP Mail SMTP to transport those calls but
forbids TagCore from depending on WP Mail SMTP private APIs, tables, settings,
logs, or provider SDKs. A successful `wp_mail()` call proves only submission
acceptance, not confirmed delivery.

RT-336 investigated whether TagCore could retain that transport while obtaining
the Resend Email ID needed to correlate signed delivery webhooks. WP Mail SMTP
4.7 and later currently read the Resend response-body `id`, add it temporarily
to the mailcatcher as an `X-Msg-ID` custom header, and expose mailer and
mailcatcher objects through an after-send action. It does not expose a
documented provider-message-ID getter or compatibility contract. Depending on
the header would therefore bind TagCore to an upstream implementation detail
and would not satisfy the approved public-interface condition.

The operational Resend account now has a verified sending domain with verified
DKIM and SPF. DMARC exists in monitoring mode and open/click tracking is not
configured. These facts establish account readiness evidence but do not change
the transport-boundary decision. Environment-isolated credentials, the signed
webhook endpoint, and real staging dispatch remain external gates.

## Decision

RT-337 will implement one provider-neutral `TransactionalEmailGateway` port and
a direct Resend Infrastructure adapter. TagCore transactional email will not
use WP Mail SMTP after the RT-337 cutover.

The direct adapter must:

- submit through the documented Resend HTTPS API;
- return the Resend Email ID from the successful response through a bounded,
  provider-neutral result object;
- store no recipient address, subject, body, attachment bytes, or complete
  provider response in the delivery projection;
- read credentials and approved From identity only from environment or the
  approved secret-injection mechanism, never WordPress Options;
- use the existing `returntag_email_dispatch_enabled` control as the immediate
  dispatch kill switch;
- fail without falling back to `wp_mail()` or a second provider path;
- keep business persistence before dispatch and preserve idempotent worker
  claims and retry limits;
- keep open/click tracking disabled.

RT-337 will also implement the signed Resend webhook boundary. It must verify
Svix signatures against the raw request body, deduplicate by provider event ID,
converge duplicate and out-of-order events, ignore open/click events, and never
allow an older event to regress a terminal delivery state.

WP Mail SMTP may remain installed for WordPress or third-party mail outside
TagCore. TagCore must not inspect its storage or use it as an automatic fallback.
There is no permanent dual TagCore transport.

## Rejected alternatives

### Read `X-Msg-ID` after `wp_mail()`

Rejected because it is an undocumented temporary-header convention rather than
a stable provider-ID API. A staging send could demonstrate current behavior but
could not turn the implementation detail into a supported contract.

### Read WP Mail SMTP logging or delivery tables

Rejected because it violates ADR 0023, creates version coupling, and would
encourage recipient or content persistence outside TagCore's privacy contract.

### Keep both WP Mail SMTP and direct Resend as selectable transports

Rejected because it doubles retry, correlation, incident, privacy, and rollback
states and leaves webhook ownership ambiguous.

## Data and migration impact

This ADR changes no runtime or Schema. RT-337 owns additive Schema 15 for the
provider-neutral email-delivery and webhook-event projections. It must include
fresh-install, Schema 14 upgrade, migration retry, previous-code compatibility,
and feature-disable evidence.

## Security and privacy

- Resend credentials and webhook secrets must not enter Git, WordPress Options,
  ordinary logs, Issues, screenshots, or acceptance artifacts.
- A credential disclosed through an uncontrolled channel is revoked and
  replaced before staging or production use.
- Staging and production use different restricted credentials and test data.
- Delivery records contain provider identifiers and state metadata only; they
  do not duplicate addresses or message content.
- Webhook handlers persist only the allowlisted event identity, event time,
  provider message identity, mapped state, and processing metadata.

## Rollout and rollback

RT-337 is dark-deployed with dispatch disabled until migrations, credentials,
webhook verification, synthetic staging sends, delivery-state convergence, and
failure drills pass. Cutover enables only the direct adapter. A provider outage
disables `returntag_email_dispatch_enabled`; it does not fall back to
`wp_mail()`.

Code rollback is permitted only after Schema 15 previous-code compatibility is
verified. Delivery and webhook audit data are preserved; rollback must not drop
or rewrite accepted business actions or delivery history.

## Evidence

- [WP Mail SMTP Resend mailer](https://github.com/awesomemotive/WP-Mail-SMTP/blob/master/src/Providers/Resend/Mailer.php)
- [WP Mail SMTP mailcatcher send path](https://github.com/awesomemotive/WP-Mail-SMTP/blob/master/src/MailCatcherTrait.php)
- [Resend send-email API](https://resend.com/docs/api-reference/emails/send-email)
- [Resend webhook verification](https://resend.com/docs/webhooks/verify-webhooks-requests)
- [Resend webhook delivery behavior](https://resend.com/docs/webhooks/introduction)
