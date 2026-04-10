# JobBridge Project

## Overview
JobBridge is a student-focused job platform built with PHP and PostgreSQL that connects students with:
- Company/Store Jobs
- Odd Jobs (individuals/civilians hiring help)
- Volunteer Work
- Internships

## Current State
- **Status**: Fully migrated to Replit environment and running
- **Tech Stack**: PHP 8.2, PostgreSQL (Replit built-in), Bootstrap 5, HTML/CSS/JavaScript
- **Last Updated**: April 2026

## Project Structure
- Core pages: `index.php`, `register.php`, `login.php`, `profile.php`, `edit-profile.php`, `jobs.php`, `volunteer.php`, `internship.php`, `messages.php`, `chat.php`, `send_message.php`, `get_conversations.php`, `saved-jobs.php`, `admin.php`, `resource-hub.php`
- Helper files in `includes/`: `config.php`, `db.php`, `auth.php`, `functions.php`, `header.php`
- Static assets in `public/` directory
- File uploads in `uploads/` subdirectories
- Database schema defined in `database/schema.sql` (PostgreSQL version)

## Database
- **Database**: PostgreSQL (Replit built-in)
- **Connection**: Uses environment variables `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`
- **Schema**: All 10 tables created (users, profiles, jobs, applications, saved_jobs, messages, job_confirmations, ratings, flags, ads)
- **Extra columns**: `profiles.age`, `jobs.pay_type` added during migration

## Architecture
- **Authentication**: PHP sessions with bcrypt password hashing
- **Database**: PostgreSQL with normalized schema (10 tables)
- **Privacy**: Contact info hidden by default, messaging guardrails for minors
- **Moderation**: Admin tools for content management
- **Monetization**: Ad placement slots (banner, in-feed, sidebar)
- **Server**: PHP built-in server on port 5000

## Migration Notes (MySQL → PostgreSQL)
- PDO DSN updated from `mysql:` to `pgsql:` in `includes/db.php`
- `ON DUPLICATE KEY UPDATE` → `ON CONFLICT ... DO UPDATE SET` in `edit-profile.php`
- `GROUP BY IF(...)` → `GROUP BY CASE WHEN ... END` in `messages.php`
- `SERIAL` used instead of `AUTO_INCREMENT`
- `VARCHAR` with `CHECK` constraints used instead of `ENUM`
- `profiles.user_id` has a UNIQUE constraint for upsert support
