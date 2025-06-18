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

$successMessage = '';
$errorMessage = '';

// Handle prescription creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_prescription'])) {
    $patient_name = htmlspecialchars(trim($_POST['patient_name']));
    $patient_age = (int)$_POST['patient_age'];
    $patient_gender = htmlspecialchars(trim($_POST['patient_gender']));
    $patient_phone = htmlspecialchars(trim($_POST['patient_phone']));
    $pharmacy_id = (int)$_POST['pharmacy_id'];
    $diagnosis = htmlspecialchars(trim($_POST['diagnosis']));
    $notes = htmlspecialchars(trim($_POST['notes']));
    $medications = $_POST['medications'] ?? [];

    if (empty($patient_name) || empty($pharmacy_id) || empty($medications)) {
        $errorMessage = "Please fill in all required fields and add at least one medication.";
    } else {
        try {
            $pdo->beginTransaction();

            // Generate order number
            $stmt = $pdo->prepare("CALL GenerateOrderNumber(@new_order_number)");
            $stmt->execute();
            $stmt = $pdo->query("SELECT @new_order_number AS order_number");
            $orderResult = $stmt->fetch(PDO::FETCH_ASSOC);
            $orderNumber = $orderResult['order_number'];

            // Create order
            $stmt = $pdo->prepare("INSERT INTO orders (order_number, doctor_id, pharmacy_id, patient_name, patient_age, patient_gender, patient_phone, diagnosis, notes, status, order_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$orderNumber, $doctor_id, $pharmacy_id, $patient_name, $patient_age, $patient_gender, $patient_phone, $diagnosis, $notes]);
            $orderId = $pdo->lastInsertId();

            // Add medications to order
            $totalAmount = 0;
            foreach ($medications as $medication) {
                if (!empty($medication['medication_id']) && !empty($medication['quantity'])) {
                    // Get medication price from pharmacy inventory
                    $stmt = $pdo->prepare("SELECT unit_price FROM pharmacy_inventory WHERE pharmacy_id = ? AND medication_id = ?");
                    $stmt->execute([$pharmacy_id, $medication['medication_id']]);
                    $priceData = $stmt->fetch();
                    $unitPrice = $priceData ? $priceData['unit_price'] : 0;
                    $totalPrice = $unitPrice * $medication['quantity'];
                    $totalAmount += $totalPrice;

                    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, medication_id, quantity, unit_price, total_price, dosage_instructions, duration_days) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $orderId,
                        $medication['medication_id'],
                        $medication['quantity'],
                        $unitPrice,
                        $totalPrice,
                        $medication['dosage_instructions'] ?? '',
                        $medication['duration_days'] ?? null
                    ]);
                }
            }

            // Update total amount
            $stmt = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $stmt->execute([$totalAmount, $orderId]);

            $pdo->commit();
            $successMessage = "Prescription created successfully! Order number: " . $orderNumber;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errorMessage = "Error creating prescription: " . $e->getMessage();
        }
    }
}

// Fetch pharmacies
$pharmacies = [];
try {
    $stmt = $pdo->query("SELECT id, pharmacy_name, address FROM pharmacies WHERE is_verified = 1 ORDER BY pharmacy_name");
    $pharmacies = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching pharmacies: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Prescription - PharmaSys</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'doctor_sidebar.php'; ?>

        <main class="content">
            <h1>Create New Prescription</h1>

            <?php if ($successMessage): ?>
                <div class="alert success"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert error"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <div class="prescription-form-container">
                <form method="POST" id="prescriptionForm" class="prescription-form">
                    <input type="hidden" name="create_prescription" value="1">
                    
                    <!-- Patient Information -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Patient Information</h2>
                        </div>
                        <div class="card-content">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="patient_name">Patient Name *</label>
                                    <input type="text" id="patient_name" name="patient_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="patient_age">Age</label>
                                    <input type="number" id="patient_age" name="patient_age" min="0" max="150">
                                </div>
                                <div class="form-group">
                                    <label for="patient_gender">Gender</label>
                                    <select id="patient_gender" name="patient_gender">
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="patient_phone">Phone Number</label>
                                    <input type="text" id="patient_phone" name="patient_phone">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pharmacy Selection -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Select Pharmacy</h2>
                        </div>
                        <div class="card-content">
                            <div class="form-group">
                                <label for="pharmacy_id">Pharmacy *</label>
                                <select id="pharmacy_id" name="pharmacy_id" required onchange="loadPharmacyMedications()">
                                    <option value="">Select Pharmacy</option>
                                    <?php foreach ($pharmacies as $pharmacy): ?>
                                        <option value="<?php echo $pharmacy['id']; ?>">
                                            <?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?> - 
                                            <?php echo htmlspecialchars($pharmacy['address']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Medical Information -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Medical Information</h2>
                        </div>
                        <div class="card-content">
                            <div class="form-group">
                                <label for="diagnosis">Diagnosis *</label>
                                <textarea id="diagnosis" name="diagnosis" rows="3" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="notes">Additional Notes</label>
                                <textarea id="notes" name="notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Medications -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Medications</h2>
                            <p>Select medications available at the chosen pharmacy</p>
                        </div>
                        <div class="card-content">
                            <div id="medications-container">
                                <div class="medication-item" data-index="0">
                                    <div class="medication-grid">
                                        <div class="form-group">
                                            <label>Medication *</label>
                                            <select name="medications[0][medication_id]" class="medication-select" required>
                                                <option value="">Select pharmacy first</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Quantity *</label>
                                            <input type="number" name="medications[0][quantity]" min="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Duration (Days)</label>
                                            <input type="number" name="medications[0][duration_days]" min="1">
                                        </div>
                                        <div class="form-group full-width">
                                            <label>Dosage Instructions</label>
                                            <textarea name="medications[0][dosage_instructions]" rows="2" placeholder="e.g., Take 1 tablet twice daily after meals"></textarea>
                                        </div>
                                    </div>
                                    <div class="medication-actions">
                                        <button type="button" class="btn danger small" onclick="removeMedication(0)">Remove</button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="add-medication" class="btn btn-secondary">Add Another Medication</button>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="form-actions">
                        <a href="doctor_orders.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn primary">Create Prescription</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        let medicationIndex = 1;
        let availableMedications = [];

        // Load medications when pharmacy is selected
        async function loadPharmacyMedications() {
            const pharmacyId = document.getElementById('pharmacy_id').value;
            
            if (!pharmacyId) {
                // Clear all medication selects
                document.querySelectorAll('.medication-select').forEach(select => {
                    select.innerHTML = '<option value="">Select pharmacy first</option>';
                });
                return;
            }

            try {
                const response = await fetch(`get_pharmacy_medications.php?pharmacy_id=${pharmacyId}`);
                const medications = await response.json();
                availableMedications = medications;
                
                // Update all medication selects
                updateMedicationSelects();
                
            } catch (error) {
                console.error('Error loading medications:', error);
                alert('Error loading medications. Please try again.');
            }
        }

        function updateMedicationSelects() {
            document.querySelectorAll('.medication-select').forEach(select => {
                const currentValue = select.value;
                select.innerHTML = '<option value="">Select Medication</option>';
                
                availableMedications.forEach(med => {
                    const option = document.createElement('option');
                    option.value = med.medication_id;
                    option.textContent = `${med.name} - ${med.strength || ''} (Stock: ${med.stock_quantity}) - Rp ${parseInt(med.unit_price).toLocaleString()}`;
                    option.dataset.stock = med.stock_quantity;
                    option.dataset.price = med.unit_price;
                    if (currentValue == med.medication_id) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            });
        }

        // Add new medication row
        document.getElementById('add-medication').addEventListener('click', function() {
            const container = document.getElementById('medications-container');
            const newMedication = document.createElement('div');
            newMedication.className = 'medication-item';
            newMedication.dataset.index = medicationIndex;
            
            newMedication.innerHTML = `
                <div class="medication-grid">
                    <div class="form-group">
                        <label>Medication *</label>
                        <select name="medications[${medicationIndex}][medication_id]" class="medication-select" required>
                            <option value="">Select Medication</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity *</label>
                        <input type="number" name="medications[${medicationIndex}][quantity]" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Duration (Days)</label>
                        <input type="number" name="medications[${medicationIndex}][duration_days]" min="1">
                    </div>
                    <div class="form-group full-width">
                        <label>Dosage Instructions</label>
                        <textarea name="medications[${medicationIndex}][dosage_instructions]" rows="2" placeholder="e.g., Take 1 tablet twice daily after meals"></textarea>
                    </div>
                </div>
                <div class="medication-actions">
                    <button type="button" class="btn danger small" onclick="removeMedication(${medicationIndex})">Remove</button>
                </div>
            `;
            
            container.appendChild(newMedication);
            
            // Update the new select with available medications
            updateMedicationSelects();
            
            medicationIndex++;
        });

        // Remove medication row
        function removeMedication(index) {
            const medicationItem = document.querySelector(`[data-index="${index}"]`);
            if (medicationItem && document.querySelectorAll('.medication-item').length > 1) {
                medicationItem.remove();
            }
        }

        // Validate stock before submission
        document.getElementById('prescriptionForm').addEventListener('submit', function(e) {
            let isValid = true;
            const medicationItems = document.querySelectorAll('.medication-item');
            
            medicationItems.forEach(item => {
                const select = item.querySelector('.medication-select');
                const quantityInput = item.querySelector('input[type="number"]');
                
                if (select.value && quantityInput.value) {
                    const selectedOption = select.querySelector(`option[value="${select.value}"]`);
                    const availableStock = parseInt(selectedOption.dataset.stock);
                    const requestedQuantity = parseInt(quantityInput.value);
                    
                    if (requestedQuantity > availableStock) {
                        alert(`Insufficient stock for ${selectedOption.textContent.split(' - ')[0]}. Available: ${availableStock}, Requested: ${requestedQuantity}`);
                        isValid = false;
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
