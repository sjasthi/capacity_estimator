<?php
require 'db.php';
$db = db();

// Handle CSV download
if (isset($_POST['export'])) {
    $type    = $_POST['type'];
    $artId   = intval($_POST['artId']  ?? 0);
    $teamId  = intval($_POST['teamId'] ?? 0);
    $period  = intval($_POST['period'] ?? 6);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="capacityhub_export_' . $type . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');

    if ($type == 'capacity') {
        fputcsv($out, ['ART Name', 'Team Name', 'Program Increment', 'Iteration', 'Start Date', 'End Date', 'Capacity (SP)', 'Submitted At']);

        $where = [];
        if ($artId)  $where[] = "a.artId = $artId";
        if ($teamId) $where[] = "t.teamId = $teamId";
        $whereSql = $where ? 'AND ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT a.artName, t.teamName, pi.piName, i.iterationName,
                   i.startDate, i.endDate, c.storyPoints, c.createdAt
            FROM capacities c
            JOIN teams t       ON c.teamId      = t.teamId
            JOIN arts a        ON t.artId        = a.artId
            JOIN iterations i  ON c.iterationId  = i.iterationId
            JOIN program_increments pi ON i.piId = pi.piId
            WHERE 1=1 $whereSql
            ORDER BY i.startDate DESC, a.artName, t.teamName
            LIMIT " . ($period * 100) . "
        ";
        $result = $db->query($sql);
        while ($row = $result->fetch_assoc()) {
            fputcsv($out, [
                $row['artName'], $row['teamName'], $row['piName'],
                $row['iterationName'], $row['startDate'], $row['endDate'],
                $row['storyPoints'], $row['createdAt']
            ]);
        }

    } elseif ($type == 'composition') {
        fputcsv($out, ['ART Name', 'Scrum Team Name', 'Team Member Name', 'Team Member Email', 'Role', 'Allocation %']);

        $where = $artId ? "WHERE a.artId = $artId" : '';
        $sql = "
            SELECT a.artName, t.teamName, p.name, p.email, tm.role, tm.allocationPct
            FROM team_members tm
            JOIN teams t   ON tm.teamId   = t.teamId
            JOIN arts a    ON t.artId     = a.artId
            JOIN persons p ON tm.personId = p.personId
            $where
            ORDER BY a.artName, t.teamName, tm.role, p.name
        ";
        $result = $db->query($sql);
        while ($row = $result->fetch_assoc()) {
            fputcsv($out, [
                $row['artName'], $row['teamName'], $row['name'],
                $row['email'], $row['role'], $row['allocationPct']
            ]);
        }

    } elseif ($type == 'iterations') {
        fputcsv($out, ['ART Name', 'Program Increment', 'Iteration', 'Iteration Start Date', 'Iteration End Date']);

        $where = $artId ? "WHERE a.artId = $artId" : '';
        $sql = "
            SELECT a.artName, pi.piName, i.iterationName, i.startDate, i.endDate
            FROM iterations i
            JOIN program_increments pi ON i.piId  = pi.piId
            JOIN arts a                ON pi.artId = a.artId
            $where
            ORDER BY a.artName, i.startDate
        ";
        $result = $db->query($sql);
        while ($row = $result->fetch_assoc()) {
            fputcsv($out, [
                $row['artName'], $row['piName'], $row['iterationName'],
                $row['startDate'], $row['endDate']
            ]);
        }
    }

    fclose($out);
    exit;
}

// Load filter data
$arts    = $db->query("SELECT artId, artName FROM arts ORDER BY artName");
$allArts = [];
while ($r = $arts->fetch_assoc()) $allArts[] = $r;

$selectedArtId = intval($_GET['artId'] ?? 0);
$teams = [];
if ($selectedArtId) {
    $res = $db->query("SELECT teamId, teamName FROM teams WHERE artId=$selectedArtId ORDER BY teamName");
    while ($r = $res->fetch_assoc()) $teams[] = $r;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Export - CapacityHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .export-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .export-card {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 28px 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }
        .export-card:hover {
            border-color: #6366f1;
            box-shadow: 0 4px 12px rgba(99,102,241,0.12);
        }
        .export-card.selected {
            border-color: #6366f1;
            background: #f5f3ff;
        }
        .export-card i {
            font-size: 36px;
            margin-bottom: 14px;
            display: block;
        }
        .export-card h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #111827;
        }
        .export-card p {
            font-size: 13px;
            color: #6b7280;
        }
        .card-capacity i { color: #10b981; }
        .card-composition i { color: #6366f1; }
        .card-iterations i { color: #f59e0b; }
        .filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-group select {
            padding: 10px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            color: #111827;
            background: white;
            width: 100%;
        }
        .filter-group select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .filter-group select:disabled {
            background: #f9fafb;
            color: #9ca3af;
        }
        .export-btn-wrap {
            display: flex;
            justify-content: flex-end;
            margin-top: 8px;
        }
        .btn-export-dl {
            padding: 12px 32px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-export-dl:hover { background: #5558e3; }
        .info-note {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px 18px;
            font-size: 13px;
            color: #6b7280;
            margin-top: 20px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand">
            <div class="brand-logo"><i class="fas fa-chart-line"></i></div>
            CapacityHub
        </div>
                        <div class="nav-tabs">
            <a href="index.php"      class="nav-tab">Dashboard</a>
            <a href="arts.php"       class="nav-tab">ARTs</a>
            <a href="teams.php"      class="nav-tab">Teams</a>
            <a href="iterations.php" class="nav-tab">Iterations</a>
            <a href="capacity.php"   class="nav-tab">Capacity Entry</a>
            <a href="reports.php"    class="nav-tab">Reports</a>
            <a href="import.php"     class="nav-tab">Import</a>
            <a href="export.php"     class="nav-tab">Export</a>
            <a href="test.php"       class="nav-tab">Test</a>
        </div>
        <div class="user-menu">
            <div class="notification-icon"><i class="far fa-bell"></i></div>
            <div class="user-avatar">AD</div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Export Data</h1>
            <p class="page-subtitle">Download capacity data as CSV files</p>
        </div>

        <form method="POST" action="export.php">

            <!-- Step 1: Choose export type -->
            <div class="data-panel" style="margin-bottom:24px;">
                <div class="panel-header">
                    <h2 class="panel-title">Step 1 &mdash; Choose Export Type</h2>
                </div>
                <div style="padding:24px 28px;">
                    <div class="export-grid">
                        <label class="export-card card-capacity">
                            <input type="radio" name="type" value="capacity" style="display:none;" required>
                            <i class="fas fa-bolt"></i>
                            <h3>Capacity Data</h3>
                            <p>Story points per team per iteration, with submission timestamps</p>
                        </label>
                        <label class="export-card card-composition">
                            <input type="radio" name="type" value="composition" style="display:none;">
                            <i class="fas fa-users"></i>
                            <h3>Team Composition</h3>
                            <p>All team members, roles, and allocation percentages</p>
                        </label>
                        <label class="export-card card-iterations">
                            <input type="radio" name="type" value="iterations" style="display:none;">
                            <i class="fas fa-calendar-alt"></i>
                            <h3>Iteration Schedule</h3>
                            <p>All PIs and iterations with their start and end dates</p>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Step 2: Filters -->
            <div class="data-panel" style="margin-bottom:24px;">
                <div class="panel-header">
                    <h2 class="panel-title">Step 2 &mdash; Filter (Optional)</h2>
                </div>
                <div style="padding:24px 28px;">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label><i class="fas fa-rocket" style="margin-right:5px;"></i>ART</label>
                            <select name="artId" onchange="this.form.action='export.php?artId='+this.value; this.form.submit();">
                                <option value="">All ARTs</option>
                                <?php foreach ($allArts as $art): ?>
                                <option value="<?= $art['artId'] ?>" <?= $selectedArtId == $art['artId'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($art['artName']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-users" style="margin-right:5px;"></i>Team</label>
                            <select name="teamId" <?= !$teams ? 'disabled' : '' ?>>
                                <option value="">All Teams</option>
                                <?php foreach ($teams as $team): ?>
                                <option value="<?= $team['teamId'] ?>"><?= htmlspecialchars($team['teamName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-sync-alt" style="margin-right:5px;"></i>Period (Capacity export)</label>
                            <select name="period">
                                <option value="6">Last 6 iterations</option>
                                <option value="12">Last 12 iterations</option>
                                <option value="24">Last 24 iterations</option>
                                <option value="9999">All time</option>
                            </select>
                        </div>
                    </div>

                    <div class="export-btn-wrap">
                        <button type="submit" name="export" value="1" class="btn-export-dl">
                            <i class="fas fa-download"></i> Download CSV
                        </button>
                    </div>

                    <div class="info-note">
                        <i class="fas fa-info-circle" style="color:#6366f1;margin-top:2px;"></i>
                        <span>
                            The exported CSV format matches the import format exactly, so you can use exports as templates or for data migration.
                            Team filter only applies to <strong>Capacity</strong> exports.
                        </span>
                    </div>
                </div>
            </div>

        </form>
    </div>

    <script>
        // Highlight selected export card
        document.querySelectorAll('.export-card').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.export-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
            });
        });
        // Pre-select first card
        document.querySelector('.export-card').classList.add('selected');
    </script>
</body>
</html>