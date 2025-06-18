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

// Fetch pharmacies from database
try {
    $stmt = $pdo->query("SELECT * FROM pharmacies");
    $pharmacies = $stmt->fetchAll();
} catch (PDOException $e) {
    $pharmacies = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Pharmacies - PharmaSys</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
    <div class="container">
        <?php include 'doctor_sidebar.php'; ?>

        <main class="content">
            <h1>Available Pharmacies</h1>

            <?php if (count($pharmacies) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Pharmacy Name</th>
                                <th>Owner</th>
                                <th>Phone</th>
                                <th>Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pharmacies as $pharmacy): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pharmacy['pharmacy_name'] ?? 'Unnamed Pharmacy'); ?></td>
                                    <td><?php echo htmlspecialchars($pharmacy['owner_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($pharmacy['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($pharmacy['address'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No pharmacies found.</p>
            <?php endif; ?>
