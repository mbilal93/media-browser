'use strict';

class MediaBrowser {
  constructor({endpoint = 'api.php', csrfToken = '', csrfHeader = 'X-CSRF-Token', csrfField = null, headers = {}} = {}) {
    this.endpoint = new URL(endpoint, document.baseURI);
    this.csrf = csrfToken;
    this.csrfHeader = csrfHeader;
    this.csrfField = csrfField;
    this.headers = headers;
    this.limits = {maxBytes: 20 * 1024 * 1024, types: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'video/mp4', 'video/webm']};
    this.selected = new Map();
    this.page = 1;
    this.requestNumber = 0;
    this.dialog = document.createElement('dialog');
    this.dialog.className = 'media-browser';
    this.dialog.setAttribute('aria-labelledby', 'media-browser-title');
    this.dialog.innerHTML = `<div class="browser-head"><div><span class="eyebrow">YOUR CREATIVE TOOLKIT</span><h2 id="media-browser-title">Media library</h2></div><button type="button" class="secondary close" aria-label="Close media browser">Close ✕</button></div>
      <div class="browser-tools"><form class="search"><label class="sr-only" for="media-search">Search media</label><input id="media-search" type="search" placeholder="Search files…"><button>Search</button></form><label class="sr-only" for="media-type">File type</label><select id="media-type"><option value="">All files</option><option value="image">Images</option><option value="document">Documents</option><option value="video">Video</option></select><label class="upload-button">Upload files<input class="upload" type="file" multiple accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,video/mp4,video/webm"></label></div>
      <p class="browser-status" role="status" aria-live="polite"></p><div class="media-grid"></div><div class="browser-bottom"><div class="pagination"><button class="secondary previous">← Previous</button><span class="page-label"></span><button class="secondary next">Next →</button></div><div><span class="selection-count"></span> <button class="confirm">Use selected files</button></div></div>`;
    // Multiple reusable fields must not duplicate label IDs in the document.
    const prefix = `media-picker-${++MediaBrowser.instanceCount}-`;
    for (const node of this.dialog.querySelectorAll('[id]')) node.id = prefix + node.id;
    for (const label of this.dialog.querySelectorAll('label[for]')) label.htmlFor = prefix + label.htmlFor;
    this.dialog.setAttribute('aria-labelledby', prefix + 'media-browser-title');
    document.body.append(this.dialog);
    this.grid = this.dialog.querySelector('.media-grid');
    this.status = this.dialog.querySelector('.browser-status');
    this.search = this.dialog.querySelector('input[type=search]');
    this.type = this.dialog.querySelector('select');
    this.dialog.querySelector('.close').onclick = () => this.dialog.close();
    this.dialog.querySelector('.search').onsubmit = event => { event.preventDefault(); this.load(1); };
    this.type.onchange = () => this.load(1);
    this.dialog.querySelector('.previous').onclick = () => this.load(this.page - 1);
    this.dialog.querySelector('.next').onclick = () => this.load(this.page + 1);
    this.dialog.querySelector('.upload').onchange = event => this.upload(event.target);
    this.dialog.querySelector('.confirm').onclick = () => { this.onSelect?.([...this.selected.values()]); this.dialog.close(); };
  }

  async request(action, data = null, query = {}) {
    const url = new URL(this.endpoint);
    for (const [key, value] of Object.entries({action, ...query})) url.searchParams.set(key, value);
    if (data && this.csrfField) data.set(this.csrfField, this.csrf || '');
    const headers = {...this.headers, Accept: 'application/json'};
    if (data && this.csrfHeader) headers[this.csrfHeader] = this.csrf || '';
    const response = await fetch(url, {method: data ? 'POST' : 'GET', body: data, headers, credentials: 'same-origin'});
    const refreshedToken = this.csrfHeader && response.headers.get(this.csrfHeader);
    if (refreshedToken) this.csrf = refreshedToken;
    if (response.redirected) throw new Error('Sign in to the host application, then reopen the media browser.');
    if (!(response.headers.get('Content-Type') || '').includes('application/json')) throw new Error(`Expected a JSON response (HTTP ${response.status}). Check the endpoint, host login and CSRF configuration.`);
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || result.errors?.map(item => `${item.name}: ${item.error}`).join('; ') || 'Request failed.');
    return result;
  }

  async session() {
    const result = await this.request('session');
    this.csrf = result.csrf;
    this.csrfHeader = result.csrfHeader || this.csrfHeader;
    this.csrfField = result.csrfField || null;
    if (result.settings) {
      this.limits = result.settings;
      this.dialog.querySelector('.upload').accept = this.limits.types.join(',');
    }
    return result;
  }

  async open({multiple = false, selected = [], type = '', onSelect = null, manage = false, maxItems = Infinity} = {}) {
    this.multiple = multiple;
    this.maxItems = maxItems;
    this.onSelect = onSelect;
    this.selected = new Map(selected.map(item => [item.id, item]));
    this.type.value = type;
    this.type.disabled = type === 'image';
    this.search.value = '';
    this.dialog.querySelector('.confirm').hidden = manage;
    this.dialog.showModal();
    await this.load(1);
  }

  absolute(item) {
    return {...item, url: new URL(item.url, this.endpoint).href};
  }

  async load(page = this.page) {
    const requestNumber = ++this.requestNumber;
    this.status.textContent = 'Loading your files…';
    try {
      const data = await this.request('list', null, {q: this.search.value, page, type: this.type.value});
      if (requestNumber !== this.requestNumber) return;
      this.page = data.page;
      this.grid.replaceChildren(...data.items.map(item => this.card(this.absolute(item))));
      if (!data.items.length) {
        const empty = document.createElement('p');
        empty.className = 'empty-state';
        empty.textContent = 'No files found. Upload a file or try another search.';
        this.grid.append(empty);
      }
      this.status.textContent = `${data.total} file${data.total === 1 ? '' : 's'} in this view · ${Math.round(this.limits.maxBytes / 1024 / 1024 * 10) / 10} MB per file`;
      this.dialog.querySelector('.page-label').textContent = `${data.page} / ${data.pages}`;
      this.dialog.querySelector('.previous').disabled = data.page <= 1;
      this.dialog.querySelector('.next').disabled = data.page >= data.pages;
      this.updateSelection();
    } catch (error) { if (requestNumber === this.requestNumber) this.status.textContent = error.message; }
  }

  card(item) {
    const card = document.createElement('article');
    card.className = 'media-card';
    card.dataset.id = item.id;
    const select = document.createElement('button');
    select.className = 'media-select';
    select.type = 'button';
    select.setAttribute('aria-label', `Select ${item.name}`);
    if (item.mime.startsWith('image/')) {
      const image = document.createElement('img');
      image.src = item.url; image.alt = ''; image.loading = 'lazy'; select.append(image);
    } else {
      const placeholder = document.createElement('span');
      placeholder.className = 'file-placeholder'; placeholder.textContent = item.mime.startsWith('video/') ? '▶ VIDEO' : '▤ PDF'; select.append(placeholder);
    }
    select.onclick = () => {
      if (this.selected.has(item.id)) this.selected.delete(item.id);
      else {
        if (this.multiple && this.selected.size >= this.maxItems) { this.status.textContent = `Select at most ${this.maxItems} images.`; return; }
        if (!this.multiple) this.selected.clear(); this.selected.set(item.id, item);
      }
      this.updateSelection();
    };
    const name = document.createElement('strong'); name.textContent = item.name; name.title = item.name;
    const details = document.createElement('small'); details.textContent = `${item.extension.toUpperCase()} · ${Math.max(1, Math.round(item.size / 1024))} KB`;
    const actions = document.createElement('div'); actions.className = 'file-actions';
    const link = document.createElement('a'); link.href = item.url; link.target = '_blank'; link.rel = 'noopener'; link.textContent = 'Open';
    actions.append(link);
    for (const [label, handler] of [['Copy URL', async () => { await navigator.clipboard.writeText(item.url); this.status.textContent = 'URL copied.'; }], ['Rename', async () => {
      const value = prompt('Display name (the URL remains unchanged)', item.name);
      if (value === null) return;
      const result = await this.request('rename', new URLSearchParams({id: item.id, name: value}));
      if (this.selected.has(item.id)) this.selected.set(item.id, this.absolute(result.item));
      await this.load();
    }], ['Delete', async () => {
      if (!confirm(`Delete “${item.name}”? Existing references to this file will stop working.`)) return;
      await this.request('delete', new URLSearchParams({id: item.id}));
      this.selected.delete(item.id); await this.load();
    }]]) {
      const button = document.createElement('button'); button.type = 'button'; button.textContent = label;
      button.onclick = async () => { try { await handler(); } catch (error) { this.status.textContent = error.message; } };
      actions.append(button);
    }
    card.append(select, name, details, actions);
    return card;
  }

  updateSelection() {
    for (const card of this.grid.children) {
      const selected = this.selected.has(card.dataset.id);
      card.classList.toggle('selected', selected);
      card.querySelector('.media-select')?.setAttribute('aria-pressed', String(selected));
    }
    this.dialog.querySelector('.selection-count').textContent = `${this.selected.size} selected`;
    this.dialog.querySelector('.confirm').disabled = !this.selected.size;
  }

  async upload(input) {
    if (!input.files.length) return;
    const data = new FormData();
    for (const file of input.files) data.append('files[]', file);
    input.disabled = true;
    this.status.textContent = 'Uploading…';
    try {
      const result = await this.request('upload', data);
      await this.load(1);
      this.status.textContent = `${result.items.length} file(s) uploaded. ${result.errors.map(item => `${item.name}: ${item.error}`).join('; ')}`;
    } catch (error) { this.status.textContent = error.message; }
    finally { input.value = ''; input.disabled = false; }
  }
}
window.MediaBrowser = MediaBrowser;
MediaBrowser.instanceCount = 0;
