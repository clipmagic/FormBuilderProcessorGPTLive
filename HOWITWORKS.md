# How FormBuilder GPT-Live works

This guide is for developers and administrators who want to understand the
installation, Action settings and form behaviour in more detail.

For a general introduction and a quick start, see [README.md](README.md).
For PHP methods and developer integration contracts, see [API.md](API.md).
Current version: **0.2.0**. Version history is in
[CHANGELOG.md](CHANGELOG.md).

The module reads the enabled form’s supported fields and builds instructions for
the voice assistant. Spoken answers become form values only after the assistant
calls the preparation tool successfully. FormBuilder continues to control field
visibility, validation, page navigation and submission.

## Uninstalling

Untick GPT-Live on each form's Actions tab and save before uninstalling through
ProcessWire's Modules screen. If an enabled form still selects GPT-Live, the
Uninstall section is disabled and lists the forms to update. The same check
blocks uninstall requests through the ProcessWire API.

FormBuilder uses a disabled flag rather than a published status. Disabled forms
do not block uninstall; their saved Action settings are not removed automatically.

## Set up a form

1. Install this module, FormBuilder 0.5.7 or later and AgentTools 0.3.6 or later
   in ProcessWire 3.0 or later.
2. In AgentTools, configure an OpenAI agent with a model and API key that can
   create GPT-Live sessions. The key must stay in the server-side AgentTools
   configuration.
3. Open the form in FormBuilder, enable **FormBuilder GPT-Live**, choose the
   preferred AgentTools model on the **Actions** tab and save the form. The
   action provides editable URL/path settings for the browser script and voice
   controls stylesheet. The module's `.js` and `.css` files are used unless you
   change those paths.
4. The action displays the shared GPT-Live endpoint for this form. It reads the
   saved action settings and current form fields each time a voice session
   starts; no per-form endpoint file or copied field schema is needed.
5. For iframe embed methods A and B, add the controls to the site's
   `/site/templates/form-builder.php` template. The template is rendered inside
   each form's own iframe, so controls are paired with that form and do not need
   to be placed in the containing page. The action's CSS is included in the
   iframe with the form styles. Each script instance binds only controls whose
   form declares that script URL, so forms using different custom scripts do
   not attach duplicate click handlers. The iframe gets microphone and
   user-initiated clipboard permissions only when that form has GPT-Live
   enabled.
6. For directly rendered forms (method C), add the controls beside the form in
   the page template and pair them with the rendered form's HTML ID:

```html
<section class="gpt-live-controls" data-gpt-live-controls>
  <button class="gpt-live-toggle" type="button" data-gpt-live-toggle data-gpt-live-for="FormBuilder_example">
    <span class="gpt-live-toggle__icons" aria-hidden="true">
      <svg data-gpt-live-play viewBox="0 0 16 16" width="16" height="16"><path fill="currentColor" d="M4 2.8v10.4L13 8 4 2.8z"/></svg>
      <svg data-gpt-live-stop-icon viewBox="0 0 16 16" width="16" height="16" hidden><path fill="currentColor" d="M4.5 3h2.5v10H4.5zM9 3h2.5v10H9z"/></svg>
    </span>
    <span data-gpt-live-label>Start voice assistant</span>
  </button>
  <p class="gpt-live-controls__status" data-gpt-live-status role="status"></p>
  <div class="gpt-live-controls__transcript" data-gpt-live-transcript hidden></div>
  <button class="gpt-live-controls__copy" type="button" data-gpt-live-copy aria-label="Copy conversation" title="Copy conversation" hidden>
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="4" width="8" height="10" rx="1"/><path d="M10 4V3.5A1.5 1.5 0 0 0 8.5 2h-4A1.5 1.5 0 0 0 3 3.5v8A1.5 1.5 0 0 0 4.5 13H5"/></svg>
  </button>
</section>
```

Replace `FormBuilder_example` with the ID of the rendered form. The action only
loads its client script and stylesheet when enabled. If no supported fields
are available, voice controls are not configured for the form. The module CSS
styles controls in iframe embeds; include or adapt those rules for directly
rendered forms.

A custom script must support the `data-gpt-live-*` attributes
shown above, including the form's `data-gpt-live-script-url` association. Once
the transcript contains text, the copy icon copies the visible conversation
to the clipboard.

If you use a custom script, it must also implement the
visitor-requested submission behavior for the optional Action checkbox; the
module script provides that behavior.

## Action settings

Open the form’s **Actions > FormBuilder GPT-Live** settings. The supplied defaults
are suitable for getting started; **most forms only need a compatible AgentTools
model selected**.

| Setting | Default / purpose | When to change it |
| --- | --- | --- |
| AgentTools model | Select a compatible OpenAI agent configured in AgentTools. | To use another configured agent/model. |
| Speaking voice | Marin. Select a built-in GPT-Live voice. | To choose a voice whose tone and regional speaking style suit your audience. |
| Preferred regional accent | Automatic pronunciation for the spoken language. | Select a preset or Custom for any language and regional accent. |
| Allow visitors to ask GPT-Live to submit this form | Off. Visitors normally submit the form themselves. | Enable if visitors should be able to confirm submission by voice after review. |
| Browser script URL/path | The module’s JavaScript file. | For a custom client implementation. |
| Voice controls stylesheet URL/path | The module’s CSS file. | For a custom stylesheet. |
| Maximum starts per visitor session | 5 starts per form. | To adjust the allowance for one browser session; 0 disables this limit. |
| Maximum starts per IP address | 20 starts per form. | To adjust the shared-network backstop; 0 disables this limit. |
| Rolling window in minutes | 10 minutes; range 1–1440. | To change how long starts count toward both limits. |
| Request timeout in seconds | 30 seconds; range 1–300. | To change the browser wait for each startup, page/configuration, diagnostic or page-script request. |
| Voice messages | Inherit the module’s translated defaults when blank. | To customise wording for a form or language. |
| Session endpoint | Displayed for reference; generated by the Action. | No separate endpoint file or manual configuration is needed. |

### Start limits and form tokens

Limits apply per form to new connection attempts, including failed OpenAI starts.
Page Break changes and pause/resume do not consume an allowance. Set an individual
start limit to 0 to disable it.

Limits do not end or cap the duration of an existing
voice conversation. Visitors sharing a network also share its IP allowance. The
IP backstop uses ProcessWire's server-observed IP, without trusting forwarded
headers; configure the hosting proxy appropriately before deploying behind one.

Every enabled render supplies a hidden one-hour token tied to that browser session
and form. Reloading or navigating to another FormBuilder page supplies a fresh
one. Multiple tabs can coexist.

Keep token-bearing forms out of full-page caches;
a cached response cannot provide a fresh token for each visitor. This does not
require a visible field, prompt or visitor action.

### Messages and translations

Under **Voice messages**, edit visitor-visible labels, statuses and transcript
speaker names. Internal tool feedback, navigation instructions and diagnostics
are not Action inputs; developers can translate them in the module language file.
Previously saved internal overrides are ignored; native translations supply the text. The existing **Friendly fallback**
message is also used for token/limit failures; the reason is logged privately.
Technical exception details and protocol/status identifiers remain in code.
Placeholders such as `{control}`, `{destination}`, `{instruction}`, `{page}` and
`{pages}` are substituted as plain text. Keep the placeholders when translating.

Voice messages uses native ProcessWire jQuery tabs, one fieldset per language,
headed with the language name (including the default language). Message defaults and admin labels use ProcessWire's native
literal translation calls.

Developers/translators can create language JSON files
through ProcessWire's translation tools for `FormBuilderProcessorGPTLiveMessages.php`
and `FormBuilderProcessorGPTLiveProtection.php`; the main module and info file have
their own text domains for their remaining admin/metadata strings. Trait calls explicitly use `__FILE__`.

Resolution order: saved visitor-language override, saved default-language override,
then the module's language-file translation (English when untranslated). Blank
Action fields inherit; defaults appear as placeholders rather than being saved.

Existing saved English/default overrides still take precedence: clear them to let
module translations apply. Keep message placeholders unchanged in language files.
Each per-form language override is a normal scalar Action setting: this avoids relying on
language-tab values which FormBuilder's Action saver does not persist automatically.
The rendered language is retained in the token for subsequent JSON requests.
Voice and delegated preparation instructions use it as an initial hint for unclear
speech. Clearly spoken visitor language takes precedence, including natural
language switching. Untranslated field labels do not determine conversation language.
For iframe embeds, enable each language on the Form Builder page as well as the
page containing the form; otherwise the localized iframe URL can return 404.

### Message delivery and custom controls

PHP publishes the catalog through `$config->jsConfig('FormBuilderProcessorGPTLive', ...)`,
which FormBuilder emits into `ProcessWire.config`. It uses a base64 UTF-8 JSON
`messageData` value to safely transport editable text in FormBuilder's inline
script, even in debug mode. A `data-gpt-live-messages` JSON attribute on each form
is the browser client's first choice, so Page Break replacement does not need to
replay inline scripts. The form also provides `data-gpt-live-token` and
`data-gpt-live-form-name`. Custom scripts must pass `token` in all endpoint JSON
requests and use the Action's message catalog.

Custom control templates can initialize labels from the same PHP catalog. Replace
`my_form` with your FormBuilder form name. The render result's `form` property
provides the underlying form and its saved Action settings:

```php
$renderedForm = $forms->render('my_form');

$action = $modules->get('FormBuilderProcessorGPTLive');
$action->fbForm($renderedForm->form);
$messages = $action->getVoiceMessages();
// Escape these strings when placing them in text or HTML attributes.
echo $sanitizer->entities($messages['idle']);
```

## Supported fields and conditions

The current action supports text, textarea, email, URL, telephone, integer,
float, number, select, radio, text-mode datetime and native HTML date fields.
Choice fields use the form's configured options. Text-mode datetime fields use
the combined date and time format configured in FormBuilder. Native HTML date
fields use the browser's `YYYY-MM-DD` value format; native time and paired
date/time fields are not currently supported.

Supported fields with `showIf` conditions are included in the tool schema with
their condition. The action prepares those fields only after FormBuilder
displays them.

Supported fields with `requiredIf` conditions are required only
when the condition matches the current form values. This includes common field
conditions such as `pet_type=other`.

Missing dependencies and unselected choices are treated as empty strings
in both PHP context and browser checks. Empty strings never become numeric zero.
Numeric comparisons accept finite decimal/scientific values (including signs and
surrounding ASCII whitespace), and reject blank values, hexadecimal and infinity.

Quoted values, OR choices and comma-separated dependencies use the same semantics
in both implementations; rendered FormBuilder visibility still decides whether a
field can be prepared.

Honeypots, nested forms and unsupported
input types are not offered to the assistant. Browser validity is checked for
visible fields after values are prepared, and FormBuilder performs its normal
validation on submission.

## Multi-page forms

For forms with FormBuilder Page Break fields, each voice session receives only
the supported fields on the page currently rendered. The assistant will not ask
about later-page fields.

It can use values already entered in visible supported
fields and saved supported values from earlier pages as context, so the visitor
does not need to repeat them. Both agents are instructed to reuse clear spoken
details volunteered on earlier pages before asking about empty fields on the
current page. Conversational recall depends on the model; unclear or conflicting
answers still need clarification.

FormBuilder still controls page navigation and
retains partial entries according to the form's settings. While voice mode is
active or paused, the module refreshes only that form after FormBuilder's normal
page-navigation POST.

The same voice connection and full visible transcript
continue on the next or previous page, including in iframe embeds. The assistant
updates its field list and reads the populated visible fields on the new page,
including when the visitor returns to a page. It does not treat those values
as missing just because the page changed. On intermediate pages it asks the
visitor to use the configured navigation control and Page Break label; it waits
until the final page to ask for review and possible submission. A full browser
refresh still starts a new voice conversation. After a successful page change,
an active session requests a brief introduction and continuation once the new
fields and tools are ready. Paused sessions stay silent. If the visitor asked to
review or check, the assistant should acknowledge the page and wait.

Within that voice conversation, GPT-Live remembers the latest visible supported
answers before each page transition and restores them when that page returns.
This includes corrections made by voice or manually and deliberate empty answers.
Restoration respects the rendered field visibility and available choices; it does
not force a hidden field into view or save an entry separately. FormBuilder still
owns validation and persistence. These temporary answers do not survive a browser
refresh; after refresh, the assistant receives the values FormBuilder restores.

The assistant waits for the preparation tool to populate and validate supported
fields before saying a page is ready. If FormBuilder prevents a page change,
its field errors remain visible and the assistant stays on that page to help
resolve them.

A visitor can still use the ordinary page controls before
starting voice help; FormBuilder applies its usual validation.

This page-transition support does not require FormBuilderHtmx.

Supported values sent as context are included in the session request to the
configured OpenAI model, like information the visitor speaks during the session.

## Dates and validation

For native HTML date fields (`inputType=html`, `htmlType=date`), the action
passes the configured `dateMin` and `dateMax` values to the model as guidance,
along with the `YYYY-MM-DD` value format. This may help the assistant ask for a
date within the configured range, but the model can overlook the guidance.
FormBuilder's native date control and normal submission validation remain
authoritative. The action also checks the browser control's validity after
preparing a value.

For text-mode datetime fields, the action supplies the configured date/time
format but does not send date bounds. A default of today and a datepicker
`yearRange` are not date validity bounds. Native time-only and paired native
date/time fields are not currently supported.

## Audio, data and troubleshooting

The form streams the visitor's audio to OpenAI's GPT-Live service. The
browser also displays the visitor and assistant transcript during the session.
Stop the voice assistant when the conversation is finished. If testing in more
than one browser or tab, end the other session first; each page has its own
voice session and can continue listening independently.

If OpenAI cannot start a voice session, visitors see a generic message asking
them to complete and submit the form normally. OpenAI session failures and
browser-side connection errors are recorded in ProcessWire's
`formbuilder-gpt-live` log (Setup > Logs), where an administrator can
investigate without exposing technical details to visitors.

## Review and submission

The assistant asks for missing required details and leaves prepared values in
the visible form for review. It can prepare corrections while the session is
open. Submission is disabled by default.

If an administrator enables the
visitor-requested submission option, GPT-Live asks the visitor to review the
prepared form and then asks for explicit confirmation before submitting. A
visitor's earlier request or general approval is not confirmation. Navigation
and corrections require fresh review and confirmation. Requests to check, go
back, wait or review should receive a brief acknowledgement followed by waiting,
without another submission offer.

Existing values are preserved, but meaningful defaults such as dates, times
and service choices should be confirmed unless the visitor already supplied or
confirmed them. The assistant should describe updates only after successful
preparation, and submission as requested only after the submission tool accepts
it. Submission requested is not a success receipt. These are agent instructions;
the browser still relies on the model interpreting spoken consent.

The Action
hands the current values to FormBuilder's normal submit control; FormBuilder
still validates and processes the submission. The assistant must not submit
automatically, and it cannot claim success until FormBuilder reports it.
Visitors can also submit through the ordinary form controls at any time.

See [CHANGELOG.md](CHANGELOG.md) for version changes.

## Timeouts and recovery

Connection and page-change requests, including response-body reads and new page
scripts, use the Action’s **Request timeout in seconds** setting (default 30; range
1–300).

Each browser request has its own deadline. The server request to OpenAI currently
has a separate fixed 30-second timeout; the Action option does not change it.

Closing the voice conversation aborts its
outstanding work. Late responses and events cannot update a newer conversation.

Failures reuse the existing Action messages; form values and transcript stay
available. Aborting a browser request does not undo processing already performed
by FormBuilder or the voice service on the server.

When a native submission event is cancelled, fresh browser validation stops it,
or `requestSubmit` throws, the assistant releases its pending-submission guard.
It does not retry automatically.

Accepted native submissions retain the guard
against another voice submission while FormBuilder processes them. A custom
handler that cancels native submission and sends it through AJAX must own its
own in-flight/duplicate protection; cancellation is not a success receipt.

### Missing model selection

A missing or incompatible saved AgentTools selection leaves the native form usable,
hides voice controls through the client, and logs the reason in `formbuilder-gpt-live`.
An unavailable-model response during startup or through the live data channel
stops voice and hides controls for that page while retaining entered values for
manual completion. Live errors are logged before cleanup. Provider availability
is learned from service responses, rather than probed on every form render. Other service failures keep the existing friendly fallback.

GPT-Live currently reads the selected agent’s model and API key, but does not merge
AgentTools optional provider request options into its delegation payload.

### Language routing and conversation

ProcessWire’s LanguageSupportPageNames resolves the URL to `$user->language`;
GPT-Live does not parse language prefixes or assume the default is English.
The rendered form token retains that language for endpoint requests. Use a
meaningful language title, especially for the language named `default`.
Conversation follows visitor speech naturally; page language is an initial
ambiguity hint, not a forced conversation language. UI/form translations and
voice-language understanding are separate. An explicit request to speak another
language can clarify ambiguous recognition without installing that UI language.

OpenAI session requests use native WireHttp with a fixed 30-second server timeout,
one selected transport attempt and no redirects. The separate Action request-timeout
setting still controls browser requests; it does not change the server timeout.

## Voice and regional accent

The selected voice is sent as `session.audio.output.voice` when the conversation
starts. It stays fixed even if the visitor changes language. End the conversation
and start again after changing voice settings.

The accent preference is speaking guidance, not a language restriction. With
English — Australian selected, GPT-Live is instructed to use that accent only
while speaking English. When the visitor switches to French, it is instructed to
use natural French pronunciation. The page language remains initial context and
clearly spoken visitor language takes precedence.

The presets are conveniences, not a supported-language list. Select **Custom — any language and regional accent** to enter any spoken language and its preferred regional pronunciation. This preference never restricts which language the visitor can speak. If the custom pair is incomplete, automatic pronunciation applies.

Regional voices retain their own influence. Instructions cannot guarantee accent
fidelity or remove that influence; listen to each voice in the languages your form
supports. Existing forms retain Marin and automatic pronunciation. No form settings
need migration.

Voice names and regional descriptions follow the [OpenAI GPT-Live session guide](https://developers.openai.com/api/docs/guides/live-conversations)
and [session API reference](https://developers.openai.com/api/reference/resources/live/methods/create).
