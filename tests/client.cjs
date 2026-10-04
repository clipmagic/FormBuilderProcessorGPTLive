/* Focused browser-client regressions, without microphone, network or form submission. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../FormBuilderProcessorGPTLive.js'), 'utf8');
const instrumented = source.replace('let activeSession = null;', 'let activeSession = null; globalThis.testAPI = {message, formMessages, startSession, sendToolResult, allowedFields, requiredFields, fieldLabels, fieldRules, bindControls};');
const context = {crypto: require('node:crypto').webcrypto, document: {currentScript: null, readyState: 'loading', addEventListener(){}}, window: {setTimeout,clearTimeout}, navigator: {}, TextDecoder, Uint8Array, atob, URL, AbortController, console};
vm.createContext(context); vm.runInContext(instrumented, context);
const form = {dataset: {gptLiveMessages: JSON.stringify({fallback:'Existing fallback', unsupportedBrowser:'Translated unsupported browser', pageChanged:'Page {page}/{pages}: {destination}'})}};
assert.equal(context.testAPI.message(form, 'pageChanged', {page:2,pages:3,destination:'Pet details'}), 'Page 2/3: Pet details');
const second = {dataset: {gptLiveMessages: JSON.stringify({fallback:'Second form'})}};
assert.equal(context.testAPI.message(second, 'fallback'), 'Second form');
assert.equal(context.testAPI.message(form, 'fallback'), 'Existing fallback');
const status = {textContent:''};
const controls = {querySelector(){return status;}};
context.testAPI.startSession(controls, form).then(()=>{
 assert.equal(status.textContent, 'Translated unsupported browser');
 console.log('PASS unsupported-browser message before session creation');
});
const translated = {fallback:'日本語 <script> plain text'};
context.window.ProcessWire = {config: {FormBuilderProcessorGPTLive: {example:{messageData: Buffer.from(JSON.stringify(translated)).toString('base64')}}}};
assert.equal(context.testAPI.message({dataset:{gptLiveFormName:'example'}}, 'fallback'), translated.fallback);
const sent=[];
context.testAPI.sendToolResult({channel:{readyState:'open',send(data){sent.push(JSON.parse(data));}}}, 'call-1', {status:'page_prepared',message:'Translated feedback'});
assert.equal(sent[0].item.call_id, 'call-1'); assert.equal(sent[1].type, 'response.create');
console.log('PASS placeholders, per-form isolation, UTF-8 config transport and tool result dispatch');

// Exercise startup far enough to inspect the JSON request, using fake local transport.
const startupMessages = {starting:'Starting', idle:'Start', requestingMicrophone:'Microphone', startingSession:'Connecting', fallback:'Existing fallback'};
const startupForm = {id:'example', dataset:{gptLiveMessages:JSON.stringify(startupMessages),gptLiveSessionUrl:'/local-test/',gptLiveToken:'test-render-token',gptLiveFieldNames:'[]',gptLivePageNum:'1'},compareDocumentPosition(){return 4;}};
const startupStatus={textContent:''};
const label={textContent:''};
const button={getAttribute(){return 'example';},setAttribute(){},querySelector(selector){return selector==='[data-gpt-live-label]'?label:null;}};
const startupControls={querySelector(selector){if(selector==='[data-gpt-live-toggle]')return button;if(selector==='[data-gpt-live-status]')return startupStatus;return null;},appendChild(){}};
context.document.querySelectorAll=()=>[startupForm];
context.document.createElement=()=>({setAttribute(){},remove(){}});
context.Node={DOCUMENT_POSITION_FOLLOWING:4};
let stopped=false;
const track={stop(){stopped=true;}};
context.navigator.mediaDevices={async getUserMedia(){return {getTracks(){return [track];},getAudioTracks(){return [track];}};}};
context.RTCPeerConnection=class {
 constructor(){this.iceGatheringState='complete';}
 addTrack(){return {};}
 addEventListener(){}
 createDataChannel(){return {readyState:'open',addEventListener(){},close(){this.readyState='closed';}};}
 async createOffer(){return {type:'offer',sdp:'offer-with-final-CRLF\r\n'};}
 async setLocalDescription(offer){this.localDescription=offer;}
 close(){}
};
const requests=[];
context.fetch=async(url,options)=>{
 requests.push(JSON.parse(options.body));
 return {ok:false,status:503,async text(){return JSON.stringify({error:'Existing fallback'});}};
};
context.testAPI.startSession(startupControls,startupForm).then(()=>{
 assert.equal(requests[0].token,'test-render-token');
 assert.equal(requests[0].sdp,'offer-with-final-CRLF\r\n');
 assert.equal(requests[1].token,'test-render-token');
 assert.ok(requests[1].clientDiagnostic);
 assert.equal(startupStatus.textContent,'Existing fallback');
 assert.equal(stopped,true);
 console.log('PASS startup and diagnostic requests carry token, preserve SDP and reuse fallback/cleanup');
}).catch(error=>{console.error(error);process.exitCode=1;});

// Shared attribute reader preserves shape checks and string-only field allow-lists.
for(const value of [undefined, '', '{broken', 'null', '42', 'true']) {
 const malformed={dataset:{gptLiveFieldNames:value,gptLiveRequiredFieldNames:value,gptLiveFieldLabels:value,gptLiveFieldRules:value}};
 assert.equal(context.testAPI.allowedFields(malformed).size,0);
 assert.equal(context.testAPI.requiredFields(malformed).length,0);
 assert.equal(Object.keys(context.testAPI.fieldLabels(malformed)).length,0);
 assert.equal(Object.keys(context.testAPI.fieldRules(malformed)).length,0);
}
const metadata={dataset:{gptLiveFieldNames:'["name",9,null]',gptLiveRequiredFieldNames:'["name",false]',gptLiveFieldLabels:'{"name":"Your name"}',gptLiveFieldRules:'{"name":{"requiredIf":"type=other"}}'}};
assert.equal([...context.testAPI.allowedFields(metadata)].join(','),'name');
assert.equal(context.testAPI.requiredFields(metadata).join(','),'name');
assert.equal(context.testAPI.fieldLabels(metadata).name,'Your name');
assert.equal(context.testAPI.fieldRules(metadata).name.requiredIf,'type=other');
metadata.dataset.gptLiveFieldNames='{}';metadata.dataset.gptLiveFieldLabels='[]';
assert.equal(context.testAPI.allowedFields(metadata).size,0);
assert.equal(Object.keys(context.testAPI.fieldLabels(metadata)).length,0);
console.log('PASS JSON attribute shape checks, malformed fallback and field-name filtering');

// A stale configured model hides the site-owned controls without microphone access.
const missingForm = {id:'missing', dataset:{gptLiveUnavailable:'1'}, compareDocumentPosition(){return 4;}};
const missingControls = {dataset:{}, hidden:false, querySelector(){return {getAttribute(){return 'missing';}};}};
const oldQuery = context.document.querySelectorAll;
context.document.querySelectorAll = (selector) => {
 assert.ok(selector.includes('form[data-gpt-live-unavailable]'));
 return [missingForm];
};
context.testAPI.bindControls(missingControls);
assert.equal(missingControls.hidden, true);
context.document.querySelectorAll = oldQuery;
console.log('PASS missing saved model hides voice controls');
