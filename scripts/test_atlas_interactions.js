// Run the real controller with small DOM/Leaflet adapters; no network or database.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
    constructor(tag = 'div') { this.tag = tag; this.children = []; this.events = {}; this.attrs = {}; this.value = ''; this.hidden = false; this.textContent = ''; }
    addEventListener(name, callback) { this.events[name] = callback; }
    fire(name, extra = {}) { this.events[name]?.({target: this, preventDefault() {}, ...extra}); }
    appendChild(child) { this.children.push(child); return child; }
    replaceChildren() { this.children = []; }
    setAttribute(key, value) { this.attrs[key] = value; }
    removeAttribute(key) { delete this.attrs[key]; }
    querySelectorAll() { return this.children.filter(child => child.attrs.role === 'option'); }
    scrollIntoView() {}
    contains(target) { return target === this || this.children.includes(target); }
    focus() { this.fire('focus'); }
    remove() { this.removed = true; }
}
function setup(clustered = true, data = null) {
    const ids = ['atlas-data','atlas-notice','atlas-summary','atlas-search-input','atlas-search-clear','atlas-search-dropdown','atlas-state','atlas-status','atlas-show-all','btn-locate','atlas-search-wrapper'];
    const nodes = Object.fromEntries(ids.map(id => [id, new Element()]));
    nodes['atlas-status'].value = 'all';
    nodes['atlas-data'].textContent = JSON.stringify({siteUrl: 'https://example.com', locations: data || [
        {id:1, post_office:'Bangalore GPO', state:'Karnataka', latitude:12.98, longitude:77.59, is_acquired:1, linked_item_id:12, linked_item_title:'Public stamp', preview_url:'/uploads/thumbnails/stamp.webp'},
        {id:2, post_office:'Bangalore museum', state:'Karnataka', latitude:12.98, longitude:77.59, is_acquired:0},
        {id:3, post_office:'Thrissur', state:'Kerala', latitude:10.52, longitude:76.21, is_acquired:1},
        {id:4, post_office:'Unmapped', latitude:null, longitude:null, is_acquired:0}
    ]});
    const group = {layers:[], addTo(){return this;}, clearLayers(){this.layers=[];}, addLayers(items){this.layers.push(...items);}, addLayer(item){this.layers.push(item);}, zoomToShowLayer(marker, cb){this.revealed=marker; cb();}};
    const map = {fits:0, setView(point){this.point=point;return this;}, fitBounds(bounds){this.bounds=bounds;this.fits++;}, closePopup(){}, removeLayer(){}, getZoom(){return 5;}};
    let tileUrl, geolocationError, geolocationSuccess;
    const watchesCleared = [];
    const L = {
        map:()=>map, tileLayer:(url)=>{tileUrl=url;return {addTo(){return this;},on(){}};},
        control:{scale:()=>({addTo(){}})}, featureGroup:()=>group, divIcon:options=>options,
        latLngBounds:points=>({points,pad(){return this;}}),
        marker:(point, options)=>({point,options,bindPopup(factory){this.popup=factory;return this;},bindTooltip(){return this;},openPopup(){this.opened=true;}})
    };
    if (clustered) L.markerClusterGroup = () => group;
    const window = {L,AtlasText:require('../assets/js/atlas_text.js'),isSecureContext:true,addEventListener(){}};
    const document = {readyState:'complete',getElementById:id=>nodes[id],createElement:tag=>new Element(tag),addEventListener(){}};
    const timers = new Map(); let timerId=0;
    const context = {window,document,URL,setTimeout:fn=>{timers.set(++timerId,fn);return timerId;},clearTimeout:id=>timers.delete(id),navigator:{geolocation:{watchPosition(success,error){geolocationSuccess=success;geolocationError=error;return 7;},clearWatch:id=>watchesCleared.push(id)}}};
    vm.runInNewContext(fs.readFileSync(require.resolve('../assets/js/atlas_public.js'),'utf8'),context);
    return {nodes,group,map,tileUrl,watchesCleared,denyLocation:()=>geolocationError({code:1}),lateLocation:()=>geolocationSuccess({coords:{latitude:1,longitude:2,accuracy:10}}),flush:()=>{for(const fn of timers.values())fn();timers.clear();}};
}
const app = setup();
const n = app.nodes;
assert.equal(app.tileUrl,'https://tile.openstreetmap.org/{z}/{x}/{y}.png');
assert.equal(app.group.layers.length,3);
assert.match(n['atlas-summary'].textContent,/3 of 3 locations.*2 collected.*1 seeking.*1 without/);
// Search selection must respect combined filters, including markers at identical coordinates.
n['atlas-status'].value='seeking'; n['atlas-status'].fire('change');
n['atlas-search-input'].value='Bangalore'; n['atlas-search-input'].fire('input'); app.flush();
assert.equal(app.group.layers.length,1);
assert.equal(n['atlas-search-dropdown'].querySelectorAll().length,1);
n['atlas-search-input'].fire('keydown',{key:'ArrowDown'});
n['atlas-search-input'].fire('keydown',{key:'Enter'});
assert.equal(app.group.revealed.options.title,'Bangalore museum — Seeking');
assert.equal(app.group.revealed.opened,true);
// Rapid new input must not activate an old suggestion on Enter.
n['atlas-search-input'].value='No match'; n['atlas-search-input'].fire('input');
n['atlas-search-input'].fire('keydown',{key:'Enter'});
assert.equal(app.group.layers.length,0);
assert.match(n['atlas-summary'].textContent,/No locations match/);
n['atlas-state'].value='Kerala'; n['atlas-state'].fire('change');
n['atlas-show-all'].fire('click');
assert.equal(n['atlas-search-input'].value,''); assert.equal(n['atlas-status'].value,'all'); assert.equal(n['atlas-state'].value,'');
assert.equal(app.group.layers.length,3);
// Preview is constructed as text/DOM and links to the visible item.
const popup=app.group.layers[0].popup();
const link=popup.children.find(child=>child.tag==='a');
assert.equal(link.href,'https://example.com/item/12');
assert.equal(link.children[0].src,'https://example.com/uploads/thumbnails/stamp.webp');
// A permission denial resets the button/watch and ignores late callbacks.
n['btn-locate'].fire('click'); app.denyLocation(); app.lateLocation();
assert.equal(n['btn-locate'].textContent,'Locate me'); assert.equal(n['btn-locate'].attrs['aria-pressed'],'false');
assert.deepEqual(app.watchesCleared,[7]); assert.match(n['atlas-notice'].textContent,/denied/);
const fallback=setup(false); assert.equal(fallback.group.layers.length,3); assert.match(fallback.nodes['atlas-notice'].textContent,/grouping is unavailable/);
const empty=setup(true,[]); assert.match(empty.nodes['atlas-summary'].textContent,/No mapped locations/); empty.nodes['atlas-show-all'].fire('click');
console.log('PASS Atlas controller: OSM, clustering selection, combined filters, rapid search, reset, previews, denied geolocation, fallback, empty data');
