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

// Explicit query selections are already validated against WordPress by PHP.
context.p26ProfileAction='';
context.p26PageProfileData={order:['d0','d3'],dimensions:{d3:['nsw']},selections:{d3:'qld',d0:'parents'}};
storage.set('p26_profile',JSON.stringify({persona:'nsw',counters:{d3:{nsw:260,tas:259,qld:259}}}));
classes.clear(); classes.add('nsw');
vm.runInContext(script,context);
profile=JSON.parse(storage.get('p26_profile'));
assert.deepEqual(profile.counters.d3,{nsw:261,tas:259,qld:262});
assert.deepEqual(profile.counters.d0,{parents:1});
assert.equal(profile.persona,'parents, qld');
assert(!classes.has('nsw') && classes.has('qld') && classes.has('parents'));
assert.equal(decodeURIComponent(cookies.get('p26_profile')),storage.get('p26_profile'));
vm.runInContext(script,context);
assert.equal(JSON.parse(storage.get('p26_profile')).counters.d3.qld,263);
context.p26PageProfileData.dimensions={};
storage.clear();
cookies.set('p26_profile',encodeURIComponent(JSON.stringify({persona:'nsw',counters:{d3:{nsw:260,tas:259,qld:259}}})));
vm.runInContext(script,context);
assert.equal(JSON.parse(storage.get('p26_profile')).counters.d3.qld,261);
for (const action of ['get','clear']) {
    context.p26ProfileAction=action;
    const saved=storage.get('p26_profile');
    vm.runInContext(script,context);
    assert.equal(storage.get('p26_profile'),saved);
}
context.p26ProfileAction='show';
context.p26PageProfileData.selections={d3:'__proto__',d9:'unknown'};
const saved=storage.get('p26_profile');
vm.runInContext(script,context);
assert.equal(storage.get('p26_profile'),saved);
context.p26PageProfileData.selections={d3:'qld'};
vm.runInContext(script,context);
assert.equal(JSON.parse(storage.get('p26_profile')).counters.d3.qld,262);
console.log('PASS: URL boosts after page increments, multiple/empty dimensions, reloads, cookie fallback/mirror, classes, get/clear/show, unsafe selections');
