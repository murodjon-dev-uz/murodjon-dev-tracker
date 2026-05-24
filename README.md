# Murodjon Dev Tracker — Fullstack Kanban Task Manager

[Русский](#russian) | [English](#english)

---

<a name="russian"></a>
## 🇷🇺 Русский (Russian)

### О проекте
**Murodjon Dev Tracker** — это высокопроизводительный Fullstack Kanban-трекер, построенный на чистом **PHP 8.5**, **MySQL 8** и **Vanilla JavaScript**. Проект разработан с акцентом на безопасность, слоистую архитектуру и высокую скорость работы без использования тяжелых фреймворков.

Проект специально оптимизирован под требования стажировки **Digital Channels Development (Ипотека Банк)**.

### Ключевые особенности
- **Интерактивная доска:** Полноценный Kanban-интерфейс с Drag-and-Drop (Desktop) и адаптивной версткой.
- **Слоистая архитектура:** Четкое разделение ответственности (Business Logic, Auth, Validator, Database Layer).
- **Безопасность (Security First):**
    - Защита от **CSRF** (токены на сессию).
    - Защита от **XSS** (автоматическое экранирование).
    - Защита от **SQL-инъекций** (PDO Prepared Statements).
    - Безопасные сессии (HttpOnly, SameSite, Secure).
    - Хеширование паролей через **bcrypt (cost 12)**.
- **Audit Trail:** Полное логирование действий пользователей в формате JSON в базе данных.
- **RESTful API:** Чистый JSON API с корректным использованием HTTP-методов и кодов состояний.

### Технологический стек
- **Backend:** PHP 8.5+, PDO.
- **Database:** MySQL 8 (JSON types, Foreign Keys, Indexes, Check Constraints).
- **Frontend:** HTML5, CSS3 (BEM, Custom Properties), Vanilla JS (ES6+).
- **Architecture:** Layered Architecture / Component-based UI.

### Быстрый старт
```bash
# 1. Склонируйте репозиторий
git clone git@github.com:murodjon-dev-uz/murodjon-dev-tracker.git
cd murodjon-dev-tracker

# 2. Настройте базу данных (MySQL 8)
sudo mysql < sql/schema.sql

# 3. Создайте файл окружения
cp .env.example .env

# 4. Запустите встроенный сервер
php -S 127.0.0.1:8000 -t public
```

---

<a name="english"></a>
## 🇺🇸 English

### Overview
**Murodjon Dev Tracker** is a high-performance Fullstack Kanban Task Manager built with **Vanilla PHP 8.5**, **MySQL 8**, and **Vanilla JavaScript**. The project emphasizes security, layered architecture, and performance without the overhead of modern frameworks.

### Key Features
- **Interactive Kanban Board:** Smooth task management with Drag-and-Drop support and mobile-first responsive design.
- **Layered Backend Architecture:** Solid separation of concerns (Business Logic, Auth, Validator, Database Layer).
- **Security First:**
    - **CSRF Protection:** Session-bound tokens for state-changing requests.
    - **XSS Prevention:** Context-aware output escaping.
    - **SQL Injection Protection:** Strict use of PDO Prepared Statements.
    - **Hardened Sessions:** HttpOnly, SameSite, and Session Fixation protection.
    - **Modern Hashing:** Secure password storage using **bcrypt (cost 12)**.
- **Audit Trail:** Comprehensive activity logging using MySQL **JSON** data type.
- **RESTful API:** Professional JSON-based API with proper HTTP status codes and methods.

### Technical Stack
- **Backend:** PHP 8.5+, PDO.
- **Database:** MySQL 8 (JSON types, Foreign Keys, Indexes, Check Constraints).
- **Frontend:** HTML5, CSS3 (BEM, Design Tokens), Vanilla JS (ES6+).
- **Architecture:** Clean Layered Architecture / Component-driven Frontend.

### Project Structure
```text
murodjon-dev-tracker/
├── src/          # Business logic & Core services (Private)
├── public/       # Web-root (API endpoints & UI assets)
├── sql/          # Database migrations & Demo seeds
└── .env          # Environment configuration
```

### Quick Start
```bash
# 1. Clone the repository
git clone git@github.com:murodjon-dev-uz/murodjon-dev-tracker.git
cd murodjon-dev-tracker

# 2. Setup Database (MySQL 8)
sudo mysql < sql/schema.sql

# 3. Configure Environment
cp .env.example .env

# 4. Run Development Server
php -S 127.0.0.1:8000 -t public
```

### Why Vanilla?
This project demonstrates a deep understanding of core web technologies (PHP, JS, SQL) and the ability to implement enterprise-grade features (Auth, Security, Architecture) from scratch—skills essential for high-level software engineering and platform development.

---
**Author:** [Murodjon Nuritdinov](https://github.com/murodjon-dev-uz)
