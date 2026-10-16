'use strict';

class MediaBrowserField {
  constructor(element) {
    this.element = element;
    this.options = JSON.parse(element.dataset.mediaField);
    this.input = element.querySelector('input[type=hidden]');
    this.list = element.querySelector('.sortable-images');
    this.status = element.querySelector('.field-status');
    this.images = this.options.images.map(item => ({...item, url: new URL(item.url, document.baseURI).href}));
    this.initialImages = this.value();
    this.picker = new MediaBrowser({endpoint: this.options.endpoint});
    const upload = this.picker.dialog.querySelector('.upload');
    upload.accept = this.options.types.join(',');
    element.querySelector('.field-choose').onclick = async () => {
      try {
        await this.picker.session();
        await this.picker.open({multiple: this.options.multiple, selected: this.images, type: 'image', maxItems: this.options.maxItems, onSelect: items => {
          if (items.some(item => !this.options.types.includes(item.mime) || item.size > this.options.maxBytes)) {
            this.status.textContent = 'Some images exceed this field’s allowed type or file size. Choose other images.';
            return;
          }
          this.images = items;
          this.render();
        }});
      } catch (error) { this.status.textContent = error.message; }
    };
    element.mediaBrowserField = this;
    element.closest('form')?.addEventListener('reset', event => {
      queueMicrotask(() => {
        if (event.defaultPrevented) return;
        this.reset();
      });
    });
    this.render(false);
  }

  move(id, targetIndex) {
    const sourceIndex = this.images.findIndex(item => item.id === id);
    if (!this.options.sortable || sourceIndex < 0 || targetIndex < 0 || targetIndex >= this.images.length || sourceIndex === targetIndex) return;
    const [item] = this.images.splice(sourceIndex, 1);
    this.images.splice(targetIndex, 0, item);
    this.render();
    this.status.textContent = `${item.name} moved to position ${targetIndex + 1}.`;
  }

  remove(id) {
    this.images = this.images.filter(item => item.id !== id);
    this.render();
  }

  render(notify = true) {
    this.input.value = JSON.stringify(this.images.map(item => item.id));
    this.list.replaceChildren(...this.images.map((item, index) => this.card(item, index)));
    this.list.classList.toggle('is-empty', !this.images.length);
    this.status.textContent = this.images.length ? `${this.images.length} image(s) selected${this.options.sortable ? ' · Drag handles or use arrows to reorder.' : '.'}` : 'No images selected. Choose images to get started.';
    if (notify) {
      this.input.dispatchEvent(new Event('input', {bubbles: true}));
      this.input.dispatchEvent(new Event('change', {bubbles: true}));
      this.element.dispatchEvent(new CustomEvent('media-browser:change', {bubbles: true, detail: {images: this.value()}}));
    }
  }

  value() { return this.images.map(item => ({...item})); }

  reset() {
    this.images = this.initialImages.map(item => ({...item}));
    this.render();
  }

  card(item, index) {
    const card = document.createElement('li');
    card.className = 'sortable-image'; card.dataset.id = item.id;
    const image = document.createElement('img'); image.src = item.url; image.alt = item.name; image.draggable = false;
    const caption = document.createElement('span'); caption.className = 'sortable-image-name'; caption.textContent = `${index + 1}. ${item.name}`;
    const controls = document.createElement('div'); controls.className = 'sortable-image-controls';
    const button = (label, text, action, disabled = false) => {
      const node = document.createElement('button'); node.type = 'button'; node.className = 'secondary'; node.setAttribute('aria-label', label); node.textContent = text; node.disabled = disabled; node.onclick = action; return node;
    };
    if (this.options.sortable) {
      const handle = button(`Drag ${item.name} to reorder`, '⠿', () => {});
      handle.classList.add('image-drag-handle');
      handle.onpointerdown = event => {
        if (event.button !== 0 || !event.isPrimary) return;
        event.preventDefault(); handle.setPointerCapture(event.pointerId); card.classList.add('is-dragging');
      };
      handle.onpointermove = event => {
        if (!handle.hasPointerCapture(event.pointerId)) return;
        for (const node of this.list.children) node.classList.remove('drop-target');
        const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('.sortable-image');
        if (target && this.list.contains(target) && target !== card) target.classList.add('drop-target');
      };
      handle.onpointerup = event => {
        if (!handle.hasPointerCapture(event.pointerId)) return;
        handle.releasePointerCapture(event.pointerId);
        const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('.sortable-image');
        card.classList.remove('is-dragging');
        for (const node of this.list.children) node.classList.remove('drop-target');
        if (target && this.list.contains(target)) this.move(item.id, this.images.findIndex(image => image.id === target.dataset.id));
      };
      handle.onpointercancel = () => { for (const node of this.list.children) node.classList.remove('is-dragging', 'drop-target'); };
      controls.append(handle,
        button(`Move ${item.name} earlier`, '←', () => { this.move(item.id, index - 1); this.list.children[index - 1]?.querySelector('.image-drag-handle')?.focus(); }, index === 0),
        button(`Move ${item.name} later`, '→', () => { this.move(item.id, index + 1); this.list.children[index + 1]?.querySelector('.image-drag-handle')?.focus(); }, index === this.images.length - 1));
    }
    controls.append(button(`Remove ${item.name} from selection`, '×', () => this.remove(item.id)));
    card.append(image, caption, controls);
    return card;
  }

  static mount(root = document) {
    for (const element of root.querySelectorAll('[data-media-field]')) if (!element.mediaBrowserField) new MediaBrowserField(element);
  }
}
window.MediaBrowserField = MediaBrowserField;
MediaBrowserField.mount();
