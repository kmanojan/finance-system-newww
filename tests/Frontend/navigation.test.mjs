import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

function page(standalone = false) {
    const events = {document:{}, window:{}};
    const frames = [], assigned = [], timers = new Map();
    const element = () => ({hidden:true, attributes:{}, classList:{toggle(){},contains(){return false;}}, setAttribute(k,v){this.attributes[k]=v;}, removeAttribute(k){delete this.attributes[k];}});
    const loading = element(), text = element(), dismiss = element(), main = element();
    dismiss.addEventListener = (name, handler) => {dismiss[name] = handler;};
    loading.querySelector = selector => selector.includes('text') ? text : dismiss;
    let timerId = 0;
    const listen = surface => (name, handler) => (events[surface][name] ||= []).push(handler);
    const context = {
        document:{documentElement:element(),body:element(),getElementById:id=>({navigationLoading:loading,'main-content':main}[id] || null),querySelectorAll:()=>[],addEventListener:listen('document')},
        window:{addEventListener:listen('window')}, navigator:{standalone,onLine:true},
        location:{href:'https://finance.test/loans',origin:'https://finance.test',pathname:'/loans',search:'',assign:url=>assigned.push(url)},
        matchMedia:query=>({matches:query.includes('standalone') ? standalone : true,addEventListener(){}}),
        URL, setTimeout:(callback,delay)=>{const id=++timerId;timers.set(id,{callback,delay});return id;},clearTimeout:id=>timers.delete(id),requestAnimationFrame:callback=>frames.push(callback),
    };
    context.window.navigator = context.navigator; context.window.matchMedia = context.matchMedia;
    vm.createContext(context);
    for (const file of ['script.js','navigation.js']) vm.runInContext(readFileSync(new URL(`../../public/${file}`,import.meta.url),'utf8'),context);
    events.document.DOMContentLoaded.forEach(fn=>fn());
    function click(options={}) {
        const attrs = {href:'/employees',...options.attrs};
        const link = {href:new URL(attrs.href,context.location.href).href,target:attrs.target||'',textContent:'Employees',getAttribute:key=>attrs[key]||null,hasAttribute:key=>key in attrs,setAttribute(){}};
        const event = {button:0,defaultPrevented:false,target:{closest:()=>link},preventDefault(){this.defaultPrevented=true;},...options.event};
        for (const surface of ['document','window']) (events[surface].click||[]).forEach(fn=>fn(event));
        return event;
    }
    return {click,loading,text,dismiss,main,frames,assigned,timers,events};
}

test('standalone navigation paints feedback before changing documents',()=>{
    const p=page(true); const event=p.click();
    assert.equal(event.defaultPrevented,true);
    assert.equal(p.loading.hidden,false);
    assert.equal(p.text.textContent,'Opening Employees…');
    assert.equal(p.main.attributes['aria-busy'],'true');
    assert.deepEqual(p.assigned,[]);
    p.frames.shift()(); assert.deepEqual(p.assigned,[]);
    p.frames.shift()(); assert.deepEqual(p.assigned,['https://finance.test/employees']);
    p.events.window.pageshow.forEach(fn=>fn());
    assert.equal(p.loading.hidden,true); assert.equal(p.main.attributes['aria-busy'],undefined);
});

test('ordinary navigation shows feedback without replacing native navigation',()=>{
    const p=page(); assert.equal(p.click().defaultPrevented,false); assert.equal(p.loading.hidden,false); assert.deepEqual(p.assigned,[]);
    [...p.timers.values()].find(timer=>timer.delay===8000).callback();
    assert.match(p.text.textContent,/Still loading/); assert.equal(p.dismiss.hidden,false);
    p.dismiss.click(); assert.equal(p.loading.hidden,true);
});

test('tabs, downloads, new tabs, modified and cancelled clicks remain untouched',()=>{
    for(const options of [
        {attrs:{href:'#salary-history'}}, {attrs:{href:'/loans#details'}}, {attrs:{href:'/employees',download:''}},
        {attrs:{target:'_blank'}}, {attrs:{href:'/employees/export'}}, {attrs:{href:'https://other.test/'}},
        {event:{ctrlKey:true}}, {event:{metaKey:true}}, {event:{button:1}}, {event:{defaultPrevented:true}},
    ]) {const p=page(true);p.click(options);assert.equal(p.loading.hidden,true,JSON.stringify(options));assert.equal(p.frames.length,0);}
});
