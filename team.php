<?php
require 'db.php';
$db = db();
$teamId = intval($_GET['id']);

$team = $db->query("SELECT t.teamName, a.artId, a.artName FROM teams t JOIN arts a ON t.artId=a.artId WHERE t.teamId=$teamId")->fetch_assoc();
$iter = $db->query("SELECT iterationName FROM iterations ORDER BY endDate DESC LIMIT 1")->fetch_assoc()['iterationName'] ?? 'N/A';
$iterEscaped = $db->real_escape_string($iter);
$capacity = $db->query("SELECT storyPoints FROM capacities c JOIN iterations i ON c.iterationId=i.iterationId WHERE c.teamId=$teamId AND i.iterationName='$iterEscaped'")->fetch_assoc()['storyPoints'] ?? 0;

// Fetch all members into array first to avoid result conflicts
$membersResult = $db->query("SELECT p.name, p.email, tm.role, tm.allocationPct FROM team_members tm JOIN persons p ON tm.personId=p.personId WHERE tm.teamId=$teamId ORDER BY CASE tm.role WHEN 'Scrum Master' THEN 1 WHEN 'Product Owner' THEN 2 ELSE 3 END");
$members = [];
while ($row = $membersResult->fetch_assoc()) {
    $members[] = $row;
}

// Calculate from the already-fetched members array
$calc = 0;
$memberCount = count($members);
foreach ($members as $m) {
    $calc += 3.75 * ($m['allocationPct'] / 100);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($team['teamName']) ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>📊 Capacity</h2>
        </div>
        <nav>
            <a href="index.php" class="nav-item">
                <span>🏠</span>
                <span>Dashboard</span>
            </a>
            <a href="reports.php" class="nav-item">
                <span>📈</span>
                <span>Reports</span>
            </a>
            <a href="import.php" class="nav-item">
                <span>📤</span>
                <span>Import</span>
            </a>
        </nav>
    </div>

    <div class="main">
        <div class="header">
            <div>
                <a href="art.php?id=<?= $team['artId'] ?>" class="back">← <?= htmlspecialchars($team['artName']) ?></a>
                <h1><?= htmlspecialchars($team['teamName']) ?></h1>
            </div>
            <div class="user">Admin</div>
        </div>

        <div class="cards">
            <div class="card purple">
                <div class="card-icon">🔄</div>
                <div>
                    <div class="card-label">Iteration</div>
                    <div class="card-value"><?= htmlspecialchars($iter) ?></div>
                </div>
            </div>
            <div class="card blue">
                <div class="card-icon">👥</div>
                <div>
                    <div class="card-label">Members</div>
                    <div class="card-value"><?= $memberCount ?></div>
                </div>
            </div>
            <div class="card green">
                <div class="card-icon">⚡</div>
                <div>
                    <div class="card-label">Capacity</div>
                    <div class="card-value"><?= number_format($capacity, 0) ?> SP</div>
                </div>
            </div>
            <div class="card orange">
                <div class="card-icon">🎯</div>
                <div>
                    <div class="card-label">Calculated</div>
                    <div class="card-value"><?= number_format($calc, 2) ?> SP</div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="content-header">
                <h2>Team Members</h2>
                <input type="text" placeholder="Search..." class="search" id="memberSearch">
            </div>
            <table id="membersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Allocation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center; color:#888; padding:24px;">No members found for this team.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($members as $member):
                        $names = explode(' ', $member['name']);
                        $initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));
                        $roleClass = $member['role'] == 'Scrum Master' ? 'sm' : ($member['role'] == 'Product Owner' ? 'po' : 'dev');
                    ?>
                    <tr>
                        <td>
                            <div class="name">
                                <div class="avatar"><?= $initials ?></div>
                                <?= htmlspecialchars($member['name']) ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($member['email']) ?></td>
                        <td><span class="role <?= $roleClass ?>"><?= htmlspecialchars($member['role']) ?></span></td>
                        <td><?= $member['allocationPct'] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.getElementById('memberSearch').addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#membersTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>