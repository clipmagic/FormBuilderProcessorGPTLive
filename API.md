# FormBuilderProcessorGPTLive

Adds GPT-Live voice input to a ProcessWire FormBuilder form. Spoken answers are
prepared in supported visible fields; FormBuilder retains validation, saving,
pagination and submission. Credentials remain on the server.

This reference describes version **0.3.0**. See [HOWITWORKS.md](HOWITWORKS.md)
for setup, controls markup and detailed Action settings, or [README.md](README.md)
for the general overview.

## Access and form context

### Native widget preparation event

After a visitor manually selects a value, a trusted widget integration can dispatch
`gpt-live:manual-entry` on the form with `detail: { fieldName: 'native_field_name' }`.
The client silently reads that supported visible native control and updates voice
context. Values supplied in the event are ignored. Send it only after a manual
widget action, not a voice preparation write. It revokes prior page readiness but
does not imply submission permission or start a spoken response.

Before applying supported tool values, the browser dispatches a bubbling
`gpt-live:prepare` CustomEvent on the current form. Trusted site widget code can
inspect `event.detail.values` (a copy) and call `event.detail.waitUntil(promise)`
synchronously during the event. A promise may resolve with feedback such as
`{status: 'clarification_required', fields: ['location'], options: [...], message: '...'}`.
This feedback is returned to the assistant without standard preparation or a
success claim. Resolving with no feedback (or `status: 'resolved'`) continues
normal preparation of the original values; modifying the copy does not change
them. Widget rejection returns validation feedback. Closed sessions and changed
pages cannot apply late results. Widgets must bound their own lookup timeouts.
The event grants no navigation/submission permission and contains no site-specific
lookup logic; site guidance must explain how to confirm returned native options.

```php
$action = $modules->get('FormBuilderProcessorGPTLive');
$action->fbForm($forms->form('my_form'));
```

The module extends `FormBuilderProcessorAction`. FormBuilder creates and populates
Action instances automatically when rendering or processing an enabled form.
Instances are not singleton: bind the intended form when retrieving one yourself.
Loading an Action instance does not start a voice session or enable it on a form.

### `fbForm($fbForm = null)` — inherited

Sets or retrieves the underlying `FormBuilderForm`. With no argument, returns the
current form, or `null` if none has been attached. The module reads saved per-form
Action settings from this context.

For an already rendered form, the context can instead come from FormBuilder's
render result:

```php
$renderedForm = $forms->render('my_form');
$action = $modules->get('FormBuilderProcessorGPTLive');
$action->fbForm($renderedForm->form);
```

`$renderedForm` is the object returned by `$forms->render()`; its `form` property
provides the underlying `FormBuilderForm`.

## Progressive preparation

The strict preparation tool still requires every supported property, but accepts
`null` for an unchanged field. Null preserves its existing value; explicit empty
strings/arrays clear or skip applicable fields. A confirmed answer can update
independently while other required fields remain unanswered. Partial updates
return `fields_updated`, revoke earlier page readiness and preserve other values.
They do not authorise Next/review. When page questions are complete, provide all
visible values for `page_prepared` or `prepared_for_review`; native FormBuilder
still owns pagination, server validation and submission. Newly revealed fields
must be answered/offered before complete page preparation.
Partial feedback includes `remainingFields` with current blank visible names and
labels, excluding the just-updated/skipped fields. Earlier explicit skips still
belong to conversation state and must not be re-asked merely because they are blank.
Both agents are told to continue with one next unanswered question after an answer
or skip, rather than stopping at an acknowledgement.

## Native date/time tool contract

HTML Datetime fields use native control values: date `YYYY-MM-DD`, time `HH:MM`
(or `HH:MM:SS` for a seconds-enabled step). A paired field named `pickup` exports
flat string properties `pickup` and `pickup__time`; the latter is its existing
native time control, not a saved FormBuilder field. Labels, page schemas, known
values and browser snapshots include both. Field rules link them using
`timeField`/`dateField` and share `showIf`; only the date inherits required rules.
Configured bounds/steps remain browser guidance and validity checks.

Corrections carry the unchanged counterpart, explicit clears use `""`, and the
browser rejects nonempty paired time without a date before changing answers.
Saved native datetime timestamps are expanded into both control values; current
browser values, including blanks, take precedence. Site date/time and timezone
are included in startup and refreshed page context. Text mode retains its
configured format; select mode and colliding native control names remain manual.

## Public methods

### `getVoiceMessages(): array`

Returns an associative array of plain-text messages keyed by their purpose,
including `idle` and `fallback`. For visitor-visible messages, resolves saved visitor-language overrides, saved
default-language overrides, then native module translations. Internal tool feedback,
diagnostics and agent navigation instructions always use native module translations;
previous saved internal overrides are ignored. Without a form
context, returns module defaults in the current language.

```php
$action = $modules->get('FormBuilderProcessorGPTLive');
$action->fbForm($forms->form('my_form'));
$messages = $action->getVoiceMessages();
echo $sanitizer->entities($messages['idle']);
```

Escape text when placing it in HTML or attributes. Use JSON encoding when
exporting your own JavaScript data. Preserve placeholders such as `{page}` and
`{pages}` in overridden messages; the browser substitutes them where applicable.
Message defaults and labels live in `classes/Messages.php`;
translation files must use that source file's text domain.

### `getAssistantGuidance(array $context): string` - hookable

Returns additional trusted developer instructions for this form/page. Default is
an empty string; the module has no site-specific guidance built in. Register an
after-hook in **`site/init.php`** or an autoload module's `init()`, not a form
template or only `site/ready.php`: the JSON path hook can execute before ready.

**Why `init.php` for this hook?**
`site/ready.php` is normally a good place for ProcessWire hooks. This extension
is different because GPT-Live's autoload module registers its JSON URL hook in
`init()`. ProcessWire's `ProcessPageView` can execute URL/path hooks once before
the ready stage and again afterwards. GPT-Live builds the startup or page-update
response on the first invocation and reuses it for the remainder of that HTTP
request. This prevents a second provider session or duplicate start-limit charge.

A guidance hook registered only in `ready.php` may therefore arrive after the
instructions have already been built; the later path-hook pass reuses the first
response rather than rebuilding it. Registering in `init.php` makes the hook
available for that first pass. This is a requirement of the module's current
endpoint lifecycle, not a general preference for moving ProcessWire hooks out of
`ready.php`. Ordinary native FormBuilder/site hooks can remain there.

Register the callback early, but inspect the form context **inside** the callback
when it runs. Do not require a populated `$page` or render a form while registering
it in `init.php`; the callback receives its bound form/page context later.

```php
$wire->addHookAfter('FormBuilderProcessorGPTLive::getAssistantGuidance', function(HookEvent $event) {
    $context = $event->arguments(0);
    if($context['formName'] !== 'transport_request') return;

    $guidance = 'Ask one short question at a time. Reuse previously confirmed details.';
    if($context['pageNum'] === 3) {
        $guidance .= ' A previously selected transfer location already answers the destination question. Leave the optional destination address blank unless the visitor specifies another address. Do not invent an address or choose between multiple selected transfers.';
    }
    $event->return = trim($event->return . ' ' . $guidance);
});
```

This is an illustrative form-specific rule, not an installed hook. Adapt the form
name, page and field relationships to the actual native form. `$event->object`
is the current Action; its inherited `fbForm()` supplies the bound FormBuilderForm.

| Context key | Value |
|-------------|-------|
| `formName` | Bound FormBuilder form name. |
| `pageNum`, `pageCount` | Current rendered page and total pages. |
| `fields` | Current-page supported preparation schemas, including exact allowed choice values and labels in descriptions. |
| `fieldLabels` | Current-page field name to label map. |
| `knownValues` | Bounded supported answer records (`name`, `label`, `value`) from this/earlier pages. Values are strings; multiple selections are JSON-encoded strings. Prefilled values are not proof of visitor confirmation. |

The hook runs once when assembling startup or page-configuration responses,
including Back/Next; it is not called after every spoken answer or preparation.
The same guidance reaches both the speaking and preparation agents. On navigation,
the latest guidance supersedes earlier developer guidance, even when empty. Voice
instructions are appended after the page/tool update acknowledgement; paused
sessions receive guidance without speaking or resuming the microphone.

Return a plain-text string of at most 8000 bytes. Invalid returns or hook exceptions
use the existing manual-form fallback and private server diagnostics, before any
provider request. Hook code is trusted application code: never copy raw visitor
answers into instructions, include secrets, or assume guidance is deterministic.
Guidance is sent to OpenAI and page-update guidance is visible to the browser.
Keep visitor values as data in the supplied context, not executable prompt rules.

The hook only adds guidance. It does not replace core instructions, alter allowed
fields/tools, bypass native validation or enable submission. Required/visibility
business rules must still be enforced by FormBuilder/site code; conversational
guidance alone is not validation. Multiple hooks may append to `$event->return`.

### `getSelectedAgent(): ?AgentToolsAgent`

Returns the compatible AgentTools agent selected by the current form's `agentId`,
or `null` when no compatible selection is available. Compatibility checks the
OpenAI provider, model and API key; it does not verify remote model availability.

```php
$action->fbForm($forms->form('my_form'));
$agent = $action->getSelectedAgent();
if($agent) {
    // Inspect configuration on the server only; never export the agent/API key.
}
```

## Saved Action settings

Configure these through the form's **Actions** tab. Values are per form, rather
than module-wide settings. The inherited `setConfigValue($name, $value,
$saveNow = false)` and `saveConfigValue($name, $value)` methods are available for
server-side integration with an attached form; see FormBuilder's Action API for
persistence behaviour. The ordinary `set()` method is not a saved configuration API.

| Setting | Default | Purpose |
|---------|---------|---------|
| `agentId` | No selection | Stable AgentTools agent ID. |
| `assistantSpeaksFirst` | On | Standard Checkbox. Missing setting defaults on; saved `0` disables. Requests one opening reply after a new session starts, not on Resume or page changes. |
| `clarificationLimit` | `1` | Integer, 1–5. Maximum comprehension clarification/read-back questions per field before manual fallback. The initial question does not count. Invalid settings use 1. Applies to all fields; ordinary choice/date disambiguation does not count. Conversation policy interpreted by the agents, rather than a browser-enforced counter. |
| `choiceNoticeThreshold` | `4` | Integer, 1–100. Before offering more than this many valid choices, explain that visitors can interrupt or select on screen at any time. Applies to single/multiple choices and dynamic widget options. Empty placeholders are excluded; equality does not trigger it. Invalid settings use 4. Reminder delivery is an agent instruction. |
| `message_welcome` | Inherit | Spoken opening greeting; uses the same language/default/module inheritance as other visitor messages. Greeting wording is not instructions or submission permission. |
| `voice` | `marin` | Supported built-in GPT-Live voice; unknown values fall back to Marin. Fixed at session startup. |
| `accent` | Empty (automatic) | Language-specific regional preference, e.g. `en-AU` or `fr-FR`. Applies only when speaking that language; unknown values use automatic pronunciation. `custom` uses the two fields below. |
| `accentLanguage` / `accentRegion` | Empty | With `accent=custom`, any language name and regional accent name, capped at 100 characters each in the prompt. Blank/incomplete pairs retain automatic pronunciation. |
| `allowVisitorRequestedSubmission` | Off | Allow submission after explicit visitor permission; available only on the final page. |
| `jsURL` / `cssURL` | Bundled module assets | Custom browser script/style URLs. |
| `visitorStartLimit` | `5` | Starts per browser session and form; `0` disables this limit. |
| `ipStartLimit` | `20` | Starts per IP and form; `0` disables this limit. |
| `startWindowMinutes` | `10` | Rolling limit window, 1–1440 minutes. |
| `requestTimeoutSeconds` | `30` | Browser request/script deadline, 1–300 seconds; server cURL has a separate 30-second timeout. |
| `message_<key>` | Inherit | Visitor-visible plain-text default-language message override. |
| `message_<key>__lang<ID>` | Inherit | Visitor-visible installed-language override, using the language page ID. |

## Lifecycle methods and hooks

These methods are integration callbacks, not methods templates should call directly:

| Method | Responsibility |
|--------|----------------|
| `init()` | Register the shared route, iframe permissions hook and uninstall guard. |
| `getConfigInputfields(InputfieldWrapper $inputfields)` | Add Action settings to FormBuilder's configuration UI. |
| `renderReady()` | Hide voice enhancement and log missing/incompatible model selections; otherwise attach page/field contracts, messages, token and endpoint URL to the rendered form; enqueue configured assets. |
| `addRequiredPermissionsToEmbed(HookEvent $event)` | Add microphone and clipboard-write permissions to Action-enabled iframe embeds. |
| `guardActionUninstall(HookEvent $event)` | Veto `Modules::isUninstallable` while enabled forms select this Action, returning their names when a reason is requested. |

The module hooks **after** `FormBuilder::embed` and `Modules::isUninstallable`.
Disabled forms do not block uninstall; their saved settings are retained.
`getAssistantGuidance()` is the module's dedicated hookable customization point.
Use it for conversational behaviour, and FormBuilder's documented lifecycle hooks
for native form behaviour. Do not call private schema, transport or protection helpers.

## Browser integration

`renderReady()` exports messages through ProcessWire's
`$config->jsConfig('FormBuilderProcessorGPTLive')`, keyed by form name. Each entry
contains `requestTimeoutSeconds` and a base64 UTF-8 JSON `messageData` catalog.
Form-local `data-gpt-live-*` attributes retain the same context across pagination.

The bundled client reads these attributes and binds controls using the rendered
form ID and script URL. No global public JavaScript API is exposed. Custom scripts
must implement the contracts described in [HOWITWORKS.md](HOWITWORKS.md).

The shared `/formbuilder-gpt-live/{formName}/` route is an internal browser
integration endpoint, not a general-purpose public API. It requires POST JSON,
the expected Origin, an enabled Action and a server-validated browser/form token.
Session starts additionally use the configured rolling limits. Page configuration
refreshes do not start a second paid session. Failure responses reuse the Action's
friendly fallback; detailed reasons stay in server diagnostics.

Preparation properties are strings for scalar/single choices and arrays of
strings for Checkboxes, SelectMultiple, AsmSelect and multi-choice Page widgets.
Use exact submitted values, `[]` to clear multiple choices, and `"0"` to uncheck
a single Checkbox. Page values are native allowed page IDs, not titles.
Custom clients must preserve arrays in `currentValues` and follow each schema.
Tokens issued by the rendered form also bind its page number and server-generated
choice schema. After navigation, use the new rendered token; posting another page
number with an old token is rejected. Native validation remains authoritative.
Page-configuration JSON also includes `assistantGuidance`, a server-generated
voice instruction block (including an explicit reset when no custom guidance
applies). Custom clients should append it through `session.instructions.append`
after the `session.update` acknowledgement and current-page context, before
requesting the new-page response. Do not treat visitor text as this trusted block.

Exclude token-bearing form renders from full-page caches. Tokens expire one hour
after rendering; this does not end an existing running or paused voice connection.

## Development tests

`tests/` contains explicitly launched PHP, Node.js and HTTP regression checks.
The module does not load these files during normal operation. The PHP suite follows WireTests conventions and creates unsaved form fixtures,
without depending on existing site forms. The optional HTTP check accepts a
developer-supplied URL and form selection. See [tests/README.md](tests/README.md)
for prerequisites, commands and cleanup behaviour. Manual browser/voice testing remains
necessary for real microphone, widget and spoken interaction behaviour.

**Source layout:** `FormBuilderProcessorGPTLive.module.php` owns lifecycle hooks,
module identity and protocol constants. It explicitly requires and composes the
support traits in `classes/`: `Messages.php`, `Protection.php`, `Configuration.php`,
`Assets.php`, `Rendering.php`, `Session.php`, `Instructions.php`, `FormContext.php`
and `Fields.php`. All stay in `namespace ProcessWire`, with module-prefixed trait
names. Methods retain the module's hook/API identity and private access; the
traits are not independently instantiated modules. The browser client remains
`FormBuilderProcessorGPTLive.js`.

Language context is restored from the validated form token. The session and
delegation instructions receive the page language as a hint; visitor speech takes
precedence. AgentTools `requestOptions` are not merged into these payloads.
Model-specific service failures include `manualOnly: true` with the generic error;
the client closes voice transport and hides controls.

## Conversation and navigation policy

Startup voice and delegation instructions share page-specific navigation/review
rules, confirmation of meaningful unconfirmed defaults, waiting during visitor
review, fresh consent after corrections/navigation and verified tool-outcome
reporting. Page changes replace delegation tools, append the new voice context
and request `response.create` once the active session is ready; paused sessions
stay silent. Earlier clear spoken facts should be reused for current-page fields,
but recall and consent interpretation remain model-dependent.

Unavailable-model live error events log a private diagnostic and close the
connection/microphone before hiding voice controls. Manual form values remain.
