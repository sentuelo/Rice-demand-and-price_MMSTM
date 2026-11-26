<?php
include_once "../conn.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$vendor_id = (int)$_SESSION['user_id'];
$message = ''; $success = false;

// ===== FETCH VENDOR INFO =====
$stmt = $conn->prepare("
    SELECT vendors_id, f_name, l_name
    FROM tb_vendors
    WHERE vendors_id = :id
    LIMIT 1
");
$stmt->execute([':id' => $vendor_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);


$firstName  = trim($vendor['f_name'] ?? '');
$lastName   = trim($vendor['l_name'] ?? '');
$vendorName = trim($firstName . ' ' . $lastName);
if ($vendorName === '') $vendorName = 'Vendor';

$initials = strtoupper(
    ($firstName !== '' ? $firstName[0] : '')
    . ($lastName !== '' ? $lastName[0] : '')
);
if ($initials === '') $initials = 'V';

// ===== ADD RICE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_rice'])) {
    $variety     = trim($_POST['variety'] ?? '');
    $quantity    = (int)($_POST['quantity'] ?? 0);
    $price_kilo  = trim($_POST['price_kilo'] ?? '');
    $price_sacks = trim($_POST['price_sacks'] ?? '');

    // Automatic date only (Philippine time, no time)
    date_default_timezone_set('Asia/Manila');
    $date_added = date('Y-m-d');

    if ($variety === '' || $quantity <= 0 || $price_kilo === '' || $price_sacks === '') {
        $message = 'Please fill in all fields correctly.';
    } else {
        try {
            $ins = $conn->prepare("
                INSERT INTO tb_rice (vendors_id, variety, quantity, price_kilo, price_sacks, date_added)
                VALUES (:vid, :v, :q, :pk, :ps, :da)
            ");
            $ins->execute([
                ':vid' => $vendor_id,
                ':v'   => $variety,
                ':q'   => $quantity,
                ':pk'  => $price_kilo,
                ':ps'  => $price_sacks,
                ':da'  => $date_added,
            ]);
            $message = 'Rice variety added successfully on ' . date('M d, Y', strtotime($date_added)) . '.';
            $success = true;
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Kunin base sa vendors_id
$stmt = $conn->prepare("SELECT * FROM tb_rice WHERE vendors_id = :id ORDER BY variety ASC");
$stmt->execute([':id' => $vendor_id]);
$riceList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<head>
    <title>Rice Page</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="admin-body">
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">👤</div>
                <div class="brand-text">
                    <h2 title="<?= htmlspecialchars($vendorName) ?>"><?= htmlspecialchars($vendorName) ?></h2>
                    <span>Vendor</span>
                </div>
            </div>
            <nav class="nav">
                <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span><span>Dashboard</span></a>
                <a href="rice.php" class="nav-item active"><span class="nav-icon">🌾</span><span>My Rice</span></a>
                <a href="reports.php" class="nav-item"><span class="nav-icon">📄</span><span>Reports</span></a>
            </nav>
            <div class="sidebar-footer">
                <a href="../logout.php" class="logout-btn"><span>Log out</span></a>
            </div>
        </aside>

        <main class="main">
            <section class="panel">
                <div class="panel-head">
                    <div><h2>Add New Rice</h2></div>
                </div>
                <?php if ($message !== ''): ?>
                    <div class="alert <?= $success ? 'alert-success' : 'alert-error' ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <form method="POST" action="rice.php" class="form-grid">
                    <div class="field"><label>Variety</label><input type="text" name="variety" required></div>
                    <div class="field"><label>Quantity (Sacks)</label><input type="number" name="quantity" min="1" required></div>
                    <div class="field"><label>Price per Kilo</label><input type="text" name="price_kilo" required></div>
                    <div class="field"><label>Price per Sacks</label><input type="text" name="price_sacks" required></div>

                    <!-- Automatic date display (read-only, for confirmation) -->
                    <div class="field">
                        <label>Date Added (auto)</label>
                        <input type="text" value="<?= date('M d, Y') ?>" readonly>
                    </div>

                    <div class="form-actions"><button type="submit" name="add_rice" class="btn-primary">Add Rice</button></div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div><h2>My Rice List</h2></div>
                </div>
                <?php if (count($riceList) === 0): ?>
                    <div class="empty-state"><div class="empty-icon"></div></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Variety</th>
                                    <th>Quantity</th>
                                    <th>Price / Kilo</th>
                                    <th>Price / Sacks</th>
                                    <th>Date Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riceList as $r): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($r['variety']) ?></strong></td>
                                        <td><?= htmlspecialchars($r['quantity']) ?></td>
                                        <td>₱ <?= htmlspecialchars($r['price_kilo']) ?></td>
                                        <td>₱ <?= htmlspecialchars($r['price_sacks']) ?></td>
                                        <td>
                                            <?= !empty($r['date_added'])
                                                ? htmlspecialchars(date('M d, Y', strtotime($r['date_added'])))
                                                : '—' ?>
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
</body>
</html>