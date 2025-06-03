<?php
session_start();

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'pharmacy') {
        header("Location: dashboard.php");
    } else {
        header("Location: doctor_dashboard.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];
    
    // Initialize users if not exists
    if (!isset($_SESSION['users'])) {
        $_SESSION['users'] = [
            'pharmacies' => [],
            'doctors' => []
        ];
    }
    
    // Find user
    $users = $_SESSION['users'][$user_type . 's'] ?? [];
    $user_found = false;
    
    foreach ($users as $user) {
        if ($user['email'] === $email && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user_type;
            $_SESSION['user_data'] = $user;
            
            // Set pharmacy data for pharmacy users
            if ($user_type === 'pharmacy') {
                $_SESSION['pharmacy'] = [
                    'name' => $user['pharmacy_name'],
                    'address' => $user['address'],
                    'phone' => $user['phone'],
                    'email' => $user['email'],
                    'operationalHours' => $user['operational_hours'],
                    'description' => $user['description']
                ];
                
                // Initialize sample data for new pharmacy
                if (!isset($_SESSION['medications'])) {
                    $_SESSION['medications'] = [
                        [
                            'id' => 1,
                            'name' => 'Paracetamol',
                            'description' => 'Pain reliever',
                            'unit' => 'tablet',
                            'price' => 5000,
                            'stock' => 100
                        ],
                        [
                            'id' => 2,
                            'name' => 'Amoxicillin',
                            'description' => 'Antibiotic',
                            'unit' => 'capsule',
                            'price' => 15000,
                            'stock' => 50
                        ]
                    ];
                }
                
                if (!isset($_SESSION['orders'])) {
                    $_SESSION['orders'] = [];
                }
                
                header("Location: dashboard.php");
            } else {
                header("Location: doctor_dashboard.php");
            }
            exit();
        }
    }
    
    $error = 'Invalid email or password';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PharmaSys</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>PharmaSys</h1>
                <p>Pharmacy Management System</p>
            </div>
            
            <form action="" method="POST" class="auth-form">
                <h2>Login to Your Account</h2>
                
                <?php if ($error): ?>
                    <div class="alert error">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="user_type">Login as:</label>
                    <select id="user_type" name="user_type" required>
                        <option value="">Select user type</option>
                        <option value="pharmacy">Pharmacy</option>
                        <option value="doctor">Doctor</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn primary full-width">Login</button>
                
                <div class="auth-links">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
