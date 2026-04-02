<?php
require 'db.php';
$db = db();

$message = '';
$messageType = '';

// Handle Add ART
if (isset($_POST['add_art'])) {
    $name = trim($_POST['artName'] ?? '');
    if ($name !== '') {
        $stmt = $db->prepare("INSERT INTO arts (artName) VALUES (?)");
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) {
            $message = "ART \"$name\" added successfully.";
            $messageType = 'success';
        } else {
            $message = "Error adding ART.";
            $messageType = 'error';
        }
    }
}

// Handle Edit ART
if (isset($_POST['edit_art'])) {
    $id   = intval($_POST['artId']);
    $name = trim($_POST['artName'] ?? '');
    if ($id && $name !== '') {
        $stmt = $db->prepare("UPDATE arts SET artName=? WHERE artId=?");
        $stmt->bind_param("si", $name, $id);
        if ($stmt->execute()) {
            $message = "ART updated successfully.";
            $messageType = 'success';
        } else {
            $message = "Error updating ART.";
            $messageType = 'error';
        }
    }
}

// Handle Delete ART
if (isset($_POST['delete_art'])) {
    $id = intval($_POST['artId']);
    if ($id) {
        $stmt = $db->prepare("DELETE FROM arts WHERE artId=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "ART deleted.";
            $messageType = 'success';
        } else {
            $message = "Error deleting ART (it may have associated teams).";
            $messageType = 'error';
        }
    }
}

$editId  = intval($_GET['edit'] ?? 0);
$arts    = $db->query("
    SELECT a.artId, a.artName,
           COUNT(DISTINCT t.teamId) as teamCount,
           COUNT(DISTINCT tm.teamMemberId) as memberCount
    FROM arts a
    LEFT JOIN teams t ON a.artId = t.artId
    LEFT JOIN team_members tm ON t.teamId = tm.teamId
    GROUP BY a.artId, a.artName
    ORDER BY a.artName
");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ARTs - CapacityHub</title>
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
        .btn-edit { background: #eff6ff; color: #1e40af; }
        .btn-edit:hover { background: #dbeafe; }
        .btn-danger { background: #fef2f2; color: #991b1b; }
        .btn-danger:hover { background: #fee2e2; }
        .btn-save { background: #6366f1; color: white; }
        .btn-save:hover { background: #5558e3; }
        .btn-cancel { background: #f3f4f6; color: #374151; }
        .btn-cancel:hover { background: #e5e7eb; }
        .inline-edit-input {
            padding: 7px 12px;
            border: 1px solid #6366f1;
            border-radius: 6px;
            font-size: 14px;
            color: #111827;
            outline: none;
            width: 260px;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .add-panel {
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 20px 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .add-panel input[type="text"] {
            padding: 10px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            color: #111827;
            outline: none;
            width: 300px;
        }
        .add-panel input[type="text"]:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .error-message {
            padding: 12px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #6b7280;
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
            <h1 class="page-title">Agile Release Trains</h1>
            <p class="page-subtitle">Manage all ARTs in the organization</p>
        </div>

        <?php if ($message): ?>
        <div class="<?= $messageType === 'success' ? 'success-message' : 'error-message' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="data-panel">
            <div class="panel-header">
                <h2 class="panel-title">All ARTs</h2>
                <span style="font-size:13px;color:#6b7280;"><?= $arts->num_rows ?> total</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ART Name</th>
                        <th>Teams</th>
                        <th>Members</th>
                        <th style="width:200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $arts->data_seek(0); while ($art = $arts->fetch_assoc()): ?>
                    <tr>
                        <?php if ($editId === $art['artId']): ?>
                        <td colspan="3">
                            <form method="POST" style="display:flex;align-items:center;gap:10px;">
                                <input type="hidden" name="artId" value="<?= $art['artId'] ?>">
                                <input class="inline-edit-input" type="text" name="artName" value="<?= htmlspecialchars($art['artName']) ?>" required autofocus>
                                <button type="submit" name="edit_art" class="btn-sm btn-save"><i class="fas fa-check"></i> Save</button>
                                <a href="arts.php" class="btn-sm btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                            </form>
                        </td>
                        <td></td>
                        <?php else: ?>
                        <td>
                            <div class="team-info">
                                <div class="team-logo"><i class="fas fa-rocket"></i></div>
                                <div class="team-details">
                                    <h4><?= htmlspecialchars($art['artName']) ?></h4>
                                    <p>Release Train</p>
                                </div>
                            </div>
                        </td>
                        <td><span class="stat-badge"><i class="fas fa-users"></i> <?= $art['teamCount'] ?> teams</span></td>
                        <td><span class="stat-badge"><i class="fas fa-user"></i> <?= $art['memberCount'] ?> members</span></td>
                        <td>
                            <div style="display:flex;gap:8px;">
                                <a href="art.php?id=<?= $art['artId'] ?>" class="btn-sm btn-edit">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="arts.php?edit=<?= $art['artId'] ?>" class="btn-sm btn-edit">
                                    <i class="fas fa-pencil-alt"></i> Edit
                                </a>
                                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this ART? This may fail if it has associated data.')">
                                    <input type="hidden" name="artId" value="<?= $art['artId'] ?>">
                                    <button type="submit" name="delete_art" class="btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Add new ART row -->
            <form method="POST">
                <div class="add-panel">
                    <i class="fas fa-plus-circle" style="color:#6366f1;font-size:18px;"></i>
                    <input type="text" name="artName" placeholder="New ART name..." required>
                    <button type="submit" name="add_art" class="btn">
                        <i class="fas fa-plus" style="margin-right:6px;"></i>Add ART
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>