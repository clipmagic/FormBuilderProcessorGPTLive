# Developer tests

These checks are optional development tools. GPT-Live does not load them during
normal operation, and WireTests is not a runtime dependency of the Action.

ChoiceNotice covers the native Integer threshold (default 4), saved/invalid
settings, strict greater-than comparison, placeholder exclusion, single/multiple
choice counts and both-agent policies across pages. Spoken reminders and actual
interruption behaviour require a voice acceptance test.

Clarification coverage includes the native per-form Integer setting, invalid-value
fallback, both-agent policy on successive pages, and `manual-fallback.cjs` for
silent visitor edits, prior readiness revocation and synthetic/stale event guards.
Focused typing updates after a short pause, without waiting for blur; duplicate
blur events and pending edits on a stale/navigating session are also checked.
Spoken attempt counting/manual fallback still requires a real voice acceptance test.

## PHP: WireTests

Install [WireTests](https://processwire.com/modules/wire-tests/) on a development
site, following its requirements (ProcessWire 3.0.259+, PHP 8+ and the Functions
API). Install FormBuilder, AgentTools and GPT-Live as normal. From the ProcessWire
root, run:

```bash
php index.php test site/modules/FormBuilderProcessorGPTLive/tests/WireTest_FormBuilderProcessorGPTLive.php
```

The class follows WireTests' `WireTest_<ModuleName>` convention. The test uses
unsaved, in-memory forms; it needs no existing form, selected agent or API key.
It covers conditions, asset URLs, page/submission payload policies, response
filtering, tokens/limits, message overrides/translations, duplicate endpoint
execution, uninstall protection, missing-model fallback and native WireHttp request
contracts with intercepted sends (no provider calls), plus saved voice selection,
startup audio configuration, language-scoped accents and arbitrary-language custom preferences.
Native Datetime fixtures cover component schemas, formats/bounds, conditional
required date with optional time, saved timestamp expansion, explicit blanks,
native rendering and control-name collisions.

It does not save forms or entries, uninstall modules or contact OpenAI. Limit
checks temporarily use uniquely named session/cache records, which are deleted
in a `finally` block. Language, form context and response headers are restored.
WireTests itself creates its own test template/page during installation; this
suite does not use that page.

## JavaScript: Node.js

For a native browser scroll/focus check, open `tests/navigation-scroll.html` from
the module's URL on a local development site. Click Next test page beneath the
long first form: the replacement form should be visible and focused, with the
120-line conversation retained. Press Tab to reach Request. Repeat at a narrow
viewport. The fixture loads the current client source; it does not start voice,
call a provider or submit an enquiry.

From the ProcessWire root, run each check:

```bash
node site/modules/FormBuilderProcessorGPTLive/tests/conditions.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/client.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/recovery.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/page-values.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/model-unavailable.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/choices.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/guidance.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/welcome.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/validation-feedback.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/context-appends.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/datetime.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/transcript.cjs
```

These use simulated browser controls and connections, with no microphone or
network session. Recovery checks include new-page response requests and silence
for paused, blocked or stale sessions. Missing-model checks cover live teardown,
retained form values and private diagnostic delivery. Payload checks verify shared
review and previous-page answer reuse instructions reach both agents; they do
not establish actual conversation quality. `conditions.json` is the shared PHP/JavaScript condition fixture.
Choice checks cover single/group checkboxes, single-option groups, multiple
selects, AsmSelect native change notifications, Page IDs, disabled/unknown choices,
required consent, conditional fields and structured selection retention. PHP
choice fixtures exercise native Page delegates and server-side token contracts
without saving forms or pages.
Empty native checkbox groups are excluded from the contract; browser regression
checks cover corrected page readiness after visible name/email changes.
Guidance checks exercise real ProcessWire after-hooks, empty defaults, form/page
isolation, both-agent instructions, unchanged preparation/submission tools and
invalid hook returns. Browser checks cover guidance delivery, clearing/Back,
paused silence and stale/closed session guards. These do not establish spoken fidelity.
Context append checks cover the service limit, complete text/order preservation,
Unicode, paused silence and closed/stale ownership guards.
Transcript checks cover reserved output/copy state, literal text and speaker
grouping, internal following versus earlier-history reading, complete-history
copy and reset. Open `tests/transcript-layout.html` on a local development site
for the native FormBuilder iframe-resize check: stream text at normal and narrow
widths, then check the PASS result and keyboard scrolling. No voice/provider or
enquiry request is started by that fixture.
Welcome checks cover the standard Checkbox/default-on configuration, inherited
and quoted greeting wording, voice-only policy, one opening response, duplicate
startup events, Resume, disabled/legacy forms and inactive-session guards.
They also assert the exact opening-event keys (`event_id`, not `client_event_id`)
and populated-postcode context plus summary/next-step policies. These fixtures
do not establish first-attempt connection reliability or spoken interpretation.
Validation-feedback checks cover native non-clearing error reads, bounded/reset
feedback, server-only either/or rules, a corrective reply on blocked Next, paused
and stale silence, and no extra response request for a tool finishing mid-navigation.

## HTTP: Python 3

This optional integration check needs a rendered GPT-Live form with a compatible
agent selected. Supply the development page URL; no host or form name is built
into the script:

```bash
python3 site/modules/FormBuilderProcessorGPTLive/tests/endpoint_test.py https://example.test/enquiry/
```

If the page contains several GPT-Live forms, add `--form my_form`. For iframe
embeds, supply the iframe's form URL. Normal certificate verification applies.

The script checks fallback responses, token isolation, exported timeout settings
and repeated page-configuration refreshes. It never sends an SDP offer, starts
paid voice or submits the form. Rejection checks can create normal diagnostic
log records on the selected development site.

## Manual checks and distribution

Real microphone/voice behaviour, custom widgets, installed language-file/admin
flows and actual uninstall/reinstall still need manual testing.

Keep these tests with the development source. They may be omitted from a minimal
administrator installation package. A developer package must include the test
class and `conditions.json` to run the PHP suite; the other files support the
separate JavaScript/HTTP checks.
