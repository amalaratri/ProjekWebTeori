<?php
session_start();

$success = '';
$error = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['next_step'])) {
        // Store step 1 data in session
        $_SESSION['doctor_registration'] = [
            'full_name' => $_POST['full_name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'address' => $_POST['address']
        ];
        header("Location: register_doctor.php?step=2");
        exit();
    }
    
    if (isset($_POST['register_doctor'])) {
        // Initialize users if not exists
        if (!isset($_SESSION['users'])) {
            $_SESSION['users'] = [
                'pharmacies' => [],
                'doctors' => []
            ];
        }
        
        // Check if email already exists
        $email_exists = false;
        foreach ($_SESSION['users']['doctors'] as $doctor) {
            if ($doctor['email'] === $_SESSION['doctor_registration']['email']) {
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
            $doctor_data = $_SESSION['doctor_registration'];
            
            // Create new doctor user
            $new_id = count($_SESSION['users']['doctors']) + 1;
            
            $new_doctor = [
                'id' => $new_id,
                'full_name' => $doctor_data['full_name'],
                'email' => $doctor_data['email'],
                'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                'phone' => $doctor_data['phone'],
                'specialization' => $_POST['specialization'],
                'license_number' => $_POST['license_number'],
                'hospital_clinic' => $_POST['hospital_clinic'],
                'address' => $doctor_data['address'],
                'experience_years' => $_POST['experience_years'],
                'education' => $_POST['education'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $_SESSION['users']['doctors'][] = $new_doctor;
            
            // Clear registration data
            unset($_SESSION['doctor_registration']);
            
            $success = 'Doctor account registered successfully! You can now login.';
        }
    }
}

// Get stored data for step 2
$stored_data = $_SESSION['doctor_registration'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Registration - PharmaSys</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card large">
            <div class="auth-header">
                <h1>PharmaSys</h1>
                <p>Doctor Registration - Step <?php echo $step; ?> of 2</p>
            </div>
            
            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-step <?php echo $step >= 1 ? 'active' : ''; ?>">
                        <div class="step-number">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="progress-line <?php echo $step >= 2 ? 'active' : ''; ?>"></div>
                    <div class="progress-step <?php echo $step >= 2 ? 'active' : ''; ?>">
                        <div class="step-number">2</div>
                        <div class="step-label">Professional Details</div>
                    </div>
                </div>
            </div>
            
            <?php if ($step === 1): ?>
                <!-- Step 1: Personal Information -->
                <form action="" method="POST" class="auth-form">
                    <h2>Personal Information</h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" required 
                                   value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="text" id="phone" name="phone" required 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="address">Address *</label>
                            <textarea id="address" name="address" rows="3" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="register.php" class="btn btn-secondary">Back</a>
                        <button type="submit" name="next_step" class="btn primary">Next Step</button>
                    </div>
                </form>
                
            <?php else: ?>
                <!-- Step 2: Professional Information -->
                <form action="" method="POST" class="auth-form">
                    <h2>Professional Information</h2>
                    
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
                            <label for="license_number">Medical License Number *</label>
                            <input type="text" id="license_number" name="license_number" required 
                                   value="<?php echo isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="specialization">Specialization *</label>
                            <select id="specialization" name="specialization" required>
                                <option value="">Select specialization</option>
                                <option value="General Practitioner" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'General Practitioner') ? 'selected' : ''; ?>>General Practitioner</option>
                                <option value="Internal Medicine" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'Internal Medicine') ? 'selected' : ''; ?>>Internal Medicine</option>
                                <option value="Pediatrics" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'Pediatrics') ? 'selected' : ''; ?>>Pediatrics</option>
                                <option value="Cardiology" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'Cardiology') ? 'selected' : ''; ?>>Cardiology</option>
                                <option value="Dermatology" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'Dermatology') ? 'selected' : ''; ?>>Dermatology</option>
                                <option value="Orthopedics" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'Orthopedics') ? 'selected' : ''; ?>>Orthopedics</option>
                                <option value="Neurology" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'Neurology') ? 'selected' : ''; ?>>Neurology</option>
                                <option value="Psychiatry" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'Psychiatry') ? 'selected' : ''; ?>>Psychiatry</option>
                                <option value="Other" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="hospital_clinic">Hospital/Clinic Name *</label>
                            <input type="text" id="hospital_clinic" name="hospital_clinic" required 
                                   value="<?php echo isset($_POST['hospital_clinic']) ? htmlspecialchars($_POST['hospital_clinic']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="experience_years">Years of Experience *</label>
                            <input type="number" id="experience_years" name="experience_years" min="0" required 
                                   value="<?php echo isset($_POST['experience_years']) ? htmlspecialchars($_POST['experience_years']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="education">Medical Education *</label>
                            <input type="text" id="education" name="education" 
                                   placeholder="e.g., MD from University of Indonesia" required 
                                   value="<?php echo isset($_POST['education']) ? htmlspecialchars($_POST['education']) : ''; ?>">
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
                        <h3>Personal Details Summary</h3>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <strong>Name:</strong> <?php echo htmlspecialchars($stored_data['full_name'] ?? ''); ?>
                            </div>
                            <div class="summary-item">
                                <strong>Email:</strong> <?php echo htmlspecialchars($stored_data['email'] ?? ''); ?>
                            </div>
                            <div class="summary-item">
                                <strong>Phone:</strong> <?php echo htmlspecialchars($stored_data['phone'] ?? ''); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="register_doctor.php?step=1" class="btn btn-secondary">Previous</a>
                        <button type="submit" name="register_doctor" class="btn primary">Complete Registration</button>
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
