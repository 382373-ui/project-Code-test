# JobBridge Project

## Overview
JobBridge is a student-focused job platform built with PHP and MySQL that connects students with:
- Company/Store Jobs
- Odd Jobs (individuals/civilians hiring help)
- Volunteer Work
- Internships

## Current State
- **Status**: Running on Replit — fully migrated
- **Tech Stack**: PHP 8.2, MySQL (external Hostinger), Bootstrap 5, HTML/CSS/JavaScript
- **Last Updated**: April 2025

## Project Structure
- Core pages: index, register, login, profile, jobs, volunteer, internship, messages, admin, chat, resource-hub, saved-jobs
- Helper files in `includes/` directory (db.php, config.php, auth helpers)
- Static assets in `public/` directory
- File uploads organized in `uploads/` subdirectories
- Database schema defined in `database/schema.sql`

## Architecture
- **Authentication**: PHP sessions with bcrypt password hashing
- **Database**: External MySQL (Hostinger) via PDO with prepared statements
- **Database Credentials**: Stored securely as Replit environment secrets (DB_HOST, DB_NAME, DB_USER, DB_PASS)
- **Privacy**: Contact info hidden by default, messaging guardrails for minors
- **Moderation**: Admin tools for content management
- **Monetization**: Ad placement slots (banner, in-feed, sidebar)

## Workflow
- **Name**: Start application
- **Command**: `php -S 0.0.0.0:5000 -t .`
- **Port**: 5000

## Security Notes
- Database credentials are NOT hardcoded — they are read from environment secrets via `getenv()`
- `includes/config.php` reads DB_HOST, DB_NAME, DB_USER, DB_PASS from environment
