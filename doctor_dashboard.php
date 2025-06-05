<?php
session_start();

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctor = $_SESSION['user_data'];

// Initialize doctor's orders if not exists
if (!isset($_SESSION['doctor_orders'])) {
    $_SESSION['doctor_orders'] = [];
}

// Get available pharmacies
$pharmacies = $_SESSION['users']['pharmacies'] ?? [];

// Calculate statistics
$totalOrders = count($_SESSION['doctor_orders']);
$pendingOrders = 0;
$completedOrders = 0;

foreach ($_SESSION['doctor_orders'] as $order) {
    if ($order['status'] === 'pending' || $order['status'] === 'new') {
        $pendingOrders++;
    } elseif ($order['status'] === 'completed') {
        $completedOrders++;
    }
}

// Get recent orders (limit to 3)
$recentOrders = array_slice(array_reverse($_SESSION['doctor_orders']), 0, 3);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - PharmaSys</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'doctor_sidebar.php'; ?>
        
        <main class="content">
            <h1>Welcome, Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h1>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Orders</h3>
                    </div>
                    <div class="stat-content">
                        <p class="stat-value"><?php echo $totalOrders; ?></p>
                        <p class="stat-label">Orders placed</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Pending Orders</h3>
                    </div>
                    <div class="stat-content">
                        <p class="stat-value"><?php echo $pendingOrders; ?></p>
                        <p class="stat-label">Awaiting pharmacy response</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Completed Orders</h3>
                    </div>
                    <div class="stat-content">
                        <p class="stat-value"><?php echo $completedOrders; ?></p>
                        <p class="stat-label">Successfully fulfilled</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Available Pharmacies</h3>
                    </div>
                    <div class="stat-content">
                        <p class="stat-value"><?php echo count($pharmacies); ?></p>
                        <p class="stat-label">Registered pharmacies</p>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-header">
                        <h2>Quick Actions</h2>
                        <p>Common tasks for doctors</p>
                    </div>
                    <div class="card-content">
                        <div class="quick-actions">
                            <a href="doctor_orders.php?action=new" class="action-card">
                                <div class="action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                                        <polyline points="14,2 14,8 20,8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                    </svg>
                                </div>
                                <h3>New Prescription</h3>
                                <p>Create a new medication order for a patient</p>
                            </a>
                            
                            <a href="doctor_orders.php" class="action-card">
                                <div class="action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </div>
                                <h3>View Orders</h3>
                                <p>Check status of your medication orders</p>
                            </a>
                            
                            <a href="pharmacies.php" class="action-card">
                                <div class="action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M3 21h18l-9-18-9 18zM12 8v4m0 4h.01"/>
                                    </svg>
                                </div>
                                <h3>Find Pharmacies</h3>
                                <p>Browse available pharmacies in the system</p>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Recent Orders</h2>
                        <p>Your latest medication orders</p>
                    </div>
                    <div class="card-content">
                        <?php if (count($recentOrders) > 0): ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <div class="order-item">
                                    <div>
                                        <p class="order-doctor">Order #<?php echo $order['id']; ?></p>
                                        <p class="order-patient">Patient: <?php echo htmlspecialchars($order['patient_name']); ?></p>
                                    </div>
                                    <div class="order-details">
                                        <p><?php echo count($order['medications']); ?> medication(s)</p>
                                        <p class="order-status"><?php echo ucfirst($order['status']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No orders yet. <a href="doctor_orders.php?action=new">Create your first order</a></p>
                        <?php endif; ?>
                        
                        <div class="card-actions">
                            <a href="doctor_orders.php" class="btn">View All Orders</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
