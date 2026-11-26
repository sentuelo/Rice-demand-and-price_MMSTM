<?php
include_once "../conn.php";
include_once "../demand_helper.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
//FETCH DATA
$totalStalls  = (int)$conn->query("SELECT COUNT(*) FROM tb_stall")->fetchColumn();
$totalVendors = (int)$conn->query("SELECT COUNT(*) FROM tb_vendors")->fetchColumn();
$totalReports = (int)$conn->query("SELECT COUNT(*) FROM tb_weekly")->fetchColumn();
$totalRice    = (int)$conn->query("SELECT COUNT(*) FROM tb_rice")->fetchColumn();

$recentReports = $conn->query("
    SELECT
    w.week_start,
    w.week_end,
    w.quantity_sold,
    w.beginning_stock,
    COALESCE(s.stall_name, CONCAT(v.f_name, ' ', v.l_name), 'Unknown Vendor') AS stall_name,
    w.variety,
    r.price_kilo,
    r.price_sacks
    FROM tb_weekly w
    LEFT JOIN tb_stall s ON w.stall_id = s.stall_id
    LEFT JOIN tb_vendors v ON w.vendors_id = v.vendors_id
    LEFT JOIN tb_rice  r ON r.vendors_id = w.vendors_id AND r.variety = w.variety
    ORDER BY w.weekly_id
")->fetchAll(PDO::FETCH_ASSOC);

// Compute sell
foreach ($recentReports as &$row) {
    $beg  = (float)($row['beginning_stock'] ?? 0);
    $sold = (float)($row['quantity_sold'] ?? 0);
    $row['_pct']    = $beg > 0 ? ($sold / $beg) : 0;
    $row['_demand'] = calculate_demand_level($sold, $beg);
}
unset($row);

usort($recentReports, function ($a, $b) {
    if (abs($a['_pct'] - $b['_pct']) < 0.0000001) {
        return strcmp($b['week_start'] ?? '', $a['week_start'] ?? '');
    }
    return ($a['_pct'] < $b['_pct']) ? 1 : -1;
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Page</title>
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
                <a href="../admin/dashboard.php" class="nav-item active">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="../admin/reports.php" class="nav-item">
                    <span class="nav-icon">📄</span>
                    <span>Reports</span>
                </a>
                <a href="../admin/stall.php" class="nav-item">
                    <span class="nav-icon">🏪</span>
                    <span>Stalls</span>
                </a>
                <a href="../admin/rice.php" class="nav-item">
                    <span class="nav-icon">🌾</span>
                    <span>Rice Varieties</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="user-card">
                    <div class="user-meta"></div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <span>Log out</span>
                </a>
            </div>
        </aside>

        <main class="main">
            <header class="page-head">
                <div><h1>Dashboard</h1></div>
            </header>

            <section class="stats">
                <div class="stat-card">
                    <div class="stat-icon">🏪</div>
                    <div class="stat-body">
                        <span class="stat-label">Total Stalls</span>
                        <span class="stat-value"><?= $totalStalls ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-body">
                        <span class="stat-label">Total Vendors</span>
                        <span class="stat-value"><?= $totalVendors ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📄</div>
                    <div class="stat-body">
                        <span class="stat-label">Weekly Reports</span>
                        <span class="stat-value"><?= $totalReports ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🌾</div>
                    <div class="stat-body">
                        <span class="stat-label">Rice Varieties</span>
                        <span class="stat-value"><?= $totalRice ?></span>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div><h2>Recent Weekly Reports</h2></div>
                    <a href="reports.php" class="link">View all →</a>
                </div>

                <?php if (count($recentReports) === 0): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <p>No weekly reports yet.</p>
                    </div>
                <?php else: ?>
    
                    <div class="filter-bar">
                        <button type="button" class="filter-btn active" data-filter="All">All</button>
                        <button type="button" class="filter-btn" data-filter="High Demand">High Demand</button>
                        <button type="button" class="filter-btn" data-filter="Medium Demand">Medium Demand</button>
                        <button type="button" class="filter-btn" data-filter="Low Demand">Low Demand</button>
                    </div>

                    <div class="table-wrap">
                        <table class="data-table" id="reportsTable">
                            <thead>
                                <tr>
                                    <th>Vendors</th>
                                    <th>Week Start</th>
                                    <th>Week End</th>
                                    <th>Variety</th>
                                    <th>Beg.</th>
                                    <th>Qty Sold</th>
                                    <th>Sold %</th>
                                    <th>Price / Kilo</th>
                                    <th>Price / Sacks</th>
                                    <th>Demand</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentReports as $r): ?>
                                    <?php $autoDemand = $r['_demand']; ?>
                                    <tr data-demand="<?= htmlspecialchars($autoDemand) ?>">
                                        <td><?= htmlspecialchars($r['stall_name'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($r['week_start']) ?></td>
                                        <td><?= htmlspecialchars($r['week_end']) ?></td>
                                        <td><?= htmlspecialchars($r['variety'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($r['beginning_stock'] ?? '0') ?></td>
                                        <td><strong><?= htmlspecialchars($r['quantity_sold'] ?? '0') ?></strong></td>
                                        <td>
                                            <span class="pct-tag <?= demand_badge_class($autoDemand) ?>">
                                                <?= htmlspecialchars(format_sell_through_percent($r['quantity_sold'], $r['beginning_stock'])) ?>
                                            </span>
                                        </td>
                                        <td>₱<?= number_format((float)($r['price_kilo'] ?? 0), 2) ?></td>
                                        <td>₱<?= number_format((float)($r['price_sacks'] ?? 0), 2) ?></td>
                                        <td>
                                            <span class="badge <?= demand_badge_class($autoDemand) ?>">
                                                <?= htmlspecialchars($autoDemand) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="filter-empty" id="filterEmpty" style="display:none;">
                            There are no reports for this category.
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="../js/adboard.js"></script>

</body>

</html>