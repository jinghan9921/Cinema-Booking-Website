<?php
session_start();

$conn = new mysqli("localhost", "root", "", "moviedb");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin_dashboard.php");
    exit();
}


// Handle status updates
if (isset($_POST['update_contact_status'])) {
    $id = intval($_POST['contact_id']);
    $status = $conn->real_escape_string($_POST['status']);
    $conn->query("UPDATE contact_submissions SET status='$status' WHERE id=$id");
    header("Location: admin_contact_applications.php?tab=contact");
    exit();
}

if (isset($_POST['update_application_status'])) {
    $id = intval($_POST['application_id']);
    $status = $conn->real_escape_string($_POST['status']);
    $conn->query("UPDATE job_applications SET status='$status' WHERE id=$id");
    header("Location: admin_contact_applications.php?tab=applications");
    exit();
}

// Handle delete
if (isset($_GET['delete_contact'])) {
    $id = intval($_GET['delete_contact']);
    $conn->query("DELETE FROM contact_submissions WHERE id=$id");
    header("Location: admin_contact_applications.php?tab=contact");
    exit();
}

if (isset($_GET['delete_application'])) {
    $id = intval($_GET['delete_application']);
    $conn->query("DELETE FROM job_applications WHERE id=$id");
    header("Location: admin_contact_applications.php?tab=applications");
    exit();
}
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'contact';
?>

<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="sidebarandfooter.css">
  <title>Contact & Applications Management</title>
  <style>
    body { margin:0; font-family: 'Segoe UI', Arial, sans-serif; background:#191a1c; color:#eee; }
    #content { 
      max-width: 1500px; 
      margin: 0 auto; 
      padding: 40px 16px 120px 220px;
      min-height: 100vh;
    }
    
    .header-section {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }
    
    .header-section h1 {
      color: #fff;
      margin: 0;
      font-size: 32px;
    }
    
    .user-badge {
      display: inline-flex;
      align-items: center;
      background: linear-gradient(135deg, #2a3f5a 0%, #21344b 100%);
      padding: 12px 25px;
      border-radius: 30px;
      border: 2px solid #ffa726;
    }
    
    .user-badge .username {
      color: #ffa726;
      font-weight: bold;
      font-size: 16px;
      margin-right: 15px;
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
    }
    
    .nav-buttons {
      display: flex;
      gap: 15px;
      margin: 25px 0;
      padding-bottom: 25px;
      border-bottom: 2px solid #2a3f5a;
    }
    
    .nav-btn {
      background: #ffa726;
      padding: 12px 28px;
      border-radius: 8px;
      color: #23325d;
      font-weight: bold;
      text-decoration: none;
      font-size: 15px;
      transition: all 0.3s ease;
    }
    
    .tab-navigation {
      display: flex;
      gap: 10px;
      margin: 30px 0 20px 0;
      border-bottom: 2px solid #2a3f5a;
    }
    
    .tab-btn {
      background: transparent;
      padding: 12px 28px;
      border: none;
      border-bottom: 3px solid transparent;
      color: #aaa;
      font-weight: bold;
      font-size: 16px;
      cursor: pointer;
      text-decoration: none;
    }
    
    .tab-btn.active {
      color: #ffa726;
      border-bottom-color: #ffa726;
    }
    
    .table-card {
      background: #23242a;
      border-radius: 10px;
      margin-top: 20px;
      padding: 20px;
      overflow-x: auto;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }
    
    th, td {
      padding: 12px;
      border-bottom: 1px solid #2a2b31;
      text-align: left;
    }
    
    th {
      background: #212f49;
      color: #ffa726;
      font-weight: bold;
    }
    
    tr:hover {
      background: #2a2c33;
    }
    
    .status-badge {
      padding: 4px 12px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: bold;
    }
    
    .status-new { background: #4caf50; color: white; }
    .status-read { background: #2196F3; color: white; }
    .status-responded { background: #9e9e9e; color: white; }
    .status-pending { background: #ff9800; color: white; }
    .status-reviewed { background: #2196F3; color: white; }
    .status-shortlisted { background: #4caf50; color: white; }
    .status-rejected { background: #f44336; color: white; }
    
    select {
      padding: 6px 10px;
      background: #191b20;
      color: #eee;
      border: 1px solid #444;
      border-radius: 4px;
      font-size: 13px;
    }
    
    .action-btn {
      background: #ffa726;
      color: #000;
      padding: 5px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      font-weight: bold;
      margin-right: 5px;
    }
    
    .delete-btn {
      background: #f44336;
      color: white;
      padding: 5px 12px;
      border-radius: 4px;
      text-decoration: none;
      font-size: 12px;
      font-weight: bold;
    }
    
    .view-btn {
      background: #2196F3;
      color: white;
      padding: 5px 12px;
      border-radius: 4px;
      text-decoration: none;
      font-size: 12px;
      font-weight: bold;
      margin-right: 5px;
    }
  </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div id="content">
  <div class="header-section">
    <h1>Contact & Applications Management</h1>
    <div class="user-badge">
      <span class="username"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
      <a href="?logout" class="logout-btn">Logout</a>
    </div>
  </div>
  
  <div class="nav-buttons">
    <a href="manage_movies.php" class="nav-btn">Manage Movies</a>
    <a href="manage_showtimes.php" class="nav-btn">Manage Showtimes</a>
    <a href="manage_fnb.php" class="nav-btn">Manage Food & Beverages</a>
    <a href="admin_contact_applications.php" class="nav-btn">Feedback and Careers</a>
  </div>

  <div class="tab-navigation">
    <a href="?tab=contact" class="tab-btn <?= $tab === 'contact' ? 'active' : '' ?>">Contact Submissions</a>
    <a href="?tab=applications" class="tab-btn <?= $tab === 'applications' ? 'active' : '' ?>">Job Applications</a>
  </div>

  <?php if ($tab === 'contact'): ?>
    <!-- CONTACT SUBMISSIONS -->
    <div class="table-card">
      <table>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Subject</th>
          <th>Message</th>
          <th>Date</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
        <?php
        $result = $conn->query("SELECT * FROM contact_submissions ORDER BY submitted_at DESC");
        while ($row = $result->fetch_assoc()):
        ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['full_name']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td><?= htmlspecialchars($row['phone']) ?></td>
          <td><?= htmlspecialchars($row['subject']) ?></td>
          <td><?= substr(htmlspecialchars($row['message']), 0, 50) ?>...</td>
          <td><?= date('d M Y', strtotime($row['submitted_at'])) ?></td>
          <td>
            <form method="post" style="display:inline;">
              <input type="hidden" name="contact_id" value="<?= $row['id'] ?>">
              <select name="status" onchange="this.form.submit()">
                <option value="new" <?= $row['status']=='new'?'selected':'' ?>>New</option>
                <option value="read" <?= $row['status']=='read'?'selected':'' ?>>Read</option>
                <option value="responded" <?= $row['status']=='responded'?'selected':'' ?>>Responded</option>
              </select>
              <input type="hidden" name="update_contact_status" value="1">
            </form>
          </td>
          <td>
            <a href="?delete_contact=<?= $row['id'] ?>&tab=contact" class="delete-btn" onclick="return confirm('Delete this submission?')">Delete</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </table>
    </div>

  <?php else: ?>
    <!-- JOB APPLICATIONS -->
    <div class="table-card">
      <table>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Position</th>
          <th>Start Date</th>
          <th>Resume</th>
          <th>Date Applied</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
        <?php
        $result = $conn->query("SELECT * FROM job_applications ORDER BY submitted_at DESC");
        while ($row = $result->fetch_assoc()):
        ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['full_name']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td><?= htmlspecialchars($row['phone']) ?></td>
          <td><?= htmlspecialchars($row['position']) ?></td>
          <td><?= date('d M Y', strtotime($row['start_date'])) ?></td>
          <td>
            <?php if ($row['resume_path']): ?>
              <a href="<?= htmlspecialchars($row['resume_path']) ?>" target="_blank" class="view-btn">View</a>
            <?php else: ?>
              No file
            <?php endif; ?>
          </td>
          <td><?= date('d M Y', strtotime($row['submitted_at'])) ?></td>
          <td>
            <form method="post" style="display:inline;">
              <input type="hidden" name="application_id" value="<?= $row['id'] ?>">
              <select name="status" onchange="this.form.submit()">
                <option value="pending" <?= $row['status']=='pending'?'selected':'' ?>>Pending</option>
                <option value="reviewed" <?= $row['status']=='reviewed'?'selected':'' ?>>Reviewed</option>
                <option value="shortlisted" <?= $row['status']=='shortlisted'?'selected':'' ?>>Shortlisted</option>
                <option value="rejected" <?= $row['status']=='rejected'?'selected':'' ?>>Rejected</option>
              </select>
              <input type="hidden" name="update_application_status" value="1">
            </form>
          </td>
          <td>
            <a href="?delete_application=<?= $row['id'] ?>&tab=applications" class="delete-btn" onclick="return confirm('Delete this application?')">Delete</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </table>
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
