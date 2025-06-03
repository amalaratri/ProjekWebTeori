<?php
session_start();

// Check if user is logged in and is a pharmacy
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacy') {
    header("Location: login.php");
    exit();
}

// Calculate statistics
$totalMedications = count($_SESSION['medications']);
$newOrders = 0;
$processingOrders = 0;
$completedOrders = 0;

foreach ($_SESSION['orders'] as $order) {
    if ($order['status'] === 'new') {
        $newOrders++;
    } elseif ($order['status'] === 'preparing' || $order['status'] === 'ready') {
        $processingOrders++;
    } elseif ($order['status'] === 'completed') {
        $completedOrders++;
    }
}

// Get recent orders (limit to 2)
$recentOrders = array_slice($_SESSION['orders'], 0, 2);

// Get low stock medications (less than 60)
$lowStockMedications = [];
foreach ($_SESSION['medications'] as $medication) {
    if ($medication['stock'] < 60) {
        $lowStockMedications[] = $medication;
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
