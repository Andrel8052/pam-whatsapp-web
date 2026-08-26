# Parity certification

The authenticated suite complements unit tests, the pinned API matrix, and the
unauthenticated QR smoke. It always uses a dedicated `LocalAuth` profile and is
read-only unless mutations are explicitly enabled.

Enroll the dedicated profile once and scan the QR payload before the smoke
deadline:

```bash
PAM_WWEB_AUTH_PATH=/secure/test-profiles pam tests/integration/qr.php
```

Closing this smoke preserves the profile. Calling `logout()` instead would
intentionally remove it.

```bash
PAM_WWEB_AUTH_PATH=/secure/test-profiles \
PAM_WWEB_TEST_CHAT_ID=5511999999999@c.us \
pam tests/integration/authenticated.php
```

Optional read-only domains:

- `PAM_WWEB_TEST_CHAT_ID`: a chat controlled by the test account. When omitted,
  the read-only suite uses the first private chat with a hydrated last message;
  mutation scenarios still require this variable explicitly.
- `PAM_WWEB_TEST_CHAT_INDEX` plus `PAM_WWEB_TEST_CHAT_EXPECTED_NAME`: a 1-based
  chat-list selector guarded by an exact expected name. This is an alternative
  explicit mutation target. If internal and visual ordering differ, exactly one
  private chat with that name may be selected; zero or multiple matches abort
  before mutations.
- `PAM_WWEB_TEST_GROUP_ID`: a group controlled by the test account. When
  omitted, the read-only suite uses the first hydrated group; mutations still
  require this variable explicitly.
- `PAM_WWEB_TEST_CHANNEL_ID`: a channel controlled by the test account.
- `PAM_WWEB_CERTIFICATION_REPORT`: path for the JSON evidence report.

Enroll the dedicated profile once with a visible Chrome window:

```bash
PAM_WWEB_AUTH_PATH=/secure/pam-whatsapp-profile \
  pam tests/integration/enroll.php
```

The command exits successfully only after WhatsApp reaches `Ready` and the
`LocalAuth` profile has been persisted. For a remote/headless host, set
`PAM_WWEB_HEADLESS=1`; each ephemeral QR payload is then written as
`PAM_WWEB_QR_PAYLOAD=...` for rendering on a trusted operator device. Do not
publish or retain QR payloads in build logs.
Alternatively, set `PAM_WWEB_QR_SCREENSHOT` to a file inside an existing trusted
directory. The enrollment loop writes a mode-`0600` PNG of the current WhatsApp
Web page whenever the QR changes and suppresses the raw QR payload from output;
delete that ephemeral image after scanning.

Message mutations require a dedicated chat and explicit authorization:

```bash
PAM_WWEB_ALLOW_MUTATIONS=1 \
PAM_WWEB_CERTIFY_EVENTS=1 \
PAM_WWEB_CERTIFY_INBOUND=1 \
PAM_WWEB_CERTIFY_MEDIA=1 \
PAM_WWEB_CERTIFY_GROUP_MUTATIONS=1 \
PAM_WWEB_CERTIFY_CHANNEL_MUTATIONS=1 \
PAM_WWEB_CERTIFY_CHANNEL_POSTS=1 \
PAM_WWEB_CERTIFY_CHAT_MUTATIONS=1 \
PAM_WWEB_CERTIFY_CONTACT_MUTATIONS=1 \
PAM_WWEB_AUTH_PATH=/secure/test-profiles \
PAM_WWEB_TEST_CHAT_ID=5511999999999@c.us \
PAM_WWEB_TEST_CONTACT_ID=5511888888888@c.us \
PAM_WWEB_TEST_GROUP_PARTICIPANT_ID=5511777777777@c.us \
pam tests/integration/authenticated.php
```

The mutation scenario sends one uniquely identified message, exercises message
lookup, edit, reaction, star, reload, info and reactions, and removes the test
message in a `finally` block. Never point it at a production conversation.
With `PAM_WWEB_CERTIFY_EVENTS=1`, the same run additionally waits for typed
`message_create`, `message_ack`, `message_edit`, `message_reaction`, and
`message_revoke_everyone` deliveries. The five event entries receive evidence
only when every expected event arrives through the live CDP bridge within the
bounded deadline.

With `PAM_WWEB_CERTIFY_MEDIA=1`, the suite sends a deterministic one-pixel PNG,
waits for typed `media_uploaded`, downloads the resulting message media, checks
that its base64 payload is valid, audits the hydrated `Message`, `MessageId`, and
`MessageMedia` properties, and removes the media message in `finally`.

With `PAM_WWEB_CERTIFY_GROUP_MUTATIONS=1`, the configured group must be a
dedicated group administered by the test account. The suite changes its subject
and description, waits for typed `group_update`, and restores both original
values in `finally`. Restoration attempts are independent so a failure restoring
one field does not prevent restoration of the other.

With `PAM_WWEB_CERTIFY_CHANNEL_MUTATIONS=1`, the configured channel must be a
dedicated channel owned by the test account. The suite changes and restores its
subject, description, and mute state, verifies each local transition, and calls
`sendSeen()`. All restoration attempts run independently.

`PAM_WWEB_CERTIFY_CHANNEL_POSTS=1` separately authorizes publishing one uniquely
identified text post to the dedicated channel. The returned `Message` and
`MessageId` are audited and the post is removed in `finally`.

`PAM_WWEB_CERTIFY_CHAT_MUTATIONS=1` requires a dedicated private chat. It
temporarily toggles archive, pin, and mute state; exercises recording/clear
presence and history synchronization; waits for typed `chat_archived`; and
restores the original archive, pin, mute, and timed-mute expiration values.
Every restoration is attempted even if another restoration throws.

Each schema-v2 check records the exact `api-matrix.json` entries it exercised.
Deterministic content contracts run separately and do not launch Chromium:

```bash
PAM_WWEB_CONTRACT_CERTIFICATION_REPORT=/secure/reports/contracts.json \
pam tests/integration/contracts.php
```

This runner currently proves 534 entries covering all 81 public symbols, all 105 public enum constants,
`Location`, `Poll`, `Buttons`,
`List`, `ScheduledEvent`, both `MessageMedia` factories,
`MediaFromURLOptions`, message/group/channel/content option objects,
`ClientOptions`, all web-cache option variants, client/commerce/battery
payloads, group/channel creation results, membership results, and selected poll
options, the `ChatId` compatibility shape, the default authentication decision,
the complete `Call` payload/rejection contract, and exact client command forwarding for pairing, invites, calls, reactions, account preferences,
address-book writes, status, display-name changes, and exact command contracts for chat, group, channel, and message operations. Together with all
authenticated scenarios, the two runners can name all 751 distinct
members. Certification still requires PHP 8.5+, and each generated report is
reviewed and promoted independently.

The certification ledger contains deterministic and authenticated reports
generated with the PAM PHP 8.5.9 runtime. Their combined evidence promotes all
751 public symbol/member entries to `complete`; the strict parity gate passes
with zero incomplete entries. Inbound `MESSAGE_RECEIVED` delivery is also
certified against an authenticated WhatsApp Web session.

Enum evidence is generated directly from the matrix. Every mapped symbol must
resolve to an integer-backed PHP enum, every mapped case must exist, and each
PHP enum must use sequential values beginning at `1`. Upstream aliases such as
`Events::CALL` and `Events::INCOMING_CALL` may intentionally map to the same PHP
case while retaining separate matrix evidence.
Symbol evidence is likewise matrix-driven: every mapping must resolve exactly
to an existing PHP class, interface, or enum. Symbol evidence alone does not
complete a symbol; the strict gate still requires all of its members to be
individually complete.

Validate a report without changing the matrix:

```bash
php bin/parity certify /secure/reports/authenticated.json --dry-run
```

After reviewing the report, record its evidence and promote only its passing
entries:

```bash
php bin/parity certify /secure/reports/authenticated.json
```

The matrix stores the report SHA-256 and check name for every promoted entry.
Failed or skipped checks provide no coverage, unknown entry identifiers are
rejected, and the strict parity gate rejects any `complete` entry without
certification provenance.

Hydrated `Client`, `ClientInfo`, `Contact`, `ContactId`, `Chat`, `GroupChat`,
`Channel`, `Message`, `MessageId`, `MessageMedia`, `Label`, `Broadcast`,
`Order`, `Payment`, `Product`, and `PollVote` objects are audited from the
matrix itself. Label, broadcast, order, payment, product, and poll-vote checks
are skipped without claiming their shape coverage when the authenticated
account has no matching data.
Every mapped public property must exist, remain public, be initialized, and be
readable before its entry is included in a passing check. With every optional
domain, mutation, event, media, group, channel, private-chat, label, broadcast,
existing-message, order, payment, product, and poll-vote scenario available,
the suite can currently certify up to 327 distinct public members. Event checks
bind each observed delivery to both the `Client` event and its public `Events`
constant. Reaction and group-update deliveries additionally audit their hydrated
`Reaction` and `GroupNotification` payloads; group notification chat, author,
and recipient relations are resolved from the live session.
Contact certification also exercises country-code, formatted-number, and
broadcast lookups. When the account contains a hydrated business contact, its
profile, categories, business-hours configuration, and daily hours are audited
without claiming absent optional structures.
The dedicated message lifecycle uses the `Chat` API directly to send and mark
the conversation seen, then replies to and reversibly pins/unpins the generated
message before deleting all generated content. Client listener registration and
the final transition through `destroy()` are also checked explicitly.
The certification `LocalAuth` subclass records the real strategy lifecycle
without changing behavior. Its construction and public properties, plus
`setup()`, browser pre/post initialization, authenticated payload, ready hook,
and destruction are promoted only when every expected hook was observed.
An isolated `RemoteAuth` contract scenario uses a controlled clock, a recording
store, and a uniquely named temporary profile. It verifies restoration, timed
backup, logout, disconnect, and every public store operation, then removes the
temporary profile without touching the authenticated `LocalAuth` directory.
The dedicated private-chat run also exercises the `Client` variants for archive,
pin, mute, unread/seen, and history synchronization. Each state pair is
round-tripped and reloaded, presence is returned to available, and a final
snapshot restores any partially completed transition.
With an explicit contact target, block/unblock is round-tripped to its observed
state. An explicit non-super-admin group participant is likewise promoted and
demoted back to its original role. The group-update notification reply is
audited and deleted before subject/description restoration completes.
