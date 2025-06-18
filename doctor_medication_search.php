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

$searchTerm = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search'])) : '';
$selectedPharmacy = isset($_GET['pharmacy']) ? (int)$_GET['pharmacy'] : 0;

// Fetch pharmacies
$pharmacies = [];
try {
    $stmt = $pdo->query("SELECT id, pharmacy_name, address FROM pharmacies WHERE is_verified = 1 ORDER BY pharmacy_name");
    $pharmacies = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching pharmacies: " . $e->getMessage());
}

// Search medications across pharmacies
$medications = [];
if (!empty($searchTerm)) {
    try {
        $sql = "
            SELECT 
                pi.pharmacy_id,
                p.pharmacy_name,
                p.address as pharmacy_address,
                pi.medication_id,
                m.name,
                m.generic_name,
                m.strength,
                m.dosage_form,
                m.manufacturer,
                m.description,
                pi.unit_price,
                pi.stock_quantity,
                pi.unit,
                mc.name as category_name
            FROM pharmacy_inventory pi
            JOIN medications m ON pi.medication_id = m.id
            JOIN pharmacies p ON pi.pharmacy_id = p.id
            LEFT JOIN medication_categories mc ON m.category_id = mc.id
            WHERE pi.stock_quantity > 0 
            AND (m.name LIKE ? OR m.generic_name LIKE ? OR m.description LIKE ?)
        ";
        
        $params = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
        
        if ($selectedPharmacy > 0) {
            $sql .= " AND pi.pharmacy_id = ?";
            $params[] = $selectedPharmacy;
        }
        
        $sql .= " ORDER BY m.name ASC, p.pharmacy_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error searching medications: " . $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medication Search - PharmaSys</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'doctor_sidebar.php'; ?>

        <main class="content">
            <h1>Search Medications</h1>
            <p>Find available medications across all pharmacies</p>

            <!-- Search Form -->
            <div class="card">
                <div class="card-content">
                    <form method="GET" class="search-medications-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="search">Search Medications</label>
                                <input type="text" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($searchTerm); ?>" 
                                       placeholder="Enter medication name, generic name, or description">
                            </div>
                            <div class="form-group">
                                <label for="pharmacy">Filter by Pharmacy (Optional)</label>
                                <select id="pharmacy" name="pharmacy">
                                    <option value="">All Pharmacies</option>
                                    <?php foreach ($pharmacies as $pharmacy): ?>
                                        <option value="<?php echo $pharmacy['id']; ?>" 
                                                <?php echo $selectedPharmacy == $pharmacy['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn primary">Search</button>
                            <a href="doctor_medication_search.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Search Results -->
            <?php if (!empty($searchTerm)): ?>
                <div class="search-results">
                    <h2>Search Results for "<?php echo htmlspecialchars($searchTerm); ?>"</h2>
                    
                    <?php if (count($medications) > 0): ?>
                        <div class="medications-grid">
                            <?php 
                            $currentMedication = '';
                            foreach ($medications as $med): 
                                $medicationKey = $med['name'] . '|' . $med['strength'];
                                if ($currentMedication !== $medicationKey):
                                    if ($currentMedication !== '') echo '</div></div>'; // Close previous medication card
                                    $currentMedication = $medicationKey;
                            ?>
                                <div class="medication-card">
                                    <div class="medication-header">
                                        <h3><?php echo htmlspecialchars($med['name']); ?></h3>
                                        <?php if ($med['strength']): ?>
                                            <span class="medication-strength"><?php echo htmlspecialchars($med['strength']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="medication-details">
                                        <?php if ($med['generic_name']): ?>
                                            <p><strong>Generic:</strong> <?php echo htmlspecialchars($med['generic_name']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($med['dosage_form']): ?>
                                            <p><strong>Form:</strong> <?php echo htmlspecialchars($med['dosage_form']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($med['manufacturer']): ?>
                                            <p><strong>Manufacturer:</strong> <?php echo htmlspecialchars($med['manufacturer']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($med['category_name']): ?>
                                            <p><strong>Category:</strong> <?php echo htmlspecialchars($med['category_name']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($med['description']): ?>
                                            <p><strong>Description:</strong> <?php echo htmlspecialchars($med['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pharmacy-availability">
                                        <h4>Available at:</h4>
                            <?php endif; ?>
                            
                            <!-- Pharmacy availability for this medication -->
                            <div class="pharmacy-item">
                                <div class="pharmacy-info">
                                    <strong><?php echo htmlspecialchars($med['pharmacy_name']); ?></strong>
                                    <small><?php echo htmlspecialchars($med['pharmacy_address']); ?></small>
                                </div>
                                <div class="availability-info">
                                    <span class="stock">Stock: <?php echo $med['stock_quantity']; ?> <?php echo htmlspecialchars($med['unit']); ?></span>
                                    <span class="price">Rp <?php echo number_format($med['unit_price']); ?></span>
                                </div>
                                <div class="pharmacy-actions">
                                    <a href="doctor_prescriptions.php?pharmacy_id=<?php echo $med['pharmacy_id']; ?>&medication_id=<?php echo $med['medication_id']; ?>" 
                                       class="btn small primary">Create Prescription</a>
                                </div>
                            </div>
                            
                            <?php endforeach; ?>
                            <?php if ($currentMedication !== '') echo '</div></div>'; // Close last medication card ?>
                        </div>
                        
                        <div class="search-summary">
                            <p>Found <?php echo count($medications); ?> medication(s) available across pharmacies</p>
                        </div>
                        
                    <?php else: ?>
                        <div class="no-results">
                            <p>No medications found matching your search criteria.</p>
                            <p>Try searching with different keywords or check all pharmacies.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="search-instructions">
                    <div class="card">
                        <div class="card-content">
                            <h3>How to Search</h3>
                            <ul>
                                <li>Enter the medication name, generic name, or description</li>
                                <li>Optionally filter by a specific pharmacy</li>
                                <li>View availability and prices across different pharmacies</li>
                                <li>Create prescriptions directly from search results</li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
