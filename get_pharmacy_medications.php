<?php
session_start();

require_once 'config/database.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

$pharmacy_id = isset($_GET['pharmacy_id']) ? (int)$_GET['pharmacy_id'] : 0;

if (!$pharmacy_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Pharmacy ID required']);
    exit();
}

try {
    // Get medications available at the selected pharmacy with stock > 0
    $stmt = $pdo->prepare("
        SELECT 
            pi.medication_id,
            m.name,
            m.generic_name,
            m.strength,
            m.dosage_form,
            m.manufacturer,
            pi.unit_price,
            pi.stock_quantity,
            pi.unit,
            mc.name as category_name
        FROM pharmacy_inventory pi
        JOIN medications m ON pi.medication_id = m.id
        LEFT JOIN medication_categories mc ON m.category_id = mc.id
        WHERE pi.pharmacy_id = ? AND pi.stock_quantity > 0
        ORDER BY m.name ASC
    ");
    
    $stmt->execute([$pharmacy_id]);
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($medications);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
