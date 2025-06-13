<?php
session_start();

require_once 'config/database.php'; 

$database = new Database();
$pdo = $database->getConnection();

if ($pdo === null) {
    $error = "Failed to connect to the database. Please try again later.";
}

$success = '';
$error = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['next_step'])) {
        // Validate and store step 1 data in session
        $_SESSION['pharmacy_registration'] = [
            'pharmacy_name' => htmlspecialchars(trim($_POST['pharmacy_name'])),
            'license_number' => htmlspecialchars(trim($_POST['license_number'])),
            'address' => htmlspecialchars(trim($_POST['address'])),
            'phone' => htmlspecialchars(trim($_POST['phone'])),
            'operational_hours' => htmlspecialchars(trim($_POST['operational_hours'])),
            'description' => htmlspecialchars(trim($_POST['description']))
        ];
        header("Location: register_pharmacy.php?step=2");
        exit();
    }
    
    if (isset($_POST['register_pharmacy'])) {
        // Ensure step 1 data exists in session
        if (!isset($_SESSION['pharmacy_registration'])) {
            $error = 'Please complete step 1 first.';
            $step = 1; // Redirect back to step 1 if session data is missing
        } else {
            // Combine step 1 and step 2 data
            $pharmacy_data = $_SESSION['pharmacy_registration'];
            $owner_name = htmlspecialchars(trim($_POST['owner_name']));
            $email = htmlspecialchars(trim($_POST['email']));
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            // Validation for step 2
            if ($password !== $confirm_password) {
                $error = 'Passwords do not match.';
            } else {
                try {
                    // Check if email already exists in the users table
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Email already registered.';
                    } else {
                        // Hash the password securely
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                        // Start a transaction for atomicity
                        $pdo->beginTransaction();

                        // 1. Insert into `users` table
                        $stmt = $pdo->prepare("INSERT INTO users (email, password, user_type, status, email_verified) VALUES (?, ?, 'pharmacy', 'pending', 0)");
                        $stmt->execute([$email, $hashed_password]);
                        $user_id = $pdo->lastInsertId(); // Get the ID of the newly inserted user

                        // 2. Insert into `pharmacies` table
                        $stmt = $pdo->prepare("INSERT INTO pharmacies (user_id, pharmacy_name, owner_name, license_number, phone, address, operational_hours, description, is_verified) 
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
                        $stmt->execute([
                            $user_id,
                            $pharmacy_data['pharmacy_name'],
                            $owner_name,
                            $pharmacy_data['license_number'],
                            $pharmacy_data['phone'],
                            $pharmacy_data['address'],
                            $pharmacy_data['operational_hours'],
                            $pharmacy_data['description']
                        ]);

                        // Commit the transaction if both inserts are successful
                        $pdo->commit();

                        // Clear registration data from session
                        unset($_SESSION['pharmacy_registration']);
                        
                        // Set success message and redirect to login
                        $_SESSION['success_message'] = 'Pharmacy registered successfully! Your account is pending verification. You can now login.';
                        header("Location: login.php");
                        exit();
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack(); // Rollback transaction on error
                    $error = 'Registration failed: ' . $e->getMessage();
                    // In a production environment, log the full error without displaying it to the user
                    error_log("Pharmacy registration error: " . $e->getMessage());
                }
            }
        }
    }
}

// Get stored data for step 2 (from session)
$stored_data = $_SESSION['pharmacy_registration'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Registration - PharmaSys</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        // This can help retain input values if user goes back via browser button
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Page was loaded from cache (e.g., via back/forward button)
                // You might need more robust JavaScript for complex form state
                // For now, PHP's value attribute handles POST data
            }
        });
    </script>
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card large">
            <div class="auth-header">
                <h1>PharmaSys</h1>
                <p>Pharmacy Registration - Step <?php echo $step; ?> of 2</p>
            </div>
            
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-step <?php echo $step >= 1 ? 'active' : ''; ?>">
                        <div class="step-number">1</div>
                        <div class="step-label">Pharmacy Info</div>
                    </div>
                    <div class="progress-line <?php echo $step >= 2 ? 'active' : ''; ?>"></div>
                    <div class="progress-step <?php echo $step >= 2 ? 'active' : ''; ?>">
                        <div class="step-number">2</div>
                        <div class="step-label">Owner Details</div>
                    </div>
                </div>
            </div>
            
            <?php if ($step === 1): ?>
                <form action="" method="POST" class="auth-form">
                    <h2>Pharmacy Information</h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="pharmacy_name">Pharmacy Name *</label>
                            <input type="text" id="pharmacy_name" name="pharmacy_name" required 
                                   value="<?php echo isset($_SESSION['pharmacy_registration']['pharmacy_name']) ? htmlspecialchars($_SESSION['pharmacy_registration']['pharmacy_name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="license_number">License Number *</label>
                            <input type="text" id="license_number" name="license_number" required 
                                   value="<?php echo isset($_SESSION['pharmacy_registration']['license_number']) ? htmlspecialchars($_SESSION['pharmacy_registration']['license_number']) : ''; ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="address">Address *</label>
                            <textarea id="address" name="address" rows="3" required><?php echo isset($_SESSION['pharmacy_registration']['address']) ? htmlspecialchars($_SESSION['pharmacy_registration']['address']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="text" id="phone" name="phone" required 
                                   value="<?php echo isset($_SESSION['pharmacy_registration']['phone']) ? htmlspecialchars($_SESSION['pharmacy_registration']['phone']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="operational_hours">Operational Hours *</label>
                            <input type="text" id="operational_hours" name="operational_hours" 
                                   placeholder="e.g., 08:00 - 21:00" required 
                                   value="<?php echo isset($_SESSION['pharmacy_registration']['operational_hours']) ? htmlspecialchars($_SESSION['pharmacy_registration']['operational_hours']) : ''; ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" 
                                      placeholder="Brief description of your pharmacy"><?php echo isset($_SESSION['pharmacy_registration']['description']) ? htmlspecialchars($_SESSION['pharmacy_registration']['description']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="register.php" class="btn btn-secondary">Back</a>
                        <button type="submit" name="next_step" class="btn primary">Next Step</button>
                    </div>
                </form>
                
            <?php else: // $step === 2 ?>
                <form action="" method="POST" class="auth-form">
                    <h2>Owner Information</h2>
                    
                    <?php if ($success): ?>
                        <div class="alert success">
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert error">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="owner_name">Owner/Manager Name *</label>
                            <input type="text" id="owner_name" name="owner_name" required 
                                   value="<?php echo isset($_POST['owner_name']) ? htmlspecialchars($_POST['owner_name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="registration-summary">
                        <h3>Pharmacy Details Summary</h3>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <strong>Pharmacy:</strong> <?php echo htmlspecialchars($stored_data['pharmacy_name'] ?? ''); ?>
                            </div>
                            <div class="summary-item">
                                <strong>License:</strong> <?php echo htmlspecialchars($stored_data['license_number'] ?? ''); ?>
                            </div>
                            <div class="summary-item">
                                <strong>Phone:</strong> <?php echo htmlspecialchars($stored_data['phone'] ?? ''); ?>
                            </div>
                            <div class="summary-item">
                                <strong>Hours:</strong> <?php echo htmlspecialchars($stored_data['operational_hours'] ?? ''); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="register_pharmacy.php?step=1" class="btn btn-secondary">Previous</a>
                        <button type="submit" name="register_pharmacy" class="btn primary">Complete Registration</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="auth-links">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</body>
</html>