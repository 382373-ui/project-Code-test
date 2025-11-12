# JobBridge

A student-focused job platform connecting students with company jobs, odd jobs, volunteer work, and internships.

## File Structure

```
project/
├── index.php              # Landing page
├── register.php           # User registration
├── login.php              # Login
├── logout.php             # Logout
├── profile.php            # User profile
├── edit-profile.php       # Edit profile
├── change-password.php    # Change password
├── jobs.php               # Company + Odd jobs
├── volunteer.php          # Volunteer opportunities
├── internship.php         # Internships
├── saved-jobs.php         # Saved jobs
├── messages.php           # In-app messaging
├── resource-hub.php       # Student resources
├── admin.php              # Admin dashboard
├── includes/
│   ├── config.php         # Configuration
│   ├── db.php             # Database connection
│   ├── auth.php           # Authentication
│   ├── functions.php      # Helper functions
│   ├── header.php         # Navigation header
│   └── upload_helpers.php # File upload utilities
├── public/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── main.js
│   └── images/
├── uploads/
│   ├── profiles/
│   ├── resumes/
│   ├── attachments/
│   └── proofs/
└── database/
    └── schema.sql         # Database schema
```

## Tech Stack

- **Backend**: PHP (with sessions)
- **Frontend**: HTML, CSS, JavaScript, Bootstrap 5
- **Database**: MySQL
- **Server**: PHP built-in server

## Setup Instructions

1. Import the database schema from `database/schema.sql`
2. Update database credentials in `includes/config.php`
3. Start the PHP server
4. Navigate to the application in your browser
