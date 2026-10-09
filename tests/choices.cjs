/* Native choice setters and retention; no network, microphone, database or submit. */
const assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm'), path = require('node:path');
class Input {
 constructor(name, type, value) { Object.assign(this, {name, type, value, checked:false, disabled:false, events:[], wrapper:{hidden:false, getClientRects(){return this.hidden ? [] : [1];}}}); }
 closest(){return this.wrapper;}
 dispatchEvent(event){this.events.push(event.type); if(this.change) this.change();}
 compareDocumentPosition(){return 4;}
}
class Select {
 constructor(name, multiple, values){Object.assign(this,new Input(name,'select', '')); this.multiple=multiple; this.options=values.map(value=>({value, selected:false, disabled:false, parentElement:null}));}
 closest(){return this.wrapper;}
 dispatchEvent(event){this.events.push(event.type);}
 compareDocumentPosition(){return 4;}
 get selectedOptions(){return this.options.filter(option=>option.selected);}
}
class Group extends Array {}
const context={HTMLInputElement:Input, HTMLSelectElement:Select, HTMLTextAreaElement:class{}, HTMLOptGroupElement:class{}, Node:{DOCUMENT_POSITION_FOLLOWING:4}, Event:class{constructor(type){this.type=type;}}, window:{RadioNodeList:Group}, getComputedStyle(){return {visibility:'visible'};}, document:{currentScript:null, readyState:'loading', addEventListener(){}, querySelector(){return null;}}};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname,'../FormBuilderProcessorGPTLive.js'),'utf8').replace('let activeSession = null;', 'let activeSession = null;globalThis.api={setFieldValue,existingFieldValues,conditionMatches,prepareForm,rememberPageValues,restorePageValues};'),context);
const api=context.api, plain=value=>JSON.parse(JSON.stringify(value));
function form(controls, required=[], rules={}){return {dataset:{gptLivePageNum:'1',gptLivePageCount:'1',gptLiveFieldNames:JSON.stringify(Object.keys(controls).map(name=>name.replace(/\[\]$/, ''))),gptLiveRequiredFieldNames:JSON.stringify(required),gptLiveFieldRules:JSON.stringify(rules)}, elements:{namedItem(name){return controls[name] || null;}},getAttribute(){return 'fixture';}};}
const consent=new Input('consent','checkbox','agree');
const services=new Group(new Input('services[]','checkbox','airport'),new Input('services[]','checkbox','cruise'),new Input('services[]','checkbox','disabled'));
services[2].disabled=true;
const only=new Input('only[]','checkbox','one');
const multi=new Select('multi[]',true,['100','200','300']);multi.options[2].disabled=true;
const asm=new Select('asm[]',true,['10','20']);
const page=new Select('page',false,['','100','200']);
const f=form({consent,'services[]':services,'only[]':only,'multi[]':multi,'asm[]':asm,page});
assert.equal(api.setFieldValue(f,'consent','agree'),true);assert.equal(consent.checked,true);
assert.equal(api.setFieldValue(f,'consent','0'),true);assert.equal(consent.checked,false);
assert.equal(api.setFieldValue(f,'consent','yes'),false);assert.equal(consent.checked,false);
assert.equal(api.setFieldValue(f,'services',['airport','cruise']),true);
assert.equal(api.conditionMatches(f,'services=cruise'),true);
assert.deepEqual(plain(api.existingFieldValues(f).services),['airport','cruise']);
for(const invalid of [['airport','invented'],['disabled'],['airport','airport'],'airport']) {
 assert.equal(api.setFieldValue(f,'services',invalid),false);assert.deepEqual(plain(api.existingFieldValues(f).services),['airport','cruise']);
}
assert.equal(api.setFieldValue(f,'services',['cruise']),true);assert.equal(services[0].checked,false);
assert.equal(api.setFieldValue(f,'only',['one']),true);assert.deepEqual(plain(api.existingFieldValues(f).only),['one']);
assert.equal(api.setFieldValue(f,'only',[]),true);assert.deepEqual(plain(api.existingFieldValues(f).only),[]);
assert.equal(api.setFieldValue(f,'multi',['100','200']),true);assert.equal(api.setFieldValue(f,'multi',['300']),false);
assert.deepEqual(plain(api.existingFieldValues(f).multi),['100','200']);
assert.equal(api.setFieldValue(f,'asm',['20']),true);assert.ok(asm.events.includes('change'));
assert.equal(api.setFieldValue(f,'page','200'),true);assert.equal(page.value,'200');
assert.equal(api.setFieldValue(f,'page','999'),false);assert.equal(page.value,'200');
const session={form:f,preparedPages:{},controls:{querySelector(){return null;}}};
f.dataset.gptLiveRequiredFieldNames='["consent","services"]';
assert.deepEqual(plain(api.prepareForm(session,{consent:'0',services:['cruise']}).fields),['consent']);
assert.deepEqual(plain(api.prepareForm(session,{consent:'agree',services:[]}).fields),['services']);
const airport=new Select('airport',false,['','sydney']);airport.wrapper.hidden=true;
services[0].change=()=>{airport.wrapper.hidden=!services[0].checked;};
const conditional=form({'services[]':services,airport},[],{airport:{showIf:'services=airport',requiredIf:'services=airport'}});
const conditionalSession={form:conditional,preparedPages:{},controls:{querySelector(){return null;}}};
assert.equal(api.prepareForm(conditionalSession,{services:['airport'],airport:'sydney'}).status,'prepared_for_review');
assert.equal(airport.value,'sydney');
assert.equal(api.prepareForm(conditionalSession,{services:[],airport:''}).status,'prepared_for_review');
assert.equal(airport.wrapper.hidden,true);
// Back/Next must restore structured selections and intentional empty arrays.
api.setFieldValue(f,'services',['airport','cruise']);api.rememberPageValues(session,f);
api.setFieldValue(f,'services',[]);api.setFieldValue(f,'multi',[]);api.setFieldValue(f,'asm',[]);
api.restorePageValues(session,f);
assert.deepEqual(plain(api.existingFieldValues(f).services),['airport','cruise']);
assert.deepEqual(plain(api.existingFieldValues(f).multi),['100','200']);
assert.deepEqual(plain(api.existingFieldValues(f).asm),['20']);
assert.deepEqual(plain(api.existingFieldValues(f).only),[]);
api.setFieldValue(f,'services',[]);api.rememberPageValues(session,f);api.setFieldValue(f,'services',['airport']);api.restorePageValues(session,f);
assert.deepEqual(plain(api.existingFieldValues(f).services),[]);
// An empty native checkbox group renders no controls; omit it from the contract.
const name=new Input('name','text','Pru'),email=new Input('email','email','pru@example.com'),request=new Input('message','text','');
const noChoices=form({name,email,message:request});
noChoices.dataset.gptLivePageNum='2';noChoices.dataset.gptLivePageCount='3';
const noChoicesSession={form:noChoices,preparedPages:{},controls:{querySelector(){return null;}}};
noChoices.dataset.gptLiveFieldNames='["name","email","message","services_requested"]';
assert.deepEqual(plain(api.prepareForm(noChoicesSession,{name:'Prue',email:'prue@example.com',message:'Dentist transport next week',services_requested:[]}).fields),['services_requested']);
assert.equal(name.value,'Prue');assert.equal(email.value,'prue@example.com');
noChoices.dataset.gptLiveFieldNames='["name","email","message"]';
assert.equal(api.prepareForm(noChoicesSession,{name:'Pru',email:'pru@example.com',message:'Dentist transport next week'}).status,'page_prepared');
assert.equal(api.prepareForm(noChoicesSession,{name:'Prue',email:'prue@example.com',message:'Dentist transport next week'}).status,'page_prepared');
assert.equal(name.value,'Prue');assert.equal(email.value,'prue@example.com');
const progressiveName=new Input('name','text',''),progressivePhone=new Input('phone','text',''),progressiveNote=new Input('note','text','Keep this');
const progressive=form({name:progressiveName,phone:progressivePhone,note:progressiveNote},['name']);
const progressiveSession={form:progressive,preparedPages:{},controls:{querySelector(){return null;}}};
assert.equal(api.prepareForm(progressiveSession,{name:null,phone:'0444555666',note:null}).status,'fields_updated');
assert.equal(progressiveName.value,'');assert.equal(progressivePhone.value,'0444555666');assert.equal(progressiveNote.value,'Keep this');
assert.equal(api.prepareForm(progressiveSession,{name:'Sam',phone:null,note:null}).status,'fields_updated');
assert.equal(progressiveName.value,'Sam');assert.equal(progressivePhone.value,'0444555666');assert.equal(progressiveSession.preparedPages[1],undefined);
assert.equal(api.prepareForm(progressiveSession,{name:'Sam',phone:'0444555666',note:'Keep this'}).status,'prepared_for_review');
assert.equal(api.prepareForm(progressiveSession,{name:null,phone:null,note:''}).status,'fields_updated');assert.equal(progressiveNote.value,'');assert.equal(progressiveSession.preparedPages[1],undefined);
const children=new Input('children','number','2'),seats=new Input('seats','number','1'),date=new Input('date','date','');
const skippedSeatsForm=form({children,seats,date},[],{seats:{showIf:'children>0'}});
const skippedSeats={form:skippedSeatsForm,preparedPages:{},controls:{querySelector(){return null;}}};
const skip=api.prepareForm(skippedSeats,{children:null,seats:'',date:null});
assert.equal(skip.status,'fields_updated');assert.equal(seats.value,'');assert.equal(children.value,'2');
assert.deepEqual(plain(skip.remainingFields),[{name:'date',label:'date'}]);assert.equal(skippedSeats.preparedPages[1],undefined);
console.log('PASS native choices and progressive answers, null preservation, explicit clears, required-field progress and separate final preparation');
