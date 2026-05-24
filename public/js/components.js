/**
 * View components — pure functions that turn data into BEM markup.
 * They never touch the network or global state, which keeps them reusable
 * and easy to reason about. State + events live in app.js.
 */
const Components = (() => {
  const STATUSES = [
    { key: 'new', label: 'Новые' },
    { key: 'in_progress', label: 'В работе' },
    { key: 'completed', label: 'Выполнено' },
  ];
  const PRIORITY = { low: 'Низкий', medium: 'Средний', high: 'Высокий' };

  const isOverdue = (task) =>
    task.deadline &&
    task.status !== 'completed' &&
    new Date(String(task.deadline).replace(' ', 'T')) < new Date();

  function TaskCard(task) {
    const chip = task.category_name
      ? `<span class="chip" style="--chip:${UI.esc(task.category_color || '#94a3b8')}">${UI.esc(task.category_name)}</span>`
      : '';
    const overdue = isOverdue(task);
    const deadline = task.deadline
      ? `<span class="task__deadline ${overdue ? 'task__deadline--over' : ''}">${overdue ? '⚠' : '⏰'} ${UI.fmtDate(task.deadline)}</span>`
      : '';

    return `
      <article class="task task--${task.priority}" data-id="${task.id}" draggable="true">
        <div class="task__top">
          <span class="badge badge--${task.priority}">${PRIORITY[task.priority]}</span>
          <div class="task__actions">
            <button class="icon-btn" data-action="edit-task" data-id="${task.id}" aria-label="Изменить" title="Изменить">✎</button>
            <button class="icon-btn icon-btn--danger" data-action="delete-task" data-id="${task.id}" aria-label="Удалить" title="Удалить">✕</button>
          </div>
        </div>
        <h3 class="task__title">${UI.esc(task.title)}</h3>
        ${task.description ? `<p class="task__desc">${UI.esc(task.description)}</p>` : ''}
        <div class="task__progress" title="Прогресс ${task.progress}%">
          <div class="task__progress-bar" style="width:${task.progress}%"></div>
        </div>
        <div class="task__meta">${chip}${deadline}</div>
      </article>`;
  }

  function Column(status, tasks) {
    const items = tasks.filter((t) => t.status === status.key);
    const body = items.length
      ? items.map(TaskCard).join('')
      : '<p class="column__empty">Перетащите задачу сюда</p>';
    return `
      <section class="column column--${status.key}">
        <header class="column__head">
          <h2 class="column__title">${status.label}</h2>
          <span class="column__count">${items.length}</span>
        </header>
        <div class="column__body" data-dropzone="${status.key}">${body}</div>
      </section>`;
  }

  function renderBoard(tasks) {
    UI.el('board').innerHTML = STATUSES.map((s) => Column(s, tasks)).join('');
  }

  function renderStats(stats) {
    const cards = [
      { label: 'Всего', value: stats.total, mod: 'total' },
      { label: 'В работе', value: stats.in_progress, mod: 'progress' },
      { label: 'Выполнено', value: stats.completed, mod: 'done' },
      { label: 'Просрочено', value: stats.overdue, mod: 'over' },
    ];
    UI.el('stats').innerHTML = cards.map((c) => `
      <div class="stat stat--${c.mod}">
        <span class="stat__value">${c.value}</span>
        <span class="stat__label">${c.label}</span>
      </div>`).join('');
  }

  function renderCategories(categories, activeId) {
    const all = `
      <li>
        <button class="cat ${activeId == null ? 'cat--active' : ''}" data-action="filter-category" data-id="">
          <span class="cat__dot" style="--dot:#64748b"></span>
          <span class="cat__name">Все задачи</span>
        </button>
      </li>`;

    const items = categories.map((cat) => `
      <li>
        <button class="cat ${String(activeId) === String(cat.id) ? 'cat--active' : ''}" data-action="filter-category" data-id="${cat.id}">
          <span class="cat__dot" style="--dot:${UI.esc(cat.color)}"></span>
          <span class="cat__name">${UI.esc(cat.name)}</span>
          <span class="cat__count">${cat.task_count}</span>
        </button>
        <span class="cat__tools">
          <button class="icon-btn" data-action="edit-category" data-id="${cat.id}" aria-label="Изменить категорию" title="Изменить">✎</button>
          <button class="icon-btn icon-btn--danger" data-action="delete-category" data-id="${cat.id}" aria-label="Удалить категорию" title="Удалить">✕</button>
        </span>
      </li>`).join('');

    UI.el('category-list').innerHTML = all + items;
  }

  // ---- form builders -------------------------------------------------------

  const options = (map, selected) =>
    Object.entries(map)
      .map(([value, label]) => `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`)
      .join('');

  function TaskForm(task, categories) {
    const t = task || { title: '', description: '', status: 'new', priority: 'medium', progress: 0, deadline: null, category_id: null };
    const catOptions = ['<option value="">— без категории —</option>']
      .concat(categories.map((c) =>
        `<option value="${c.id}" ${String(c.id) === String(t.category_id) ? 'selected' : ''}>${UI.esc(c.name)}</option>`))
      .join('');

    return `
      <header class="modal__head">
        <h2 class="modal__title">${task ? 'Редактировать задачу' : 'Новая задача'}</h2>
        <button class="icon-btn" data-close aria-label="Закрыть">✕</button>
      </header>
      <form class="form" id="task-form" data-id="${task ? task.id : ''}" novalidate>
        <div class="field">
          <label class="field__label" for="f-title">Название</label>
          <input class="field__input" id="f-title" name="title" maxlength="255" value="${UI.esc(t.title)}" autocomplete="off" />
          <span class="field__error" data-error="title"></span>
        </div>
        <div class="field">
          <label class="field__label" for="f-desc">Описание</label>
          <textarea class="field__input" id="f-desc" name="description" rows="3" maxlength="5000">${UI.esc(t.description || '')}</textarea>
          <span class="field__error" data-error="description"></span>
        </div>
        <div class="form__row">
          <div class="field">
            <label class="field__label" for="f-priority">Приоритет</label>
            <select class="field__input" id="f-priority" name="priority">${options(PRIORITY, t.priority)}</select>
          </div>
          <div class="field">
            <label class="field__label" for="f-status">Статус</label>
            <select class="field__input" id="f-status" name="status">
              ${STATUSES.map((s) => `<option value="${s.key}" ${s.key === t.status ? 'selected' : ''}>${s.label}</option>`).join('')}
            </select>
          </div>
        </div>
        <div class="form__row">
          <div class="field">
            <label class="field__label" for="f-progress">Прогресс: <output id="f-progress-out">${t.progress}</output>%</label>
            <input class="field__range" id="f-progress" name="progress" type="range" min="0" max="100" step="5" value="${t.progress}" />
            <span class="field__error" data-error="progress"></span>
          </div>
          <div class="field">
            <label class="field__label" for="f-deadline">Дедлайн</label>
            <input class="field__input" id="f-deadline" name="deadline" type="datetime-local" value="${UI.toLocalInput(t.deadline)}" />
            <span class="field__error" data-error="deadline"></span>
          </div>
        </div>
        <div class="field">
          <label class="field__label" for="f-category">Категория</label>
          <select class="field__input" id="f-category" name="category_id">${catOptions}</select>
          <span class="field__error" data-error="category_id"></span>
        </div>
        <div class="form__actions">
          <button type="button" class="btn btn--ghost" data-close>Отмена</button>
          <button type="submit" class="btn btn--primary">${task ? 'Сохранить' : 'Создать'}</button>
        </div>
      </form>`;
  }

  function CategoryForm(category) {
    const c = category || { name: '', color: '#3498db' };
    return `
      <header class="modal__head">
        <h2 class="modal__title">${category ? 'Редактировать категорию' : 'Новая категория'}</h2>
        <button class="icon-btn" data-close aria-label="Закрыть">✕</button>
      </header>
      <form class="form" id="category-form" data-id="${category ? category.id : ''}" novalidate>
        <div class="field">
          <label class="field__label" for="c-name">Название</label>
          <input class="field__input" id="c-name" name="name" maxlength="100" value="${UI.esc(c.name)}" autocomplete="off" />
          <span class="field__error" data-error="name"></span>
        </div>
        <div class="field">
          <label class="field__label" for="c-color">Цвет</label>
          <input class="field__color" id="c-color" name="color" type="color" value="${UI.esc(c.color)}" />
          <span class="field__error" data-error="color"></span>
        </div>
        <div class="form__actions">
          <button type="button" class="btn btn--ghost" data-close>Отмена</button>
          <button type="submit" class="btn btn--primary">${category ? 'Сохранить' : 'Создать'}</button>
        </div>
      </form>`;
  }

  return { STATUSES, PRIORITY, renderBoard, renderStats, renderCategories, TaskForm, CategoryForm };
})();

window.Components = Components;
