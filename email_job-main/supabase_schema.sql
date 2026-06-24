-- ============================================================
-- ReachOut — Supabase Schema
-- Run this ONCE in Supabase SQL Editor (supabase.com → SQL Editor)
-- ============================================================

-- 1. Companies (contacts you email)
CREATE TABLE IF NOT EXISTS companies (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(255),
    email      VARCHAR(255),
    company    VARCHAR(255),
    contact    VARCHAR(255),
    status     VARCHAR(50) DEFAULT 'pending',
    sent_at    TIMESTAMP NULL,
    ai_used    INTEGER DEFAULT 0,
    opened     INTEGER DEFAULT 0,
    opened_at  TIMESTAMP NULL
);

-- 2. User profile (your info, SMTP, resume path, etc.)
CREATE TABLE IF NOT EXISTS user_profile (
    id            SERIAL PRIMARY KEY,
    name          VARCHAR(255),
    email         VARCHAR(255),
    mobile        VARCHAR(50),
    resume        VARCHAR(255),
    cover_letter  TEXT,
    skills        TEXT,
    job_role      VARCHAR(255),
    experience    VARCHAR(50),
    daily_limit   INTEGER DEFAULT 20,
    smtp_pass     VARCHAR(255),
    groq_key      VARCHAR(255),
    location      VARCHAR(255),
    cron_enabled  INTEGER DEFAULT 0,
    cron_time     VARCHAR(10) DEFAULT '09:00',
    cron_days     VARCHAR(50) DEFAULT 'mon,tue,wed,thu,fri',
    cron_last_run TIMESTAMP NULL
);

-- 3. Jobs fetched from Remotive / Jobicy / RSS
CREATE TABLE IF NOT EXISTS jobs (
    id           SERIAL PRIMARY KEY,
    title        VARCHAR(255),
    company      VARCHAR(255),
    email        VARCHAR(255),
    job_link     VARCHAR(500),
    location     VARCHAR(255),
    source       VARCHAR(50),
    description  TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    applied      INTEGER DEFAULT 0,
    matched      INTEGER DEFAULT 0,
    match_reason TEXT
);

-- 4. AI email queue (matched jobs ready to send)
CREATE TABLE IF NOT EXISTS ai_queue (
    id         SERIAL PRIMARY KEY,
    job_id     INTEGER,
    title      VARCHAR(255),
    company    VARCHAR(255),
    email      VARCHAR(255),
    status     VARCHAR(20) DEFAULT 'pending',
    sent_at    TIMESTAMP NULL,
    ai_used    INTEGER DEFAULT 0,
    opened     INTEGER DEFAULT 0,
    opened_at  TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Cron run history
CREATE TABLE IF NOT EXISTS cron_logs (
    id          SERIAL PRIMARY KEY,
    run_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_sent  INTEGER DEFAULT 0,
    total_fail  INTEGER DEFAULT 0,
    total_skip  INTEGER DEFAULT 0,
    details     TEXT,
    status      VARCHAR(20) DEFAULT 'running'
);

-- 6. Email template (subject + body used in send_one.php)
CREATE TABLE IF NOT EXISTS email_template (
    id      SERIAL PRIMARY KEY,
    subject TEXT,
    body    TEXT
);

-- 7. Email open tracking (1x1 pixel)
CREATE TABLE IF NOT EXISTS email_tracking (
    id         SERIAL PRIMARY KEY,
    company_id INTEGER,
    email      VARCHAR(255),
    opened_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip         VARCHAR(50),
    user_agent VARCHAR(255)
);

-- Default email template
INSERT INTO email_template (subject, body)
SELECT
    'Application from {{name}} — {{company}}',
    'Dear Hiring Manager,

I am writing to express my interest in joining {{company}}. Please find my resume attached.

Best regards,
{{name}}
{{mobile}}
{{email}}'
WHERE NOT EXISTS (SELECT 1 FROM email_template LIMIT 1);
