/* Deterministic recovery tests: no real waits, network, microphone or submissions. */
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../FormBuilderProcessorGPTLive.js'),'utf8');
const timers=new Map(); let next=0;
const context={AbortController,URL,location:{origin:"https://example.test"},document:{currentScript:null,readyState:'loading',addEventListener(){}},window:{setTimeout(fn,ms){const id=++next;timers.set(id,{fn,ms});return id;},clearTimeout(id){timers.delete(id);}},navigator:{}};
vm.createContext(context);
vm.runInContext(source.replace('let activeSession = null;','let activeSession = null; globalThis.api={fetchText,requestFormSubmission,handleLiveEvent,cleanup,loadPageAssets,introduceChangedPage,setOwner(value){activeSession=value;}};'),context);
function session(){return {closed:false,controls:{querySelector(){return {textContent:''};}},form:{dataset:{}}};}
async function test(){
 const first=session();first.form.dataset.gptLiveRequestTimeoutSeconds="7";context.api.setOwner(first);
 context.fetch=(_,options)=>new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>reject(new Error('aborted'))));
 const stalled=context.api.fetchText(first,'/test',{});
 const deadline=[...timers.values()].find(t=>t.ms===7000);assert.ok(deadline);deadline.fn();
 await assert.rejects(stalled,/aborted/);assert.equal(first.requests.size,0);assert.equal(timers.size,0);
 // Slow body reads remain inside the same deadline.
 context.fetch=async(_,options)=>({text:()=>new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>reject(new Error('body aborted'))))});
 const body=context.api.fetchText(first,'/test',{});await new Promise(setImmediate);
 [...timers.values()][0].fn();await assert.rejects(body,/body aborted/);assert.equal(timers.size,0);
 // Even if transport completes after ownership changed, its response is rejected.
 let finish;
 context.fetch=()=>new Promise(resolve=>{finish=resolve;});
 const late=context.api.fetchText(first,'/test',{});
 context.api.setOwner(session());finish({text:async()=> 'old response'});
 await assert.rejects(late,/no longer active/);assert.equal(first.requests.size,0);
 // Late model events cannot prepare/submit or update controls on a replaced session.
 context.api.handleLiveEvent(first,JSON.stringify({type:'session.started'}));assert.equal(first.ready,undefined);
 // Closing an owner aborts pending work without waiting for the timeout.
 const closing=session();context.api.setOwner(closing);
 context.fetch=(_,options)=>new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>reject(new Error('closed abort'))));
 const pending=context.api.fetchText(closing,'/test',{});
 // Minimal control stub avoids unrelated rendering paths in cleanup.
 closing.controls={querySelector(){return null;}};
 context.api.cleanup(closing,'');await assert.rejects(pending,/closed abort/);assert.equal(closing.closed,true);
 // Native submit event cancellation must release the pending guard, without a retry.
 for(const mode of ['cancelled','validation','throw','native']){
  const current=session(); let observed,calls=0;
  const submitter={type:'submit',disabled:false};
  current.form={dataset:{gptLiveSubmissionEnabled:'1'},getAttribute(){return 'test';},elements:{namedItem(){return submitter;}},checkValidity(){return true;},addEventListener(_,fn){observed=fn;},removeEventListener(){observed=null;},requestSubmit(){calls++;if(mode==='throw')throw new Error('cancel');if(mode!=='validation')observed({defaultPrevented:mode==='cancelled'});}};
  context.api.setOwner(current);
  assert.equal(context.api.requestFormSubmission(current).status,'submission_requested');
  [...timers.values()].find(t=>t.ms===0).fn();timers.clear();
  assert.equal(calls,1);assert.equal(observed,null);assert.equal(current.submissionPending,mode==='native');
  if(mode!=='native')assert.equal(context.api.requestFormSubmission(current).status,'submission_requested');
  else assert.equal(context.api.requestFormSubmission(current).status,'submission_pending');
  timers.clear();
 }
 // A stalled page script is removed on timeout; closure also cancels it.
 context.document.querySelectorAll=()=>[];
 let script;
 context.document.createElement=()=>({remove(){this.removed=true;}});
 context.document.head={appendChild(value){script=value;}};
 const page={querySelectorAll(selector){return selector==='script[src]'?[{getAttribute(){return '/form.js';}}]:[];}};
 for(const close of [false,true]){
  const owner=session();owner.form.dataset.gptLiveRequestTimeoutSeconds="5";owner.controls={querySelector(){return null;}};context.api.setOwner(owner);
  const assets=context.api.loadPageAssets(owner,page,'https://example.test/contact/');
  assert.equal(owner.requests.size,1);
  if(close)context.api.cleanup(owner,'');else [...timers.values()].find(t=>t.ms===5000).fn();
  await assert.rejects(assets,/stopped or timed out/);
  assert.equal(script.removed,true);assert.equal(owner.requests.size,0);assert.equal(timers.size,0);
 }
 // Navigation introduces its new page once, while paused/blocked/stale paths stay silent.
 const spoken=session(), sent=[];spoken.channel={readyState:'open',send(data){sent.push(JSON.parse(data));}};
 context.crypto=require('node:crypto').webcrypto;context.api.setOwner(spoken);
 context.api.introduceChangedPage(spoken,true,false);
 assert.equal(sent.length,1);assert.equal(sent[0].type,'response.create');
 context.api.introduceChangedPage(spoken,false,false);
 context.api.introduceChangedPage(spoken,true,true);
 context.api.setOwner(session());context.api.introduceChangedPage(spoken,true,false);
 context.api.setOwner(spoken);spoken.closed=true;context.api.introduceChangedPage(spoken,true,false);
 assert.equal(sent.length,1);
 console.log('PASS header/body timeouts, close aborts, stale responses/events, cancelled/validation/throw recovery native duplicate guard and page-script cancellation');
}
test().catch(error=>{console.error(error);process.exitCode=1;});
