# Capacity Estimator
 
A PHP/MySQL web application for ART capacity planning within the Scaled Agile Framework. This document covers architecture, local setup, and contribution guidelines.
 
---
 
## Tech Stack
 
| Layer | Technology |
|---|---|
| Backend | PHP |
| Database | MySQL |
| Frontend | HTML, CSS (`styles.css`) |
| Schema | `Cap_scheme.sql` |
 
---
 
## Project Structure
 
```
├── index.php              # App entry point
├── Cap_scheme.sql         # MySQL database schema
├── db.php                 # Database connection config
├── Header.php             # Shared page header component
├── Footer.html            # Shared page footer component
│
├── arts.php               # ART listing view
├── art.php                # ART detail view
├── Teams.php              # Team management (list/create)
├── team.php               # Individual team view/edit
├── Iterations.php         # Iteration management
├── Capacity.php           # Capacity calculation logic
├── reports.php            # Reporting view
├── Export.php             # Data export functionality
├── import.php             # Data import functionality
│
├── styles.css             # Global application styles
├── Test.php               # Testing utilities
└── requirements_loud_thinking.txt  # Project requirements and notes
```
 
---
 
## Local Setup
 
### Prerequisites
 
- PHP (check `requirements_loud_thinking.txt` for any version constraints)
- MySQL
- A web server: Apache, Nginx, or PHP's built-in server
### Steps
 
1. **Clone the repository**
   ```bash
   git clone <repo-url>
   cd capacity-estimator
   ```
 
2. **Create the database**
   ```bash
   mysql -u root -p
   CREATE DATABASE capacity_estimator;
   EXIT;
   ```
 
3. **Import the schema**
   ```bash
   mysql -u root -p capacity_estimator < Cap_scheme.sql
   ```
 
4. **Configure the database connection**
   Open `db.php` and update the credentials:
   ```php
   $host = 'localhost';
   $db   = 'capacity_estimator';
   $user = 'your_user';
   $pass = 'your_password';
   ```
 
5. **Start the app**
   Using PHP's built-in server:
   ```bash
   php -S localhost:8000
   ```
 
   Or point your Apache/Nginx virtual host's document root to the project folder.
6. Open `http://localhost:8000` in your browser.
---
 
## Architecture Notes
 
### Request Flow
 
All requests enter through `index.php`. Individual pages (e.g. `Teams.php`, `Capacity.php`) handle their own logic and rendering. There is no MVC framework each page file is self-contained, querying the database via `db.php` and rendering HTML directly.
 
### Shared Layout

`Header.php` and `Footer.html` are included on each page to provide consistent navigation and page structure.
 
### Capacity Logic
 
Core calculation logic lives in `Capacity.php`. This is the right place to look when modifying how available capacity is computed from team member availability data.
 
### Data Flow
 
- Teams and ARTs are set up first (master data)
- Iterations are defined per ART
- Availability is entered per team member per iteration
- Capacity figures are derived from availability inputs
- Reports and exports aggregate this data for output
---
 
## Database
 
The full schema is in `Cap_scheme.sql`. Import it fresh when setting up a new environment. If you make schema changes, update this file and document the migration steps here.
 
---
 
## Testing
 
`Test.php` contains testing utilities. Review this file before adding new features to understand what test coverage exists and how to run checks locally.
 
---
 
## Contributing
 
1. Branch from `main` for any new work
2. Follow the existing file-per-page pattern for new views
3. Keep database queries in the relevant page file or a shared helper — avoid scattering raw SQL
4. Update `Cap_scheme.sql` if you change the database schema
5. Test against a clean database import before opening a PR
---
 
## Deployment
 
Point your web server's document root to the project root. Ensure the database credentials in `db.php` match your production environment. No build step required.
 
---
 
## License
 
Licensed under the terms in the [LICENSE](LICENSE) file.
