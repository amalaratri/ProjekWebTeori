<?php
session_start();

// Check if user is logged in and has appropriate user type
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['doctor', 'pharmacy'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['doctor_data'])) {
    $doctor = $_SESSION['doctor_data'];
} elseif (isset($_SESSION['pharmacy_data'])) {
    $pharmacy = $_SESSION['pharmacy_data'];
} else {
    header("Location: login.php");
    exit();
}

// For simplicity, just display a placeholder list of pharmacies
$pharmacies = $_SESSION['users']['pharmacies'] ?? [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacies - PharmaSys</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>

        <main class="content">
            <h1>Available Pharmacies</h1>

            <?php if (count($pharmacies) > 0): ?>
                <ul class="pharmacies-list">
                    <?php foreach ($pharmacies as $pharmacy): ?>
                        <li>
                            <h3><?php echo htmlspecialchars($pharmacy['pharmacy_name'] ?? 'Unnamed Pharmacy'); ?></h3>
                            <p>Owner: <?php echo htmlspecialchars($pharmacy['owner_name'] ?? 'N/A'); ?></p>
                            <p>Phone: <?php echo htmlspecialchars($pharmacy['phone'] ?? 'N/A'); ?></p>
                            <p>Address: <?php echo htmlspecialchars($pharmacy['address'] ?? 'N/A'); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No pharmacies found.</p>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
