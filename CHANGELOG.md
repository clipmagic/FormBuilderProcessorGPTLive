# Changelog

User-facing module changes are grouped by the version in
`FormBuilderProcessorGPTLive.info.php`. Dates identify release preparation and earlier local development.

## 1.16 — 2026-10-05

### Changed

- Prepare the first non-alpha version, 1.16, following owner-approved sandbox
  voice testing of cross-page recall, navigation, date clarification and consent.

- Prompt a brief introduction after a successful active-session page change.
  Keep paused sessions silent and respect visitor review requests. Reinforce
  reuse of earlier spoken answers before asking about new-page empty fields.

- Clarify meaningful prefilled preferences, wait during visitor review, require
  fresh consent after navigation/corrections and describe only verified tool
  outcomes in both agents and voice page-change guidance.

- Give the speaking agent the exact current-page navigation and submission
  policy at startup, matching the delegation model and later page updates.

- Handle unavailable delegation models reported through the live data channel:
  log the private diagnostic, stop voice and hide its controls while preserving
  the regular form and entered values.

- Set the four Voice session limits inputs to 50% native column widths.

- Expose only visitor-visible messages in Action language tabs. Internal tool
  feedback, navigation instructions and diagnostics use native language-file
  translations, ignoring previous per-form internal overrides.

- Use ProcessWire WireHttp for session creation, retaining the 30-second server
  timeout, raw JSON, status/error handling and private diagnostics. Use one
  transport attempt without redirects to avoid repeating session-creation POSTs.

- Consolidate PHP regressions into a portable WireTests class using unsaved form
  fixtures. Remove local form dependencies and parameterize HTTP checks by URL
  and optional form name. Add developer test-running instructions.

### Added

- Developer API reference covering form context, public methods, saved settings,
  lifecycle hooks, browser contracts and development-test boundaries.

## 0.1.15 Alpha — 2026-10-01

### Changed

- Present Voice messages in native ProcessWire language tabs, including the default
  language, while preserving scalar Action setting names and saved overrides.
- Use the rendered page language as an initial hint for voice and delegated form
  preparation. Follow clearly spoken visitor language naturally; untranslated
  form labels must not override it.

### Fixed

- Missing/incompatible saved AgentTools selections render a manual-only form and
  log the reason. Model-specific service rejection hides controls after startup
  fails, without exposing provider details to visitors.

### Verified

- DDEV unsaved fixtures cover missing selections, private logging and provider
  rejection payloads. Client checks cover hidden controls and startup/recovery.
- Real headless Chrome checked DDEV-rendered English/French language tabs,
  switching both ways and retaining a saved French phrase.
- Voice/delegation payload fixtures include French context and visitor-language
  precedence; actual spoken language switching remains unverified.

## 0.1.14 Alpha — 2026-10-01

### Added

- Block uninstall through ProcessWire's native module check while enabled forms
  select GPT-Live, listing their names and asking the administrator to untick
  the Action and save first. Disabled forms do not block removal.

### Fixed

- Supply prefilled answers to the voice agent at startup as well as the backend
  preparation instructions, so populated fields are treated as existing answers.
- Retain current visible supported answers within the voice conversation across
  Back/Next replacement. Restore them before updating page context, respecting
  current field visibility and configured choices. FormBuilder still owns saving.

### Changed

- Remove the pass-through constructor and inherit the base Action constructor.

### Verified

- Read-only tests confirm the native uninstall veto with a saved selected form,
  sorted form names, disabled/unselected exclusions, preserved core reasons and
  an unused Action. The admin display and actual uninstall/reinstall remain
  awaiting manual verification.
- The owner manually confirmed that the voice agent recognises existing field
  data and that a corrected date remains after Back/Next paging.

## 0.1.13 Alpha — 2026-10-01

### Changed

- Split documentation into a friendly defaults-first README and a detailed
  HOWITWORKS guide covering installation, Action options and technical behaviour.
- Use ProcessWire-discoverable literal translations and explicit trait-file text
  domains for message defaults, message labels and protection settings.
- Leave unsaved message fields blank with inherited placeholders, preserving saved
  Action overrides while allowing module translation files to supply defaults.
- Keep the exported message catalog plain text; escape only at rendering boundaries.

## 0.1.12 Alpha — 2026-09-30

### Added

- Require a short-lived token tied to the visitor session and rendered form before
  accepting session starts, page-tool refreshes or browser diagnostics.
- Add per-form Action settings for maximum starts per visitor session, maximum
  starts per IP and the rolling window. Defaults: 5 / 20 starts in 10 minutes.
  Page navigation and pause/resume do not count; failed service starts do.
- Make voice control labels, statuses, transcript labels, tool feedback and fixed
  diagnostic messages editable in Action settings. When LanguageSupport is
  installed, offer a separate message group for each language with default fallback.
- Share PHP message settings through ProcessWire jsConfig and form-local attributes.
  Reuse the existing friendly fallback for token/limit failures; log details privately.

### Changed

- Share configured JS/CSS URL resolution and form JSON attribute parsing while
  preserving site-root handling, cache versions and metadata shape checks.
- Split endpoint assembly into named helpers for tool schemas, page context/review,
  preparation and voice policies, transport and service-response validation.
  Preserve request payloads and existing permission/error contracts.
- Bound startup, pagination/configuration, diagnostic and page-script requests to
  the Action’s configurable request timeout (default 30 seconds). Abort owner work on closure and ignore stale responses/events.
- Release the submission guard when native submission is cancelled, fails fresh
  validation or throws. Preserve the guard for accepted native submissions and
  never retry automatically. Reuse existing fallback/failure messages.
- Align PHP/browser conditions for missing and empty values, finite decimal/scientific
  numbers, choice lists, quoted values and string comparisons. Rendered FormBuilder
  visibility remains authoritative; shared fixtures cover both evaluators.
- Process each endpoint request once across Action instances and ProcessWire path-hook
  phases, preventing a single voice start from consuming multiple allowances or
  creating duplicate paid sessions.
- Reuse shared messages for starting, navigation, copying, listening, connection
  failures and missing details. Remove duplicate Action fields while retaining
  fallback reads of previously saved text and translations.
- Initial labels in this project's direct/iframe control templates use Action messages.
- Document server/browser responsibilities and preserve the existing optional,
  explicitly confirmed submission flow.

## 0.1.11 Alpha — 2026-09-29

### Changed

- Keep the same voice session, paused or active state, and complete visible
  transcript through FormBuilder Page Break navigation in rendered and iframe
  forms.
- Update the assistant's page-specific fields and tools after navigation. On
  intermediate pages, guide the visitor using the configured Page Break and
  navigation labels; reserve final review and submission for the last page.
- Give the voice assistant the current page's field labels at startup and after
  each page change, so broad requests do not pull its questions onto another
  page.
- Restore the voice assistant's view of populated fields after returning to a
  page, so it does not ask again for values that remain in the form.
- Require a successful preparation-tool result before the assistant says a
  page is ready. If FormBuilder blocks page navigation, keep its validation
  errors visible and report the failed attempt to the assistant.

## 0.1.10 Alpha — 2026-09-28

### Changed

- Keep transcript text and its background readable in light and dark color
  schemes.

## 0.1.9 Alpha — 2026-09-28

### Changed

- Record sanitized browser-side GPT-Live connection errors and unexpected
  OpenAI session response shapes in ProcessWire's `formbuilder-gpt-live` log
  while keeping visitor messages generic.
- Return only the WebRTC SDP answer the browser needs instead of echoing the
  complete Live session response.
- Return session endpoint JSON through ProcessWire's URL hook response instead
  of echoing it and returning a silent handled flag.

## 0.1.8 Alpha — 2026-09-28

### Added

- Limit each GPT-Live session to the currently rendered FormBuilder page and
  provide already-entered supported values as context, including saved values
  from earlier pages.

### Fixed

- Keep GPT-Live from asking about supported fields on later FormBuilder pages.
- Describe preparation as applying to the current page, and enable the
  optional visitor-requested submission tool only on the final page.

## 0.1.7 Alpha — 2026-09-28

### Fixed

- Log missing or incompatible AgentTools model configuration in ProcessWire's
  `formbuilder-gpt-live` log while keeping the visitor-facing fallback generic.

## 0.1.6 Alpha — 2026-09-28

### Changed

- The voice control now pauses and resumes the same live session, preserving
  the conversation and prepared form values across button clicks.
- The active control uses a pause icon and the label “Pause voice assistant”.

## 0.1.5 Alpha — 2026-09-28

### Changed

- Make the submission confirmation sequence explicit: prepare and validate
  first, ask the visitor to review, then request permission to submit.

## 0.1.4 Alpha — 2026-09-28

### Changed

- OpenAI session-start failures now show visitors a generic manual-completion
  message. Technical response details are recorded in the ProcessWire
  `formbuilder-gpt-live` log.

## 0.1.3 Alpha — 2026-09-28

### Added

- An optional per-Action setting lets visitors ask GPT-Live to submit the form
  after reviewing it and explicitly confirming. It is off by default;
  FormBuilder's normal submit control, validation and processing remain in use.

## 0.1.2 Alpha — 2026-09-26

### Added

- Support native HTML date fields and include configured minimum/maximum dates
  as model guidance. FormBuilder's browser and submission validation remain
  authoritative.

## 0.1.1 Alpha — 2026-09-26

### Added

- Support for `showIf` and `requiredIf` conditions on supported fields. The
  action waits for FormBuilder to reveal a conditional field before preparing
  it and checks conditional requirements against current form values.

## 0.1.0 Alpha — 2026-09-25

### Added

- A per-form FormBuilder action setting to select a compatible OpenAI
  AgentTools agent.
- One shared ProcessWire endpoint that reads the selected agent and builds the
  preparation schema from the current form fields for each voice session.
- Browser controls for starting and ending voice mode, showing session status
  and displaying the visitor and assistant transcript.
- An editable per-form URL/path for the browser controls script, with the module
  script supplied as the default.
- An editable per-form URL/path for the voice controls stylesheet, with module
  CSS supplied as the default and loaded inside iframe embeds.
- Preparation for supported visible form fields, with the visitor responsible
  for review and submission.

### Changed

- GPT-Live preparation is available as an independently selectable FormBuilder
  action. The existing Contact-page voice proof of concept remains separate.
- Iframe embed controls are associated with the individual action-enabled form;
  direct-rendered forms can keep developer-placed controls.
- Browser script instances bind only controls paired to forms that declare the
  same script URL, preventing duplicate handlers when forms use different
  custom scripts.
- Mixed inline and iframe form rendering no longer lets the parent and iframe
  FormBuilder resize handlers compete for the iframe height.
- Required field guidance and datetime instructions now use the current
  FormBuilder field settings, including the combined date and time input format.

### Fixed

- Preserve the SDP offer required to start a WebRTC session.
- Ask for missing required details and reject preparation while an
  unconditional required field is empty.
- Re-run field preparation after a visitor corrects or adds information, so the
  visible form is updated before the assistant confirms the change.
- Hide the inactive play/stop SVG icon when controls are rendered inside the
  FormBuilder iframe.
- Switch the visible play/stop icon from the button's pressed state so site or
  FormBuilder theme CSS cannot leave the play icon visible during a session.

### Verified

- The owner confirmed the Chrome voice flow prepared the form, showed the live
  transcript and caught a weekday/date mismatch. The in-app browser had remained
  in its own active session while Chrome was being tested; end a voice session
  before switching browsers to avoid parallel capture.
