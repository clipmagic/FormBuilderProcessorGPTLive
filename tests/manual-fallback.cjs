/* Manual fallback context: no network, microphone or submission. */
const assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm'), path = require('node:path');
class Input {
 constructor(value) { this.type = 'text'; this.name = 'phone'; this.value = value; }
 closest() { return { hidden: false, getClientRects() { return [1]; } }; }
}
const timers = new Map(); let timerId = 0;
function flush() { const pending = Array.from(timers.values()); timers.clear(); pending.forEach(fn => fn()); }
const context = { HTMLInputElement: Input, HTMLSelectElement: class {}, HTMLTextAreaElement: class {}, HTMLOptGroupElement: class {}, window: { setTimeout(fn) { timers.set(++timerId, fn); return timerId; }, clearTimeout(id) { timers.delete(id); } }, TextEncoder, crypto: { randomUUID() { return 'fixture'; } }, getComputedStyle() { return { visibility: 'visible' }; }, document: { currentScript: null, readyState: 'loading', addEventListener() {} } };
vm.createContext(context);
const source = fs.readFileSync(path.join(__dirname, '../FormBuilderProcessorGPTLive.js'), 'utf8');
vm.runInContext(source.replace('let activeSession = null;', 'let activeSession = null; globalThis.attach = function(s) { activeSession = s; attachPageNavigation(s); };'), context);
const listeners = {}, input = new Input('0444555666'), sent = [];
const form = { dataset: { gptLivePageNum: '1', gptLiveFieldNames: '["phone"]' }, elements: { namedItem(name) { return name === 'phone' ? input : null; } }, classList: { contains() { return false; } }, addEventListener(type, listener) { listeners[type] = listener; } };
const session = { form, preparedPages: { 1: 'old' }, channel: { readyState: 'open', send(data) { sent.push(JSON.parse(data)); } } };
context.attach(session);
const change = listeners.change;
change({ isTrusted: false, target: input });
assert.equal(sent.length, 0, 'Synthetic voice writes must not become manual corrections');
change({ isTrusted: true, target: input });
assert.equal(session.preparedPages[1], undefined);
assert(sent.some(event => event.type === 'session.thinking.append' && event.content.includes('0444555666')));
assert(sent.some(event => event.type === 'session.instructions.append' && event.content.includes('continue waiting')));
assert(!sent.some(event => event.type === 'response.create'), 'Typing must not trigger speech');
sent.length = 0;
input.value = '044';
listeners.input({ type: 'input', isTrusted: true, target: input });
input.value = '0444444555';
listeners.input({ type: 'input', isTrusted: true, target: input });
assert.equal(timers.size, 1, 'Typing must debounce');
assert.equal(sent.length, 0);
flush();
assert(sent.some(event => event.type === 'session.thinking.append' && event.content.includes('0444444555')), 'Focused input must reach context without blur');
const instructions = sent.filter(event => event.type === 'session.instructions.append').map(event => event.content).join('');
assert(instructions.includes('without asking them to say, spell or confirm'));
sent.length = 0;
change({ isTrusted: true, target: input });
assert.equal(sent.length, 0, 'Blur must not duplicate the same manual value');
input.value = '2064'; // Custom widget selects a value and emits a synthetic native change.
change({ isTrusted: false, target: input });
assert.equal(sent.length, 0);
listeners['gpt-live:manual-entry']({ detail: { fieldName: 'unknown', value: 'spoof' } });
assert.equal(sent.length, 0, 'Widget notification cannot address unsupported fields');
listeners['gpt-live:manual-entry']({ detail: { fieldName: 'phone', value: 'spoof' } });
assert(sent.some(event => event.type === 'session.thinking.append' && event.content.includes('2064')));
assert(!sent.some(event => event.content.includes('spoof')), 'Widget notification must read native value, not event data');
assert(!sent.some(event => event.type === 'response.create'));
sent.length = 0;
input.value = 'changed during navigation';
listeners.input({ type: 'input', isTrusted: true, target: input });
session.pageNavigating = true;
flush();
assert.equal(sent.length, 0, 'Pending edits must not leak across navigation');
session.pageNavigating = false;
listeners.input({ type: 'input', isTrusted: true, target: input });
session.closed = true;
flush();
change({ isTrusted: true, target: input });
assert.equal(sent.length, 0);
console.log('PASS silent focused/blurred manual context, debounce, deduplication, no spoken re-entry, readiness and stale guards');
