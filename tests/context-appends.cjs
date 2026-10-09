/* Service append limits: no network, microphone or enquiry submission. */
const assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm'), path = require('node:path');
const context = {crypto:require('node:crypto').webcrypto, window:{}, document:{currentScript:null, readyState:'loading', addEventListener(){}}};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../FormBuilderProcessorGPTLive.js'), 'utf8').replace('let activeSession = null;', 'let activeSession = null;globalThis.api={appendLiveContext,applyAssistantGuidance,setOwner(value){activeSession=value;}};'), context);
const sent = [], session = {paused:true, channel:{readyState:'open', send(data){sent.push(JSON.parse(data));}}};
context.api.setOwner(session);
for(const text of ['Short guidance.', 'airport pickup or destination '.repeat(300), '東京 🚕 café '.repeat(300), '🚕'.repeat(300), 'x'.repeat(1200)]) {
    sent.length = 0;
    context.api.applyAssistantGuidance(session, text);
    assert.equal(sent.map(event => event.content).join(''), text);
    assert.ok(sent.every(event => Buffer.byteLength(event.content, 'utf8') <= 480));
    assert.ok(sent.every(event => event.type === 'session.instructions.append' && event.delegation_id === null));
    assert.equal(new Set(sent.map(event => event.event_id)).size, sent.length);
    assert.ok(sent.every(event => !event.content.includes('\uFFFD')));
}
sent.length = 0;
const facts = JSON.stringify({errors:Array(12).fill('Please clarify the address. '.repeat(20)), values:{address:'東京'}});
context.api.appendLiveContext(session, 'session.thinking.append', facts);
assert.equal(sent.map(event => event.content).join(''), facts);
assert.ok(sent.every(event => event.type === 'session.thinking.append' && Buffer.byteLength(event.content, 'utf8') <= 480));
const before = sent.length;
for(const text of ['', null, undefined]) context.api.appendLiveContext(session, 'session.thinking.append', text);
session.closed = true; context.api.applyAssistantGuidance(session, 'closed'); session.closed = false;
session.channel.readyState = 'closed'; context.api.applyAssistantGuidance(session, 'closed channel'); session.channel.readyState = 'open';
context.api.setOwner({}); context.api.applyAssistantGuidance(session, 'stale session');
assert.equal(sent.length, before);
console.log('PASS bounded ASCII/Unicode context appends, exact text/order, paused silence and stale/closed guards');
