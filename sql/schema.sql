-- ===================================================================
--  Murodjon Dev Tracker — database schema (MySQL 8)
--  Idempotent: safe on a clean machine or an existing database.
--  Run once as a MySQL administrator — it also creates the app user.
--      sudo mysql < sql/schema.sql
-- ===================================================================

CREATE DATABASE IF NOT EXISTS tracker_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Least-privilege application account: DML only, no DDL, scoped to one schema.
CREATE USER IF NOT EXISTS 'tracker_app'@'localhost' IDENTIFIED BY 'tracker_dev_pwd';
GRANT SELECT, INSERT, UPDATE, DELETE ON tracker_db.* TO 'tracker_app'@'localhost';
FLUSH PRIVILEGES;

USE tracker_db;

-- -------------------------------------------------------------------
--  users
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT          NOT NULL AUTO_INCREMENT,
    username   VARCHAR(50)  NOT NULL,
    email      VARCHAR(100) NOT NULL,
    password   VARCHAR(255) NOT NULL,           -- bcrypt hash ($2y$12$...)
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY username (username),
    UNIQUE KEY email (email),
    KEY idx_email (email),
    KEY idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
--  categories  (per-user, cascade-deleted with the owner)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id         INT          NOT NULL AUTO_INCREMENT,
    user_id    INT          NOT NULL,
    name       VARCHAR(100) NOT NULL,
    color      VARCHAR(7)   DEFAULT '#3498db',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_name_per_user (user_id, name),
    KEY idx_user_id (user_id),
    CONSTRAINT categories_ibfk_1 FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
--  tasks
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
    id          INT          NOT NULL AUTO_INCREMENT,
    user_id     INT          NOT NULL,
    category_id INT          DEFAULT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    status      ENUM('new','in_progress','completed') DEFAULT 'new',
    priority    ENUM('low','medium','high')           DEFAULT 'medium',
    progress    INT          DEFAULT 0,
    deadline    DATETIME     DEFAULT NULL,
    created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_id (user_id),
    KEY idx_status (status),
    KEY idx_priority (priority),
    KEY idx_category_id (category_id),
    KEY idx_deadline (deadline),
    KEY idx_created_at (created_at),
    CONSTRAINT tasks_ibfk_1 FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT tasks_ibfk_2 FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT tasks_chk_1 CHECK (progress >= 0 AND progress <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
--  activity_logs  (append-only audit trail)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
    id         INT          NOT NULL AUTO_INCREMENT,
    user_id    INT          DEFAULT NULL,
    action     VARCHAR(100) DEFAULT NULL,
    entity     VARCHAR(50)  DEFAULT NULL,
    entity_id  INT          DEFAULT NULL,
    details    JSON         DEFAULT NULL,
    ip_address VARCHAR(45)  DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_id (user_id),
    KEY idx_created_at (created_at),
    CONSTRAINT activity_logs_ibfk_1 FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
