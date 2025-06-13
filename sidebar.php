<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a pharmacy, otherwise redirect
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacy') {
    header("Location: login.php");
    exit();
}

// Get current page for highlighting active menu item
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <h1>PharmaSys</h1>
            <p><?php echo htmlspecialchars($_SESSION['pharmacy_data']['pharmacy_name'] ?? 'Pharmacy Name'); ?></p> 
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul>
            <li class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <a href="dashboard.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-6h-8v10zm0-18v6h8V3h-8z"/>
                        </svg>
                    </span>
                    <span class="label">Dashboard</span>
                </a>
            </li>
            <li class="<?php echo $currentPage === 'inventory.php' ? 'active' : ''; ?>">
                <a href="inventory.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    </span>
                    <span class="label">Inventory</span>
                </a>
            </li>
            <li class="<?php echo $currentPage === 'orders.php' ? 'active' : ''; ?>">
                <a href="orders.php">
                    <span class="icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                            <polyline points="14,2 14,8 20,8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10,9 9,9 8,9"/>
                        </svg>
                    </span>
                    <span class="label">Orders</span>
                </a>
            </li>
            <li class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
                <a href="profile.php">
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
            <span class="icon">
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