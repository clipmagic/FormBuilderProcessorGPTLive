/* Native time and paired control contract; no network, microphone or submission. */
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
class Input {
 constructor(name,type,value='') { Object.assign(this,{name,type,_value:value,disabled:false,willValidate:true,validity:{valid:true},events:[],wrapper:{hidden:false,getClientRects(){return this.hidden?[]:[1];}}}); }
 get value(){return this._value;}
 set value(value){this._value=this.type==='date' && value==='2026-02-30' ? '' : value;}
 closest(){return this.wrapper;}
 dispatchEvent(event){this.events.push(event.type);}
 compareDocumentPosition(){return 4;}
}
const context={HTMLInputElement:Input,HTMLSelectElement:class{},HTMLTextAreaElement:class{},HTMLOptGroupElement:class{},Node:{DOCUMENT_POSITION_FOLLOWING:4},Event:class{constructor(type){this.type=type;}},window:{},getComputedStyle(){return {visibility:'visible'};},document:{currentScript:null,readyState:'loading',addEventListener(){},querySelector(){return null;}}};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname,'../FormBuilderProcessorGPTLive.js'),'utf8').replace('let activeSession = null;','let activeSession = null;globalThis.api={prepareForm,setFieldValue,existingFieldValues,rememberPageValues,restorePageValues};'),context);
const api=context.api,plain=value=>JSON.parse(JSON.stringify(value));
function fixture() {
 const controls={pickup:new Input('pickup','date'),pickup__time:new Input('pickup__time','time'),time_only:new Input('time_only','time')};
 const rules={pickup:{nativeType:'date',timeField:'pickup__time'},pickup__time:{nativeType:'time',dateField:'pickup'}};
 const form={dataset:{gptLivePageNum:'2',gptLivePageCount:'3',gptLiveFieldNames:JSON.stringify(Object.keys(controls)),gptLiveFieldRules:JSON.stringify(rules),gptLiveRequiredFieldNames:'[]'},elements:{namedItem(name){return controls[name]||null;}},getAttribute(){return 'fixture';}};
 return {controls,form,session:{form,preparedPages:{},controls:{querySelector(){return null;}}}};
}
const {controls,form,session}=fixture();
assert.equal(api.prepareForm(session,{pickup:'2026-10-15',pickup__time:'14:30',time_only:'09:00:15'}).status,'page_prepared');
assert.deepEqual(plain(api.existingFieldValues(form)),{pickup:'2026-10-15',pickup__time:'14:30',time_only:'09:00:15'});
assert.equal(api.prepareForm(session,{pickup:'2026-10-16'}).status,'fields_updated');assert.equal(controls.pickup__time.value,'14:30');
assert.equal(api.prepareForm(session,{pickup__time:'16:45'}).status,'fields_updated');assert.equal(controls.pickup.value,'2026-10-16');
assert.equal(api.prepareForm(session,{pickup__time:''}).status,'fields_updated');assert.equal(controls.pickup.value,'2026-10-16');
api.prepareForm(session,{pickup__time:'16:45'});
assert.deepEqual(plain(api.prepareForm(session,{pickup:''}).fields),['pickup']);assert.equal(controls.pickup.value,'2026-10-16');
assert.equal(api.prepareForm(session,{pickup:'',pickup__time:''}).status,'fields_updated');
assert.deepEqual(plain(api.prepareForm(session,{pickup__time:'10:00'}).fields),['pickup']);assert.equal(controls.pickup__time.value,'');
for(const invalid of ['2:30 pm','24:00','12:60','12:00:60']) {
 assert.equal(api.setFieldValue(form,'time_only',invalid),false);assert.equal(controls.time_only.value,'09:00:15');
}
assert.equal(api.setFieldValue(form,'pickup','2026-10-15'),true);
assert.equal(api.setFieldValue(form,'pickup','2026-02-30'),false);assert.equal(controls.pickup.value,'2026-10-15');
assert.equal(api.setFieldValue(form,'pickup','15/10/2026'),false);
controls.pickup__time.validity.valid=false; // Native range/step mismatch feedback.
assert.deepEqual(plain(api.prepareForm(session,{pickup__time:'08:30'}).fields),['pickup__time']);
controls.pickup__time.validity.valid=true;
api.prepareForm(session,{pickup:'2026-10-15',pickup__time:'14:30'});
api.rememberPageValues(session,form);
const returned=fixture();returned.controls.pickup.value='2026-10-01';returned.controls.pickup__time.value='09:00';
api.restorePageValues(session,returned.form);
assert.equal(returned.controls.pickup.value,'2026-10-15');assert.equal(returned.controls.pickup__time.value,'14:30');
api.prepareForm(returned.session,{pickup:'',pickup__time:''});api.rememberPageValues(session,returned.form);
api.restorePageValues(session,form);assert.equal(controls.pickup.value,'');assert.equal(controls.pickup__time.value,'');
form.dataset.gptLiveRequiredFieldNames='["pickup"]';
assert.equal(api.prepareForm(session,{pickup:'2026-10-15',pickup__time:''}).status,'fields_updated');
assert.deepEqual(plain(api.prepareForm(session,{pickup:'',pickup__time:''}).fields),['pickup']);
form.dataset.gptLiveRequiredFieldNames='[]';
form.dataset.gptLiveFieldRules=JSON.stringify({pickup:{nativeType:'date',timeField:'pickup__time',showIf:'time_only=10:00',requiredIf:'time_only=10:00'},pickup__time:{nativeType:'time',dateField:'pickup',showIf:'time_only=10:00'}});
controls.pickup.wrapper.hidden=true;controls.pickup__time.wrapper.hidden=true;
assert.equal(api.prepareForm(session,{time_only:'09:00',pickup:'',pickup__time:''}).status,'page_prepared');
controls.pickup.wrapper.hidden=false;controls.pickup__time.wrapper.hidden=false;
assert.deepEqual(plain(api.prepareForm(session,{time_only:'10:00',pickup:'',pickup__time:''}).fields),['pickup']);
assert.equal(api.prepareForm(session,{pickup:'2026-10-15',pickup__time:''}).status,'fields_updated');
console.log('PASS native time/pairs, partial corrections, clears, missing-date guard, sanitized-value rollback, validity, required/conditional and Back/Next retention');
