
<?php
session_start();

require_once 'config/database.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['doctor_data'])) {
    header("Location: login.php");
    exit();
}

$doctor = $_SESSION['doctor_data'];

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
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug output for order count and IDs
// Remove or comment out after verification
// echo "<pre>Orders count: " . count($orders) . "\nOrder IDs: " . implode(', ', array_column($orders, 'id')) . "</pre>";
} catch (PDOException $e) {
    $orders = [];
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = htmlspecialchars(trim($_POST['new_status']));

    // Update order status in database
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$newStatus, $orderId, $doctor_id]);
        $successMessage = "Order status updated successfully!";

        // Refresh orders list after update
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE doctor_id = ? ORDER BY order_date DESC");
        $stmt->execute([$doctor_id]);
        $orders = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errorMessage = "Failed to update order status.";
    }
}

// Handle new order creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $patientName = htmlspecialchars(trim($_POST['patient_name']));
    $medications = $_POST['medications']; // Expecting array of medication names

    if (empty($patientName) || empty($medications) || !is_array($medications)) {
        $errorMessage = "Please fill in all required fields and add at least one medication.";
    } else {
        try {
            // Generate order number using stored procedure
            $stmtOrderNum = $pdo->prepare("CALL GenerateOrderNumber(@new_order_number)");
            $stmtOrderNum->execute();
            $stmtSelect = $pdo->query("SELECT @new_order_number AS order_number");
            $orderNumberRow = $stmtSelect->fetch(PDO::FETCH_ASSOC);
            $orderNumber = $orderNumberRow['order_number'] ?? null;

            if (!$orderNumber) {
                throw new Exception("Failed to generate order number.");
            }

            // Insert new order with generated order number
            $stmt = $pdo->prepare("INSERT INTO orders (order_number, doctor_id, pharmacy_id, patient_name, status, order_date) VALUES (?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$orderNumber, $doctor_id, $_POST['pharmacy_id'], $patientName]);
            $orderId = $pdo->lastInsertId();

            // Insert medications for the order
            $stmtMed = $pdo->prepare("INSERT INTO order_items (order_id, medication_id, quantity, unit_price, total_price, dosage_instructions, duration_days) VALUES (?, ?, 1, 0, 0, '', NULL)");
            foreach ($medications as $medicationId) {
                if (!empty($medicationId)) {
                    $stmtMed->execute([$orderId, $medicationId]);
                }
            }

            $successMessage = "New order created successfully!";

            // Refresh orders list after insertion
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE doctor_id = ? ORDER BY order_date DESC");
            $stmt->execute([$doctor_id]);
            $orders = $stmt->fetchAll();
        } catch (PDOException $e) {
            $errorMessage = "Error creating order: " . $e->getMessage();
        }
    }
}

// Determine if viewing a single order
$viewOrderId = isset($_GET['view']) ? (int)$_GET['view'] : null;
$viewOrder = null;

if ($viewOrderId) {
    // Fetch detailed order info including pharmacy and order items
    try {
        $stmt = $pdo->prepare("SELECT o.*, p.pharmacy_name FROM orders o JOIN pharmacies p ON o.pharmacy_id = p.id WHERE o.id = ? AND o.doctor_id = ?");
        $stmt->execute([$viewOrderId, $doctor_id]);
        $viewOrder = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($viewOrder) {
            // Fetch order items with medication details
            $stmtItems = $pdo->prepare("SELECT oi.*, m.name AS medication_name FROM order_items oi JOIN medications m ON oi.medication_id = m.id WHERE oi.order_id = ?");
            $stmtItems->execute([$viewOrderId]);
            $orderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $viewOrder = null;
            $orderItems = [];
        }
    } catch (PDOException $e) {
        $viewOrder = null;
        $orderItems = [];
    }
} else {
    $orderItems = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Doctor Orders - PharmaSys</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
    <div class="container">
        <?php include 'doctor_sidebar.php'; ?>

        <main class="content">
            <h1>My Orders</h1>

            <div class="actions">
                <button id="openNewOrderModal" class="btn primary">Add New Order</button>
            </div>

            <!-- New Order Modal -->
            <div id="newOrderModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span id="closeNewOrderModal" class="close">&times;</span>
                    <h2>Create New Order</h2>
                    <form id="newOrderForm" method="POST" action="doctor_orders.php">
                        <input type="hidden" name="create_order" value="1" />
                        <div class="form-group">
                            <label for="patient_name">Patient Name</label>
                            <input type="text" id="patient_name" name="patient_name" required />
                        </div>
                        <div class="form-group">
                            <label for="pharmacy_id">Select Pharmacy</label>
                            <select id="pharmacy_id" name="pharmacy_id" required>
                                <option value="">-- Select Pharmacy --</option>
                                <?php
                                // Fetch pharmacies for dropdown
                                try {
                                    $stmtPharmacies = $pdo->prepare("SELECT id, pharmacy_name FROM pharmacies ORDER BY pharmacy_name ASC");
                                    $stmtPharmacies->execute();
                                    $pharmaciesList = $stmtPharmacies->fetchAll();
                                    foreach ($pharmaciesList as $pharmacy) {
                                        echo '<option value="' . htmlspecialchars($pharmacy['id']) . '">' . htmlspecialchars($pharmacy['pharmacy_name']) . '</option>';
                                    }
                                } catch (PDOException $e) {
                                    echo '<option value="">Error loading pharmacies</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Medications</label>
                            <div id="medications-container">
                                <select name="medications[]" required>
                                    <option value="">-- Select Medication --</option>
                                    <?php
                                    // Fetch medications for dropdown
                                    try {
                                        $stmtMeds = $pdo->prepare("SELECT id, name FROM medications ORDER BY name ASC");
                                        $stmtMeds->execute();
                                        $medicationsList = $stmtMeds->fetchAll();
                                        foreach ($medicationsList as $med) {
                                            echo '<option value="' . htmlspecialchars($med['id']) . '">' . htmlspecialchars($med['name']) . '</option>';
                                        }
                                    } catch (PDOException $e) {
                                        echo '<option value="">Error loading medications</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="button" id="addMedicationBtn">Add Another Medication</button>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn primary">Create Order</button>
                            <button type="button" id="cancelNewOrderBtn" class="btn">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                const modal = document.getElementById('newOrderModal');
                const openBtn = document.getElementById('openNewOrderModal');
                const closeBtn = document.getElementById('closeNewOrderModal');
                const cancelBtn = document.getElementById('cancelNewOrderBtn');
                const addMedBtn = document.getElementById('addMedicationBtn');
                const medsContainer = document.getElementById('medications-container');

                openBtn.onclick = function() {
                    modal.style.display = 'block';
                }
                closeBtn.onclick = function() {
                    modal.style.display = 'none';
                }
                cancelBtn.onclick = function() {
                    modal.style.display = 'none';
                }
                window.onclick = function(event) {
                    if (event.target == modal) {
                        modal.style.display = 'none';
                    }
                }
                addMedBtn.onclick = function() {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = 'medications[]';
                    input.placeholder = 'Medication name';
                    input.required = true;
                    medsContainer.appendChild(input);
                }
            </script>

            <?php if ($successMessage): ?>
                <div class="alert success"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert error"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <?php if ($viewOrder): ?>
                <div class="back-link">
                    <a href="doctor_orders.php">&larr; Back to Orders</a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Order #<?php echo htmlspecialchars($viewOrder['id']); ?> Details</h2>
                        <div class="status-badge <?php echo getStatusClass($viewOrder['status']); ?>">
                            <?php echo getStatusLabel($viewOrder['status']); ?>
                        </div>
                    </div>

            <div class="card-content">
                <div class="order-info">
                    <div class="info-group">
                        <label>Order Number:</label>
                        <span><?php echo htmlspecialchars($viewOrder['order_number']); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Patient Name:</label>
                        <span><?php echo htmlspecialchars($viewOrder['patient_name']); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Patient Age:</label>
                        <span><?php echo htmlspecialchars($viewOrder['patient_age']); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Patient Gender:</label>
                        <span><?php echo htmlspecialchars(ucfirst($viewOrder['patient_gender'])); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Patient Phone:</label>
                        <span><?php echo htmlspecialchars($viewOrder['patient_phone']); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Pharmacy:</label>
                        <span><?php echo htmlspecialchars($viewOrder['pharmacy_name']); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Diagnosis:</label>
                        <span><?php echo nl2br(htmlspecialchars($viewOrder['diagnosis'])); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Notes:</label>
                        <span><?php echo nl2br(htmlspecialchars($viewOrder['notes'])); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Status:</label>
                        <span><?php echo ucfirst(htmlspecialchars($viewOrder['status'])); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Order Date:</label>
                        <span><?php echo htmlspecialchars($viewOrder['order_date']); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Confirmed At:</label>
                        <span><?php echo htmlspecialchars($viewOrder['confirmed_at'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Completed At:</label>
                        <span><?php echo htmlspecialchars($viewOrder['completed_at'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-group">
                        <form action="doctor_orders.php?view=<?php echo $viewOrder['id']; ?>" method="POST">
                            <label for="new_status">Update Status:</label>
                            <select id="new_status" name="new_status" onchange="this.form.submit()">
                                <option value="pending" <?php echo $viewOrder['status'] === 'pending' ? 'selected' : ''; ?>>New Order</option>
                                <option value="confirmed" <?php echo $viewOrder['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="preparing" <?php echo $viewOrder['status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                <option value="ready" <?php echo $viewOrder['status'] === 'ready' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                <option value="completed" <?php echo $viewOrder['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $viewOrder['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>" />
                            <input type="hidden" name="update_status" value="1" />
                        </form>
                    </div>
                </div>
                <div class="order-items">
                    <h3>Medications</h3>
                    <?php if (!empty($orderItems)): ?>
                        <ul>
                            <?php foreach ($orderItems as $item): ?>
                                <li>
                                    <?php echo htmlspecialchars($item['medication_name']); ?> - Quantity: <?php echo htmlspecialchars($item['quantity']); ?>
                                    <?php if (!empty($item['dosage_instructions'])): ?>
                                        <br /><small>Instructions: <?php echo nl2br(htmlspecialchars($item['dosage_instructions'])); ?></small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No medications found for this order.</p>
                    <?php endif; ?>
                </div>
            </div>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Patient Name</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($orders)): ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['id']); ?></td>
                                        <td><?php echo htmlspecialchars($order['patient_name']); ?></td>
                                        <td>
                                            <form method="POST" action="doctor_orders.php" class="inline-status-form">
                                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>" />
                                                <select name="new_status" onchange="this.form.submit()" class="status-select">
                                                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                    <option value="preparing" <?php echo $order['status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                                    <option value="ready" <?php echo $order['status'] === 'ready' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                                    <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1" />
                                            </form>
                                        </td>
                                        <td class="actions">
                                            <a href="doctor_orders.php?view=<?php echo htmlspecialchars($order['id']); ?>" class="btn small">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No orders found.</td>
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
