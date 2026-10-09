/* Transcript retention/scroll policy: no microphone, network or submission. */
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
let copied,click;
const context={navigator:{clipboard:{async writeText(text){copied=text;}}},window:{setTimeout(){}},document:{currentScript:null,readyState:'loading',addEventListener(){}}};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname,'../FormBuilderProcessorGPTLive.js'),'utf8').replace('let activeSession = null;','let activeSession = null;globalThis.api={resetTranscript,appendTranscript,bindTranscriptCopy};'),context);
const output={textContent:'Old conversation',hidden:true,dataset:{lastSpeaker:'Old'},scrollTop:0,scrollHeight:100,clientHeight:100};
const copy={hidden:true,dataset:{},addEventListener(event,handler){click=handler;},setAttribute(){}};
const status={textContent:''};
const controls={querySelector(selector){return selector==='[data-gpt-live-transcript]'?output:selector==='[data-gpt-live-copy]'?copy:selector==='[data-gpt-live-status]'?status:null;}};
context.api.resetTranscript(controls);
assert.equal(output.hidden,false);assert.equal(output.textContent,'');assert.equal(output.tabIndex,0);assert.equal(copy.hidden,false);assert.equal(copy.disabled,true);
context.api.appendTranscript(controls,'Assistant','Hello <script>');
context.api.appendTranscript(controls,'Assistant',' plain text');
context.api.appendTranscript(controls,'You','An answer');
assert.equal(output.textContent,'Assistant: Hello <script> plain text\n\nYou: An answer');
assert.equal(copy.disabled,false);
// New text follows only the transcript's bottom; reviewing old text stays put.
output.scrollHeight=600;output.clientHeight=100;output.scrollTop=500;
context.api.appendTranscript(controls,'Assistant','Latest');assert.equal(output.scrollTop,600);
output.scrollTop=120;
context.api.appendTranscript(controls,'Assistant',' more');assert.equal(output.scrollTop,120);
const before=output.textContent;context.api.appendTranscript(controls,'You','');assert.equal(output.textContent,before);
context.api.bindTranscriptCopy(controls);
click().then(()=>{
 assert.equal(copied,output.textContent); // Full history, including content above the viewport.
 context.api.resetTranscript(controls);
 assert.equal(output.scrollTop,0);assert.equal(output.dataset.lastSpeaker,undefined);assert.equal(copy.disabled,true);
 console.log('PASS reserved transcript layout, speaker/text retention, internal follow/read position, full-history copy and reset');
}).catch(error=>{console.error(error);process.exitCode=1;});
