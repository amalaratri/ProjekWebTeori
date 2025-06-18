<?php
session_start();

// Include your Database class file
require_once 'config/database.php'; 

// Instantiate the Database class and get the PDO connection object
$database = new Database();
$pdo = $database->getConnection();

// Check if user is logged in and is a pharmacy, and if DB connection is successful
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacy' || $pdo === null) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$pharmacy_id = $_SESSION['pharmacy_id'];

$successMessage = '';
$errorMessage = '';

// Fetch current pharmacy and user data for form pre-fill and validation
$pharmacyData = [];
$userData = [];

try {
    // Fetch pharmacy details
    $stmt = $pdo->prepare("SELECT pharmacy_name, license_number, phone, address, operational_hours, description FROM pharmacies WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$pharmacy_id, $user_id]);
    $pharmacyData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch user details (for email and password hash)
    $stmt = $pdo->prepare("SELECT email, password FROM users WHERE id = ? AND user_type = 'pharmacy' LIMIT 1");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pharmacyData || !$userData) {
        // Critical error: Data not found for logged-in user/pharmacy
        // This might happen if session data is out of sync with DB, or DB record is deleted
        session_unset();
        session_destroy();
        header("Location: login.php?error=profile_data_missing");
        exit();
    }

} catch (PDOException $e) {
    $errorMessage = "Error loading profile data: " . $e->getMessage();
    error_log("Profile data load error for user ID " . $user_id . ": " . $e->getMessage());
}

// Display messages after redirect
if (isset($_SESSION['form_message'])) {
    if ($_SESSION['form_message']['type'] === 'success') {
        $successMessage = $_SESSION['form_message']['message'];
    } else {
        $errorMessage = $_SESSION['form_message']['message'];
    }
    unset($_SESSION['form_message']);
}


// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update pharmacy profile
    if (isset($_POST['update_profile'])) {
        $name = htmlspecialchars(trim($_POST['name']));
        $address = htmlspecialchars(trim($_POST['address']));
        $phone = htmlspecialchars(trim($_POST['phone']));
        $email = htmlspecialchars(trim($_POST['email']));
        $operationalHours = htmlspecialchars(trim($_POST['operationalHours']));
        $description = htmlspecialchars(trim($_POST['description']));

        // Basic validation
        if (empty($name) || empty($address) || empty($phone) || empty($email) || empty($operationalHours)) {
            $errorMessage = "Please fill in all required fields.";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Update `pharmacies` table
                $stmt = $pdo->prepare("UPDATE pharmacies SET pharmacy_name = ?, address = ?, phone = ?, operational_hours = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
                $stmt->execute([$name, $address, $phone, $operationalHours, $description, $pharmacy_id, $user_id]);

                // 2. Update `users` table if email has changed
                if ($email !== $userData['email']) {
                    // Check if new email is already in use by another user
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->fetchColumn() > 0) {
                        $errorMessage = "The new email address is already registered by another user.";
                        $pdo->rollBack(); // Rollback pharmacy update
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$email, $user_id]);
                        // Update session email immediately
                        $_SESSION['user_email'] = $email;
                        $userData['email'] = $email; // Update local variable for current request
                    }
                }

                if (empty($errorMessage)) { // Only commit if no email error occurred
                    $pdo->commit();
                    $successMessage = "Profile updated successfully!";
                    // After successful update, refresh pharmacyData to reflect changes
                    $_SESSION['pharmacy_data'] = array_merge($_SESSION['pharmacy_data'], $_POST); // Quick update session
                    // Re-fetch fresh data from DB for safety, or simply rely on the form submission
                    $stmt = $pdo->prepare("SELECT pharmacy_name, license_number, phone, address, operational_hours, description FROM pharmacies WHERE id = ? AND user_id = ? LIMIT 1");
                    $stmt->execute([$pharmacy_id, $user_id]);
                    $pharmacyData = $stmt->fetch(PDO::FETCH_ASSOC); // Update local pharmacyData
                }

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errorMessage = "Error updating profile: " . $e->getMessage();
                error_log("Profile update error for user ID " . $user_id . ": " . $e->getMessage());
            }
        }
    }
    
    // Change password
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMessage = "Please fill in all password fields.";
        } elseif (!password_verify($currentPassword, $userData['password'])) {
            $errorMessage = "Current password is incorrect.";
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = "New password and confirm password do not match.";
        } elseif (strlen($newPassword) < 6) { // Example: minimum password length
            $errorMessage = "New password must be at least 6 characters long.";
        } else {
            try {
                $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$hashedNewPassword, $user_id]);
                $successMessage = "Password changed successfully!";
                $userData['password'] = $hashedNewPassword; // Update local variable for current request
            } catch (PDOException $e) {
                $errorMessage = "Error changing password: " . $e->getMessage();
                error_log("Password change error for user ID " . $user_id . ": " . $e->getMessage());
            }
        }
    }

    // Redirect to prevent form resubmission on refresh
    if (!empty($successMessage) || !empty($errorMessage)) {
        $_SESSION['form_message'] = ['type' => (!empty($successMessage) ? 'success' : 'error'), 'message' => (!empty($successMessage) ? $successMessage : $errorMessage)];
        header("Location: profile.php");
        exit();
    }
}
// Re-fetch data if any changes were made and not redirected, or if an error occurred that prevented redirect
// This ensures the form fields always show the latest data from the DB or the posted value if an error occurred.
if (empty($pharmacyData) || empty($userData)) { // Re-fetch only if initial fetch failed
    try {
        $stmt = $pdo->prepare("SELECT pharmacy_name, license_number, phone, address, operational_hours, description FROM pharmacies WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$pharmacy_id, $user_id]);
        $pharmacyData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT email, password FROM users WHERE id = ? AND user_type = 'pharmacy' LIMIT 1");
        $stmt->execute([$user_id]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $errorMessage = "Error loading profile data: " . $e->getMessage();
        error_log("Profile data load error (post-submit) for user ID " . $user_id . ": " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - PharmaSys</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="content">
            <h1>Pharmacy Profile</h1>
            
            <?php if (!empty($successMessage)): ?>
                <div class="alert success">
                    <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($errorMessage)): ?>
                <div class="alert error">
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <div class="profile-container">
                <div class="card">
                    <div class="card-header">
                        <h2>Pharmacy Information</h2>
                        <p>Update your pharmacy details</p>
                    </div>
                    
                    <div class="card-content">
                        <form action="" method="POST">
                            <div class="form-group">
                                <label for="name">Pharmacy Name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($pharmacyData['pharmacy_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" rows="3" required><?php echo htmlspecialchars($pharmacyData['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($pharmacyData['phone'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="operationalHours">Operational Hours</label>
                                <input type="text" id="operationalHours" name="operationalHours" value="<?php echo htmlspecialchars($pharmacyData['operational_hours'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($pharmacyData['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Account Settings</h2>
                        <p>Manage your account security</p>
                    </div>
                    
                    <div class="card-content">
                        <form action="" method="POST">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="change_password" class="btn primary">Change Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
