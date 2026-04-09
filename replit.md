# JobBridge Project

## Overview
JobBridge is a student-focused job platform built with PHP and MySQL that connects students with:
- Company/Store Jobs
- Odd Jobs (individuals/civilians hiring help)
- Volunteer Work
- Internships

## Current State
- **Status**: File structure setup complete
- **Tech Stack**: PHP, MySQL, Bootstrap 5, HTML/CSS/JavaScript
- **Last Updated**: November 12, 2025

## Project Structure
Complete file structure has been set up according to PRD specifications:
- Core pages: index, register, login, profile, jobs, volunteer, internship, messages, admin
- Helper files in `includes/` directory
- Static assets in `public/` directory
- File uploads organized in `uploads/` subdirectories
- Database schema defined in `database/schema.sql`

## User Preferences
- Placeholder files created without full implementation
- Waiting for user to implement code functionality

## Architecture
- **Authentication**: PHP sessions with bcrypt password hashing
- **Database**: MySQL with normalized schema (10 tables)
- **Privacy**: Contact info hidden by default, messaging guardrails for minors
- **Moderation**: Admin tools for content management
- **Monetization**: Ad placement slots (banner, in-feed, sidebar)

## Next Steps
- Implement page functionality based on PRD requirements
- Set up MySQL database and import schema
- Build out authentication system
- Create job posting and browsing features
- Implement messaging system with privacy controls
