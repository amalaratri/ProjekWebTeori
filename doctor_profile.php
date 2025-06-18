<?php
session_start();

require_once 'config/database.php';

// Instantiate the Database class and get the PDO connection object
$database = new Database();
$pdo = $database->getConnection();

// Check if user is logged in and is a doctor, and if DB connection is successful
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor' || $pdo === null) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$successMessage = '';
$errorMessage = '';

// Fetch current doctor and user data for form pre-fill and validation
$doctorData = [];
$userData = [];

try {
    // Fetch doctor details
    $stmt = $pdo->prepare("SELECT full_name, license_number, specialization, phone, address, hospital_clinic FROM doctors WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $doctorData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch user details (for email and password hash)
    $stmt = $pdo->prepare("SELECT email, password FROM users WHERE id = ? AND user_type = 'doctor' LIMIT 1");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctorData || !$userData) {
        $errorMessage = "Profile data is missing or incomplete. Please contact support.";
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
    // Update doctor profile
    if (isset($_POST['update_profile'])) {
        $name = htmlspecialchars(trim($_POST['name']));
        $address = htmlspecialchars(trim($_POST['address']));
        $phone = htmlspecialchars(trim($_POST['phone']));
        $email = htmlspecialchars(trim($_POST['email']));
        $hospitalClinic = htmlspecialchars(trim($_POST['hospitalClinic']));
        $specialization = htmlspecialchars(trim($_POST['specialization']));

        // Basic validation
        if (empty($name) || empty($address) || empty($phone) || empty($email) || empty($hospitalClinic) || empty($specialization)) {
            $errorMessage = "Please fill in all required fields.";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Update `doctors` table
                $stmt = $pdo->prepare("UPDATE doctors SET full_name = ?, address = ?, phone = ?, hospital_clinic = ?, specialization = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
                $stmt->execute([$name, $address, $phone, $hospitalClinic, $specialization, $user_id]);

                // 2. Update `users` table if email has changed
                if ($email !== $userData['email']) {
                    // Check if new email is already in use by another user
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->fetchColumn() > 0) {
                        $errorMessage = "The new email address is already registered by another user.";
                        $pdo->rollBack(); // Rollback doctor update
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
                    // After successful update, refresh doctorData to reflect changes
                    // Re-fetch fresh data from DB for safety
                    $stmt = $pdo->prepare("SELECT full_name, license_number, specialization, phone, address, hospital_clinic FROM doctors WHERE user_id = ? LIMIT 1");
                    $stmt->execute([$user_id]);
                    $doctorData = $stmt->fetch(PDO::FETCH_ASSOC);
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
        header("Location: doctor_profile.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Doctor Profile - PharmaSys</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
    <div class="container">
        <?php include 'doctor_sidebar.php'; ?>

        <main class="content">
            <h1>Doctor Profile</h1>

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
                        <h2>Profile Information</h2>
                        <p>Update your profile details</p>
                    </div>

                    <div class="card-content">
                        <form action="" method="POST">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($doctorData['full_name'] ?? ''); ?>" required />
                            </div>

                            <div class="form-group">
                                <label for="license_number">License Number</label>
                                <input type="text" id="license_number" name="license_number" value="<?php echo htmlspecialchars($doctorData['license_number'] ?? ''); ?>" readonly />
                            </div>

                            <div class="form-group">
                                <label for="specialization">Specialization</label>
                                <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($doctorData['specialization'] ?? ''); ?>" required />
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($doctorData['phone'] ?? ''); ?>" required />
                            </div>

                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" rows="3" required><?php echo htmlspecialchars($doctorData['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="hospitalClinic">Hospital/Clinic</label>
                                <input type="text" id="hospitalClinic" name="hospitalClinic" value="<?php echo htmlspecialchars($doctorData['hospital_clinic'] ?? ''); ?>" required />
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required />
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
                                <input type="password" id="current_password" name="current_password" required />
                            </div>

                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required />
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required />
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
</create_file>
