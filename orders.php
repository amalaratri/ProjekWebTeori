<?php
session_start();

// Check if user is logged in and is a pharmacy
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacy') {
    header("Location: login.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update order status
    if (isset($_POST['update_status'])) {
        $orderId = (int)$_POST['order_id'];
        $newStatus = $_POST['new_status'];
        
        foreach ($_SESSION['orders'] as &$order) {
            if ($order['id'] === $orderId) {
                $order['status'] = $newStatus;
                break;
            }
        }
        
        $successMessage = "Order status updated successfully!";
    }
}

// Get medication by ID
function getMedicationById($id) {
    foreach ($_SESSION['medications'] as $medication) {
        if ($medication['id'] === $id) {
            return $medication;
        }
    }
    return [
        'name' => 'Unknown',
        'unit' => 'unit',
        'price' => 0
    ];
}

// Calculate order total
function calculateOrderTotal($order) {
    $total = 0;
    foreach ($order['items'] as $item) {
        $medication = getMedicationById($item['medicationId']);
        $total += $medication['price'] * $item['quantity'];
    }
    return $total;
}

// Get status label
function getStatusLabel($status) {
    switch ($status) {
        case 'new':
            return 'New';
        case 'preparing':
            return 'Preparing';
        case 'ready':
            return 'Ready for Pickup';
        case 'completed':
            return 'Completed';
        default:
            return ucfirst($status);
    }
}

// Get status class
function getStatusClass($status) {
    switch ($status) {
        case 'new':
            return 'status-new';
        case 'preparing':
            return 'status-preparing';
        case 'ready':
            return 'status-ready';
        case 'completed':
            return 'status-completed';
        default:
            return '';
    }
}

// Get order by ID
function getOrderById($id) {
    foreach ($_SESSION['orders'] as $order) {
        if ($order['id'] === $id) {
            return $order;
        }
    }
    return null;
}

// View order details
$viewOrderId = isset($_GET['view']) ? (int)$_GET['view'] : null;
$viewOrder = $viewOrderId ? getOrderById($viewOrderId) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - PharmaSys</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="content">
            <h1>Orders</h1>
            
            <?php if (isset($successMessage)): ?>
                <div class="alert success">
                    <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($viewOrder): ?>
                <!-- Order Details View -->
                <div class="back-link">
                    <a href="orders.php">&larr; Back to Orders</a>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Order #<?php echo $viewOrder['id']; ?> Details</h2>
                        <div class="status-badge <?php echo getStatusClass($viewOrder['status']); ?>">
                            <?php echo getStatusLabel($viewOrder['status']); ?>
                        </div>
                    </div>
                    
                    <div class="card-content">
                        <div class="order-info">
                            <div class="info-group">
                                <label>Doctor:</label>
                                <span><?php echo htmlspecialchars($viewOrder['doctorName']); ?></span>
                            </div>
                            
                            <div class="info-group">
                                <label>Patient:</label>
                                <span><?php echo htmlspecialchars($viewOrder['patientName']); ?></span>
                            </div>
                            
                            <div class="info-group">
                                <label>Date:</label>
                                <span><?php echo $viewOrder['date']; ?></span>
                            </div>
                            
                            <div class="info-group">
                                <form action="" method="POST">
                                    <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                                    <label for="new_status">Status:</label>
                                    <select id="new_status" name="new_status" onchange="this.form.submit()">
                                        <option value="new" <?php echo $viewOrder['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                        <option value="preparing" <?php echo $viewOrder['status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                        <option value="ready" <?php echo $viewOrder['status'] === 'ready' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                        <option value="completed" <?php echo $viewOrder['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </div>
                        </div>
                        
                        <h3>Order Items</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Medication</th>
                                    <th class="text-right">Quantity</th>
                                    <th class="text-right">Unit Price</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($viewOrder['items'] as $item): ?>
                                    <?php $medication = getMedicationById($item['medicationId']); ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($medication['name']); ?></td>
                                        <td class="text-right"><?php echo $item['quantity']; ?></td>
                                        <td class="text-right">Rp <?php echo number_format($medication['price']); ?></td>
                                        <td class="text-right">Rp <?php echo number_format($medication['price'] * $item['quantity']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="3" class="text-right"><strong>Total</strong></td>
                                    <td class="text-right"><strong>Rp <?php echo number_format(calculateOrderTotal($viewOrder)); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <!-- Orders List View -->
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Doctor</th>
                                <th>Patient</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($_SESSION['orders']) > 0): ?>
                                <?php foreach ($_SESSION['orders'] as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['doctorName']); ?></td>
                                        <td><?php echo htmlspecialchars($order['patientName']); ?></td>
                                        <td><?php echo $order['date']; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusClass($order['status']); ?>">
                                                <?php echo getStatusLabel($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <a href="?view=<?php echo $order['id']; ?>" class="btn small">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No orders found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
