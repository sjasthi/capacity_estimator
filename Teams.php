<?php
require 'db.php';
$db = db();
// === Inline Link Tester ===
$testRan = false;
$testResults = [];
$pass = 0;
$fail = 0;
$closeUrl = '';
$testLink = '';

$testParams = $_GET;
$testParams['test'] = '1';
$testLink = basename($_SERVER['PHP_SELF']) . '?' . http_build_query($testParams);

if (isset($_GET['test']) && $_GET['test'] == '1') {
    $testRan = true;
    $closeParams = $_GET;
    unset($closeParams['test']);
    $closeUrl = basename($_SERVER['PHP_SELF']);
    if (!empty($closeParams)) {
        $closeUrl .= '?' . http_build_query($closeParams);
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']) . '/' . basename($_SERVER['PHP_SELF']);
    $fetchParams = $_GET;
    unset($fetchParams['test']);
    $fetchUrl = $scheme . '://' . $host . $path;
    if (!empty($fetchParams)) {
        $fetchUrl .= '?' . http_build_query($fetchParams);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fetchUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $html = curl_exec($ch);
    curl_close($ch);
    $links = [];
    if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
        $links = array_unique($matches[1]);
    }
    $baseUrl = $scheme . '://' . $host . dirname($_SERVER['PHP_SELF']) . '/';
    foreach ($links as $link) {
        if (preg_match('/^(https?:\/\/|#|mailto:|javascript:|tel:)/i', $link)) continue;
        if (strpos($link, 'test=') !== false) continue;
        if (empty(trim($link))) continue;
        $checkUrl = $baseUrl . $link;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $checkUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ok = ($httpCode >= 200 && $httpCode < 400);
        if ($ok) { $pass++; } else { $fail++; }
        $testResults[] = array('link' => $link, 'code' => $httpCode, 'ok' => $ok);
    }
}
// === End Inline Link Tester ===


$message = '';
$messageType = '';

// Handle Add Team
if (isset($_POST['add_team'])) {
    $name  = trim($_POST['teamName'] ?? '');
    $artId = intval($_POST['artId'] ?? 0);
    if ($name !== '' && $artId) {
        $stmt = $db->prepare("INSERT INTO teams (teamName, artId) VALUES (?,?)");
        $stmt->bind_param("si", $name, $artId);
        if ($stmt->execute()) {
            $message = "Team \"$name\" added successfully.";
            $messageType = 'success';
        } else {
            $message = "Error adding team.";
            $messageType = 'error';
        }
    }
}

// Handle Edit Team
if (isset($_POST['edit_team'])) {
    $id    = intval($_POST['teamId']);
    $name  = trim($_POST['teamName'] ?? '');
    $artId = intval($_POST['artId'] ?? 0);
    if ($id && $name !== '' && $artId) {
        $stmt = $db->prepare("UPDATE teams SET teamName=?, artId=? WHERE teamId=?");
        $stmt->bind_param("sii", $name, $artId, $id);
        if ($stmt->execute()) {
            $message = "Team updated.";
            $messageType = 'success';
        } else {
            $message = "Error updating team.";
            $messageType = 'error';
        }
    }
}

// Handle Delete Team
if (isset($_POST['delete_team'])) {
    $id = intval($_POST['teamId']);
    if ($id) {
        $stmt = $db->prepare("DELETE FROM teams WHERE teamId=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Team deleted.";
            $messageType = 'success';
        }
    }
}

// Handle Add Member
if (isset($_POST['add_member'])) {
    $teamId    = intval($_POST['mem_teamId']);
    $name      = trim($_POST['mem_name'] ?? '');
    $email     = trim($_POST['mem_email'] ?? '');
    $role      = trim($_POST['mem_role'] ?? '');
    $allocPct  = intval($_POST['mem_alloc'] ?? 100);
    if ($teamId && $name && $email && $role) {
        // Upsert person
        $stmt = $db->prepare("SELECT personId FROM persons WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $personId = $res->fetch_assoc()['personId'];
        } else {
            $stmt = $db->prepare("INSERT INTO persons (name, email) VALUES (?,?)");
            $stmt->bind_param("ss", $name, $email);
            $stmt->execute();
            $personId = $db->insert_id;
        }
        $stmt = $db->prepare("INSERT INTO team_members (teamId, personId, role, allocationPct) VALUES (?,?,?,?)");
        $stmt->bind_param("iisi", $teamId, $personId, $role, $allocPct);
        if ($stmt->execute()) {
            $message = "Member added.";
            $messageType = 'success';
        } else {
            $message = "Error adding member.";
            $messageType = 'error';
        }
    }
}

// Handle Edit Member
if (isset($_POST['edit_member'])) {
    $tmId     = intval($_POST['tmId']);
    $name     = trim($_POST['mem_name'] ?? '');
    $email    = trim($_POST['mem_email'] ?? '');
    $role     = trim($_POST['mem_role'] ?? '');
    $allocPct = intval($_POST['mem_alloc'] ?? 100);
    if ($tmId) {
        // Get personId
        $res = $db->query("SELECT personId FROM team_members WHERE teamMemberId=$tmId");
        $personId = $res->fetch_assoc()['personId'];
        $stmt = $db->prepare("UPDATE persons SET name=?, email=? WHERE personId=?");
        $stmt->bind_param("ssi", $name, $email, $personId);
        $stmt->execute();
        $stmt = $db->prepare("UPDATE team_members SET role=?, allocationPct=? WHERE teamMemberId=?");
        $stmt->bind_param("sii", $role, $allocPct, $tmId);
        if ($stmt->execute()) {
            $message = "Member updated.";
            $messageType = 'success';
        } else {
            $message = "Error updating member.";
            $messageType = 'error';
        }
    }
}

// Handle Delete Member
if (isset($_POST['delete_member'])) {
    $tmId = intval($_POST['tmId']);
    if ($tmId) {
        $stmt = $db->prepare("DELETE FROM team_members WHERE teamMemberId=?");
        $stmt->bind_param("i", $tmId);
        if ($stmt->execute()) {
            $message = "Member removed.";
            $messageType = 'success';
        }
    }
}

// Filter
$filterArtId  = intval($_GET['artId'] ?? 0);
$expandTeamId = intval($_GET['expand'] ?? 0);
$editTeamId   = intval($_GET['editTeam'] ?? 0);
$editMemberId = intval($_GET['editMember'] ?? 0);

// Load ARTs
$allArts = $db->query("SELECT artId, artName FROM arts ORDER BY artName");

// Load Teams
$whereSql = $filterArtId ? "WHERE t.artId=$filterArtId" : "";
$teams = $db->query("
    SELECT t.teamId, t.teamName, a.artId, a.artName,
           COUNT(tm.teamMemberId) as memberCount
    FROM teams t
    JOIN arts a ON t.artId = a.artId
    LEFT JOIN team_members tm ON t.teamId = tm.teamId
    $whereSql
    GROUP BY t.teamId, t.teamName, a.artId, a.artName
    ORDER BY a.artName, t.teamName
");

// Load members for expanded team
$expandedMembers = [];
if ($expandTeamId) {
    $res = $db->query("
        SELECT tm.teamMemberId, p.name, p.email, tm.role, tm.allocationPct
        FROM team_members tm
        JOIN persons p ON tm.personId = p.personId
        WHERE tm.teamId = $expandTeamId
        ORDER BY CASE tm.role WHEN 'Scrum Master' THEN 1 WHEN 'Product Owner' THEN 2 ELSE 3 END, p.name
    ");
    while ($r = $res->fetch_assoc()) $expandedMembers[] = $r;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Teams - CapacityHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .inline-form { display: inline; }
        .btn-sm {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-edit   { background: #eff6ff; color: #1e40af; }
        .btn-edit:hover { background: #dbeafe; }
        .btn-danger { background: #fef2f2; color: #991b1b; }
        .btn-danger:hover { background: #fee2e2; }
        .btn-save   { background: #6366f1; color: white; }
        .btn-save:hover { background: #5558e3; }
        .btn-cancel { background: #f3f4f6; color: #374151; }
        .btn-cancel:hover { background: #e5e7eb; }
        .btn-green  { background: #d1fae5; color: #065f46; }
        .btn-green:hover { background: #a7f3d0; }
        .inline-edit-input, .inline-select {
            padding: 7px 12px;
            border: 1px solid #6366f1;
            border-radius: 6px;
            font-size: 13px;
            color: #111827;
            outline: none;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .inline-edit-input { width: 180px; }
        .inline-select     { width: 160px; background: white; }
        .add-panel {
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 18px 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .add-panel input[type="text"] {
            padding: 9px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            width: 220px;
        }
        .add-panel input[type="text"]:focus, .add-panel select:focus {
            outline: none; border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .add-panel select {
            padding: 9px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            width: 180px;
        }
        .members-subpanel {
            background: #f9fafb;
            border-top: 1px solid #f3f4f6;
        }
        .members-subpanel table thead { background: #f0f0ff; }
        .members-subpanel table th { font-size: 11px; padding: 10px 20px; }
        .members-subpanel table td { padding: 12px 20px; }
        .expand-row { background: #f0f0ff; }
        .expand-row td { padding: 10px 28px; }
        .error-message {
            padding: 12px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .filter-bar {
            padding: 20px 28px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .filter-bar label { font-size: 13px; font-weight: 600; color: #6b7280; }
        .filter-bar select {
            padding: 8px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            outline: none;
        }
        .filter-bar select:focus { border-color: #6366f1; }
        .alloc-input {
            width: 75px;
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 13px;
            text-align: center;
        }
        .alloc-input:focus { outline: none; border-color: #6366f1; }
            .test-panel { background: #fefce8; border: 2px solid #facc15; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; }
        .test-panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .test-panel-title { font-size: 16px; font-weight: 700; color: #854d0e; display: flex; align-items: center; gap: 8px; }
        .test-panel-close { background: #fef3c7; color: #92400e; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .test-panel-close:hover { background: #fde68a; }
        .test-summary { display: flex; gap: 16px; margin-bottom: 16px; }
        .test-stat { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; }
        .test-stat.pass { background: #dcfce7; color: #166534; }
        .test-stat.fail { background: #fee2e2; color: #991b1b; }
        .test-results { max-height: 300px; overflow-y: auto; background: white; border: 1px solid #e5e7eb; border-radius: 8px; }
        .test-result-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; border-bottom: 1px solid #f3f4f6; font-size: 13px; }
        .test-result-row:last-child { border-bottom: none; }
        .test-result-link { color: #374151; font-family: monospace; word-break: break-all; }
        .test-result-status { padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; white-space: nowrap; }
        .test-result-status.ok { background: #dcfce7; color: #166534; }
        .test-result-status.error { background: #fee2e2; color: #991b1b; }
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
            <a href="<?= htmlspecialchars($testLink) ?>" class="nav-tab" style="color: #8b5cf6;"><i class="fas fa-flask"></i> Test</a>
            </div>
        <div class="user-menu">
            <div class="notification-icon"><i class="far fa-bell"></i></div>
            <div class="user-avatar">AD</div>
        </div>
    </div>

    <div class="container">

        <?php if ($testRan): ?>
        <div class="test-panel">
            <div class="test-panel-header">
                <div class="test-panel-title"><i class="fas fa-flask"></i> Link Test Results</div>
                <a href="<?= htmlspecialchars($closeUrl) ?>" class="test-panel-close"><i class="fas fa-times"></i> Close</a>
            </div>
            <div class="test-summary">
                <div class="test-stat pass"><i class="fas fa-check-circle"></i> <?= $pass ?> Passed</div>
                <div class="test-stat fail"><i class="fas fa-times-circle"></i> <?= $fail ?> Failed</div>
            </div>
            <div class="test-results">
                <?php foreach ($testResults as $result): ?>
                <div class="test-result-row">
                    <span class="test-result-link"><?= htmlspecialchars($result['link']) ?></span>
                    <span class="test-result-status <?= $result['ok'] ? 'ok' : 'error' ?>"><?= $result['ok'] ? '+ ' . $result['code'] : 'x ' . $result['code'] ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($testResults)): ?>
                <div class="test-result-row"><span style="color: #6b7280;">No internal links found to test.</span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>


        <div class="page-header">
            <h1 class="page-title">Teams</h1>
            <p class="page-subtitle">Manage scrum teams and their members</p>
        </div>

        <?php if ($message): ?>
        <div class="<?= $messageType === 'success' ? 'success-message' : 'error-message' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="data-panel">
            <!-- Filter bar -->
            <form method="GET" action="teams.php">
                <div class="filter-bar">
                    <label><i class="fas fa-filter" style="margin-right:5px;"></i>Filter by ART:</label>
                    <select name="artId" onchange="this.form.submit()">
                        <option value="">All ARTs</option>
                        <?php $allArts->data_seek(0); while ($a = $allArts->fetch_assoc()): ?>
                        <option value="<?= $a['artId'] ?>" <?= $filterArtId == $a['artId'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['artName']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>

            <div class="panel-header" style="border-top: 1px solid #e5e7eb;">
                <h2 class="panel-title">All Teams</h2>
                <span style="font-size:13px;color:#6b7280;"><?= $teams->num_rows ?> total</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>ART</th>
                        <th>Members</th>
                        <th style="width:240px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $teams->data_seek(0); while ($team = $teams->fetch_assoc()):
                    $isExpanded = ($expandTeamId == $team['teamId']);
                    $isEditing  = ($editTeamId   == $team['teamId']);
                ?>
                    <tr>
                    <?php if ($isEditing): ?>
                        <td colspan="3">
                            <form method="POST" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <input type="hidden" name="teamId" value="<?= $team['teamId'] ?>">
                                <input class="inline-edit-input" type="text" name="teamName" value="<?= htmlspecialchars($team['teamName']) ?>" required autofocus>
                                <select class="inline-select" name="artId" required>
                                    <?php $allArts->data_seek(0); while ($a = $allArts->fetch_assoc()): ?>
                                    <option value="<?= $a['artId'] ?>" <?= $team['artId'] == $a['artId'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($a['artName']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                                <button type="submit" name="edit_team" class="btn-sm btn-save"><i class="fas fa-check"></i> Save</button>
                                <a href="teams.php?<?= $filterArtId ? 'artId='.$filterArtId : '' ?>" class="btn-sm btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                            </form>
                        </td>
                        <td></td>
                    <?php else: ?>
                        <td>
                            <div class="team-info">
                                <div class="team-logo"><i class="fas fa-users"></i></div>
                                <div class="team-details">
                                    <h4><?= htmlspecialchars($team['teamName']) ?></h4>
                                    <p>Scrum Team</p>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:13px;color:#6b7280;"><?= htmlspecialchars($team['artName']) ?></td>
                        <td><span style="font-size:13px;color:#6b7280;"><i class="fas fa-user" style="margin-right:4px;"></i><?= $team['memberCount'] ?></span></td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <a href="teams.php?<?= $filterArtId ? 'artId='.$filterArtId.'&' : '' ?>expand=<?= $isExpanded ? 0 : $team['teamId'] ?>"
                                   class="btn-sm <?= $isExpanded ? 'btn-save' : 'btn-green' ?>">
                                    <i class="fas fa-<?= $isExpanded ? 'chevron-up' : 'users' ?>"></i>
                                    <?= $isExpanded ? 'Collapse' : 'Members' ?>
                                </a>
                                <a href="teams.php?<?= $filterArtId ? 'artId='.$filterArtId.'&' : '' ?>editTeam=<?= $team['teamId'] ?><?= $isExpanded ? '&expand='.$team['teamId'] : '' ?>"
                                   class="btn-sm btn-edit">
                                    <i class="fas fa-pencil-alt"></i> Edit
                                </a>
                                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this team and all its members?')">
                                    <input type="hidden" name="teamId" value="<?= $team['teamId'] ?>">
                                    <button type="submit" name="delete_team" class="btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    <?php endif; ?>
                    </tr>

                    <?php if ($isExpanded): ?>
                    <tr>
                        <td colspan="4" style="padding:0;">
                            <div class="members-subpanel">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Allocation %</th>
                                            <th style="width:160px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($expandedMembers as $m):
                                        $isEditingMember = ($editMemberId == $m['teamMemberId']);
                                        $roleClass = $m['role'] == 'Scrum Master' ? 'sm' : ($m['role'] == 'Product Owner' ? 'po' : 'dev');
                                        $names    = explode(' ', $m['name']);
                                        $initials = strtoupper(substr($names[0],0,1).(isset($names[1]) ? substr($names[1],0,1) : ''));
                                    ?>
                                        <tr>
                                        <?php if ($isEditingMember): ?>
                                            <td colspan="4">
                                                <form method="POST" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                                    <input type="hidden" name="tmId" value="<?= $m['teamMemberId'] ?>">
                                                    <input class="inline-edit-input" type="text"  name="mem_name"  value="<?= htmlspecialchars($m['name'])  ?>" placeholder="Name"  required style="width:160px;">
                                                    <input class="inline-edit-input" type="email" name="mem_email" value="<?= htmlspecialchars($m['email']) ?>" placeholder="Email" required style="width:200px;">
                                                    <select class="inline-select" name="mem_role" required>
                                                        <?php foreach (['Developer','Scrum Master','Product Owner','Release Train Engineer'] as $r): ?>
                                                        <option value="<?= $r ?>" <?= $m['role'] == $r ? 'selected' : '' ?>><?= $r ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input class="alloc-input" type="number" name="mem_alloc" value="<?= $m['allocationPct'] ?>" min="0" max="100" step="5" placeholder="%">
                                                    <button type="submit" name="edit_member" class="btn-sm btn-save"><i class="fas fa-check"></i> Save</button>
                                                    <a href="teams.php?<?= $filterArtId ? 'artId='.$filterArtId.'&' : '' ?>expand=<?= $expandTeamId ?>" class="btn-sm btn-cancel"><i class="fas fa-times"></i></a>
                                                </form>
                                            </td>
                                            <td></td>
                                        <?php else: ?>
                                            <td>
                                                <div class="team-info">
                                                    <div class="avatar" style="width:34px;height:34px;font-size:12px;"><?= $initials ?></div>
                                                    <div class="team-details"><h4><?= htmlspecialchars($m['name']) ?></h4></div>
                                                </div>
                                            </td>
                                            <td style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($m['email']) ?></td>
                                            <td><span class="role-badge <?= $roleClass ?>"><?= htmlspecialchars($m['role']) ?></span></td>
                                            <td><?= $m['allocationPct'] ?>%</td>
                                            <td>
                                                <div style="display:flex;gap:6px;">
                                                    <a href="teams.php?<?= $filterArtId ? 'artId='.$filterArtId.'&' : '' ?>expand=<?= $expandTeamId ?>&editMember=<?= $m['teamMemberId'] ?>"
                                                       class="btn-sm btn-edit"><i class="fas fa-pencil-alt"></i> Edit</a>
                                                    <form method="POST" class="inline-form" onsubmit="return confirm('Remove this member?')">
                                                        <input type="hidden" name="tmId" value="<?= $m['teamMemberId'] ?>">
                                                        <button type="submit" name="delete_member" class="btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>

                                    <!-- Add member row -->
                                    <tr>
                                        <td colspan="5" style="padding:0;">
                                            <form method="POST">
                                                <input type="hidden" name="mem_teamId" value="<?= $expandTeamId ?>">
                                                <div class="add-panel" style="border-top:1px dashed #e5e7eb;">
                                                    <i class="fas fa-user-plus" style="color:#6366f1;font-size:16px;"></i>
                                                    <input type="text"  name="mem_name"  placeholder="Full name"  required style="width:150px;">
                                                    <input type="email" name="mem_email" placeholder="Email"      required style="width:200px;">
                                                    <select name="mem_role" required style="width:160px;">
                                                        <option value="">— Role —</option>
                                                        <option>Developer</option>
                                                        <option>Scrum Master</option>
                                                        <option>Product Owner</option>
                                                        <option>Release Train Engineer</option>
                                                    </select>
                                                    <input type="number" name="mem_alloc" placeholder="Alloc %" min="0" max="100" step="5" value="100" class="alloc-input">
                                                    <button type="submit" name="add_member" class="btn" style="padding:8px 16px;font-size:13px;">
                                                        <i class="fas fa-plus" style="margin-right:5px;"></i>Add Member
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Add new team -->
            <form method="POST">
                <div class="add-panel">
                    <i class="fas fa-plus-circle" style="color:#6366f1;font-size:18px;"></i>
                    <input type="text" name="teamName" placeholder="New team name..." required>
                    <select name="artId" required>
                        <option value="">— Select ART —</option>
                        <?php $allArts->data_seek(0); while ($a = $allArts->fetch_assoc()): ?>
                        <option value="<?= $a['artId'] ?>" <?= $filterArtId == $a['artId'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['artName']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" name="add_team" class="btn">
                        <i class="fas fa-plus" style="margin-right:6px;"></i>Add Team
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>