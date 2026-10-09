/* Async widget feedback, failure and stale-session guards; no provider or submit. */
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const context={document:{currentScript:null,readyState:'loading',addEventListener(){}},window:{},CustomEvent:class{constructor(type,options){this.type=type;this.detail=options.detail;}}};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname,'../FormBuilderProcessorGPTLive.js'),'utf8').replace('let activeSession = null;','let activeSession = null;globalThis.api={prepareWithWidgets,setOwner(value){activeSession=value;}};'),context);
(async()=>{
 const form={dataset:{gptLiveFieldNames:'["location"]'},dispatchEvent(event){assert.equal(event.type,'gpt-live:prepare');event.detail.waitUntil(Promise.resolve({status:'clarification_required',options:[{label:'Test suburb',value:'1234'}]}));}};
 const session={form};context.api.setOwner(session);
 const result=await context.api.prepareWithWidgets(session,{location:'Test'});
 assert.equal(result.status,'clarification_required');assert.equal(result.options[0].value,'1234');
 form.dispatchEvent=event=>event.detail.waitUntil(Promise.reject(new Error('Lookup failed')));
 assert.equal((await context.api.prepareWithWidgets(session,{location:'Test'})).status,'validation_error');
 let release;form.dispatchEvent=event=>event.detail.waitUntil(new Promise(resolve=>{release=resolve;}));
 const old=context.api.prepareWithWidgets(session,{location:'Test'});session.closed=true;release({status:'resolved'});assert.equal(await old,null);
 session.closed=false;context.api.setOwner(session);
 const navigation=context.api.prepareWithWidgets(session,{location:'Test'});session.form={};release({status:'resolved'});assert.equal((await navigation).status,'page_changing');
 console.log('PASS async native widget clarification, lookup failure, closed-session and navigation guards');
})().catch(error=>{console.error(error);process.exitCode=1;});
