<?php
session_start();

require_once 'config/database.php'; 

$database = new Database();
$pdo = $database->getConnection();

// Check if user is logged in and is a pharmacy, and if DB connection is successful
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacy' || $pdo === null) {
    header("Location: login.php");
    exit();
}

$pharmacy_id = $_SESSION['pharmacy_id'] ?? null;

$totalMedications = 0;
$newOrders = 0;
$processingOrders = 0;
$completedOrders = 0;
$recentOrders = [];
$lowStockMedications = [];
$dashboardError = ''; 

if ($pharmacy_id === null) {
    $dashboardError = "Pharmacy ID not found in session. Please log in again.";
    // For critical missing data, you might want a stronger redirect
    // header("Location: login.php"); exit();
} else {
    try {
        // --- Calculate Statistics ---

        // Total Medications in this pharmacy's inventory
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pharmacy_inventory WHERE pharmacy_id = ?");
        $stmt->execute([$pharmacy_id]);
        $totalMedications = $stmt->fetchColumn();

        // Orders Statistics
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM orders WHERE pharmacy_id = ? GROUP BY status");
        $stmt->execute([$pharmacy_id]);
        $orderCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Gets an associative array like ['pending' => 5, 'completed' => 10]

        $newOrders = $orderCounts['pending'] ?? 0; 
        $processingOrders = ($orderCounts['confirmed'] ?? 0) + ($orderCounts['preparing'] ?? 0) + ($orderCounts['ready'] ?? 0);
        $completedOrders = $orderCounts['completed'] ?? 0;

        // --- Get Recent Orders ---
        // Fetch orders, ordered by creation date, limited to 2, and join with doctors for name
        $stmt = $pdo->prepare("SELECT o.id, o.order_number, o.patient_name, o.status, d.full_name as doctorName 
                               FROM orders o
                               JOIN doctors d ON o.doctor_id = d.id
                               WHERE o.pharmacy_id = ?
                               ORDER BY o.created_at DESC
                               LIMIT 2");
        $stmt->execute([$pharmacy_id]);
        $recentOrders = $stmt->fetchAll();

        // For each recent order, fetch the item count
        foreach ($recentOrders as &$order) { 
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ?");
            $stmt->execute([$order['id']]);
            $order['item_count'] = $stmt->fetchColumn();
        }
        unset($order); 

        // --- Get Low Stock Medications ---
        // Using the v_low_stock_alerts view
        $stmt = $pdo->prepare("SELECT medication_name, stock_quantity, unit, m.description 
                               FROM v_low_stock_alerts v
                               JOIN medications m ON v.medication_id = m.id
                               WHERE v.pharmacy_id = ? AND (v.stock_quantity <= v.minimum_stock OR v.days_to_expiry <= 90)"); // Added join for description
        $stmt->execute([$pharmacy_id]);
        $lowStockMedications = $stmt->fetchAll();

    } catch (PDOException $e) {
        $dashboardError = "Error loading dashboard data: " . $e->getMessage();
        error_log("Dashboard data load error for pharmacy ID " . $pharmacy_id . ": " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PharmaSys</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="content">
            <h1>PharmaSys Dashboard</h1>
            
            <?php if ($dashboardError): ?>
                <div class="alert error">
                    <?php echo $dashboardError; ?>
                </div>
            <?php endif; ?>

            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Medications</h3>
                    </div>
                    <div class="stat-content">
                        <p class="stat-value"><?php echo $totalMedications; ?></p>
                        <p class="stat-label">Items in inventory</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>New Orders</h3>
                    </div>
                    <div class="stat-content">
                        <p class="stat-value"><?php echo $newOrders; ?></p>
                        <p class="stat-label">Waiting for confirmation</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Processing</h3>
                    </div>
                    <div class="stat-content">
                        <p class="stat-value"><?php echo $processingOrders; ?></p>
                        <p class="stat-label">Orders being prepared</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Completed</h3>
                    </div>
                    <div class="stat-content">
                        <p class="stat-value"><?php echo $completedOrders; ?></p>
                        <p class="stat-label">Orders fulfilled</p>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-header">
                        <h2>Recent Orders</h2>
                        <p>Latest orders from doctors</p>
                    </div>
                    <div class="card-content">
                        <?php if (count($recentOrders) > 0): ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <div class="order-item">
                                    <div>
                                        <p class="order-doctor"><?php echo htmlspecialchars($order['doctorName']); ?></p>
                                        <p class="order-patient">For: <?php echo htmlspecialchars($order['patient_name']); ?></p>
                                    </div>
                                    <div class="order-details">
                                        <p><?php echo $order['item_count']; ?> item(s)</p>
                                        <p class="order-status"><?php echo ucfirst(htmlspecialchars($order['status'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No recent orders.</p>
                        <?php endif; ?>
                        <div class="card-actions">
                            <a href="orders.php" class="btn">View All Orders</a>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Low Stock Alert</h2>
                        <p>Medications that need restocking or are expiring soon</p>
                    </div>
                    <div class="card-content">
                        <?php if (count($lowStockMedications) > 0): ?>
                            <?php foreach ($lowStockMedications as $medication): ?>
                                <div class="order-item">
                                    <div>
                                        <p class="order-doctor"><?php echo htmlspecialchars($medication['medication_name']); ?></p>
                                        <p class="order-patient"><?php echo htmlspecialchars($medication['description'] ?? 'No description'); ?></p>
                                    </div>
                                    <div class="order-details">
                                        <p><?php echo htmlspecialchars($medication['stock_quantity']); ?> <?php echo htmlspecialchars($medication['unit']); ?>(s)</p>
                                        <p class="low-stock">Low stock / Expiring</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No low stock or expiring medications.</p>
                        <?php endif; ?>
                        <div class="card-actions">
                            <a href="inventory.php" class="btn">Manage Inventory</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>