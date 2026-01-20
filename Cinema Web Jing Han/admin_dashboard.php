<?php
session_start();

$conn = new mysqli("localhost", "root", "", "moviedb");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin_dashboard.php");
    exit();
}

// Handle login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (hash('sha256', $pass) === $row['password_hash']) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $user;
            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error = "Invalid credentials.";
        }
    } else {
        $error = "Invalid credentials.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="sidebarandfooter.css">
  <title>Admin Dashboard</title>
  <style>
    body { margin:0; font-family: Arial, sans-serif; background:#191a1c; color:#eee; }
    #sidebar { position:fixed; width:200px; height:100vh; background:#21344b; padding-top:30px; }
    #sidebar h2 { color:#ffa726; padding-left:20px; }
    #sidebar ul { list-style:none; padding:0; }
    #sidebar ul li a { padding:15px 20px; display:block; color:#fff; text-decoration:none; }
    #sidebar ul li a:hover { background:#e08d0fc5; color:#ffa726; }
    #content { margin-left:200px; padding:40px; }
    a.btn { 
      background:#ffa726; 
      padding:15px 30px; 
      border-radius:8px; 
      color:#23325d; 
      font-weight:bold; 
      text-decoration:none; 
      margin-right:15px; 
      display:inline-block; 
      margin-top:15px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(255, 167, 38, 0.3);
    }
    a.btn:hover { 
      background:#ff9800; 
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(255, 167, 38, 0.4);
    }
    
    /* Login Form Styles */
    .login-container {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: calc(100vh - 80px);     
    }
    
    .login-box {
      width: 700px;
      background: linear-gradient(145deg, #2a3f5a 0%, #21344b 100%);
      padding: 50px 80px;
      border-radius: 15px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.6);
      border: 1px solid rgba(255, 167, 38, 0.1);
    }
    
    .login-box h2 {
      color: #ffa726;
      text-align: center;
      margin-bottom: 35px;
      font-size: 32px;
      font-weight: 600;
      letter-spacing: 1px;
    }
    
    .form-group {
      margin-bottom: 25px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 10px;
      color: #ffa726;
      font-weight: 600;
      font-size: 14px;
      letter-spacing: 0.5px;
    }
    
    .form-group input {
      width: 100%;
      padding: 15px;
      border: 2px solid #3a4f6b;
      border-radius: 8px;
      background: #1a2938;
      color: #fff;
      font-size: 15px;
      box-sizing: border-box;
      transition: all 0.3s ease;
    }
    
    .form-group input:focus {
      outline: none;
      border-color: #ffa726;
      background: #223344;
      box-shadow: 0 0 10px rgba(255, 167, 38, 0.2);
    }
    
    .error-message {
      background: linear-gradient(135deg, #d32f2f 0%, #c62828 100%);
      color: white;
      padding: 14px;
      border-radius: 8px;
      margin-bottom: 25px;
      text-align: center;
      font-weight: 500;
      box-shadow: 0 4px 15px rgba(211, 47, 47, 0.3);
    }
    
    .login-btn {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, #ffa726 0%, #ff9800 100%);
      color: #23325d;
      border: none;
      border-radius: 8px;
      font-size: 17px;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 5px 20px rgba(255, 167, 38, 0.4);
      margin-top: 10px;
      letter-spacing: 1px;
    }
    
    .login-btn:hover {
      background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
      transform: translateY(-2px);
      box-shadow: 0 7px 25px rgba(255, 167, 38, 0.5);
    }
    
    .login-btn:active {
      transform: translateY(0);
    }
    
    /* Dashboard Header Styles */
    .dashboard-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 35px;
      padding-bottom: 20px;
      border-bottom: 2px solid #2a3f5a;
    }
    
    .welcome-section h1 {
      font-size: 36px;
      color: #fff;
      margin: 0;
      font-weight: 600;
    }
    
    .user-badge {
      display: inline-flex;
      align-items: center;
      background: linear-gradient(135deg, #2a3f5a 0%, #21344b 100%);
      padding: 12px 25px;
      border-radius: 30px;
      margin-top: 15px;
      border: 2px solid #ffa726;
      box-shadow: 0 4px 15px rgba(255, 167, 38, 0.2);
    }
    
    .user-badge .username {
      color: #ffa726;
      font-weight: bold;
      font-size: 16px;
      margin-right: 15px;
      letter-spacing: 0.5px;
    }
    
    .logout-btn {
      background: #ffa726;
      color: #23325d;
      padding: 8px 20px;
      border-radius: 20px;
      text-decoration: none;
      font-size: 14px;
      font-weight: bold;
      transition: all 0.3s ease;
      box-shadow: 0 3px 10px rgba(255, 167, 38, 0.3);
    }
    
    .logout-btn:hover {
      background: #ff9800;
      transform: scale(1.05);
      box-shadow: 0 4px 15px rgba(255, 167, 38, 0.4);
    }
    
    .buttons-container {
      margin-top: 30px;
    }
  </style>
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div id="content">
    <?php if (!isset($_SESSION['admin_logged_in'])): ?>
      <!-- Login Form -->
      <div class="login-container">
        <div class="login-box">
          <h2>Admin Login</h2>
          <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>
          <form method="POST" action="">
            <div class="form-group">
              <label>Username</label>
              <input type="text" name="username" required autocomplete="username">
            </div>
            <div class="form-group">
              <label>Password</label>
              <input type="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" name="login" class="login-btn">Login</button>
          </form>
        </div>
      </div>
    <?php else: ?>
      <!-- Dashboard Content (After Login) -->
      <div class="dashboard-header">
        <div class="welcome-section">
          <h1>Welcome, please proceed to edit!</h1>
          <div class="user-badge">
            <span class="username"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            <a href="?logout" class="logout-btn">Logout</a>
          </div>
        </div>
      </div>
      
      <div class="buttons-container">
        <a href="manage_movies.php" class="btn">Manage Movies</a>
        <a href="manage_showtimes.php" class="btn">Manage Showtimes</a>
        <a href="manage_fnb.php" class="btn">Manage Food & Beverages</a>
        <a href="admin_contact_applications.php" class="btn">Feedback and Careers</a>
      </div>
    <?php endif; ?>
  </div>
<div id="footer">
  <span><a href="contact_careers.php#contact-us">Contact Us</a></span>
  <span><a href="contact_careers.php#career-opportunities">Career Opportunities</a></span>
  <span><a href="admin_dashboard.php">Admin Panel</a></span>
  <div class="cr">Copyright &copy; IE4727WebDev</div>
</div>
</body>
</html>
<?php $conn->close(); ?>
