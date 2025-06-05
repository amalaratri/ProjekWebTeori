<?php
/**
 * PharmaSys Installation Script
 * This script helps set up the database and initial configuration
 */

// Check if already installed
if (file_exists('config/installed.lock')) {
    die('PharmaSys is already installed. Delete config/installed.lock to reinstall.');
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_connection'])) {
        // Test database connection
        try {
            $host = $_POST['db_host'];
            $username = $_POST['db_username'];
            $password = $_POST['db_password'];
            
            $pdo = new PDO("mysql:host=$host", $username, $password);
            $success = 'Database connection successful!';
            
            // Store connection details in session for next step
            session_start();
            $_SESSION['db_config'] = [
                'host' => $host,
                'username' => $username,
                'password' => $password,
                'database' => $_POST['db_name']
            ];
            
        } catch (PDOException $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['install_database'])) {
        session_start();
        $db_config = $_SESSION['db_config'];
        
        try {
            // Create database and import schema
            $pdo = new PDO("mysql:host={$db_config['host']}", $db_config['username'], $db_config['password']);
            
            // Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS {$db_config['database']}");
            $pdo->exec("USE {$db_config['database']}");
            
            // Read and execute SQL file
            $sql = file_get_contents('pharmasys_database.sql');
            $pdo->exec($sql);
            
            // Create config file
            $config_content = "<?php
class Database {
    private \$host = '{$db_config['host']}';
    private \$db_name = '{$db_config['database']}';
    private \$username = '{$db_config['username']}';
    private \$password = '{$db_config['password']}';
    private \$charset = 'utf8mb4';
    private \$conn;

    public function getConnection() {
        \$this->conn = null;
        
        try {
            \$dsn = \"mysql:host=\" . \$this->host . \";dbname=\" . \$this->db_name . \";charset=\" . \$this->charset;
            \$options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            \$this->conn = new PDO(\$dsn, \$this->username, \$this->password, \$options);
        } catch(PDOException \$exception) {
            echo \"Connection error: \" . \$exception->getMessage();
        }
        
        return \$this->conn;
    }
}

function getDBConnection() {
    \$database = new Database();
    return \$database->getConnection();
}
?>";
            
            // Create config directory if it doesn't exist
            if (!is_dir('config')) {
                mkdir('config', 0755, true);
            }
            
            file_put_contents('config/database.php', $config_content);
            
            // Create installation lock file
            file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
            
            $success = 'PharmaSys has been installed successfully!';
            $step = 3;
            
        } catch (Exception $e) {
            $error = 'Installation failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys Installation</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card large">
            <div class="auth-header">
                <h1>PharmaSys</h1>
                <p>Installation Wizard - Step <?php echo $step; ?> of 3</p>
            </div>
            
            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-step <?php echo $step >= 1 ? 'active' : ''; ?>">
                        <div class="step-number">1</div>
                        <div class="step-label">Database Config</div>
                    </div>
                    <div class="progress-line <?php echo $step >= 2 ? 'active' : ''; ?>"></div>
                    <div class="progress-step <?php echo $step >= 2 ? 'active' : ''; ?>">
                        <div class="step-number">2</div>
                        <div class="step-label">Install Database</div>
                    </div>
                    <div class="progress-line <?php echo $step >= 3 ? 'active' : ''; ?>"></div>
                    <div class="progress-step <?php echo $step >= 3 ? 'active' : ''; ?>">
                        <div class="step-number">3</div>
                        <div class="step-label">Complete</div>
                    </div>
                </div>
            </div>
            
            <?php if ($step === 1): ?>
                <!-- Step 1: Database Configuration -->
                <form action="" method="POST" class="auth-form">
                    <h2>Database Configuration</h2>
                    <p>Please provide your database connection details.</p>
                    
                    <?php if ($error): ?>
                        <div class="alert error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="db_host">Database Host</label>
                            <input type="text" id="db_host" name="db_host" value="localhost" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_name">Database Name</label>
                            <input type="text" id="db_name" name="db_name" value="pharmasys" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_username">Database Username</label>
                            <input type="text" id="db_username" name="db_username" value="root" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_password">Database Password</label>
                            <input type="password" id="db_password" name="db_password">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="test_connection" class="btn primary">Test Connection</button>
                        <?php if ($success): ?>
                            <a href="?step=2" class="btn">Next Step</a>
                        <?php endif; ?>
                    </div>
                </form>
                
            <?php elseif ($step === 2): ?>
                <!-- Step 2: Install Database -->
                <form action="" method="POST" class="auth-form">
                    <h2>Install Database</h2>
                    <p>Click the button below to create the database schema and sample data.</p>
                    
                    <?php if ($error): ?>
                        <div class="alert error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <div class="installation-info">
                        <h3>What will be installed:</h3>
                        <ul>
                            <li>Database tables for users, pharmacies, doctors, medications, and orders</li>
                            <li>Sample medication categories and medications</li>
                            <li>Sample pharmacy and doctor accounts</li>
                            <li>Database views and stored procedures</li>
                            <li>Configuration files</li>
                        </ul>
                        
                        <h3>Default Login Credentials:</h3>
                        <div class="credentials">
                            <p><strong>Pharmacy:</strong> apotek.sehat@example.com / password</p>
                            <p><strong>Doctor:</strong> dr.budi@example.com / password</p>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="?step=1" class="btn btn-secondary">Previous</a>
                        <button type="submit" name="install_database" class="btn primary">Install Database</button>
                    </div>
                </form>
                
            <?php else: ?>
                <!-- Step 3: Installation Complete -->
                <div class="auth-form">
                    <h2>Installation Complete!</h2>
                    
                    <?php if ($success): ?>
                        <div class="alert success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <div class="completion-info">
                        <h3>🎉 PharmaSys is now ready to use!</h3>
                        
                        <div class="next-steps">
                            <h4>Next Steps:</h4>
                            <ol>
                                <li>Delete this installation file (install.php) for security</li>
                                <li>Login with the default credentials provided</li>
                                <li>Change default passwords</li>
                                <li>Configure your pharmacy/doctor information</li>
                                <li>Start managing your pharmacy operations</li>
                            </ol>
                        </div>
                        
                        <div class="login-info">
                            <h4>Default Login Credentials:</h4>
                            <p><strong>Pharmacy:</strong> apotek.sehat@example.com / password</p>
                            <p><strong>Doctor:</strong> dr.budi@example.com / password</p>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="login.php" class="btn primary">Go to Login</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
        .installation-info {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .installation-info h3 {
            color: #1e293b;
            margin-bottom: 12px;
            font-size: 1rem;
        }
        
        .installation-info ul {
            margin-left: 20px;
            margin-bottom: 20px;
        }
        
        .installation-info li {
            margin-bottom: 8px;
            color: #64748b;
        }
        
        .credentials {
            background-color: #e0f2fe;
            padding: 12px;
            border-radius: 6px;
            border-left: 4px solid #0369a1;
        }
        
        .credentials p {
            margin: 4px 0;
            font-family: monospace;
            font-size: 0.875rem;
        }
        
        .completion-info {
            text-align: center;
            padding: 20px 0;
        }
        
        .next-steps, .login-info {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .next-steps ol {
            margin-left: 20px;
        }
        
        .next-steps li {
            margin-bottom: 8px;
            color: #64748b;
        }
    </style>
</body>
</html>
