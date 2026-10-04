/* Shared PHP/browser cases plus rendered visibility; no network or microphone. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../FormBuilderProcessorGPTLive.js'), 'utf8');
class Choices extends Array {}
class Select {}
const context = {document:{currentScript:null,readyState:'loading',addEventListener(){}},window:{RadioNodeList:Choices},HTMLSelectElement:Select,getComputedStyle:field=>({visibility:field.visibility||'visible'})};
vm.createContext(context);
vm.runInContext(source.replace('let activeSession = null;', 'let activeSession = null; globalThis.testAPI = {conditionMatches,fieldIsVisible};'),context);
const cases = JSON.parse(fs.readFileSync(path.join(__dirname, 'conditions.json'),'utf8'));
for(const [index,test] of cases.entries()) {
 const form={elements:{namedItem(name){
  if(!Object.hasOwn(test.values,name))return null;
  const value=test.values[name];
  if(Array.isArray(value))return Choices.from(value.map(value=>({type:'radio',checked:true,value})));
  return {type:'text',value};
 }}};
 assert.equal(context.testAPI.conditionMatches(form,test.selector),test.expected,`case ${index}: ${test.selector}`);
}
// A matching condition never overrides a wrapper FormBuilder has hidden.
const wrapper={hidden:true,getClientRects(){return [1];}};
const field={type:'text',value:'other',closest(){return wrapper;}};
const form={elements:{namedItem(){return field;}}};
assert.equal(context.testAPI.conditionMatches(form,'x=other'),true);
assert.equal(context.testAPI.fieldIsVisible(form,'x'),false);
wrapper.hidden=false; wrapper.getClientRects=()=>[];
assert.equal(context.testAPI.fieldIsVisible(form,'x'),false);
wrapper.getClientRects=()=>[1]; wrapper.visibility='hidden';
assert.equal(context.testAPI.fieldIsVisible(form,'x'),false);
wrapper.visibility='visible';
assert.equal(context.testAPI.fieldIsVisible(form,'x'),true);
console.log(`PASS browser shared condition cases: ${cases.length}; rendered visibility remains authoritative`);
