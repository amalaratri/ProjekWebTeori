<?php
session_start();

$success = '';
$error = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['next_step'])) {
        // Store step 1 data in session
        $_SESSION['pharmacy_registration'] = [
            'pharmacy_name' => $_POST['pharmacy_name'],
            'license_number' => $_POST['license_number'],
            'address' => $_POST['address'],
            'phone' => $_POST['phone'],
            'operational_hours' => $_POST['operational_hours'],
            'description' => $_POST['description']
        ];
        header("Location: register_pharmacy.php?step=2");
        exit();
    }
    
    if (isset($_POST['register_pharmacy'])) {
        // Initialize users if not exists
        if (!isset($_SESSION['users'])) {
            $_SESSION['users'] = [
                'pharmacies' => [],
                'doctors' => []
            ];
        }
        
        // Check if email already exists
        $email_exists = false;
        foreach ($_SESSION['users']['pharmacies'] as $pharmacy) {
            if ($pharmacy['email'] === $_POST['email']) {
                $email_exists = true;
                break;
            }
        }
        
        if ($email_exists) {
            $error = 'Email already registered';
        } elseif ($_POST['password'] !== $_POST['confirm_password']) {
            $error = 'Passwords do not match';
        } else {
            // Combine step 1 and step 2 data
            $pharmacy_data = $_SESSION['pharmacy_registration'];
            
            // Create new pharmacy user
            $new_id = count($_SESSION['users']['pharmacies']) + 1;
            
            $new_pharmacy = [
                'id' => $new_id,
                'pharmacy_name' => $pharmacy_data['pharmacy_name'],
                'owner_name' => $_POST['owner_name'],
                'email' => $_POST['email'],
                'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                'phone' => $pharmacy_data['phone'],
                'address' => $pharmacy_data['address'],
                'operational_hours' => $pharmacy_data['operational_hours'],
                'license_number' => $pharmacy_data['license_number'],
                'description' => $pharmacy_data['description'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $_SESSION['users']['pharmacies'][] = $new_pharmacy;
            
            // Clear registration data
            unset($_SESSION['pharmacy_registration']);
            
            $success = 'Pharmacy registered successfully! You can now login.';
        }
    }
}

// Get stored data for step 2
$stored_data = $_SESSION['pharmacy_registration'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Registration - PharmaSys</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card large">
            <div class="auth-header">
                <h1>PharmaSys</h1>
                <p>Pharmacy Registration - Step <?php echo $step; ?> of 2</p>
            </div>
            
            <!-- Progress Bar -->
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
                <!-- Step 1: Pharmacy Information -->
                <form action="" method="POST" class="auth-form">
                    <h2>Pharmacy Information</h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="pharmacy_name">Pharmacy Name *</label>
                            <input type="text" id="pharmacy_name" name="pharmacy_name" required 
                                   value="<?php echo isset($_POST['pharmacy_name']) ? htmlspecialchars($_POST['pharmacy_name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="license_number">License Number *</label>
                            <input type="text" id="license_number" name="license_number" required 
                                   value="<?php echo isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : ''; ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="address">Address *</label>
                            <textarea id="address" name="address" rows="3" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="text" id="phone" name="phone" required 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="operational_hours">Operational Hours *</label>
                            <input type="text" id="operational_hours" name="operational_hours" 
                                   placeholder="e.g., 08:00 - 21:00" required 
                                   value="<?php echo isset($_POST['operational_hours']) ? htmlspecialchars($_POST['operational_hours']) : ''; ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" 
                                      placeholder="Brief description of your pharmacy"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="register.php" class="btn btn-secondary">Back</a>
                        <button type="submit" name="next_step" class="btn primary">Next Step</button>
                    </div>
                </form>
                
            <?php else: ?>
                <!-- Step 2: Owner Information -->
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
                    
                    <!-- Summary of Step 1 Data -->
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
