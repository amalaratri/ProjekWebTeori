<?php
session_start();

// Check if user is logged in and is a pharmacy
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacy') {
    header("Location: login.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new medication
    if (isset($_POST['add_medication'])) {
        $newId = 1;
        if (count($_SESSION['medications']) > 0) {
            $ids = array_column($_SESSION['medications'], 'id');
            $newId = max($ids) + 1;
        }
        
        $newMedication = [
            'id' => $newId,
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'unit' => $_POST['unit'],
            'price' => (float)$_POST['price'],
            'stock' => (int)$_POST['stock']
        ];
        
        $_SESSION['medications'][] = $newMedication;
        $successMessage = "Medication added successfully!";
    }
    
    // Update stock
    if (isset($_POST['update_stock'])) {
        $id = (int)$_POST['medication_id'];
        $newStock = (int)$_POST['new_stock'];
        
        foreach ($_SESSION['medications'] as &$medication) {
            if ($medication['id'] === $id) {
                $medication['stock'] = $newStock;
                break;
            }
        }
        
        $successMessage = "Stock updated successfully!";
    }
    
    // Delete medication
    if (isset($_POST['delete_medication'])) {
        $id = (int)$_POST['medication_id'];
        
        foreach ($_SESSION['medications'] as $key => $medication) {
            if ($medication['id'] === $id) {
                unset($_SESSION['medications'][$key]);
                break;
            }
        }
        
        // Re-index the array
        $_SESSION['medications'] = array_values($_SESSION['medications']);
        
        $successMessage = "Medication deleted successfully!";
    }
}

// Get medication by ID
function getMedicationById($id) {
    foreach ($_SESSION['medications'] as $medication) {
        if ($medication['id'] === $id) {
            return $medication;
        }
    }
    return null;
}

// Search functionality
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$filteredMedications = $_SESSION['medications'];

if (!empty($searchTerm)) {
    $filteredMedications = array_filter($_SESSION['medications'], function($medication) use ($searchTerm) {
        return (stripos($medication['name'], $searchTerm) !== false || 
                stripos($medication['description'], $searchTerm) !== false);
    });
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
            
            <?php if (isset($successMessage)): ?>
                <div class="alert success">
                    <?php echo $successMessage; ?>
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
                            <th>Description</th>
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
                                    <td><?php echo htmlspecialchars($medication['description']); ?></td>
                                    <td><?php echo htmlspecialchars($medication['unit']); ?></td>
                                    <td class="text-right"><?php echo number_format($medication['price']); ?></td>
                                    <td class="text-right <?php echo $medication['stock'] < 20 ? 'low-stock' : ''; ?>">
                                        <?php echo $medication['stock']; ?>
                                    </td>
                                    <td class="actions">
                                        <button class="btn small" onclick="openUpdateStockModal(<?php echo $medication['id']; ?>, '<?php echo htmlspecialchars($medication['name']); ?>', <?php echo $medication['stock']; ?>)">
                                            Update Stock
                                        </button>
                                        <button class="btn small danger" onclick="confirmDelete(<?php echo $medication['id']; ?>, '<?php echo htmlspecialchars($medication['name']); ?>')">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No medications found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Add Medication Modal -->
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
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="unit">Unit</label>
                                <input type="text" id="unit" name="unit" placeholder="tablet, bottle, etc." required>
                            </div>
                            
                            <div class="form-group">
                                <label for="price">Price (Rp)</label>
                                <input type="number" id="price" name="price" min="0" required>
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
            
            <!-- Update Stock Modal -->
            <div id="updateStockModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="document.getElementById('updateStockModal').style.display='none'">&times;</span>
                    <h2>Update Stock</h2>
                    <form action="" method="POST">
                        <input type="hidden" id="update_medication_id" name="medication_id">
                        
                        <div class="form-group">
                            <label for="medication_name">Medication</label>
                            <input type="text" id="medication_name" readonly>
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
            
            <!-- Delete Confirmation Modal -->
            <div id="deleteModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="document.getElementById('deleteModal').style.display='none'">&times;</span>
                    <h2>Confirm Deletion</h2>
                    <p>Are you sure you want to delete <span id="delete_medication_name"></span>?</p>
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
                    document.getElementById('medication_name').value = name;
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
