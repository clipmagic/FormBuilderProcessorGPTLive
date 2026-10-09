/* Page guidance delivery: no microphone, network, database or form submissions. */
const assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm'), path = require('node:path');
const context = {crypto:require('node:crypto').webcrypto, window:{}, document:{currentScript:null, readyState:'loading', addEventListener(){}}};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname,'../FormBuilderProcessorGPTLive.js'),'utf8').replace('let activeSession = null;', 'let activeSession = null;globalThis.api={applyAssistantGuidance,setOwner(value){activeSession=value;}};'), context);
const sent = [], session = {paused:true, channel:{readyState:'open', send(data){sent.push(JSON.parse(data));}}};
context.api.setOwner(session);
context.api.applyAssistantGuidance(session, 'Developer guidance for page 2 replaces earlier guidance.');
assert.equal(sent.length, 1);assert.equal(sent[0].type, 'session.instructions.append');
assert.equal(sent[0].content, 'Developer guidance for page 2 replaces earlier guidance.');
assert.equal(sent[0].delegation_id, null);
// Empty-page guidance revokes earlier custom rules; Back uses the newly returned rules.
context.api.applyAssistantGuidance(session, 'No additional developer guidance applies on page 3.');
context.api.applyAssistantGuidance(session, 'Developer guidance for page 2 replaces earlier guidance.');
assert.equal(sent.length, 3);assert.ok(sent.every(event=>event.type !== 'response.create'));
for(const invalid of [undefined, null, {}, [], 42, '', '   ']) context.api.applyAssistantGuidance(session, invalid);
assert.equal(sent.length, 3);
context.api.setOwner({});context.api.applyAssistantGuidance(session, 'Stale');assert.equal(sent.length, 3);
context.api.setOwner(session);session.closed=true;context.api.applyAssistantGuidance(session, 'Closed');assert.equal(sent.length, 3);
session.closed=false;session.channel.readyState='closed';context.api.applyAssistantGuidance(session, 'Disconnected');assert.equal(sent.length, 3);
console.log('PASS trusted page guidance, clearing/Back updates, paused silence, invalid/stale/closed guards');
