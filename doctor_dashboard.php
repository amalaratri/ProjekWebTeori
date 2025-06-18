<?php
session_start();

require_once 'config/database.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['doctor_data'])) {
    $doctor = $_SESSION['doctor_data'];
} else {
    header("Location: login.php");
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

$doctor_id = $doctor['id'] ?? null;

if (!$doctor_id) {
    header("Location: login.php");
    exit();
}

// Fetch doctor's orders from database
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE doctor_id = ? ORDER BY order_date DESC");
    $stmt->execute([$doctor_id]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
}

// Fetch pharmacies from database
try {
    $stmt = $pdo->query("SELECT * FROM pharmacies");
    $pharmacies = $stmt->fetchAll();
} catch (PDOException $e) {
    $pharmacies = [];
}

// Calculate statistics
$totalOrders = count($orders);
$pendingOrders = 0;
$completedOrders = 0;

foreach ($orders as $order) {
    if ($order['status'] === 'pending' || $order['status'] === 'new') {
        $pendingOrders++;
    } elseif ($order['status'] === 'completed') {
        $completedOrders++;
    }
}

// Get recent orders (limit to 3)
$recentOrders = array_slice($orders, 0, 3);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Doctor Dashboard - PharmaSys</title>
    <link rel="stylesheet" href="styles.css" />
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
    <a href="doctor_prescriptions.php" class="action-card">
        <div class="action-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                <polyline points="14,2 14,8 20,8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <line x1="12" y1="9" x2="8" y2="9"/>
            </svg>
        </div>
        <h3>Create Prescription</h3>
        <p>Create a new prescription with available medications</p>
    </a>

    <a href="doctor_medication_search.php" class="action-card">
        <div class="action-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="11" cy="11" r="8"/>
                <path d="m21 21-4.35-4.35"/>
            </svg>
        </div>
        <h3>Search Medications</h3>
        <p>Find available medications across pharmacies</p>
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
                                <p><?php echo isset($order['medications']) && is_array($order['medications']) ? count($order['medications']) : 0; ?> medication(s)</p>
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
