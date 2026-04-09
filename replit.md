# JobBridge Project

## Overview
JobBridge is a student-focused job platform built with PHP and PostgreSQL that connects students with:
- Company/Store Jobs
- Odd Jobs (individuals/civilians hiring help)
- Volunteer Work
- Internships

## Current State
- **Status**: Migrated to Replit with PostgreSQL database — fully functional
- **Tech Stack**: PHP 8.2, PostgreSQL (Replit built-in), Bootstrap 5, HTML/CSS/JavaScript
- **Last Updated**: April 2026

## Project Structure
- Core pages: `index.php`, `register.php`, `login.php`, `profile.php`, `jobs.php`, `volunteer.php`, `internship.php`, `messages.php`, `admin.php`
- Helper files in `includes/` directory:
  - `config.php` — PostgreSQL connection settings via env vars (PGHOST, PGPORT, PGDATABASE, PGUSER, PGPASSWORD)
  - `db.php` — PDO PostgreSQL connection helper
  - `auth.php` — Session management, role-based access control, password hashing
  - `functions.php` — Security utilities (sanitization, CSRF tokens), file upload helpers
  - `header.php` — Navigation component included across pages
- Static assets in `public/` directory
- File uploads organized in `uploads/` subdirectories
- Original MySQL schema in `database/schema.sql` (reference only; actual DB is PostgreSQL)

## Architecture
- **Authentication**: PHP sessions with bcrypt password hashing
- **Database**: Replit built-in PostgreSQL (10 tables)
- **Privacy**: Contact info hidden by default, messaging guardrails for minors
- **Moderation**: Admin tools for content management
- **Monetization**: Ad placement slots (banner, in-feed, sidebar)

## Database Schema (PostgreSQL)
Tables: users, profiles, jobs, applications, saved_jobs, messages, job_confirmations, ratings, flags, ads

Key differences from original MySQL schema:
- Uses SERIAL instead of AUTO_INCREMENT
- Uses VARCHAR with CHECK constraints instead of ENUM
- `profiles.user_id` has a UNIQUE constraint to support ON CONFLICT upserts
- `jobs` table has extra `pay_type` column
- `profiles` table has extra `age` column
- Case-insensitive search uses ILIKE instead of LIKE for title searches

## Server
- PHP built-in server on port 5000
- Workflow: "PHP Server" (`php -S 0.0.0.0:5000`)
