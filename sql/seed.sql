-- ===================================================================
--  Murodjon Dev Tracker — demo data
--  Idempotent: re-running will not create duplicates.
--      sudo mysql < sql/seed.sql
--
--  Demo login →  username: demo   |   password: demo1234
-- ===================================================================

SET NAMES utf8mb4;
USE tracker_db;

-- Demo account (password "demo1234", bcrypt cost 12).
INSERT IGNORE INTO users (username, email, password) VALUES
    ('demo', 'demo@tracker.local',
     '$2y$12$ie9WlTlszOX03cns5CJuNuwE5xy8GrwJFGIsZKQY4KFIvNL5mTZfu');

-- Three starter categories (UNIQUE(user_id, name) makes this safe to repeat).
INSERT IGNORE INTO categories (user_id, name, color)
SELECT u.id, seed.name, seed.color
FROM users u
JOIN (
              SELECT 'Работа' AS name, '#3498db' AS color
    UNION ALL SELECT 'Учёба',           '#9b59b6'
    UNION ALL SELECT 'Личное',          '#2ecc71'
) seed
WHERE u.username = 'demo';

-- Sample tasks across all three columns — only seeded when demo has none.
INSERT INTO tasks (user_id, category_id, title, description, status, priority, progress, deadline)
SELECT
    u.id,
    (SELECT c.id FROM categories c WHERE c.user_id = u.id AND c.name = seed.cat),
    seed.title, seed.descr, seed.status, seed.priority, seed.progress,
    CASE WHEN seed.days IS NULL THEN NULL ELSE DATE_ADD(NOW(), INTERVAL seed.days DAY) END
FROM users u
JOIN (
              SELECT 'Свёрстать страницу логина'        AS title, 'HTML/CSS по макету, мобильная адаптация'   AS descr, 'completed'   AS status, 'high'   AS priority, 100 AS progress, 'Работа' AS cat, -2   AS days
    UNION ALL SELECT 'Валидация формы отклика',                  'Клиентская и серверная проверка полей',              'in_progress',         'high',            60,           'Работа',        3
    UNION ALL SELECT 'Компонент «карточка задачи»',             'Переиспользуемый BEM-компонент',                      'in_progress',         'medium',          30,           'Работа',        5
    UNION ALL SELECT 'Подготовиться к интервью',                'PHP, MySQL, HTTP/HTTPS, компонентный подход',         'new',                 'medium',           0,           'Учёба',         7
    UNION ALL SELECT 'Обновить портфолио',                      'Добавить таск-трекер в резюме',                       'new',                 'low',              0,           'Личное',     NULL
) seed
WHERE u.username = 'demo'
  AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.user_id = u.id);
