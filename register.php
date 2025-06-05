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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PharmaSys</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>PharmaSys</h1>
                <p>Pharmacy Management System</p>
            </div>
            
            <div class="auth-form">
                <h2>Create Your Account</h2>
                <p>Choose your account type to get started</p>
                
                <div class="registration-options">
                    <a href="register_pharmacy.php" class="registration-card">
                        <div class="registration-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M3 21h18l-9-18-9 18zM12 8v4m0 4h.01"/>
                            </svg>
                        </div>
                        <h3>Pharmacy</h3>
                        <p>Register your pharmacy to manage inventory and receive orders from doctors</p>
                    </a>
                    
                    <a href="register_doctor.php" class="registration-card">
                        <div class="registration-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </div>
                        <h3>Doctor</h3>
                        <p>Register as a doctor to place medication orders for your patients</p>
                    </a>
                </div>
                
                <div class="auth-links">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
