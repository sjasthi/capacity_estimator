# Capacity Estimator

A web application for estimating and managing team capacity within the Scaled Agile Framework. This tool helps Agile Release Trains (ARTs) plan iterations by tracking team availability, calculating capacity, and generating reports.

---

## Features

- **Iteration Planning** - Define and manage iterations across teams
- **Team Management** - Add and configure teams participating in the ART
- **Capacity Estimation** - Calculate available capacity per team per iteration
- **Import/Export** - Load team data in bulk and export results
- **Reports** - Generate capacity reports for planning sessions
- **Clean UI** - Simple interface with a shared header and footer layout

---

## Tech Stack

- **Backend:** PHP
- **Database:** MySQL (schema in `Cap_scheme.sql`)
- **Frontend:** HTML, CSS (`styles.css`)

---

## Getting Started

### Prerequisites

- PHP
- MySQL
- Web server (e.g., Apache, Nginx, PHP built in server)

### Installation

1. **Clone the repository**
2. **Set up the database**
3. **Configure the database connection**
4. **Run the app**
   - **Using Apache/Nginx:** Point your virtual host document root to the project folder
5. Open your browser and navigate to `https://localhost/filename`

---

## Project Structure

```
├── index.php              # App entry point
├── Cap_scheme.sql         # MySQL database schema
├── db.php                 # Database connection
├── Header.php             # Shared page header
├── Footer.html            # Shared page footer
├── Teams.php              # Team management
├── team.php               # Individual team view/edit
├── Iterations.php         # Iteration management
├── Capacity.php           # Capacity calculation logic
├── reports.php            # Reporting view
├── Export.php             # Data export functionality
├── import.php             # Data import functionality
├── art.php                # ART level view
├── arts.php               # ART listing
├── Test.php               # Testing utilities
├── styles.css             # Application styles
└── requirements_loud_thinking.txt  # Project requirements / notes
```

---

## Usage

1. **Set up your teams** via the Teams page
2. **Define iterations** for your Program Increment (PI)
3. **Enter availability** for each team member per iteration
4. **View capacity estimates** and adjust as needed
5. **Export reports** for use in PI planning sessions

---

## License

This project is licensed under the terms in the [LICENSE](LICENSE) file.
