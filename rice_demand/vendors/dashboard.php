<?php
include_once "../conn.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$vendor_id = (int) $_SESSION['user_id'];

// 2. FETCH VENDOR (SAFE)

$stmt = $conn->prepare("
    SELECT vendors_id, f_name, l_name
    FROM tb_vendors
    WHERE vendors_id = :id
    LIMIT 1
");
$stmt->execute([':id' => $vendor_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

/* If session points to a non-existent vendor → force re-login */
if (!$vendor) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=vendor_not_found");
    exit;
}

$firstName  = trim($vendor['f_name'] ?? '');
$lastName   = trim($vendor['l_name'] ?? '');
$vendorName = trim($firstName . ' ' . $lastName);
if ($vendorName === '') {
    $vendorName = 'Vendor';
}

$initials = strtoupper(
    ($firstName !== '' ? $firstName[0] : '')
    . ($lastName  !== '' ? $lastName[0]  : '')
);
if ($initials === '') {
    $initials = 'V';
}


// 4. STATS

$stmt = $conn->prepare("SELECT COUNT(*) FROM tb_rice WHERE vendors_id = :id");
$stmt->execute([':id' => $vendor_id]);
$totalRice = (int) $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM tb_weekly WHERE vendors_id = :id");
$stmt->execute([':id' => $vendor_id]);
$totalReports = (int) $stmt->fetchColumn();


// 5. RECENT ADMIN REPORTS

$stmt = $conn->prepare("
    SELECT report_date, variety, price_kilo, price_sacks, demand_level
    FROM tb_admin_reports
    ORDER BY
        CASE demand_level
            WHEN 'High Demand'   THEN 1
            WHEN 'Medium Demand' THEN 2
            WHEN 'Low Demand'    THEN 3
            ELSE 4
        END ASC,
        report_date DESC
    LIMIT 5
");
$stmt->execute();
$recentAdminReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// rice list
$stmt = $conn->prepare("
    SELECT variety, quantity, price_kilo, price_sacks
    FROM tb_rice
    WHERE vendors_id = :id
    ORDER BY variety ASC
    LIMIT 5
");
$stmt->execute([':id' => $vendor_id]);
$riceList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helpers
function demand_badge_class(?string $dl): string {
    $dl = trim((string) $dl);
    if ($dl === 'High Demand') return 'badge-high';
    if ($dl === 'Low Demand')  return 'badge-low';
    return 'badge-medium';
}

function demand_short_label(?string $dl): string {
    $dl = trim((string) $dl);
    if ($dl === '') return '—';
    $short = preg_replace('/\s*demand\s*/i', '', $dl);
    $short = trim($short);
    return $short !== '' ? $short : $dl;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard</title>
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
                <a href="dashboard.php" class="nav-item active">
                    <span class="nav-icon">📊</span><span>Dashboard</span>
                </a>
                <a href="rice.php" class="nav-item">
                    <span class="nav-icon">🌾</span><span>My Rice</span>
                </a>
                <a href="reports.php" class="nav-item">
                    <span class="nav-icon">📄</span><span>Reports</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="../logout.php" class="logout-btn">
                    <span>Log out</span>
                </a>
            </div>
        </aside>

        <main class="main">
            <section class="welcome-banner">
                <div class="welcome-avatar"><?= htmlspecialchars($initials) ?></div>
                <div class="welcome-text">
                    <h1>Welcome back, <?= htmlspecialchars($firstName ?: $vendorName) ?>!</h1>
                    <p><strong><?= htmlspecialchars($vendorName) ?></strong></p>
                </div>
            </section>

            <section class="stats">
                <div class="stat-card">
                    <div class="stat-body">
                        <span class="stat-label">My Rice Varieties</span>
                        <span class="stat-value"><?= $totalRice ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-body">
                        <span class="stat-label">Weekly Reports</span>
                        <span class="stat-value"><?= $totalReports ?></span>
                    </div>
                </div>
            </section>

            <!-- RECENT ADMIN REPORTS -->
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Recent Admin Reports</h2>
                    </div>
                    <a href="reports.php" class="link">View all →</a>
                </div>

                <?php if (count($recentAdminReports) === 0): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <p>No admin reports available yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Report Date</th>
                                    <th>Variety</th>
                                    <th>Price / Kilo</th>
                                    <th>Price / Sack</th>
                                    <th>Demand</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAdminReports as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['report_date'] ?? '') ?></td>
                                        <td><strong><?= htmlspecialchars($r['variety'] ?? '') ?></strong></td>
                                        <td>₱<?= htmlspecialchars($r['price_kilo'] ?? '') ?></td>
                                        <td>₱<?= htmlspecialchars($r['price_sacks'] ?? '') ?></td>
                                        <td>
                                            <span class="badge <?= demand_badge_class($r['demand_level'] ?? '') ?>">
                                                <?= htmlspecialchars(demand_short_label($r['demand_level'] ?? '')) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <!-- RICE LIST -->
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>My Rice List</h2>
                    </div>
                    <a href="rice.php" class="link">View all →</a>
                </div>

                <?php if (count($riceList) === 0): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🌾</div>
                        <p>You haven't added any rice varieties yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Variety</th>
                                    <th>Quantity</th>
                                    <th>Price / Kilo</th>
                                    <th>Price / Sacks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riceList as $r): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($r['variety'] ?? '') ?></strong></td>
                                        <td><?= htmlspecialchars($r['quantity'] ?? '') ?></td>
                                        <td>₱ <?= htmlspecialchars($r['price_kilo'] ?? '') ?></td>
                                        <td>₱ <?= htmlspecialchars($r['price_sacks'] ?? '') ?></td>
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