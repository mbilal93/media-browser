// Exercise the real transport methods without requiring a browser or npm packages.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const context = {window: {}, URL, URLSearchParams, console};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/assets/media-browser.js'), 'utf8'), context);
const picker = Object.create(context.window.MediaBrowser.prototype);
Object.assign(picker, {endpoint: new URL('https://example.test/nested/media/api?tenant=7'), csrf: 'old', csrfHeader: 'X-HOST-CSRF', csrfField: 'host_token', headers: {'X-Example': 'yes'}});
let seen;
context.fetch = async (url, options) => {
  seen = {url, options};
  return {ok: true, status: 200, redirected: false, headers: new Headers({'Content-Type': 'application/json', 'X-HOST-CSRF': 'rotated'}), json: async () => ({success: true})};
};
(async () => {
  const body = new URLSearchParams({id: 'abc', name: 'Renamed'});
  await picker.request('rename', body);
  assert.equal(seen.url.searchParams.get('tenant'), '7');
  assert.equal(seen.url.pathname, '/nested/media/api');
  assert.equal(seen.options.headers['X-HOST-CSRF'], 'old');
  assert.equal(seen.options.headers.Accept, 'application/json');
  assert.equal(body.get('host_token'), 'old');
  assert.equal(picker.csrf, 'rotated');
  assert.equal(picker.absolute({url: '?action=file&id=abc'}).url, 'https://example.test/nested/media/api?action=file&id=abc');
  context.fetch = async () => ({ok: false, status: 419, redirected: false, headers: new Headers({'Content-Type': 'text/html'})});
  await assert.rejects(() => picker.request('delete', body), /HTTP 419/);
  context.fetch = async () => ({ok: true, status: 200, redirected: true, headers: new Headers()});
  await assert.rejects(() => picker.request('list'), /Sign in/);
  console.log('PASS: framework endpoint paths, query preservation, CSRF headers/body/rotation, JSON negotiation and login redirects.');
})().catch(error => { console.error(error); process.exitCode = 1; });
