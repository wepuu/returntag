## Ticket

- RT ticket: `RT-___`
- Branch:

## Summary

Describe the outcome and why it is needed.

## Scope

- In scope:
- Explicitly out of scope:

## Frozen-requirement review

- [ ] Supported tag types remain exactly `sticker`, `classic_tag`, and `smart_tag`.
- [ ] The public six-character Tag ID remains the activation ID.
- [ ] No Claim ID or secondary activation secret was introduced.
- [ ] No order, shipment, tracking, or logistics-to-Tag-ID mapping was introduced.
- [ ] Smart finding networks remain independent from ReturnTag QR recovery.
- [ ] No Apple or Google account, device, pairing, or location data was added.
- [ ] Generated or exported Tag IDs cannot be reused.
- [ ] No database table name hard-codes `wp_`.
- [ ] Not applicable; this change cannot affect a frozen requirement.

Explain any unchecked or non-applicable item:

## Behavior and contracts

- User-visible behavior:
- API, hook, option, event, capability, or schema impact:
- Backward-compatibility impact:

## Security and privacy

- Authentication and authorization impact:
- PII, token, encryption, logging, and abuse-control impact:
- External side effects and kill switch:

## Database and rollback

- Schema version before/after:
- Migration, fresh-install, and upgrade behavior:
- Previous-release compatibility:
- Rollback or feature-disable plan:
- [ ] No database change.

## Validation

List each command and its actual result. Do not mark a command as passing if it
was not run.

```text
command: result
```

- [ ] Formatting/lint completed.
- [ ] Static analysis completed.
- [ ] Relevant unit tests completed.
- [ ] Relevant integration/end-to-end tests completed.
- [ ] Complete diff reviewed for secrets, PII, and unrelated changes.

## Documentation and evidence

- Documentation changed:
- Screenshots or reproducible verification steps, when applicable:
- Remaining risks or follow-up tickets:
