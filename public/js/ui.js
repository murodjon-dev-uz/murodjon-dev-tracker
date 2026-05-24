/**
 * UI primitives shared by every component: HTML escaping, toasts, a modal
 * host, date formatting and form-error rendering. Kept dependency-free.
 */
const UI = (() => {
  const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ESC[c]);

  const el = (id) => document.getElementById(id);

  function toast(message, type = 'info') {
    const root = el('toast-root');
    const node = document.createElement('div');
    node.className = `toast toast--${type}`;
    node.textContent = message;
    root.appendChild(node);
    requestAnimationFrame(() => node.classList.add('toast--show'));
    setTimeout(() => {
      node.classList.remove('toast--show');
      setTimeout(() => node.remove(), 300);
    }, 3200);
  }

  function openModal(contentHtml) {
    const root = el('modal-root');
    root.innerHTML =
      `<div class="modal" role="dialog" aria-modal="true">
         <div class="modal__backdrop" data-close></div>
         <div class="modal__dialog">${contentHtml}</div>
       </div>`;
    root.hidden = false;
    document.body.classList.add('is-locked');

    const onKey = (e) => { if (e.key === 'Escape') closeModal(); };
    document.addEventListener('keydown', onKey);
    root._onKey = onKey;
    root.querySelectorAll('[data-close]').forEach((node) => node.addEventListener('click', closeModal));

    return root.querySelector('.modal__dialog');
  }

  function closeModal() {
    const root = el('modal-root');
    root.innerHTML = '';
    root.hidden = true;
    document.body.classList.remove('is-locked');
    if (root._onKey) {
      document.removeEventListener('keydown', root._onKey);
      root._onKey = null;
    }
  }

  /** 'YYYY-MM-DD HH:MM:SS' → localized short date. */
  function fmtDate(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('ru-RU', {
      day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
    });
  }

  /** DB datetime → value for <input type="datetime-local">. */
  function toLocalInput(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
           `T${pad(date.getHours())}:${pad(date.getMinutes())}`;
  }

  function setFieldErrors(form, fields) {
    form.querySelectorAll('.field__error').forEach((node) => { node.textContent = ''; });
    if (!fields) return;
    Object.entries(fields).forEach(([key, message]) => {
      const node = form.querySelector(`[data-error="${key}"]`);
      if (node) node.textContent = message;
    });
  }

  return { esc, el, toast, openModal, closeModal, fmtDate, toLocalInput, setFieldErrors };
})();

window.UI = UI;
