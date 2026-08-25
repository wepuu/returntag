# RT-336 Resend Message-ID Correlation Spike

**Issue:** [RT-336](https://github.com/wepuu/returntag/issues/96)

**Status:** `IN_PROGRESS`; ADR 0029 records the direct-adapter decision and PR
acceptance remains pending

**Production impact:** none; this spike does not change TagCore runtime, Schema,
Options, feature flags, or email dispatch

## 1. Decision

RT-337 must use exactly one transactional-email transport. ADR 0029 selects:

1. a provider-neutral TagCore gateway; and
2. one direct Resend Infrastructure adapter.

The alternative `wp_mail()` path failed its public-contract gate during source
review: its observable `X-Msg-ID` value is an undocumented implementation detail,
not a supported provider-ID interface. A live send could demonstrate the current
detail but cannot make it a stable contract. The staging probe is retained only
as reproducible investigation evidence; it is no longer a release gate.

There must not be two selectable production paths. Provider acceptance remains
different from confirmed delivery.

## 2. Current repository boundary

The seven current Infrastructure senders call `wp_mail()` and receive only its
boolean acceptance result:

- `WordPressActivationOtpEmailSender`
- `WordPressAccountOtpEmailSender`
- `WordPressFinderEmailOtpSender`
- `WordPressFinderReportOwnerNotificationSender`
- `WordPressConversationRelayEmailSender`
- `WordPressOwnerTransferEmailSender`
- `WordPressOwnerTestEmailSender`

ADR 0023 permits WP Mail SMTP to intercept those calls but forbids TagCore from
depending on its private APIs, tables, settings, or logs. The existing
`returntag_messages.provider_message_id` column has no current write source and
does not solve correlation for other email purposes.

## 3. Upstream source finding

Source review used the WP Mail SMTP `master` branch at the time of this spike;
the actual staging plugin version and commit must be recorded with the result.

- WP Mail SMTP introduced its Resend mailer in 4.7.0.
- Its Resend mailer reads the response body `id` and temporarily adds it to the
  mailcatcher as an `X-Msg-ID` custom header.
- The documented `wp_mail_smtp_mailcatcher_send_after` action receives the
  provider mailer and mailcatcher objects after the API call.
- The provider base class has no public provider-message-ID getter. The Resend
  response-body ID and the `X-Msg-ID` convention are implementation details,
  not a documented compatibility promise.
- WordPress core's public `wp_mail_succeeded` action receives mail data but not
  the provider response or provider ID.

Sources:

- [WP Mail SMTP Resend mailer](https://github.com/awesomemotive/WP-Mail-SMTP/blob/master/src/Providers/Resend/Mailer.php)
- [WP Mail SMTP mailcatcher send path](https://github.com/awesomemotive/WP-Mail-SMTP/blob/master/src/MailCatcherTrait.php)
- [WordPress `wp_mail()` hooks](https://github.com/WordPress/wordpress-develop/blob/trunk/src/wp-includes/pluggable.php)

The available correlation path is technically observable but fails the approved
stable-public-contract condition. ADR 0029 therefore selects the direct adapter.

## 4. Staging probe

The probe is deliberately outside the Plugin bootstrap and release artifact:

```text
scripts/spikes/rt-336/run.php
scripts/spikes/rt-336/class-resendcorrelationprobe.php
```

It sends one fixed synthetic message, observes only the after-send hook, and
emits JSON containing:

- a fixed evidence-schema identifier;
- the decision status;
- whether `wp_mail()` accepted the call;
- whether the after-send hook and Resend mailer were observed;
- provider-ID count and length;
- SHA-256 of the provider ID when exactly one valid ID is observed.

It never emits the test recipient, subject/body, complete provider ID, API key,
webhook secret, or provider response. The hash is correlation evidence, not an
application lookup key and must not be copied into production storage.

### Preconditions

- RT-333 staging domain and Resend API key are configured outside WordPress
  Options and outside the repository.
- WP Mail SMTP is the approved staging version and its Resend mailer is active.
- WP Mail SMTP optimized/queued sending is disabled for the probe. The
  after-send hook must execute in the same WP-CLI process; an enqueued handoff
  would correctly produce `hook_not_observed` but would not test correlation.
- Open/click tracking and detailed/content logging are disabled.
- The recipient is a synthetic, approved staging inbox.
- Email dispatch is performed in an isolated staging maintenance window.

### Command

Set the recipient through the process environment so it is not included in the
command arguments or probe output:

```powershell
$env:RETURNTAG_RT336_TEST_RECIPIENT = '<approved synthetic inbox>'
wp eval-file scripts/spikes/rt-336/run.php
Remove-Item Env:RETURNTAG_RT336_TEST_RECIPIENT
```

The command exits `0` only for `correlated`. All other statuses exit non-zero:

```text
send_failed
hook_not_observed
unexpected_mailer
invalid_provider_id
provider_id_missing
provider_id_ambiguous
```

## 5. Optional investigation evidence matrix

| Case | Expected safe evidence |
|---|---|
| WP Mail SMTP Resend send succeeds | One provider ID; hash matches the Resend dashboard/API record without recording the raw ID in the repository. |
| WP Mail SMTP disabled or another mailer selected | `hook_not_observed` or `unexpected_mailer`; no fallback correlation claim. |
| Missing/malformed/duplicate ID | Non-zero exit and no provider-ID hash. |
| Invalid or revoked credential | `send_failed`; no accepted/delivered state. |
| Repeated probe | Each send has a different provider record; no application idempotency claim is inferred. |
| Evidence privacy scan | Output contains no email address, message content, credential, complete provider ID, or provider response. |

Record separately in the restricted staging evidence store:

- UTC timestamp;
- WordPress, PHP, TagCore, WP Mail SMTP, and Resend mailer versions;
- WP Mail SMTP source commit or immutable package checksum;
- sanitized probe JSON;
- operator and reviewer;
- tracking-disabled confirmation;
- provider dashboard/API comparison result;
- credential-revocation result.

Do not attach secrets, addresses, message content, raw provider responses, or a
complete provider ID to GitHub.

## 6. Applied binary decision rule

Retain `wp_mail()` plus WP Mail SMTP only if all of these are true:

- the staging matrix succeeds on the exact supported plugin line;
- the provider ID is available without reading WP Mail SMTP storage;
- the upstream maintainer documents or otherwise provides a stable public
  compatibility contract for the ID boundary;
- queue retry and one-business-action-to-one-provider-send correlation can be
  implemented without recipient, subject, or body matching;
- failure and rollback do not require a second runtime transport.

The public compatibility condition failed: the inspected `X-Msg-ID` path is not
a documented provider-ID API. RT-337 must therefore implement the
provider-neutral direct Resend adapter. No further WP Mail SMTP send is required
to select the transport.

## 7. Follow-on contract constraints

RT-337 must preserve these constraints regardless of the selected transport:

- persist the business action before dispatch;
- use idempotent, retry-safe workers;
- store no recipient address or message body in delivery projections;
- verify Resend webhooks using the raw request body and Svix headers;
- deduplicate by provider event ID and converge out-of-order events by event
  time;
- ignore open/click events;
- never equate `wp_mail()` or provider acceptance with delivery;
- keep the email-dispatch kill switch and define rollback compatibility.

Resend documents webhook signature verification and at-least-once,
potentially out-of-order delivery:

- [Webhook verification](https://resend.com/docs/webhooks/verify-webhooks-requests)
- [Webhook delivery behavior](https://resend.com/docs/webhooks/introduction)
