<?php
// Get current page for highlighting active menu item
$currentPage = basename($_SERVER['PHP_SELF']);
if (isset($_SESSION['doctor_data'])) {
    $doctor = $_SESSION['doctor_data'];
} else {
    header("Location: login.php");
    exit();
}
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <h1>PharmaSys</h1>
            <p>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></p>
            <small><?php echo htmlspecialchars($doctor['specialization']); ?></small>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul>
            <li class="<?php echo $currentPage === 'doctor_dashboard.php' ? 'active' : ''; ?>">
                <a href="doctor_dashboard.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                        </svg>
                    </span>
                    <span class="label">Dashboard</span>
                </a>
            </li>
            <li class="<?php echo $currentPage === 'doctor_orders.php' ? 'active' : ''; ?>">
                <a href="doctor_orders.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                            <polyline points="14,2 14,8 20,8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10,9 9,9 8,9"/>
                        </svg>
                    </span>
                    <span class="label">My Orders</span>
                </a>
            </li>
            <li class="<?php echo $currentPage === 'doctor_prescriptions.php' ? 'active' : ''; ?>">
                <a href="doctor_prescriptions.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                            <polyline points="14,2 14,8 20,8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <line x1="12" y1="9" x2="8" y2="9"/>
                        </svg>
                    </span>
                    <span class="label">Create Prescription</span>
                </a>
            </li>
            <li class="<?php echo $currentPage === 'doctor_medication_search.php' ? 'active' : ''; ?>">
                <a href="doctor_medication_search.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                    </span>
                    <span class="label">Search Medications</span>
                </a>
            </li>
            <li class="<?php echo $currentPage === 'doctor_pharmacies.php' ? 'active' : ''; ?>">
                <a href="doctor_pharmacies.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 21h18l-9-18-9 18zM12 8v4m0 4h.01"/>
                        </svg>
                    </span>
                    <span class="label">Pharmacies</span>
                </a>
            </li>
            <li class="<?php echo $currentPage === 'doctor_profile.php' ? 'active' : ''; ?>">
                <a href="doctor_profile.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </span>
                    <span class="label">Profile</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link">
            <span class="icon" >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16,17 21,12 16,7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </span>
            <span class="label">Logout</span>
        </a>
    </div>
</aside>
