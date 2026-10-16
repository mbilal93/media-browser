const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
class TestEvent { constructor(type, options) { this.type = type; Object.assign(this, options); } }
const context = {window: {}, document: {querySelectorAll: () => []}, Event: TestEvent, CustomEvent: TestEvent};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/assets/media-field.js'), 'utf8'), context);
const field = Object.create(context.window.MediaBrowserField.prototype);
const events = [];
Object.assign(field, {
  options: {sortable: true}, images: [{id: 'a', name: 'A'}, {id: 'b', name: 'B'}, {id: 'c', name: 'C'}],
  input: {value: '', dispatchEvent: event => events.push(event)}, status: {},
  list: {replaceChildren: () => {}, classList: {toggle: () => {}}},
  element: {dispatchEvent: event => events.push(event)}, card: item => item,
});
field.move('c', 0);
assert.equal(field.input.value, '["c","a","b"]');
assert.equal(JSON.stringify(field.value().map(item => item.id)), '["c","a","b"]');
assert.equal(events.find(event => event.type === 'media-browser:change').detail.images[0].id, 'c');
field.move('c', 2);
assert.equal(field.input.value, '["a","b","c"]');
field.move('a', -1);
assert.equal(field.input.value, '["a","b","c"]');
field.remove('b');
assert.equal(field.input.value, '["a","c"]');
field.options.sortable = false;
field.move('c', 0);
assert.equal(field.input.value, '["a","c"]');
const copy = field.value(); copy[0].name = 'Changed by caller';
assert.equal(field.images[0].name, 'A');
field.initialImages = [{id: 'b', name: 'Saved B'}, {id: 'a', name: 'Saved A'}];
field.reset();
assert.equal(field.input.value, '["b","a"]');
field.images[0].name = 'Modified after reset';
assert.equal(field.initialImages[0].name, 'Saved B');
console.log('PASS: gallery reordering/removal, ordered form values/events, limits, defensive values and restoring initial images on reset.');
