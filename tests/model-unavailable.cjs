/* Live provider rejection regression: no provider requests or microphone. */
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../FormBuilderProcessorGPTLive.js'),'utf8');
const diagnostics=[];
const context={AbortController,console,document:{currentScript:null,readyState:'loading',addEventListener(){}},window:{setTimeout,clearTimeout},navigator:{},fetch:async(url,options)=>{diagnostics.push(JSON.parse(options.body));return {text:async()=>''};}};
vm.createContext(context);
vm.runInContext(source.replace('let activeSession = null;','let activeSession = null; globalThis.api={handleLiveEvent,setOwner(value){activeSession=value;}};'),context);
async function test(){
 for(const error of [{code:'model_not_found',message:'Unavailable'}, {message:'The model `chatgpt-6-luna` does not exist or you do not have access to it.'}, {param:'session.delegation.model',message:'Rejected'}]){
  const calls=[];const field={value:'Fred'};
  const session={controls:{querySelector(){return null;}},form:{dataset:{gptLiveSessionUrl:'/session',gptLiveToken:'test'},elements:[field]},requests:new Set([{abort(){calls.push('abort');}}]),channel:{readyState:'open',close(){calls.push('channel');}},peer:{close(){calls.push('peer');}},microphone:{getTracks(){return [{stop(){calls.push('microphone');}}];}},remoteAudio:{remove(){calls.push('audio');}},updateWait:{timer:setTimeout(()=>{},10000),reject(){calls.push('update rejected');}}};
  context.api.setOwner(session);
  const before=diagnostics.length;
  context.api.handleLiveEvent(session,JSON.stringify({type:'error',error}));
  assert.equal(session.closed,true);assert.equal(session.controls.hidden,true);
  assert.deepEqual(calls,['abort','update rejected','audio','channel','peer','microphone']);
  assert.equal(field.value,'Fred');assert.equal(diagnostics.length,before+1);
  assert.equal(diagnostics.at(-1).clientDiagnostic.stage,'data-channel');
  assert.equal(diagnostics.at(-1).clientDiagnostic.message,error.message);
  context.api.handleLiveEvent(session,JSON.stringify({type:'session.started'}));
  context.api.handleLiveEvent(session,JSON.stringify({type:'error',error}));
  assert.equal(session.ready,undefined);assert.equal(diagnostics.length,before+1);
 }
 // An unrelated service failure keeps the existing fallback behavior.
 const status={textContent:''};const other={controls:{querySelector(){return status;}},form:{dataset:{gptLiveMessages:JSON.stringify({fallback:'Complete manually'})}}};
 context.api.setOwner(other);
 context.api.handleLiveEvent(other,JSON.stringify({type:'error',error:{code:'rate_limit_exceeded',message:'Try again later'}}));
 assert.equal(other.closed,undefined);assert.equal(other.controls.hidden,undefined);assert.equal(status.textContent,'Complete manually');
 await new Promise(setImmediate);
 console.log('PASS live missing-model teardown, manual form preservation, pending page update, private diagnostic and unrelated error behavior');
}
test().catch(error=>{console.error(error);process.exitCode=1;});
