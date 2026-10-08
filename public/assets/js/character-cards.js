(() => {
  'use strict';

  function status(form, message = '') {
    const node = form.querySelector('[data-card-status]');
    node.textContent = message;
    node.hidden = !message;
  }

  async function requiresLogin(response) {
    if (response.status === 401) {
      const data = await response.json().catch(() => ({}));
      if (data.redirect) {
        window.location.assign(data.redirect);
        return true;
      }
    }
    if (response.redirected &&
        /\/auth\/login\/?$/.test(new URL(response.url).pathname)) {
      window.location.assign(response.url);
      return true;
    }
    return false;
  }

  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
    const button = event.target.closest('[data-character-export]');
    if (!button) return;
    const form = document.getElementById('character-export-form');
    if (!form) return;
    form.action = button.dataset.characterExport;
    status(form);
    modalOpen('modal_character_export');
  });

  const importForm = document.getElementById('character-import-form');
  const exportForm = document.getElementById('character-export-form');

  importForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = importForm.querySelector('[type="submit"]');
    if (button.disabled) return;
    button.disabled = true;
    status(importForm);
    try {
      const response = await fetch(importForm.action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(importForm)
      });
      if (await requiresLogin(response)) return;
      const data = await response.json();
      if (!response.ok || !data.success || !data.redirect) {
        throw new Error(data.error || importForm.dataset.error);
      }
      modalClose('modal_character_import');
      window.location.assign(data.redirect);
    } catch (error) {
      status(importForm, error.message === importForm.dataset.error
        ? error.message : importForm.dataset.error);
    } finally {
      button.disabled = false;
    }
  });

  exportForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = exportForm.querySelector('[type="submit"]');
    if (button.disabled) return;
    button.disabled = true;
    status(exportForm);
    try {
      const url = new URL(exportForm.action);
      url.search = new URLSearchParams(new FormData(exportForm)).toString();
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (await requiresLogin(response)) return;
      const format = exportForm.elements.format.value;
      const expected = format === 'png' ? 'image/png' : 'application/json';
      const type = (response.headers.get('Content-Type') || '').split(';')[0].trim();
      if (!response.ok || response.redirected || type !== expected) {
        throw new Error(exportForm.dataset.error);
      }
      const blob = await response.blob();
      if (!blob.size) throw new Error(exportForm.dataset.error);

      const disposition = response.headers.get('Content-Disposition') || '';
      const filename = disposition.match(/filename="([^"]+)"/)?.[1]
        || `character.${format}`;
      const objectUrl = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = objectUrl;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      setTimeout(() => URL.revokeObjectURL(objectUrl), 30000);
      modalClose('modal_character_export');
    } catch {
      status(exportForm, exportForm.dataset.error);
    } finally {
      button.disabled = false;
    }
  });

  window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    modalClose('modal_character_import');
    modalClose('modal_character_export');
  });
})();
