/**
 * Application orchestrator: owns state, wires events with delegation, and
 * keeps the DOM in sync with the API. Rendering is delegated to Components,
 * networking to API, primitives to UI.
 */
(() => {
  const state = {
    user: null,
    tasks: [],
    categories: [],
    filters: { status: null, priority: null, category_id: null, q: '' },
  };
  let authWired = false;

  // Read a control's value by name. Using form.elements avoids collisions with
  // built-in form properties (e.g. a field named "title" or "name").
  const fieldVal = (form, name) => form.elements.namedItem(name)?.value ?? '';

  // ---------------------------------------------------------------- bootstrap
  async function init() {
    wireApp();
    try {
      const me = await API.me();
      if (me && me.authenticated) {
        state.user = me.user;
        await enterApp();
        return;
      }
    } catch { /* fall through to auth screen */ }
    showAuth();
  }

  function showAuth() {
    UI.el('view-app').hidden = true;
    UI.el('view-auth').hidden = false;
    wireAuth();
  }

  async function enterApp() {
    UI.el('view-auth').hidden = true;
    UI.el('view-app').hidden = false;
    UI.el('user-name').textContent = state.user.username;
    await Promise.all([loadCategories(), refresh()]);
  }

  // ---------------------------------------------------------------- data sync
  async function refresh() {
    const [tasks, stats] = await Promise.all([API.tasks(state.filters), API.stats()]);
    state.tasks = tasks;
    Components.renderBoard(tasks);
    Components.renderStats(stats);
  }

  async function loadCategories() {
    state.categories = await API.categories();
    Components.renderCategories(state.categories, state.filters.category_id);
  }

  // ---------------------------------------------------------------- auth views
  function wireAuth() {
    if (authWired) return;
    authWired = true;

    UI.el('auth-tabs').addEventListener('click', (e) => {
      const btn = e.target.closest('[data-tab]');
      if (btn) switchAuthTab(btn.dataset.tab);
    });
    UI.el('login-form').addEventListener('submit', onLogin);
    UI.el('register-form').addEventListener('submit', onRegister);
  }

  function switchAuthTab(tab) {
    document.querySelectorAll('[data-tab]').forEach((b) => b.classList.toggle('tab--active', b.dataset.tab === tab));
    UI.el('login-form').hidden = tab !== 'login';
    UI.el('register-form').hidden = tab !== 'register';
  }

  async function onLogin(e) {
    e.preventDefault();
    const form = e.currentTarget;
    UI.setFieldErrors(form, null);
    const login = fieldVal(form, 'login').trim();
    const password = fieldVal(form, 'password');

    const errors = {};
    if (!login) errors.login = 'Введите логин или e-mail.';
    if (!password) errors.password = 'Введите пароль.';
    if (Object.keys(errors).length) return UI.setFieldErrors(form, errors);

    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
      const data = await API.login(login, password);
      state.user = data.user;
      await enterApp();
    } catch (err) {
      if (err.status === 422 && err.fields) UI.setFieldErrors(form, err.fields);
      else UI.toast(err.message, 'error');
    } finally {
      btn.disabled = false;
    }
  }

  async function onRegister(e) {
    e.preventDefault();
    const form = e.currentTarget;
    UI.setFieldErrors(form, null);
    const username = fieldVal(form, 'username').trim();
    const email = fieldVal(form, 'email').trim();
    const password = fieldVal(form, 'password');

    const errors = {};
    if (username.length < 3) errors.username = 'Минимум 3 символа.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = 'Неверный e-mail.';
    if (password.length < 8) errors.password = 'Минимум 8 символов.';
    if (Object.keys(errors).length) return UI.setFieldErrors(form, errors);

    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
      const data = await API.register(username, email, password);
      state.user = data.user;
      UI.toast('Аккаунт создан', 'success');
      await enterApp();
    } catch (err) {
      if ((err.status === 422 || err.status === 409) && err.fields) UI.setFieldErrors(form, err.fields);
      else UI.toast(err.message, 'error');
    } finally {
      btn.disabled = false;
    }
  }

  // ---------------------------------------------------------------- app events
  function wireApp() {
    UI.el('add-task').addEventListener('click', () => openTaskModal(null));
    UI.el('add-category').addEventListener('click', () => openCategoryModal(null));

    UI.el('logout').addEventListener('click', async () => {
      try { await API.logout(); } catch { /* ignore */ }
      window.location.reload();
    });

    UI.el('status-filter').addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-status]');
      if (!btn) return;
      state.filters.status = btn.dataset.status || null;
      document.querySelectorAll('#status-filter .pill').forEach((p) => p.classList.toggle('pill--active', p === btn));
      await refresh();
    });

    UI.el('filter-priority').addEventListener('change', async (e) => {
      state.filters.priority = e.target.value || null;
      await refresh();
    });

    let timer;
    UI.el('search').addEventListener('input', (e) => {
      clearTimeout(timer);
      timer = setTimeout(async () => {
        state.filters.q = e.target.value.trim();
        await refresh();
      }, 250);
    });

    wireBoard();
    wireCategoryList();
  }

  function wireBoard() {
    const board = UI.el('board');

    board.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;
      const id = btn.dataset.id;
      if (btn.dataset.action === 'edit-task') {
        openTaskModal(state.tasks.find((t) => String(t.id) === String(id)));
      } else if (btn.dataset.action === 'delete-task') {
        if (await confirmDialog('Удалить эту задачу?')) {
          try {
            await API.deleteTask(id);
            await Promise.all([refresh(), loadCategories()]);
            UI.toast('Задача удалена', 'success');
          } catch (err) { UI.toast(err.message, 'error'); }
        }
      }
    });

    // Native drag-and-drop between status columns.
    board.addEventListener('dragstart', (e) => {
      const card = e.target.closest('.task');
      if (!card) return;
      e.dataTransfer.setData('text/plain', card.dataset.id);
      e.dataTransfer.effectAllowed = 'move';
      card.classList.add('task--dragging');
    });
    board.addEventListener('dragend', (e) => {
      e.target.closest('.task')?.classList.remove('task--dragging');
      document.querySelectorAll('.column__body--over').forEach((n) => n.classList.remove('column__body--over'));
    });
    board.addEventListener('dragover', (e) => {
      const zone = e.target.closest('[data-dropzone]');
      if (!zone) return;
      e.preventDefault();
      zone.classList.add('column__body--over');
    });
    board.addEventListener('dragleave', (e) => {
      e.target.closest('[data-dropzone]')?.classList.remove('column__body--over');
    });
    board.addEventListener('drop', async (e) => {
      const zone = e.target.closest('[data-dropzone]');
      if (!zone) return;
      e.preventDefault();
      zone.classList.remove('column__body--over');
      const id = e.dataTransfer.getData('text/plain');
      const status = zone.dataset.dropzone;
      const task = state.tasks.find((t) => String(t.id) === String(id));
      if (!task || task.status === status) return;
      try {
        await API.updateTask(id, { status });
        await Promise.all([refresh(), loadCategories()]);
      } catch (err) { UI.toast(err.message, 'error'); }
    });
  }

  function wireCategoryList() {
    UI.el('category-list').addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;
      const id = btn.dataset.id;
      const action = btn.dataset.action;

      if (action === 'filter-category') {
        state.filters.category_id = id || null;
        Components.renderCategories(state.categories, state.filters.category_id);
        await refresh();
      } else if (action === 'edit-category') {
        openCategoryModal(state.categories.find((c) => String(c.id) === String(id)));
      } else if (action === 'delete-category') {
        if (await confirmDialog('Удалить категорию? Задачи останутся без неё.')) {
          try {
            await API.deleteCategory(id);
            if (String(state.filters.category_id) === String(id)) state.filters.category_id = null;
            await Promise.all([loadCategories(), refresh()]);
            UI.toast('Категория удалена', 'success');
          } catch (err) { UI.toast(err.message, 'error'); }
        }
      }
    });
  }

  // ---------------------------------------------------------------- modals
  function openTaskModal(task) {
    const dialog = UI.openModal(Components.TaskForm(task, state.categories));
    const form = dialog.querySelector('#task-form');
    const range = form.querySelector('#f-progress');
    const out = form.querySelector('#f-progress-out');
    range.addEventListener('input', () => { out.textContent = range.value; });
    form.addEventListener('submit', onTaskSubmit);
    form.querySelector('#f-title').focus();
  }

  async function onTaskSubmit(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const id = form.dataset.id;
    const payload = {
      title: fieldVal(form, 'title').trim(),
      description: fieldVal(form, 'description').trim(),
      status: fieldVal(form, 'status'),
      priority: fieldVal(form, 'priority'),
      progress: Number(fieldVal(form, 'progress')),
      deadline: fieldVal(form, 'deadline') || null,
      category_id: fieldVal(form, 'category_id') || null,
    };

    const errors = {};
    if (!payload.title) errors.title = 'Укажите название.';
    else if (payload.title.length > 255) errors.title = 'Не более 255 символов.';
    if (Object.keys(errors).length) return UI.setFieldErrors(form, errors);

    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
      if (id) {
        await API.updateTask(id, payload);
        UI.toast('Задача обновлена', 'success');
      } else {
        await API.createTask(payload);
        UI.toast('Задача создана', 'success');
      }
      UI.closeModal();
      await Promise.all([refresh(), loadCategories()]);
    } catch (err) {
      if (err.status === 422 && err.fields) UI.setFieldErrors(form, err.fields);
      else UI.toast(err.message, 'error');
      btn.disabled = false;
    }
  }

  function openCategoryModal(category) {
    const dialog = UI.openModal(Components.CategoryForm(category));
    const form = dialog.querySelector('#category-form');
    form.addEventListener('submit', onCategorySubmit);
    form.querySelector('#c-name').focus();
  }

  async function onCategorySubmit(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const id = form.dataset.id;
    const payload = { name: fieldVal(form, 'name').trim(), color: fieldVal(form, 'color') };

    const errors = {};
    if (!payload.name) errors.name = 'Укажите название.';
    else if (payload.name.length > 100) errors.name = 'Не более 100 символов.';
    if (Object.keys(errors).length) return UI.setFieldErrors(form, errors);

    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
      if (id) {
        await API.updateCategory(id, payload);
        UI.toast('Категория обновлена', 'success');
      } else {
        await API.createCategory(payload);
        UI.toast('Категория создана', 'success');
      }
      UI.closeModal();
      await Promise.all([loadCategories(), refresh()]);
    } catch (err) {
      if ((err.status === 422 || err.status === 409) && err.fields) UI.setFieldErrors(form, err.fields);
      else UI.toast(err.message, 'error');
      btn.disabled = false;
    }
  }

  function confirmDialog(message) {
    return new Promise((resolve) => {
      const dialog = UI.openModal(`
        <header class="modal__head"><h2 class="modal__title">Подтверждение</h2></header>
        <p class="modal__text">${UI.esc(message)}</p>
        <div class="form__actions">
          <button class="btn btn--ghost" data-no>Отмена</button>
          <button class="btn btn--danger" data-yes>Удалить</button>
        </div>`);
      dialog.querySelector('[data-yes]').addEventListener('click', () => { UI.closeModal(); resolve(true); });
      dialog.querySelector('[data-no]').addEventListener('click', () => { UI.closeModal(); resolve(false); });
    });
  }

  document.addEventListener('DOMContentLoaded', init);
})();
