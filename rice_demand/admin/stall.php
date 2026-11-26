<?php
include_once __DIR__ . "/../conn.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Ga Handle ka action(Update/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'stall') {
    $action       = $_POST['stall_action'] ?? '';
    $stall_name   = trim($_POST['stall_name'] ?? '');
    $stall_number = (int)($_POST['stall_number'] ?? 0);
    $stall_id     = (int)($_POST['stall_id'] ?? 0);

    $errorMsg = '';

    try {
        if ($action === 'update' && $stall_id > 0) {
            if ($stall_name === '' || $stall_number <= 0) {
                $errorMsg = 'Stall name and number are required.';
            } else {
                // Check duplicate stall number (exclude the row being edited)
                $dupNum = $conn->prepare("
                    SELECT 1 FROM tb_stall
                    WHERE stall_number = :num AND stall_id <> :id
                    LIMIT 1
                ");
                $dupNum->execute([':num' => $stall_number, ':id' => $stall_id]);

                if ($dupNum->fetch()) {
                    $errorMsg = "Stall number #{$stall_number} is already taken. Please choose another.";
                } else {
                    $upd = $conn->prepare("UPDATE tb_stall SET stall_name = :n, stall_number = :num WHERE stall_id = :id");
                    $upd->execute([':n' => $stall_name, ':num' => $stall_number, ':id' => $stall_id]);
                    $_SESSION['flash_success'] = true;
                    $_SESSION['flash_message'] = 'Stall updated.';
                }
            }

        } elseif ($action === 'delete' && $stall_id > 0) {
            $chk = $conn->prepare("SELECT COUNT(*) FROM tb_weekly WHERE stall_id = :id");
            $chk->execute([':id' => $stall_id]);
            if ((int)$chk->fetchColumn() > 0) {
                $errorMsg = 'Cannot delete: this stall has weekly reports linked to it.';
            } else {
                $del = $conn->prepare("DELETE FROM tb_stall WHERE stall_id = :id");
                $del->execute([':id' => $stall_id]);
                $_SESSION['flash_success'] = true;
                $_SESSION['flash_message'] = 'Stall deleted.';
            }
        }
    } catch (PDOException $e) {
        // Catch DB-level unique violations (if a unique index exists)
        if ($e->getCode() === '23000') {
            $errorMsg = "Stall number #{$stall_number} is already taken. Please choose another.";
        } else {
            $errorMsg = 'Database error: ' . $e->getMessage();
        }
    }

    if ($errorMsg !== '') {
        $_SESSION['flash_success'] = false;
        $_SESSION['flash_message'] = $errorMsg;
    }

    header('Location: stall.php');
    exit;
}

// ===== READ FLASH MESSAGE =====
$stallMessage = '';
$stallSuccess = false;
if (!empty($_SESSION['flash_message'])) {
    $stallMessage = $_SESSION['flash_message'];
    $stallSuccess = !empty($_SESSION['flash_success']);
    unset($_SESSION['flash_message'], $_SESSION['flash_success']);
}

$stalls = $conn->query("
    SELECT
    s.stall_id,
    s.stall_name,
    s.stall_number,
    v.vendors_id,
    TRIM(CONCAT_WS(' ', v.f_name, v.l_name)) AS vendor_name
    FROM tb_stall s
    LEFT JOIN tb_vendors v ON v.vendors_id = s.vendors_id
    ORDER BY s.stall_number ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stalls Page</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="admin-body">
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">👤</div>
                <div class="brand-text">
                    <h2>Welcome</h2>
                    <span>Admin</span>
                </div>
            </div>

            <nav class="nav">
                <a href="../admin/dashboard.php" class="nav-item">
                    <span class="nav-icon">📊</span><span>Dashboard</span>
                </a>
                <a href="../admin/reports.php" class="nav-item">
                    <span class="nav-icon">📄</span><span>Reports</span>
                </a>
                <a href="../admin/stall.php" class="nav-item active">
                    <span class="nav-icon">🏪</span><span>Stalls</span>
                </a>
                <a href="../admin/rice.php" class="nav-item">
                    <span class="nav-icon">🌾</span><span>Rice Varieties</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="../logout.php" class="logout-btn"><span>Log out</span></a>
            </div>
        </aside>

        <main class="main">
            <header class="page-head">
                <div><h1>Stalls</h1></div>
            </header>

            <section class="panel">
                <div class="panel-head">
                    <div><h2>All Stalls</h2></div>
                </div>
                <?php if ($stallMessage !== ''): ?>
                    <div class="alert <?= $stallSuccess ? 'alert-success' : 'alert-error' ?>">
                        <?= htmlspecialchars($stallMessage) ?>
                    </div>
                <?php endif; ?>

                <?php if (count($stalls) === 0): ?>
                    <div class="empty-state"><div class="empty-icon">🏪</div><p>No stalls yet.</p></div>
                <?php else: ?>

                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Stall Name</th>
                                    <th>Stall Number</th>
                                    <th>Vendor</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($stalls as $s): ?>
                                    <tr>
                                        <td><span class="id-badge">#<?= $s['stall_id'] ?></span></td>
                                        <td>
                                            <input type="text" class="inline-input"
                                            id="stall_name_<?= $s['stall_id'] ?>"
                                            value="<?= htmlspecialchars($s['stall_name']) ?>" required>
                                        </td>
                                        <td>
                                            <input type="number" class="inline-input" min="1"
                                            id="stall_number_<?= $s['stall_id'] ?>"
                                            value="<?= (int)$s['stall_number'] ?>" required>
                                        </td>
                                        <td>
                                            <?php if (!empty($s['vendor_name'])): ?>
                                                <span class="vendor-names"><?= htmlspecialchars($s['vendor_name']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">No vendor linked</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="actions">
                                            <form method="POST" action="stall.php"
                                                class="action-form update-form"
                                                data-stall-id="<?= $s['stall_id'] ?>">
                                                <input type="hidden" name="form" value="stall">
                                                <input type="hidden" name="stall_action" value="update">
                                                <input type="hidden" name="stall_id" value="<?= $s['stall_id'] ?>">
                                                <input type="hidden" name="stall_name" value="">
                                                <input type="hidden" name="stall_number" value="">
                                                <button type="submit" class="btn-small btn-edit">Update</button>
                                            </form>

                                            <form method="POST" action="stall.php" class="action-form delete-form">
                                                <input type="hidden" name="form" value="stall">
                                                <input type="hidden" name="stall_action" value="delete">
                                                <input type="hidden" name="stall_id" value="<?= $s['stall_id'] ?>">
                                                <button type="submit" class="btn-small btn-delete">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="../js/stall.js"></script>

</body>

</html>