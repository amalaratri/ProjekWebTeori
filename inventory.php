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

$pharmacy_id = $_SESSION['pharmacy_id']; // Get the logged-in pharmacy's ID

$successMessage = '';
$errorMessage = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- Add new medication ---
        if (isset($_POST['add_medication'])) {
            $name = htmlspecialchars(trim($_POST['name']));
            $description = htmlspecialchars(trim($_POST['description']));
            $unit = htmlspecialchars(trim($_POST['unit']));
            $price = (float)$_POST['price'];
            $stock = (int)$_POST['stock'];
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null; 
            $generic_name = htmlspecialchars(trim($_POST['generic_name'] ?? '')); // Added
            $dosage_form = htmlspecialchars(trim($_POST['dosage_form'] ?? '')); // Added
            $strength = htmlspecialchars(trim($_POST['strength'] ?? '')); // Added
            $manufacturer = htmlspecialchars(trim($_POST['manufacturer'] ?? '')); // Added
            $requires_prescription = isset($_POST['requires_prescription']) ? 1 : 0; // Added

            // Start transaction
            $pdo->beginTransaction();

            // 1. Check if medication exists in the global 'medications' table
            $stmt = $pdo->prepare("SELECT id FROM medications WHERE name = ? LIMIT 1");
            $stmt->execute([$name]);
            $medication = $stmt->fetch();

            $medication_id;
            if ($medication) {
                $medication_id = $medication['id'];
                // If medication exists globally, you might update its generic info if changed,
                // but generally, these fields are considered stable for an existing medication.
                // For now, we'll just use the existing ID.
            } else {
                // Insert into global 'medications' table if it's a new medication
                $stmt = $pdo->prepare("INSERT INTO medications (name, generic_name, category_id, description, dosage_form, strength, manufacturer, requires_prescription) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $generic_name, $category_id, $description, $dosage_form, $strength, $manufacturer, $requires_prescription]); 
                $medication_id = $pdo->lastInsertId();
            }

            // 2. Check if this medication already exists in THIS pharmacy's inventory
            $stmt = $pdo->prepare("SELECT id, stock_quantity FROM pharmacy_inventory WHERE pharmacy_id = ? AND medication_id = ? LIMIT 1");
            $stmt->execute([$pharmacy_id, $medication_id]);
            $inventory_item = $stmt->fetch();

            if ($inventory_item) {
                // If it exists, update stock and unit price (or just stock)
                $newStock = $inventory_item['stock_quantity'] + $stock;
                $stmt = $pdo->prepare("UPDATE pharmacy_inventory SET stock_quantity = ?, unit_price = ?, updated_at = CURRENT_TIMESTAMP, last_restocked = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$newStock, $price, $inventory_item['id']]);
                // Record stock movement (in)
                $stmt = $pdo->prepare("INSERT INTO stock_movements (pharmacy_id, medication_id, movement_type, quantity, reference_type, created_by, notes) VALUES (?, ?, 'in', ?, 'purchase', ?, ?)");
                $stmt->execute([$pharmacy_id, $medication_id, $stock, $_SESSION['user_id'], 'Added/Restocked via inventory add form']);
                $successMessage = "Medication stock updated successfully!";
            } else {
                // If it doesn't exist for this pharmacy, add to pharmacy_inventory
                $stmt = $pdo->prepare("INSERT INTO pharmacy_inventory (pharmacy_id, medication_id, unit_price, stock_quantity, unit, last_restocked) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$pharmacy_id, $medication_id, $price, $stock, $unit]);
                // Record stock movement (in)
                $stmt = $pdo->prepare("INSERT INTO stock_movements (pharmacy_id, medication_id, movement_type, quantity, reference_type, created_by, notes) VALUES (?, ?, 'in', ?, 'purchase', ?, ?)");
                $stmt->execute([$pharmacy_id, $medication_id, $stock, $_SESSION['user_id'], 'New medication added to inventory']);
                $successMessage = "Medication added successfully to inventory!";
            }
            $pdo->commit();
        }
        
        // --- Update stock ---
        if (isset($_POST['update_stock'])) {
            $inventory_item_id = (int)$_POST['medication_id']; // This is actually pharmacy_inventory.id
            $newStock = (int)$_POST['new_stock'];

            $pdo->beginTransaction();

            // Get current stock to calculate movement quantity
            $stmt = $pdo->prepare("SELECT stock_quantity, medication_id FROM pharmacy_inventory WHERE id = ? AND pharmacy_id = ?");
            $stmt->execute([$inventory_item_id, $pharmacy_id]);
            $current_inventory = $stmt->fetch();

            if ($current_inventory) {
                $oldStock = $current_inventory['stock_quantity'];
                $medication_id = $current_inventory['medication_id'];
                $quantityChange = $newStock - $oldStock;

                $stmt = $pdo->prepare("UPDATE pharmacy_inventory SET stock_quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND pharmacy_id = ?");
                $stmt->execute([$newStock, $inventory_item_id, $pharmacy_id]);

                // Record stock movement
                if ($quantityChange !== 0) {
                    $movementType = $quantityChange > 0 ? 'in' : 'out';
                    $referenceType = 'adjustment'; 
                    $notes = 'Stock adjusted via inventory update form.';
                    
                    if ($quantityChange > 0) {
                        $notes = 'Restocked via inventory update form.';
                    } else {
                        $notes = 'Stock reduced via inventory update form.';
                    }

                    $stmt = $pdo->prepare("INSERT INTO stock_movements (pharmacy_id, medication_id, movement_type, quantity, reference_type, created_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$pharmacy_id, $medication_id, $movementType, abs($quantityChange), $referenceType, $_SESSION['user_id'], $notes]);
                }

                $pdo->commit();
                $successMessage = "Stock updated successfully!";
            } else {
                $errorMessage = "Medication not found in your inventory.";
                $pdo->rollBack();
            }
        }
        
        // --- Delete medication from THIS pharmacy's inventory ---
        if (isset($_POST['delete_medication'])) {
            $inventory_item_id = (int)$_POST['medication_id']; // This is pharmacy_inventory.id

            $pdo->beginTransaction();

            // Get medication_id and quantity before deleting for stock movement record
            $stmt = $pdo->prepare("SELECT medication_id, stock_quantity FROM pharmacy_inventory WHERE id = ? AND pharmacy_id = ?");
            $stmt->execute([$inventory_item_id, $pharmacy_id]);
            $item_to_delete = $stmt->fetch();

            if ($item_to_delete) {
                $medication_id_to_delete = $item_to_delete['medication_id'];
                $quantity_deleted = $item_to_delete['stock_quantity'];

                $stmt = $pdo->prepare("DELETE FROM pharmacy_inventory WHERE id = ? AND pharmacy_id = ?");
                $stmt->execute([$inventory_item_id, $pharmacy_id]);

                // Record stock movement (out due to removal)
                $stmt = $pdo->prepare("INSERT INTO stock_movements (pharmacy_id, medication_id, movement_type, quantity, reference_type, created_by, notes) VALUES (?, ?, 'out', ?, 'adjustment', ?, ?)");
                $stmt->execute([$pharmacy_id, $medication_id_to_delete, $quantity_deleted, $_SESSION['user_id'], 'Removed from inventory']);

                $pdo->commit();
                $successMessage = "Medication deleted from your inventory successfully!";
            } else {
                $errorMessage = "Medication not found in your inventory.";
                $pdo->rollBack();
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = "Database error: " . $e->getMessage();
        error_log("Inventory operation error for pharmacy ID " . $pharmacy_id . ": " . $e->getMessage());
    }
    // Redirect to prevent form resubmission on refresh
    if (!empty($successMessage) || !empty($errorMessage)) {
        $_SESSION['form_message'] = ['type' => (!empty($successMessage) ? 'success' : 'error'), 'message' => (!empty($successMessage) ? $successMessage : $errorMessage)];
        header("Location: inventory.php");
        exit();
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

// --- Fetch all medications for this pharmacy's inventory ---
$filteredMedications = [];
$searchTerm = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search'])) : '';

try {
    $sql = "SELECT 
                pi.id AS inventory_id, 
                m.id AS medication_global_id,
                m.name, 
                m.generic_name,             
                mc.name AS category_name,   
                m.description, 
                m.dosage_form,              
                m.strength,                 
                m.manufacturer,             
                m.requires_prescription,    
                pi.unit, 
                pi.unit_price AS price, 
                pi.stock_quantity AS stock
            FROM pharmacy_inventory pi
            JOIN medications m ON pi.medication_id = m.id
            LEFT JOIN medication_categories mc ON m.category_id = mc.id
            WHERE pi.pharmacy_id = ?";
    
    $params = [$pharmacy_id];

    if (!empty($searchTerm)) {
        $sql .= " AND (m.name LIKE ? OR m.description LIKE ? OR m.generic_name LIKE ? OR m.manufacturer LIKE ?)";
        $params[] = '%' . $searchTerm . '%';
        $params[] = '%' . $searchTerm . '%';
        $params[] = '%' . $searchTerm . '%';
        $params[] = '%' . $searchTerm . '%'; 
    }

    $sql .= " ORDER BY m.name ASC"; 

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filteredMedications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errorMessage = "Error loading inventory data: " . $e->getMessage();
    error_log("Inventory data load error for pharmacy ID " . $pharmacy_id . ": " . $e->getMessage());
}

// Fetch medication categories for the 'Add Medication' modal
$medicationCategories = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM medication_categories ORDER BY name");
    $medicationCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching medication categories: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - PharmaSys</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="content">
            <h1>Medication Inventory</h1>
            
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
            
            <div class="actions-bar">
                <form action="" method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Search medications..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn">Search</button>
                </form>
                
                <button class="btn primary" onclick="document.getElementById('addMedicationModal').style.display='block'">
                    Add Medication
                </button>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Name</th>
                            <th>Generic Name</th> 
                            <th>Category</th>           
                            <th>Description</th>
                            <th>Dosage Form</th>        
                            <th>Strength</th>           
                            <th>Manufacturer</th>       
                            <th>Rx Required?</th>       
                            <th>Unit</th>
                            <th>Price (Rp)</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($filteredMedications) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($filteredMedications as $medication): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($medication['name']); ?></td>
                                    <td><?php echo htmlspecialchars($medication['generic_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($medication['category_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($medication['description']); ?></td>
                                    <td><?php echo htmlspecialchars($medication['dosage_form'] ?? '-'); ?></td> 
                                    <td><?php echo htmlspecialchars($medication['strength'] ?? '-'); ?></td>     
                                    <td><?php echo htmlspecialchars($medication['manufacturer'] ?? '-'); ?></td> 
                                    <td><?php echo ($medication['requires_prescription'] ?? 0) ? 'Yes' : 'No'; ?></td> 
                                    <td><?php echo htmlspecialchars($medication['unit']); ?></td>
                                    <td class="text-right"><?php echo number_format($medication['price']); ?></td>
                                    <td class="text-right <?php echo $medication['stock'] < 20 ? 'low-stock' : ''; ?>">
                                        <?php echo $medication['stock']; ?>
                                    </td>
                                    <td class="actions">
                                        <button class="btn small" onclick="openUpdateStockModal(<?php echo $medication['inventory_id']; ?>, '<?php echo htmlspecialchars($medication['name']); ?>', <?php echo $medication['stock']; ?>)">
                                            Update Stock
                                        </button>
                                        <button class="btn small danger" onclick="confirmDelete(<?php echo $medication['inventory_id']; ?>, '<?php echo htmlspecialchars($medication['name']); ?>')">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="text-center">No medications found in your inventory.</td> 
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="addMedicationModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="document.getElementById('addMedicationModal').style.display='none'">&times;</span>
                    <h2>Add New Medication</h2>
                    <form action="" method="POST">
                        <div class="form-group">
                            <label for="name">Medication Name</label>
                            <input type="text" id="name" name="name" required>
                        </div>

                        <div class="form-group">
                            <label for="generic_name">Generic Name</label>
                            <input type="text" id="generic_name" name="generic_name">
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id">Category</label>
                            <select id="category_id" name="category_id">
                                <option value="">Select a Category (Optional)</option>
                                <?php foreach ($medicationCategories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['id']); ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="dosage_form">Dosage Form</label>
                                <input type="text" id="dosage_form" name="dosage_form" placeholder="e.g., Tablet, Syrup, Capsule">
                            </div>
                            <div class="form-group">
                                <label for="strength">Strength</label>
                                <input type="text" id="strength" name="strength" placeholder="e.g., 500mg, 10mg/5ml">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="manufacturer">Manufacturer</label>
                            <input type="text" id="manufacturer" name="manufacturer">
                        </div>

                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="requires_prescription" name="requires_prescription" value="1">
                            <label for="requires_prescription">Requires Prescription</label>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="unit">Unit</label>
                                <input type="text" id="unit" name="unit" placeholder="tablet, bottle, etc." required>
                            </div>
                            
                            <div class="form-group">
                                <label for="price">Price (Rp)</label>
                                <input type="number" id="price" name="price" min="0" step="any" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="stock">Initial Stock</label>
                            <input type="number" id="stock" name="stock" min="0" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn" onclick="document.getElementById('addMedicationModal').style.display='none'">Cancel</button>
                            <button type="submit" name="add_medication" class="btn primary">Add Medication</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="updateStockModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="document.getElementById('updateStockModal').style.display='none'">&times;</span>
                    <h2>Update Stock</h2>
                    <form action="" method="POST">
                        <input type="hidden" id="update_medication_id" name="medication_id">
                        
                        <div class="form-group">
                            <label for="medication_name_display">Medication</label>
                            <input type="text" id="medication_name_display" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_stock">New Stock Quantity</label>
                            <input type="number" id="new_stock" name="new_stock" min="0" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn" onclick="document.getElementById('updateStockModal').style.display='none'">Cancel</button>
                            <button type="submit" name="update_stock" class="btn primary">Update Stock</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="deleteModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="document.getElementById('deleteModal').style.display='none'">&times;</span>
                    <h2>Confirm Deletion</h2>
                    <p>Are you sure you want to delete <span id="delete_medication_name"></span> from your inventory?</p>
                    <p>This action cannot be undone.</p>
                    
                    <form action="" method="POST">
                        <input type="hidden" id="delete_medication_id" name="medication_id">
                        
                        <div class="form-actions">
                            <button type="button" class="btn" onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
                            <button type="submit" name="delete_medication" class="btn danger">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
                function openUpdateStockModal(id, name, stock) {
                    document.getElementById('update_medication_id').value = id;
                    document.getElementById('medication_name_display').value = name; 
                    document.getElementById('new_stock').value = stock;
                    document.getElementById('updateStockModal').style.display = 'block';
                }
                
                function confirmDelete(id, name) {
                    document.getElementById('delete_medication_id').value = id;
                    document.getElementById('delete_medication_name').textContent = name;
                    document.getElementById('deleteModal').style.display = 'block';
                }
                
                // Close modal when clicking outside
                window.onclick = function(event) {
                    if (event.target.className === 'modal') {
                        event.target.style.display = 'none';
                    }
                }
            </script>
        </main>
    </div>
</body>
</html>
