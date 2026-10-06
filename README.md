# FormBuilder GPT-Live

Let visitors complete a form through a conversation.

FormBuilder GPT-Live adds a voice assistant to your ProcessWire FormBuilder
forms. Visitors can explain what they need in their own words, answer follow-up
questions and watch their answers appear in the form.

They can read the conversation, correct what was heard and check the completed
fields before sending. The ordinary form is always available too.

## A little help with longer forms

A visitor might say:

> My name is Fred. You can email me at fred@example.test. I’d like an appointment
> next Tuesday morning, and my dog needs a wash and trim.

The assistant helps put those details in the right places and asks about anything
missing or unclear. Visitors can also edit the fields themselves.

For forms with several pages, the conversation and transcript continue as the
visitor moves forward or back. They can pause to find information and resume the
same conversation when ready. Refreshing the browser starts a new conversation;
FormBuilder handles saved or partially completed answers according to its settings.

The assistant can make mistakes, so visitors should always check the final details.

## Getting started

Your site needs ProcessWire, FormBuilder and AgentTools, with a compatible OpenAI
agent configured. A site developer also needs to add the voice controls to the
form template once.

Once that setup is in place:

1. Open your form in FormBuilder and go to **Actions**.
2. Enable **FormBuilder GPT-Live**, choose your configured AgentTools model and save.
3. Open the form and select **Start voice assistant**. Allow microphone access when asked.

**Keep the other settings at their defaults to begin with.** You do not need to
write prompts, copy field definitions or configure a separate service for each form.
The assistant uses the questions, choices and conditions already in FormBuilder.
Some specialised fields still need to be completed manually.

The [installation and configuration guide](HOWITWORKS.md) covers the initial
site setup and optional settings when you need them.

## Visitors stay in control

Visitors can use voice, type normally, or use both. They can pause and resume the
assistant and copy the visible conversation.

By default, visitors submit the form themselves. You can optionally let them ask
the assistant to submit it. With that option enabled, the assistant asks them to
review the form and explicitly confirm before sending.

FormBuilder still validates and processes every submission. If voice assistance
cannot connect, visitors see a friendly message and can complete the form normally.

## What to know before enabling it

Visitors need an internet connection, a supported browser and microphone access.
They do not need a ChatGPT subscription or browser extension.

Voice audio and supported form details are sent to OpenAI to provide the assistance.
API usage is paid by the site owner through the configured AgentTools account.
Default connection-start limits help control repeated use without cutting short
an existing conversation.

Messages can be translated using ProcessWire’s language tools, or customised for
an individual form. The supplied wording works without editing it.

## Find out more

- [How it works and Action settings](HOWITWORKS.md)
- [Version history](CHANGELOG.md)

Current version: **0.2.0**.
