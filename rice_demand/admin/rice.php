<?php
include_once "../conn.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

//FETCH ALL RICE VARIETIES
$riceList = $conn->query("
    SELECT
    r.rice_id,
    r.variety,
    r.quantity,
    r.price_kilo,
    r.price_sacks,
    COALESCE(CONCAT(v.f_name, ' ', v.l_name), 'Unknown Vendor') AS vendor_name
    FROM tb_rice r
    LEFT JOIN tb_vendors v ON r.vendors_id = v.vendors_id
")->fetchAll(PDO::FETCH_ASSOC);

// Sort by price_sacks high -> low
usort($riceList, function ($a, $b) {
    $sa = (float)($a['price_sacks'] ?? 0);
    $sb = (float)($b['price_sacks'] ?? 0);
    if (abs($sa - $sb) > 0.000001) {
        return ($sa < $sb) ? 1 : -1;
    }
    $ka = (float)($a['price_kilo'] ?? 0);
    $kb = (float)($b['price_kilo'] ?? 0);
    if (abs($ka - $kb) > 0.000001) {
        return ($ka < $kb) ? 1 : -1;
    }
    return strcmp($a['variety'] ?? '', $b['variety'] ?? '');
});

//BUILD DYNAMIC VARIETY LIST
$varieties = [];
foreach ($riceList as $row) {
    $v = trim((string)($row['variety'] ?? ''));
    if ($v !== '' && !in_array($v, $varieties, true)) {
        $varieties[] = $v;
    }
}
sort($varieties, SORT_NATURAL | SORT_FLAG_CASE);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rice Page</title>
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
                <a href="../admin/rice.php" class="nav-item active">
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
                <div>
                    <h1>Rice Varieties</h1>
                </div>
            </header>

            <section class="stats">
                <div class="stat-card">
                    <div class="stat-icon">🌾</div>
                    <div class="stat-body">
                        <span class="stat-label">Total Rice Entries</span>
                        <span class="stat-value"><?= count($riceList) ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏷️</div>
                    <div class="stat-body">
                        <span class="stat-label">Unique Varieties</span>
                        <span class="stat-value"><?= count($varieties) ?></span>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>All Rice Varieties</h2>
                    </div>
                </div>

                <?php if (count($riceList) === 0): ?>
                    <div class="empty-state">
                    </div>
                <?php else: ?>
                    <div class="filter-bar">
                        <button type="button" class="filter-btn active" data-filter="All">All</button>
                        <?php foreach ($varieties as $v): ?>
                            <button type="button" class="filter-btn" data-filter="<?= htmlspecialchars($v) ?>">
                                <?= htmlspecialchars(ucwords($v)) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="table-wrap">
                        <table class="data-table" id="riceTable">
                            <thead>
                                <tr>
                                    <th>Variety</th>
                                    <th>Vendor</th>
                                    <th>Quantity (sacks)</th>
                                    <th>Price / Kilo</th>
                                    <th>Price / Sacks</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($riceList as $r): ?>
                                    <?php $variety = trim((string)($r['variety'] ?? '')); ?>
                                    <tr data-variety="<?= htmlspecialchars($variety) ?>">
                                        <td><?= htmlspecialchars(ucwords($variety)) ?></td>
                                        <td><?= htmlspecialchars($r['vendor_name']) ?></td>
                                        <td><?= htmlspecialchars($r['quantity']) ?></td>
                                        <td>₱<?= number_format((float)($r['price_kilo'] ?? 0), 2) ?></td>
                                        <td>₱<?= number_format((float)($r['price_sacks'] ?? 0), 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="filter-empty" id="riceFilterEmpty" style="display:none;">
                            Theres no have Varieties of rice
                        </div>

                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="../js/rice.js"></script>

</body>

</html>