/* Two form-scoped toggles share one voice state and one transcript/copy region. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../FormBuilderProcessorGPTLive.js'), 'utf8');
const instrumented = source.replace('let activeSession = null;', `let activeSession = null; globalThis.testAPI = {
	bindControls, updateControls, findFormForControls, pauseSession, resumeSession,
	get activeSession() { return activeSession; }, set activeSession(value) { activeSession = value; }
};`);

function element(attributes = {}) {
	return {
		attributes: Object.assign({}, attributes), dataset: {}, hidden: false, disabled: false, textContent: '',
		listeners: {}, children: {},
		getAttribute(name) { return this.attributes[name] || null; },
		setAttribute(name, value) { this.attributes[name] = String(value); },
		addEventListener(name, callback) { (this.listeners[name] ||= []).push(callback); },
		querySelector(selector) { return this.children[selector] || null; },
		async click() { for(const callback of this.listeners.click || []) await callback(); }
	};
}

function makeForm(id, copyLabel) {
	return {
		id,
		dataset: { gptLiveSessionUrl: '/session/', gptLiveScriptUrl: '/client.js', gptLiveMessages: JSON.stringify({
			idle: 'Start ' + copyLabel, starting: 'Starting ' + copyLabel, active: 'Pause ' + copyLabel,
			pausing: 'Pausing ' + copyLabel, paused: 'Resume ' + copyLabel,
			resuming: 'Resuming ' + copyLabel, navigating: 'Moving ' + copyLabel, copy: 'Copy ' + copyLabel
		}) },
		matches() { return true; }, compareDocumentPosition() { return 4; }
	};
}

function makeControls(formId, withButton, withTranscript) {
	const button = withButton ? element({ 'data-gpt-live-for': formId }) : null;
	if(button) {
		button.children['[data-gpt-live-label]'] = element();
		button.children['[data-gpt-live-play]'] = element();
		button.children['[data-gpt-live-stop-icon]'] = element();
	}
	const controls = {
		dataset: {}, attributes: withButton ? {} : { 'data-gpt-live-transcript-for': formId }, hidden: false,
		getAttribute(name) { return this.attributes[name] || null; },
		children: button ? { '[data-gpt-live-toggle]': button } : {},
		querySelector(selector) { return this.children[selector] || null; }
	};
	if(withTranscript) {
		controls.children['[data-gpt-live-status]'] = element();
		controls.children['[data-gpt-live-transcript]'] = element();
		controls.children['[data-gpt-live-copy]'] = element();
	}
	return controls;
}

const forms = [makeForm('FormBuilder_long', 'Long'), makeForm('FormBuilder_other', 'Other')];
const longTop = makeControls(forms[0].id, true, false);
const longBottom = makeControls(forms[0].id, true, false);
const longTranscript = makeControls(forms[0].id, false, true);
const otherControls = makeControls(forms[1].id, true, true);
const groups = [longTop, longBottom, longTranscript, otherControls];
const buttons = groups.map(group => group.querySelector('[data-gpt-live-toggle]')).filter(Boolean);
const context = {
	crypto: require('node:crypto').webcrypto,
	document: { currentScript: { src: 'https://example.test/client.js' }, baseURI: 'https://example.test/', readyState: 'loading',
		querySelectorAll(selector) {
			if(selector.startsWith('form[')) return forms;
			if(selector === '[data-gpt-live-controls]') return groups;
			if(selector.startsWith('[data-gpt-live-toggle]')) return buttons;
			return [];
		}, addEventListener() {} },
	window: { setTimeout, clearTimeout }, navigator: {}, TextDecoder, Uint8Array, atob, URL, AbortController, console,
	Node: { DOCUMENT_POSITION_FOLLOWING: 4 }
};
vm.createContext(context);
vm.runInContext(instrumented, context);

const api = context.testAPI;
for(const group of groups) api.bindControls(group);
assert.equal(longTop.querySelector('[data-gpt-live-toggle]').querySelector('[data-gpt-live-label]').textContent, 'Start Long');
assert.equal(longBottom.querySelector('[data-gpt-live-toggle]').querySelector('[data-gpt-live-label]').textContent, 'Start Long');
assert.equal(otherControls.querySelector('[data-gpt-live-toggle]').querySelector('[data-gpt-live-label]').textContent, 'Start Other');
assert.equal(groups.filter(group => group.querySelector('[data-gpt-live-transcript]')).length, 2);
assert.equal(groups.filter(group => group.querySelector('[data-gpt-live-copy]')).length, 2);
assert.equal(longTranscript.querySelector('[data-gpt-live-copy]').listeners.click.length, 1);
assert.equal(longTop.querySelector('[data-gpt-live-copy]'), null);

const session = {
	form: forms[0], controls: longTranscript, paused: false, closed: false, transitioning: false,
	microphoneSender: { async replaceTrack() {} }, microphone: null
};
api.activeSession = session;
api.updateControls(session.controls, 'starting');
for(const button of buttons.slice(0, 2)) {
	assert.equal(button.disabled, true);
	assert.equal(button.getAttribute('aria-pressed'), 'false');
	assert.equal(button.querySelector('[data-gpt-live-label]').textContent, 'Starting Long');
}
assert.equal(buttons[2].disabled, false);

(async function () {
	api.updateControls(session.controls, 'active');
	for(const button of buttons.slice(0, 2)) {
		assert.equal(button.getAttribute('aria-pressed'), 'true');
		assert.equal(button.querySelector('[data-gpt-live-label]').textContent, 'Pause Long');
		assert.equal(button.querySelector('[data-gpt-live-play]').hidden, true);
		assert.equal(button.querySelector('[data-gpt-live-stop-icon]').hidden, false);
	}
	await longTop.querySelector('[data-gpt-live-toggle]').click();
	for(const button of buttons.slice(0, 2)) assert.equal(button.disabled, true, 'pausing state is synchronized');
	await new Promise(resolve => setTimeout(resolve, 0));
	assert.equal(session.paused, true);
	for(const button of buttons.slice(0, 2)) {
		assert.equal(button.disabled, false);
		assert.equal(button.getAttribute('aria-pressed'), 'false');
		assert.equal(button.querySelector('[data-gpt-live-label]').textContent, 'Resume Long');
	}

	const track = { stop() {} };
	context.navigator.mediaDevices = { async getUserMedia() { return { getTracks() { return [track]; }, getAudioTracks() { return [track]; } }; } };
	await longBottom.querySelector('[data-gpt-live-toggle]').click();
	for(const button of buttons.slice(0, 2)) assert.equal(button.disabled, true, 'resuming state is synchronized');
	await new Promise(resolve => setTimeout(resolve, 0));
	assert.equal(session.paused, false);
	for(const button of buttons.slice(0, 2)) {
		assert.equal(button.getAttribute('aria-pressed'), 'true');
		assert.equal(button.querySelector('[data-gpt-live-label]').textContent, 'Pause Long');
	}

	api.updateControls(session.controls, 'navigating');
	for(const button of buttons.slice(0, 2)) {
		assert.equal(button.disabled, true);
		assert.equal(button.querySelector('[data-gpt-live-label]').textContent, 'Moving Long');
	}
	assert.equal(buttons[2].disabled, false, 'another form does not inherit busy state');

	// FormBuilder pagination replaces the form node but retains its ID and controls.
	const replacement = makeForm(forms[0].id, 'Long');
	forms[0] = replacement;
	session.form = replacement;
	api.updateControls(session.controls, 'paused');
	assert.equal(api.findFormForControls(longTop), replacement);
	assert.equal(api.findFormForControls(longTranscript), replacement);
	for(const button of buttons.slice(0, 2)) {
		assert.equal(button.getAttribute('aria-pressed'), 'false');
		assert.equal(button.querySelector('[data-gpt-live-label]').textContent, 'Resume Long');
	}
	assert.equal(buttons[2].querySelector('[data-gpt-live-label]').textContent, 'Start Other');
	console.log('PASS form-scoped toggle binding, shared active/paused/busy state, either-button pause/resume, one transcript/copy target, separate forms and pagination replacement');
})().catch(error => { console.error(error); process.exitCode = 1; });
