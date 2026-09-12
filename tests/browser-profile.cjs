// Dependency-free tests for browser storage and Gravity Forms profile merging.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const storage = new Map([['p26_profile',JSON.stringify({persona:'parent, giving',counters:{d0:{parent:2},d1:{giving:3}}})]]);
const cookies = new Map();
const classes = new Set();
const events = {};
const document = {
    body: {classList:{add:x=>classes.add(x),remove:x=>classes.delete(x)},appendChild(){}},
    addEventListener:(name,fn)=>{ (events[name] ||= []).push(fn); },
    createElement:()=>({style:{},setAttribute(){},appendChild(){}}),
    get cookie(){ return Array.from(cookies,([k,v])=>`${k}=${v}`).join('; '); },
    set cookie(value){const pair=value.split(';')[0];const i=pair.indexOf('=');cookies.set(pair.slice(0,i),pair.slice(i+1));}
};
const context = {document, localStorage:{getItem:k=>storage.get(k),setItem:(k,v)=>storage.set(k,v),removeItem:k=>storage.delete(k)}, location:{protocol:'https:'}, console, URL, setTimeout};
context.window=context;
context.p26PageProfileData={order:['d0','d1'],labels:{},dimensions:{d0:['teacher']}};
vm.createContext(context);
const script=fs.readFileSync(path.join(__dirname,'../persona26/scripts/persona26-profile.js'),'utf8');
vm.runInContext(script,context);
let profile=JSON.parse(storage.get('p26_profile'));
assert.equal(profile.counters.d0.teacher,1);
assert.equal(profile.counters.d1.giving,3);
assert.equal(profile.persona,'parent, giving');
context.p26ApplyProfileUpdates([{dimKey:'d0',value:'teacher',mode:'replace',order:['d0','d1']},{dimKey:'d0',value:'carer',mode:'increment',order:['d0','d1']}],true);
profile=JSON.parse(storage.get('p26_profile'));
assert.deepEqual(profile.counters.d0,{teacher:1,carer:1});
assert.equal(profile.persona,'teacher | carer, giving');
assert(classes.has('teacher') && classes.has('carer') && classes.has('giving'));
context.p26ApplyProfileUpdates([{dimKey:'__proto__',value:'x',order:[]},{dimKey:'d0',value:'__proto__',order:[]}],false);
assert.equal({}.x,undefined);
assert.equal(JSON.parse(storage.get('p26_profile')).counters.d0.teacher,1);
context.p26ProfileAction='get';
const before=storage.get('p26_profile');
vm.runInContext(script,context);
assert.equal(storage.get('p26_profile'),before);
console.log('PASS: page counters, cross-dimension retention, Gravity Forms replace/multiselect, body classes, unsafe keys, read-only debug');
