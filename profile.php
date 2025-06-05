<?php
session_start();

// Check if user is logged in and is a pharmacy
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacy') {
    header("Location: login.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update pharmacy profile
    if (isset($_POST['update_profile'])) {
        $_SESSION['pharmacy'] = [
            'name' => $_POST['name'],
            'address' => $_POST['address'],
            'phone' => $_POST['phone'],
            'email' => $_POST['email'],
            'operationalHours' => $_POST['operationalHours'],
            'description' => $_POST['description']
        ];
        
        $successMessage = "Profile updated successfully!";
    }
    
    // Change password (in a real app, this would involve proper authentication)
    if (isset($_POST['change_password'])) {
        // This is just a placeholder since we don't have real authentication in this demo
        $successMessage = "Password changed successfully!";
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
            
            <?php if (isset($successMessage)): ?>
                <div class="alert success">
                    <?php echo $successMessage; ?>
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
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_SESSION['pharmacy']['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" rows="3" required><?php echo htmlspecialchars($_SESSION['pharmacy']['address']); ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($_SESSION['pharmacy']['phone']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['pharmacy']['email']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="operationalHours">Operational Hours</label>
                                <input type="text" id="operationalHours" name="operationalHours" value="<?php echo htmlspecialchars($_SESSION['pharmacy']['operationalHours']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($_SESSION['pharmacy']['description']); ?></textarea>
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
