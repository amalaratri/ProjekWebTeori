<?php
session_start();

// Include your Database class file
require_once 'config/database.php'; 

// Instantiate the Database class and get the PDO connection object
$database = new Database();
$pdo = $database->getConnection();

// Check if user is logged in and is a pharmacy
// Also check if $pdo connection is successful
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacy' || $pdo === null) {
    // If not logged in, not a pharmacy, or DB connection failed, redirect
    header("Location: login.php");
    exit();
}

// Get the logged-in pharmacy's ID
$pharmacy_id = $_SESSION['pharmacy_id'] ?? null;

// Initialize variables for statistics and data
$totalMedications = 0;
$newOrders = 0;
$processingOrders = 0;
$completedOrders = 0;
$recentOrders = [];
$lowStockMedications = [];
$dashboardError = ''; // For displaying specific dashboard errors

if ($pharmacy_id === null) {
    $dashboardError = "Pharmacy ID not found in session. Please log in again.";
    header("Location: login.php"); exit();
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

        $newOrders = $orderCounts['pending'] ?? 0; // 'new' status is now 'pending' in your DB
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
        foreach ($recentOrders as &$order) { // Use & to modify the array directly
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ?");
            $stmt->execute([$order['id']]);
            $order['item_count'] = $stmt->fetchColumn();
        }
        unset($order); // Unset the reference variable after the loop

        // --- Get Low Stock Medications ---
        // Using the v_low_stock_alerts view
        $stmt = $pdo->prepare("SELECT medication_name, stock_quantity, unit, description 
                               FROM v_low_stock_alerts 
                               WHERE pharmacy_id = ? AND (stock_quantity <= minimum_stock OR days_to_expiry <= 90)"); // Added expiry condition
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
                        <?php foreach ($recentOrders as $order): ?>
                            <div class="order-item">
                                <div>
                                    <p class="order-doctor"><?php echo $order['doctorName']; ?></p>
                                    <p class="order-patient">For: <?php echo $order['patientName']; ?></p>
                                </div>
                                <div class="order-details">
                                    <p><?php echo count($order['items']); ?> item(s)</p>
                                    <p class="order-status"><?php echo ucfirst($order['status']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="card-actions">
                            <a href="orders.php" class="btn">View All Orders</a>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Low Stock Alert</h2>
                        <p>Medications that need restocking</p>
                    </div>
                    <div class="card-content">
                        <?php if (count($lowStockMedications) > 0): ?>
                            <?php foreach ($lowStockMedications as $medication): ?>
                                <div class="order-item">
                                    <div>
                                        <p class="order-doctor"><?php echo $medication['name']; ?></p>
                                        <p class="order-patient"><?php echo $medication['description']; ?></p>
                                    </div>
                                    <div class="order-details">
                                        <p><?php echo $medication['stock']; ?> <?php echo $medication['unit']; ?>(s)</p>
                                        <p class="low-stock">Low stock</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No low stock medications.</p>
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
