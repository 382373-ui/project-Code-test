Product Requirements Document (PRD) — JobBridge (cleaned)
Product Name: JobBridge
 Target Users: High-school & college students (including minors), local businesses, organizations, and civilians posting jobs.
 Monetization: Ads — banner, in-feed, sidebar. Optional paid job boost feature (see Stretch Goals).

1. Summary / Overview
JobBridge is a student-focused job platform that connects students with:
Company / Store Jobs (local companies, shops, school-related roles)


Odd Jobs (individuals/civilians hiring help)


Volunteer Work


Internships


Core capabilities: user registration & authentication, basic profiles, job posting & browsing, saved jobs, in-app messaging with privacy guardrails, job confirmation workflow, ratings/reviews, admin moderation, and a resource hub for students.
Single release: This PRD describes a single, full-featured release.
Tech stack (high-level): PHP (server-side), HTML/CSS/JS + Bootstrap (UI), MySQL (all data storage). PHP sessions and secure authentication are required.

2. Goals
Provide a safe, privacy-conscious job marketplace for students.


Allow employers/civilians to post jobs and students to search, save, apply, and confirm completion.


Provide admin moderation tools to keep postings safe and appropriate.


Keep implementation simple and MySQL-driven for the course project.



3. Roles & User Stories
Roles
Guest: View landing page, browse jobs, register/login.


Registered User (Student): Browse/filter jobs, save jobs, apply/communicate, confirm completion, leave ratings/reviews, edit profile.


Employer / Civilian: Post jobs, manage posted jobs, communicate with applicants/workers, confirm job completion.


Admin: Moderate job postings and users, manage flagged content, edit Resource Hub.


Key User Stories (selected)
As a Guest, I can view the landing page and register for an account.


As a Student, I can browse and filter jobs by ZIP, type, pay, and date; save jobs for later; and apply.


As a Student, I can message job posters (DM), submit completion proof, and confirm a job is done.


As an Employer/Civilian, I can create a job post (category: company/odd/internship/volunteer), receive applications/DMs, and confirm completion.


As an Admin, I can approve/reject flagged posts and remove content that violates policy.



4. Pages & Navigation (file mappings)
Core account and site pages:
index.php — Landing page with search and featured categories.


register.php — User registration form (username, email, password, first & last name).


login.php — Login form.


logout.php — End session.


profile.php — View user profile (grade/year, skills, availability, jobs posted, ratings).


edit-profile.php — Edit profile info.


change-password.php — Change password.


jobs.php — Unified job listing page for Company jobs + Odd jobs (filters: ZIP, pay, type, date, category). Job posting form allows selecting category = company or odd.


volunteer.php — Volunteer job listing and posting page (filters: ZIP, date).


internship.php — Internship listing and posting page (separate workflow & application process).


saved-jobs.php — Bookmarked jobs.


messages.php — In-app DMs (messaging guardrails applied; see Features).


resource-hub.php — Student resources (resume templates, interview tips, verification letters).


admin.php — Admin dashboard for moderation and content management.


Navigation flow
Guests: landing/register/login.


Logged-in users: pages and features per role.


Employers/Civilians: job posting tools (create/edit/delete).


Admin: moderation & analytics tools.



5. Data Model (MySQL)
Important: All data stored in MySQL. No JSON files.
Tables (suggested columns):
users


id (PK), username, email, password_hash, role (student/employer/admin), created_at, is_verified


profiles


id (PK), user_id (FK), first_name, last_name, profile_img, grade_year, skills (text), availability, location_radius, bio, updated_at


jobs


id (PK), poster_user_id (FK), title, description, category ENUM('company','odd','volunteer','internship'), pay (nullable), zip_code, date_posted, date_needed (nullable), location_details, is_active, verified_flag, attachments (file refs)


applications


id, job_id (FK), applicant_user_id (FK), cover_text, resume_file_ref, status (applied/accepted/rejected), applied_at


saved_jobs


id, user_id (FK), job_id (FK), created_at


messages


id, sender_id (FK), receiver_id (FK), job_id (nullable FK), content, created_at, is_read


job_confirmations


id, job_id (FK), worker_user_id (FK), poster_confirmed (bool), worker_confirmed (bool), proof_text, proof_file_ref, confirmed_at


ratings


id, reviewer_id (FK), reviewed_user_id (FK), job_id (FK), rating (1–5), comment, created_at


flags


id, item_type (job/user/message), item_id, reported_by, reason, created_at, status


ads (if needed)


id, placement, html_content, start_date, end_date


Notes:
Use foreign keys where appropriate.


Indexes on jobs(zip_code), jobs(category), jobs(date_posted), and users(username,email).



6. Features (detailed)
Authentication & Sessions
PHP sessions and secure login/logout.


Passwords hashed (bcrypt/argon2).


Password policy: minimum 8 chars, at least 1 uppercase, 1 lowercase, 1 number, and 1 special char recommended.


Email verification & password reset flows (implemented in initial release if time allows).


Profiles
Editable profile fields: name, grade/year, skills, availability, profile image.


Display average rating on profile.


Jobs: Posting & Listing
jobs.php supports posting/listing for Company and Odd job categories.


volunteer.php supports posting/listing for volunteer roles.


internship.php supports internships (application-based; allow richer descriptions and resume upload).


Job posting form fields: title, description, category, pay (optional), ZIP, date needed, attachments.


Filters: ZIP, pay range, category, date posted, verified badge.


Applications & Messaging
Messaging guardrails: in-app DMs tied to a job. Phone/email hidden until mutual consent or after both parties confirm (to protect minors).


Messaging enabled for jobs in categories: Odd, Volunteer, Internship, and Company where poster allows messaging.


Resume uploads allowed (PDFs only).


Job Completion & Confirmation
Worker submits completion proof (text/photo). Poster and worker both confirm via job_confirmations.


Payment is arranged outside the platform (platform does not take a job-completion fee). (You asked to remove any platform fee.)


Ratings & Reviews
After completion, both parties may leave a 1–5 star rating and a comment.


Average rating displayed on profile.


Admin & Moderation
Admin tools: review flagged jobs/users/messages, remove content, suspend users, and moderate Resource Hub.


Basic analytics: counts of users, jobs posted, flagged items.


Resource Hub
Student-facing resources: resume templates, interview tips, volunteer verification letters.



7. Non-Functional Requirements
Privacy-first. Minimal personal data. Phone/email hidden by default.


Security. Password hashing, input validation, prepared statements to prevent SQL injection, file-type checks for uploads.


Scalability. Designed with normalized MySQL schema and indexed queries.


Accessibility. Basic a11y and responsive UI with Bootstrap.



8. Success Metrics
Number of student signups.


Jobs posted.


Jobs marked completed (confirmed by both parties).


Resource Hub engagement.


Ad revenue (if running live).



9. Stretch Goals / Optional Paid Features
Optional Job Boost: paid feature that pins a job to the top for a configurable period (example: $5 for 1 week). (Optional — not required for initial implementation.)


Note: No platform fee on job completion. (Per your instruction, the 10% fee has been removed.)



10. Security & Validation
Unique username/email enforced.


Use prepared statements for all DB queries.


Server-side and client-side input validation.


File upload sanitization & size limits.


Reporting & moderation flow for abuse/scams.



11. Technical Implementation Notes & File Structure (recommended)
project/
├── index.php
├── register.php
├── login.php
├── logout.php
├── profile.php
├── edit-profile.php
├── change-password.php
├── jobs.php            # Company + Odd jobs (listing + posting)
├── volunteer.php       # Volunteer listing + posting
├── internship.php      # Internship listing + posting
├── saved-jobs.php
├── messages.php
├── resource-hub.php
├── admin.php
├── includes/
│   ├── config.php         # DB credentials, constants
│   ├── db.php             # MySQL connection helper (PDO)
│   ├── auth.php           # session helpers, auth checks
│   ├── functions.php      # shared helpers
│   └── upload_helpers.php
├── public/
│   ├── css/
│   ├── js/
│   └── images/
└── uploads/

Database only: Remove JSON files. And use MySQL.



12. Implementation notes for jobs.php vs volunteer.php vs internship.php
jobs.php:


Must support listing Company and Odd jobs.


Filter and search UI must allow narrowing by category (company or odd) as well as ZIP/pay/date.


Posting form: allow poster to choose category company or odd.


volunteer.php:


Volunteer-specific UI and verification letter generation.


Messaging tied to volunteer posts (DMs).


internship.php:


Internship-specific flow: application objects, resume upload, ability for employers to review applicants and mark accepted candidate(s).


Messaging between applicants and employer enabled.


messages.php:


Generic messaging hub for user-to-user DMs.


Messages tied to jobs when appropriate.


