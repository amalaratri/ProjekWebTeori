<?php
session_start();

// Include your Database class file
require_once 'config/database.php'; 

// Instantiate the Database class and get the PDO connection object
$database = new Database();
$pdo = $database->getConnection();

// Check if the database connection was successful
if ($pdo === null) {
    $error = "System error: Database connection unavailable. Please try again later.";
}

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'pharmacy') {
        header("Location: dashboard.php");
    } else {
        header("Location: doctor_dashboard.php");
    }
    exit();
}

$error = 'Login Gagal';
$success_message = 'Login Berhasil';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password']; // Password not trimmed for security
    $user_type = htmlspecialchars(trim($_POST['user_type']));
    
    // Basic validation
    if (empty($email) || empty($password) || empty($user_type)) {
        $error = 'Please fill in all fields.';
    } elseif ($pdo === null) {
        // Re-check for database connection in case the initial check was not triggered
        $error = "Login failed: Database connection unavailable.";
    } else {
        try {
            // 1. Find user in the `users` table
            $stmt = $pdo->prepare("SELECT id, email, password, user_type, status FROM users WHERE email = ? AND user_type = ? LIMIT 1");
            $stmt->execute([$email, $user_type]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Apakah akun sudah aktif
                if ($user['status'] === 'pending') {
                    $error = 'Your account is pending verification. Please wait for administrator approval.';
                } elseif ($user['status'] === 'inactive') {
                    $error = 'Your account is inactive. Please contact support.';
                } else {
                    // Authentication successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['user_email'] = $user['email']; // Store email for convenience

                    // 2. Fetch user-specific details (pharmacy or doctor)
                    if ($user_type === 'pharmacy') {
                        $stmt = $pdo->prepare("SELECT id, pharmacy_name, owner_name, license_number, phone, address, operational_hours, description FROM pharmacies WHERE user_id = ? LIMIT 1");
                        $stmt->execute([$user['id']]);
                        $pharmacy_data = $stmt->fetch();

                        if ($pharmacy_data) {
                            $_SESSION['pharmacy_id'] = $pharmacy_data['id'];
                            $_SESSION['pharmacy_name'] = $pharmacy_data['pharmacy_name'];
                            // Store other pharmacy details in session as needed
                            $_SESSION['pharmacy_data'] = $pharmacy_data; // Store full data if useful

                            // Redirect to pharmacy dashboard
                            header("Location: dashboard.php");
                            exit();
                        } else {
                            // This should ideally not happen if data integrity is maintained
                            $error = 'Pharmacy profile not found. Please contact support.';
                        }
                    } elseif ($user_type === 'doctor') {
                        $stmt = $pdo->prepare("SELECT id, full_name, license_number, specialization, phone, address, hospital_clinic FROM doctors WHERE user_id = ? LIMIT 1");
                        $stmt->execute([$user['id']]);
                        $doctor_data = $stmt->fetch();

                        if ($doctor_data) {
                            $_SESSION['doctor_id'] = $doctor_data['id'];
                            $_SESSION['doctor_name'] = $doctor_data['full_name'];
                            // Store other doctor details in session as needed
                            $_SESSION['doctor_data'] = $doctor_data; // Store full data if useful

                            // Redirect to doctor dashboard
                            header("Location: doctor_dashboard.php");
                            exit();
                        } else {
                            // This should ideally not happen if data integrity is maintained
                            $error = 'Doctor profile not found. Please contact support.';
                        }
                    }
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed due to a system error. Please try again later.';
            // Log the error for debugging (never display raw PDO exceptions in production)
            error_log("Login error: " . $e->getMessage());
        }
    }
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
