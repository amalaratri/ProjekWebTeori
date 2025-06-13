<?php
session_start();

// Include your Database class file
require_once 'config/database.php'; 

// Instantiate the Database class and get the PDO connection object
$database = new Database();
$pdo = $database->getConnection();

// Check if user is logged in and is a pharmacy, and if DB connection is successful
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacy' || $pdo === null) {
    header("Location: login.php");
    exit();
}

$pharmacy_id = $_SESSION['pharmacy_id']; // Get the logged-in pharmacy's ID
$user_id = $_SESSION['user_id']; // Get the logged-in user's ID for logging status changes

$successMessage = '';
$errorMessage = '';

// --- Helper Functions (Database-driven) ---

// Get Medication details by ID from the 'medications' table
function getMedicationDetailsById($pdo, $medicationId) {
    try {
        $stmt = $pdo->prepare("SELECT name, unit FROM medications WHERE id = ? LIMIT 1");
        $stmt->execute([$medicationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching medication details: " . $e->getMessage());
        return ['name' => 'Unknown', 'unit' => 'unit']; // Fallback
    }
}

// Calculate order total from order items in the database
function calculateOrderTotal($pdo, $orderId) {
    try {
        $stmt = $pdo->prepare("SELECT SUM(total_price) FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        return (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error calculating order total: " . $e->getMessage());
        return 0.00;
    }
}

// Get status label (mapping database statuses to display labels)
function getStatusLabel($status) {
    switch ($status) {
        case 'pending':
            return 'New Order';
        case 'confirmed':
            return 'Confirmed';
        case 'preparing':
            return 'Preparing';
        case 'ready':
            return 'Ready for Pickup';
        case 'completed':
            return 'Completed';
        case 'cancelled':
            return 'Cancelled';
        default:
            return ucfirst($status);
    }
}

// Get status class for styling
function getStatusClass($status) {
    switch ($status) {
        case 'pending':
            return 'status-new';
        case 'confirmed':
            return 'status-confirmed';
        case 'preparing':
            return 'status-preparing';
        case 'ready':
            return 'status-ready';
        case 'completed':
            return 'status-completed';
        case 'cancelled':
            return 'status-cancelled';
        default:
            return '';
    }
}


// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update order status
    if (isset($_POST['update_status'])) {
        $orderId = (int)$_POST['order_id'];
        $newStatus = htmlspecialchars(trim($_POST['new_status']));
        
        try {
            $pdo->beginTransaction();

            // Get current status for logging
            $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND pharmacy_id = ? LIMIT 1");
            $stmt->execute([$orderId, $pharmacy_id]);
            $currentOrder = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($currentOrder) {
                $oldStatus = $currentOrder['status'];

                // Update order status in the 'orders' table
                $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND pharmacy_id = ?");
                $stmt->execute([$newStatus, $orderId, $pharmacy_id]);

                // The database trigger `tr_log_order_status_change` will handle inserting into `order_status_history`
                // However, the trigger uses a fixed `changed_by` (1). If you want to use the actual user,
                // you'd need to modify the trigger or insert manually here. For now, rely on the trigger.

                $pdo->commit();
                $successMessage = "Order status updated successfully!";
            } else {
                $errorMessage = "Order not found or does not belong to your pharmacy.";
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = "Error updating order status: " . $e->getMessage();
            error_log("Order status update error for order ID " . $orderId . ": " . $e->getMessage());
        }
        // Redirect to prevent form resubmission on refresh
        if (!empty($successMessage) || !empty($errorMessage)) {
            $_SESSION['form_message'] = ['type' => (!empty($successMessage) ? 'success' : 'error'), 'message' => (!empty($successMessage) ? $successMessage : $errorMessage)];
            header("Location: orders.php" . ($viewOrderId ? "?view=" . $viewOrderId : "")); // Stay on detail view if applicable
            exit();
        }
    }
}

// Display messages after redirect
if (isset($_SESSION['form_message'])) {
    if ($_SESSION['form_message']['type'] === 'success') {
        $successMessage = $_SESSION['form_message']['message'];
    } else {
        $errorMessage = $_SESSION['form_message']['message'];
    }
    unset($_SESSION['form_message']);
}

// --- Fetch Data for Display ---

// Determine if we are viewing a single order or the list
$viewOrderId = isset($_GET['view']) ? (int)$_GET['view'] : null;
$viewOrder = null;
$allOrders = [];

try {
    if ($viewOrderId) {
        // Fetch specific order details
        $stmt = $pdo->prepare("SELECT 
                                    o.id, o.order_number, o.patient_name, o.patient_age, o.patient_gender, o.patient_phone,
                                    o.diagnosis, o.notes, o.status, o.total_amount, o.order_date,
                                    d.full_name as doctorName, d.specialization as doctorSpecialization
                                FROM orders o
                                JOIN doctors d ON o.doctor_id = d.id
                                WHERE o.id = ? AND o.pharmacy_id = ? LIMIT 1");
        $stmt->execute([$viewOrderId, $pharmacy_id]);
        $viewOrder = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($viewOrder) {
            // Fetch order items for the detailed view
            $stmt = $pdo->prepare("SELECT oi.medication_id, oi.quantity, oi.unit_price, oi.total_price, oi.dosage_instructions, oi.duration_days 
                                   FROM order_items oi
                                   WHERE oi.order_id = ?");
            $stmt->execute([$viewOrder['id']]);
            $viewOrder['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Re-calculate total amount just in case, or trust the DB's total_amount
            // $viewOrder['calculated_total'] = calculateOrderTotal($pdo, $viewOrder['id']);
        } else {
            $errorMessage = "Order not found or you don't have permission to view it.";
            $viewOrderId = null; // Clear view ID to show list instead
        }

    } else {
        // Fetch all orders for the logged-in pharmacy for the list view
        $stmt = $pdo->prepare("SELECT 
                                    o.id, o.order_number, o.patient_name, o.order_date, o.status, o.total_amount,
                                    d.full_name as doctorName
                                FROM orders o
                                JOIN doctors d ON o.doctor_id = d.id
                                WHERE o.pharmacy_id = ?
                                ORDER BY o.order_date DESC");
        $stmt->execute([$pharmacy_id]);
        $allOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $errorMessage = "Error loading orders: " . $e->getMessage();
    error_log("Orders page data load error for pharmacy ID " . $pharmacy_id . ": " . $e->getMessage());
}

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
            
            <?php if (!empty($successMessage)): ?>
                <div class="alert success">
                    <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($errorMessage)): ?>
                <div class="alert error">
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($viewOrder): ?>
                <div class="back-link">
                    <a href="orders.php">&larr; Back to Orders</a>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Order #<?php echo htmlspecialchars($viewOrder['order_number']); ?> Details</h2>
                        <div class="status-badge <?php echo getStatusClass($viewOrder['status']); ?>">
                            <?php echo getStatusLabel($viewOrder['status']); ?>
                        </div>
                    </div>
                    
                    <div class="card-content">
                        <div class="order-info">
                            <div class="info-group">
                                <label>Doctor:</label>
                                <span><?php echo htmlspecialchars($viewOrder['doctorName']); ?> (<?php echo htmlspecialchars($viewOrder['doctorSpecialization']); ?>)</span>
                            </div>
                            
                            <div class="info-group">
                                <label>Patient Name:</label>
                                <span><?php echo htmlspecialchars($viewOrder['patient_name']); ?></span>
                            </div>
                            <div class="info-group">
                                <label>Patient Age/Gender:</label>
                                <span><?php echo htmlspecialchars($viewOrder['patient_age']); ?> / <?php echo htmlspecialchars(ucfirst($viewOrder['patient_gender'])); ?></span>
                            </div>
                            <div class="info-group">
                                <label>Patient Phone:</label>
                                <span><?php echo htmlspecialchars($viewOrder['patient_phone'] ?? '-'); ?></span>
                            </div>
                            
                            <div class="info-group">
                                <label>Diagnosis:</label>
                                <span><?php echo htmlspecialchars($viewOrder['diagnosis'] ?? '-'); ?></span>
                            </div>
                             <div class="info-group">
                                <label>Notes:</label>
                                <span><?php echo htmlspecialchars($viewOrder['notes'] ?? '-'); ?></span>
                            </div>

                            <div class="info-group">
                                <label>Order Date:</label>
                                <span><?php echo date('d M Y H:i', strtotime($viewOrder['order_date'])); ?></span>
                            </div>
                            
                            <div class="info-group">
                                <form action="" method="POST">
                                    <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                                    <label for="new_status">Update Status:</label>
                                    <select id="new_status" name="new_status" onchange="this.form.submit()">
                                        <option value="pending" <?php echo $viewOrder['status'] === 'pending' ? 'selected' : ''; ?>>New Order</option>
                                        <option value="confirmed" <?php echo $viewOrder['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="preparing" <?php echo $viewOrder['status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                        <option value="ready" <?php echo $viewOrder['status'] === 'ready' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                        <option value="completed" <?php echo $viewOrder['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $viewOrder['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                                    <th>Dosage Instruction</th>
                                    <th>Duration (Days)</th>
                                    <th class="text-right">Quantity</th>
                                    <th class="text-right">Unit Price</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($viewOrder['items'])): ?>
                                    <?php foreach ($viewOrder['items'] as $item): ?>
                                        <?php $medicationDetails = getMedicationDetailsById($pdo, $item['medication_id']); ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($medicationDetails['name'] ?? 'Unknown Medication'); ?></td>
                                            <td><?php echo htmlspecialchars($item['dosage_instructions'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($item['duration_days'] ?? '-'); ?></td>
                                            <td class="text-right"><?php echo htmlspecialchars($item['quantity']); ?> <?php echo htmlspecialchars($medicationDetails['unit'] ?? ''); ?></td>
                                            <td class="text-right">Rp <?php echo number_format($item['unit_price']); ?></td>
                                            <td class="text-right">Rp <?php echo number_format($item['total_price']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No items for this order.</td>
                                    </tr>
                                <?php endif; ?>
                                <tr class="total-row">
                                    <td colspan="5" class="text-right"><strong>Grand Total</strong></td>
                                    <td class="text-right"><strong>Rp <?php echo number_format($viewOrder['total_amount']); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Doctor</th>
                                <th>Patient</th>
                                <th>Order Date</th>
                                <th>Status</th>
                                <th>Total Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($allOrders) > 0): ?>
                                <?php foreach ($allOrders as $order): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($order['doctorName']); ?></td>
                                        <td><?php echo htmlspecialchars($order['patient_name']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($order['order_date'])); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusClass($order['status']); ?>">
                                                <?php echo getStatusLabel($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-right">Rp <?php echo number_format($order['total_amount']); ?></td>
                                        <td class="actions">
                                            <a href="?view=<?php echo $order['id']; ?>" class="btn small">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No orders found for your pharmacy.</td>
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