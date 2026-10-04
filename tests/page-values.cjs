/* Page roundtrip answer retention; no network, microphone, database or submit. */
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
class Input {
 constructor(value){this.type='text';this.value=value;this.wrapper={hidden:false,getClientRects(){return this.hidden?[]:[1];}};}
 closest(){return this.wrapper;}
 dispatchEvent(){}
}
class Select{};class Textarea{};class Group{};
const context={HTMLInputElement:Input,HTMLSelectElement:Select,HTMLTextAreaElement:Textarea,HTMLOptGroupElement:Group,Event:class{},window:{},getComputedStyle(){return {visibility:'visible'};},document:{currentScript:null,readyState:'loading',addEventListener(){}}};
vm.createContext(context);
const source=fs.readFileSync(path.join(__dirname,'../FormBuilderProcessorGPTLive.js'),'utf8');
vm.runInContext(source.replace('let activeSession = null;','let activeSession = null;globalThis.api={rememberPageValues,restorePageValues};'),context);
function form(page,values){const controls=Object.fromEntries(Object.entries(values).map(([name,value])=>[name,new Input(value)]));return {controls,dataset:{gptLivePageNum:String(page),gptLiveFieldNames:JSON.stringify(Object.keys(values))},elements:{namedItem(name){return controls[name]||null;}}};}
const session={};const original=form(2,{date:'original date',time:'original time',notes:'original'});
original.controls.date.value='corrected date';original.controls.time.value='corrected time';original.controls.notes.value='';
context.api.rememberPageValues(session,original);
context.api.rememberPageValues(session,form(1,{name:'Visitor'}));
const returned=form(2,{date:'original date',time:'original time',notes:'original'});
context.api.restorePageValues(session,returned);
assert.equal(returned.controls.date.value,'corrected date');assert.equal(returned.controls.time.value,'corrected time');assert.equal(returned.controls.notes.value,'');
// More recent manual changes replace the snapshot on the next navigation.
returned.controls.date.value='manual correction';context.api.rememberPageValues(session,returned);
const again=form(2,{date:'old date',time:'old time',notes:'old'});again.controls.time.wrapper.hidden=true;
context.api.restorePageValues(session,again);
assert.equal(again.controls.date.value,'manual correction');assert.equal(again.controls.time.value,'old time');
// A new conversation must never import the previous session's unsaved answers.
const fresh=form(2,{date:'FormBuilder saved date'});context.api.restorePageValues({},fresh);assert.equal(fresh.controls.date.value,'FormBuilder saved date');
console.log('PASS Back/Next date+time retention, deliberate clears, manual edits, hidden fields and conversation isolation');
