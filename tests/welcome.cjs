/* Startup greeting lifecycle: no microphone, network or submissions. */
const assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm'), path = require('node:path');
const context = {crypto:require('node:crypto').webcrypto, window:{clearTimeout(){}}, document:{currentScript:null, readyState:'loading', addEventListener(){}}};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname,'../FormBuilderProcessorGPTLive.js'),'utf8').replace('let activeSession = null;', 'let activeSession = null;globalThis.api={requestWelcome,handleLiveEvent,setOwner(value){activeSession=value;}};'), context);
function fixture(flag='1') {
    const sent=[], status={textContent:''};
    const session={form:{addEventListener(){},dataset:{gptLiveAssistantSpeaksFirst:flag,gptLiveMessages:JSON.stringify({welcoming:'Welcoming',listening:'Listening'})},classList:{contains(){return false;}}},controls:{querySelector(selector){return selector==='[data-gpt-live-status]'?status:null;}},channel:{readyState:'open',send(data){sent.push(JSON.parse(data));}}};
    context.api.setOwner(session);
    return {session,sent,status};
}
let {session,sent,status}=fixture();
context.api.handleLiveEvent(session,JSON.stringify({type:'session.started'}));
assert.equal(status.textContent,'Welcoming'); assert.equal(sent.length,1); assert.equal(sent[0].type,'response.create');
// Client messages use event_id; client_event_id is only a server acknowledgement.
assert.deepEqual(Object.keys(sent[0]).sort(),['event_id','type']);
assert.match(sent[0].event_id,/^[0-9a-f-]{36}$/);
context.api.handleLiveEvent(session,JSON.stringify({type:'session.started'}));
assert.equal(sent.length,1);
session.paused=true;assert.equal(context.api.requestWelcome(session),false);
session.paused=false;assert.equal(context.api.requestWelcome(session),false); // Resume does not restart greeting.
for(const flag of ['0',undefined,'']) {
    const test=fixture(flag); if(flag===undefined) delete test.session.form.dataset.gptLiveAssistantSpeaksFirst;
    context.api.handleLiveEvent(test.session,JSON.stringify({type:'session.started'}));
    assert.equal(test.sent.length,0);assert.equal(test.status.textContent,'Listening');
}
for(const key of ['closed','cancelled','paused','pageNavigating','visitorHasSpoken']) {
    const test=fixture();test.session.ready=true;test.session[key]=true;
    assert.equal(context.api.requestWelcome(test.session),false);assert.equal(test.sent.length,0);
}
for(const channel of [null,{readyState:'closed'}]) {
    const test=fixture();test.session.ready=true;test.session.channel=channel;
    assert.equal(context.api.requestWelcome(test.session),false);
}
const fresh=fixture();fresh.session.ready=true;
context.api.setOwner({});assert.equal(context.api.requestWelcome(fresh.session),false);
context.api.setOwner(fresh.session);assert.equal(context.api.requestWelcome(fresh.session),true);assert.equal(fresh.sent.length,1);
const populated=fixture();populated.session.form.dataset.gptLivePageNum='1';populated.session.form.dataset.gptLiveFieldNames='["postcode"]';
populated.session.form.dataset.gptLiveKnownValues='[{"name":"postcode","label":"Postcode","value":"2570"}]';
context.api.handleLiveEvent(populated.session,JSON.stringify({type:'session.started'}));
assert.equal(populated.sent.length,1);assert.deepEqual(Object.keys(populated.sent[0]).sort(),['event_id','type']);
assert.equal(populated.status.textContent,'Welcoming');
console.log('PASS opening protocol, new/populated sessions, duplicate starts, Resume, disabled/legacy controls and ownership guards');
