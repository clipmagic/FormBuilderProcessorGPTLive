# FormBuilderProcessorGPTLive

Adds GPT-Live voice input to a ProcessWire FormBuilder form. Spoken answers are
prepared in supported visible fields; FormBuilder retains validation, saving,
pagination and submission. Credentials remain on the server.

This reference describes version **1.16**. See [HOWITWORKS.md](HOWITWORKS.md)
for setup, controls markup and detailed Action settings, or [README.md](README.md)
for the general overview.

## Access and form context

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
Message defaults and labels live in `FormBuilderProcessorGPTLiveMessages.php`;
translation files must use that source file's text domain.

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
The module does not declare custom triple-underscore hookable methods. Follow
FormBuilder's documented lifecycle hooks for extensions rather than calling
private schema, transport or protection helpers.

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

Exclude token-bearing form renders from full-page caches. Tokens expire one hour
after rendering; this does not end an existing running or paused voice connection.

## Development tests

`tests/` contains explicitly launched PHP, Node.js and HTTP regression checks.
The module does not load these files during normal operation. The PHP suite follows WireTests conventions and creates unsaved form fixtures,
without depending on existing site forms. The optional HTTP check accepts a
developer-supplied URL and form selection. See [tests/README.md](tests/README.md)
for prerequisites, commands and cleanup behaviour. Manual browser/voice testing remains
necessary for real microphone, widget and spoken interaction behaviour.

**Source files:** `FormBuilderProcessorGPTLive.module.php`,
`FormBuilderProcessorGPTLiveMessages.php`, `FormBuilderProcessorGPTLiveProtection.php`
and `FormBuilderProcessorGPTLive.js`.

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
