/**
 * Data layer — a thin Fetch API wrapper around the JSON REST endpoints.
 * Automatically attaches the CSRF token to every state-changing request and
 * unwraps the { success, data } envelope returned by the backend.
 */
const API = (() => {
  let csrfToken = '';

  const setCsrf = (token) => { if (token) csrfToken = token; };

  async function request(path, { method = 'GET', body = null } = {}) {
    const options = { method, headers: {}, credentials: 'same-origin' };

    if (body !== null) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }
    if (method !== 'GET' && method !== 'HEAD') {
      options.headers['X-CSRF-Token'] = csrfToken;
    }

    const res = await fetch(`/api/${path}`, options);

    if (res.status === 204) return null;

    let payload = null;
    try { payload = await res.json(); } catch { payload = null; }

    if (!res.ok) {
      const error = new Error((payload && payload.error) || `Ошибка ${res.status}`);
      error.status = res.status;
      error.fields = (payload && payload.errors) || null;
      throw error;
    }

    const data = payload ? payload.data : null;
    if (data && data.csrfToken) setCsrf(data.csrfToken);
    return data;
  }

  const qs = (params) => {
    const parts = Object.entries(params)
      .filter(([, v]) => v !== null && v !== '' && v !== undefined)
      .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`);
    return parts.length ? `?${parts.join('&')}` : '';
  };

  return {
    setCsrf,
    me: () => request('me.php'),
    login: (login, password) => request('login.php', { method: 'POST', body: { login, password } }),
    register: (username, email, password) =>
      request('register.php', { method: 'POST', body: { username, email, password } }),
    logout: () => request('logout.php', { method: 'POST' }),

    tasks: (filters = {}) => request(`tasks.php${qs(filters)}`),
    createTask: (task) => request('tasks.php', { method: 'POST', body: task }),
    updateTask: (id, task) => request(`tasks.php?id=${id}`, { method: 'PUT', body: task }),
    deleteTask: (id) => request(`tasks.php?id=${id}`, { method: 'DELETE' }),

    categories: () => request('categories.php'),
    createCategory: (cat) => request('categories.php', { method: 'POST', body: cat }),
    updateCategory: (id, cat) => request(`categories.php?id=${id}`, { method: 'PUT', body: cat }),
    deleteCategory: (id) => request(`categories.php?id=${id}`, { method: 'DELETE' }),

    stats: () => request('stats.php'),
  };
})();

window.API = API;
