/* Native server errors inform voice navigation; no network, microphone or submission. */
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
class Input {
    constructor(value) { this.type='text';this.value=value;this.willValidate=false; }
    closest() { return this; }
    getClientRects() { return [1]; }
}
const context={crypto:require('node:crypto').webcrypto,HTMLSelectElement:class{},window:{},getComputedStyle(){return {visibility:'visible'};},document:{currentScript:null,readyState:'loading',addEventListener(){}}};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname,'../FormBuilderProcessorGPTLive.js'),'utf8').replace('let activeSession = null;', 'let activeSession = null;globalThis.api={nativeValidationErrors,pageGuidance,introduceChangedPage,handleLiveEvent,setOwner(value){activeSession=value;}};'),context);
const error='Phone number - Please enter an email address or phone number.';
const controls={name:new Input('Mary'),phone:new Input(''),email:new Input(''),enquiry_submit_next:{textContent:'Add request details'}};
const form={dataset:{gptLiveFieldNames:'["name","phone","email"]',gptLiveFieldLabels:'{"name":"Your name","phone":"Phone number","email":"Email address"}',gptLivePageNum:'2',gptLivePageCount:'3',gptLiveNextPageLabel:'Optional request details',gptLiveValidationErrors:JSON.stringify([error])},getAttribute(){return 'enquiry';},elements:{namedItem(name){return controls[name]||null;}}};
const sent=[],session={form,preparedPages:{},channel:{readyState:'open',send(data){sent.push(JSON.parse(data));}}};
context.api.setOwner(session);
const guidance=context.api.pageGuidance(session);
assert.ok(guidance.facts.includes(error));assert.ok(guidance.instructions.includes('Native validation errors replace earlier error feedback'));
assert.ok(guidance.instructions.includes('listen silently'));
// Server-only either/or rules are feedback, not invented individual required fields.
assert.ok(guidance.facts.includes('Browser-visible supported fields needing attention: []'));
context.api.introduceChangedPage(session,false,false,true);
assert.equal(sent.length,1);assert.equal(sent[0].type,'response.create');assert.deepEqual(Object.keys(sent[0]).sort(),['event_id','type']);
context.api.introduceChangedPage(session,false,true,true);assert.equal(sent.length,1);
context.api.introduceChangedPage(session,false,false,false);assert.equal(sent.length,1);
context.api.setOwner({});context.api.introduceChangedPage(session,false,false,true);assert.equal(sent.length,1);
context.api.setOwner(session);session.closed=true;context.api.introduceChangedPage(session,false,false,true);assert.equal(sent.length,1);
for(const invalid of ['not JSON','{}','null','[42,{},null,""]']) {
    form.dataset.gptLiveValidationErrors=invalid;assert.equal(context.api.nativeValidationErrors(form).length,0);
}
form.dataset.gptLiveValidationErrors=JSON.stringify(Array(20).fill('x'.repeat(1000)));
assert.equal(context.api.nativeValidationErrors(form).length,12);assert.equal(context.api.nativeValidationErrors(form)[0].length,500);
form.dataset.gptLiveValidationErrors='[]';assert.ok(!context.api.pageGuidance(session).facts.includes(error));
session.closed=false;session.pageNavigating=true;
const before=sent.length;
context.api.handleLiveEvent(session,JSON.stringify({type:'response.event',event:{type:'response.output_item.done',item:{type:'function_call',call_id:'during-navigation',name:'prepare_enquiry',arguments:'{}'}}}));
assert.equal(sent.length,before+1);assert.equal(sent.at(-1).type,'response.item.create');
assert.equal(JSON.parse(sent.at(-1).item.output).status,'page_changing');
session.pageNavigating=false;context.api.introduceChangedPage(session,true,false);
assert.equal(sent.length,before+2);assert.equal(sent.at(-1).type,'response.create');
console.log('PASS native validation feedback, bounds/reset, blocked-Next response and paused/stale silence');
