'use strict';
// External script for the Laravel and CodeIgniter example forms.
const hostPicker = new MediaBrowser({endpoint: document.querySelector('[data-media-browser]').dataset.endpoint});
for (const field of document.querySelectorAll('[data-media-browser]')) {
  let selected = [];
  field.querySelector('button').onclick = async () => {
    const output = field.querySelector('output');
    try {
      await hostPicker.session();
      await hostPicker.open({multiple: field.dataset.multiple === 'true', selected, onSelect(items) {
        selected = items;
        field.querySelector('input').value = field.dataset.multiple === 'true' ? JSON.stringify(items.map(item => item.id)) : items[0].id;
        output.textContent = items.map(item => item.name).join(', ');
      }});
    } catch (error) { output.textContent = error.message; }
  };
}
