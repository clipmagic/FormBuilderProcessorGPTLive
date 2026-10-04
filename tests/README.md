# Developer tests

These checks are optional development tools. GPT-Live does not load them during
normal operation, and WireTests is not a runtime dependency of the Action.

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
contracts with intercepted sends (no provider calls).

It does not save forms or entries, uninstall modules or contact OpenAI. Limit
checks temporarily use uniquely named session/cache records, which are deleted
in a `finally` block. Language, form context and response headers are restored.
WireTests itself creates its own test template/page during installation; this
suite does not use that page.

## JavaScript: Node.js

From the ProcessWire root, run each check:

```bash
node site/modules/FormBuilderProcessorGPTLive/tests/conditions.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/client.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/recovery.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/page-values.cjs
node site/modules/FormBuilderProcessorGPTLive/tests/model-unavailable.cjs
```

These use simulated browser controls and connections, with no microphone or
network session. Recovery checks include new-page response requests and silence
for paused, blocked or stale sessions. Missing-model checks cover live teardown,
retained form values and private diagnostic delivery. Payload checks verify shared
review and previous-page answer reuse instructions reach both agents; they do
not establish actual conversation quality. `conditions.json` is the shared PHP/JavaScript condition fixture.

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
