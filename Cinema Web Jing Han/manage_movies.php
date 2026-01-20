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

// Check if user is logged in, redirect if not
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_dashboard.php");
    exit();
}

function uploadFile($fileInputName, $targetFolder) {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == UPLOAD_ERR_OK) {
        $filename = basename($_FILES[$fileInputName]['name']);
        $targetPath = $targetFolder . $filename;
        if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetPath)) {
            return $targetPath;
        }
    }
    return '';
}

// Handle update
if (isset($_POST['update'])) {
    $id = intval($_POST['movie_id']);
    $title = $conn->real_escape_string($_POST['title']);
    $genre = $conn->real_escape_string($_POST['genre']);
    $director = $conn->real_escape_string($_POST['director']);
    $runtime = $conn->real_escape_string($_POST['runtime']);
    $synopsis = $conn->real_escape_string($_POST['synopsis']);
    $cast = $conn->real_escape_string($_POST['cast']);
    $subtitles = $conn->real_escape_string($_POST['subtitles']);
    $language = $conn->real_escape_string($_POST['language']);
    $release_date = $conn->real_escape_string($_POST['release_date']);
    $age_rating = $conn->real_escape_string($_POST['age_rating']);

    $img_path = uploadFile('img_file', 'images/');
    $vid_path = uploadFile('vid_file', 'videos/');

    $res = $conn->query("SELECT `Img Path`, `Vid Path` FROM movie_details WHERE MovieID=$id");
    $existing = $res->fetch_assoc();

    if ($img_path == '') $img_path = $existing['Img Path'];
    if ($vid_path == '') $vid_path = $existing['Vid Path'];

    $img_path = $conn->real_escape_string($img_path);
    $vid_path = $conn->real_escape_string($vid_path);

    $conn->query("UPDATE movie_details SET 
      `Title`='$title', `Genre`='$genre', `Director`='$director', `Runtime`='$runtime', 
      `Img Path`='$img_path', `Vid Path`='$vid_path', `Synopsis`='$synopsis', `Cast`='$cast', 
      `Subtitles`='$subtitles', `Language`='$language', `Release Date`='$release_date', `Age Rating`='$age_rating' 
      WHERE MovieID=$id");
    header("Location: manage_movies.php");
    exit;
}

// Handle add
if (isset($_POST['add'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $genre = $conn->real_escape_string($_POST['genre']);
    $director = $conn->real_escape_string($_POST['director']);
    $runtime = $conn->real_escape_string($_POST['runtime']);
    $synopsis = $conn->real_escape_string($_POST['synopsis']);
    $cast = $conn->real_escape_string($_POST['cast']);
    $subtitles = $conn->real_escape_string($_POST['subtitles']);
    $language = $conn->real_escape_string($_POST['language']);
    $release_date = $conn->real_escape_string($_POST['release_date']);
    $age_rating = $conn->real_escape_string($_POST['age_rating']);

    $img_path = uploadFile('img_file', 'images/');
    $vid_path = uploadFile('vid_file', 'videos/');
    $img_path = $conn->real_escape_string($img_path);
    $vid_path = $conn->real_escape_string($vid_path);

    $conn->query("INSERT INTO movie_details 
      (`Title`, `Genre`, `Director`, `Runtime`, `Img Path`, `Vid Path`, `Synopsis`, `Cast`, 
      `Subtitles`, `Language`,`Release Date`, `Age Rating`) 
      VALUES ('$title', '$genre', '$director', '$runtime', '$img_path', '$vid_path', '$synopsis',
       '$cast', '$subtitles','$language', '$release_date', '$age_rating')");
    header("Location: manage_movies.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM movie_details WHERE MovieID = $id");
    header("Location: manage_movies.php");
    exit;
}

// Prepare edit if any
$edit_mode = false;
$edit_movie = null;
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM movie_details WHERE MovieID = $id");
    if ($res->num_rows > 0) {
        $edit_movie = $res->fetch_assoc();
    } else {
        $edit_mode = false;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="sidebarandfooter.css">
  <title>Movie Management</title>
  <style>
    body { margin:0; font-family: 'Segoe UI', Arial, sans-serif; background:#191a1c; color:#eee; }
    #content { 
      max-width: 1500px; 
      margin: 0 auto; 
      padding: 40px 16px 120px 220px;
      min-height: 100vh;
    }
    h2 { 
      color:#ffa726; 
      margin-top:50px; 
    }

    input[type="date"]::-webkit-calendar-picker-indicator {
      filter: invert(1);
      cursor: pointer;
    }
    .movie-form-card {
      background: #23242a;
      border-radius: 14px;
      box-shadow: 0 2px 12px #0002;
      padding: 34px 36px 18px 36px;
      margin-bottom: 38px;
      max-width: 1500px;
      display: flex; flex-direction: column; gap: 20px;
    }
    .form-grid { 
      display: grid; 
      grid-template-columns: 1fr 1fr; 
      gap: 18px 32px; 
    }
    .form-group { 
      display: flex; 
      flex-direction: column; 
    }
    .form-group.full { 
      grid-column: 1 / 3; 
    }
    .movie-form-card label { 
      color: #ffa726; 
      font-weight: bold; 
      margin-bottom: 4px; 
      font-size: 15px; 
    }

    .movie-form-card input[type="text"],
    .movie-form-card input[type="date"],
    .movie-form-card textarea,
    .movie-form-card input[type="file"] {
      padding: 13px 14px; 
      border-radius: 7px; 
      border: none; 
      background:#191b20; 
      color:#eee;
      font-size:16px;
    }

    .movie-form-card textarea { 
      min-height: 100px; 
      font-size: 16px; 
      resize: vertical; 
    }

    .form-btn-row { 
      margin-top: 13px; 
      display: flex; 
      gap:17px; 
    }

    .movie-form-card button, .movie-form-card input[type="submit"] {
      background:#ffa726; 
      color:#23325d; 
      font-size:16px; 
      padding:12px 0;
      border: none; 
      border-radius:6px; 
      font-weight: bold; 
      cursor: pointer; 
      min-width: 140px;
      transition: background .15s;
    }

    .movie-form-card button:hover, .movie-form-card input[type="submit"]:hover { background: #ff9100; }
    @media (max-width:900px) {.form-grid{grid-template-columns:1fr;}.form-group.full{grid-column:1;}}

    .table-card {
      background: #23242a;
      border-radius: 10px;
      box-shadow: 0 2px 10px #0002;
      margin-top: 20px;
      padding-bottom: 10px;
      overflow-x: auto;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 15.7px;
      background: none;
      border-radius: 10px;
      margin-top: 0;
    }
    th, td {
      padding: 13px 18px;
      border-bottom: 1px solid #232330;
    }
    th {
      background: #212f49;
      color: #ffa726;
      text-align: left;
    }
    tr { transition: background .14s; }

    tr:hover { 
      background:#232c43; 
    }
    a.delete { 
      color: #ff5252; 
      text-decoration: none; 
      font-weight: 600; 
    }
    a.edit { 
      color: #4FC3F7; 
      text-decoration: none;
      font-weight: 600;
      margin-right: 13px; 
    }
    
    /* Header Section Styles */
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
    
    /* Navigation Buttons */
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
      box-shadow: 0 4px 15px rgba(255, 167, 38, 0.3);
    }
    
    .nav-btn:hover {
      background: #ff9800;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(255, 167, 38, 0.4);
    }
  </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div id="content">
  
  <div class="header-section">
    <h1>Movie Management</h1>
    <div class="user-badge">
      <span class="username"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
      <a href="?logout" class="logout-btn">Logout</a>
    </div>
  </div>
  
  <!-- Navigation Buttons -->
  <div class="nav-buttons">
    <a href="manage_movies.php" class="nav-btn">Manage Movies</a>
    <a href="manage_showtimes.php" class="nav-btn">Manage Showtimes</a>
    <a href="manage_fnb.php" class="nav-btn">Manage Food & Beverages</a>
    <a href="admin_contact_applications.php" class="nav-btn">Feedback and Careers</a>
  </div>

  <form method="post" class="movie-form-card" enctype="multipart/form-data" autocomplete="off">
    <?php if ($edit_mode && $edit_movie): ?>
      <input type="hidden" name="movie_id" value="<?php echo intval($edit_movie['MovieID']); ?>">
    <?php endif; ?>
    <div class="form-grid">
      <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" required value="<?php echo isset($edit_movie['Title']) ? htmlspecialchars($edit_movie['Title']) : ''; ?>">
      </div>
      <div class="form-group">
        <label>Genre</label>
        <input type="text" name="genre" required value="<?php echo isset($edit_movie['Genre']) ? htmlspecialchars($edit_movie['Genre']) : ''; ?>">
      </div>
      <div class="form-group">
        <label>Director</label>
        <input type="text" name="director" required value="<?php echo isset($edit_movie['Director']) ? htmlspecialchars($edit_movie['Director']) : ''; ?>">
      </div>
      <div class="form-group">
        <label>Runtime</label>
        <input type="text" name="runtime" placeholder="e.g. 2h 10m" required value="<?php echo isset($edit_movie['Runtime']) ? htmlspecialchars($edit_movie['Runtime']) : ''; ?>">
      </div>
      <div class="form-group">
        <label>Release Date</label>
        <input type="date" name="release_date" required value="<?php echo isset($edit_movie['Release Date']) ? htmlspecialchars($edit_movie['Release Date']) : ''; ?>">
      </div>
      <div class="form-group">
        <label>Age Rating</label>
        <input type="text" name="age_rating" required value="<?php echo isset($edit_movie['Age Rating']) ? htmlspecialchars($edit_movie['Age Rating']) : ''; ?>">
      </div>
      <div class="form-group">
        <label>Image File</label>
        <input type="file" name="img_file" accept="image/*">
        <?php if ($edit_mode && !empty($edit_movie['Img Path'])): ?>
          <small>Current: <?php echo htmlspecialchars($edit_movie['Img Path']); ?></small>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>Video File</label>
        <input type="file" name="vid_file" accept="video/*">
        <?php if ($edit_mode && !empty($edit_movie['Vid Path'])): ?>
          <small>Current: <?php echo htmlspecialchars($edit_movie['Vid Path']); ?></small>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>Language</label>
        <input type="text" name="language" required value="<?php echo isset($edit_movie['Language']) ? htmlspecialchars($edit_movie['Language']) : ''; ?>">
      </div>
      <div class="form-group">
        <label>Subtitles</label>
        <input type="text" name="subtitles" required value="<?php echo isset($edit_movie['Subtitles']) ? htmlspecialchars($edit_movie['Subtitles']) : ''; ?>">
      </div>
      <div class="form-group full">
        <label>Cast</label>
        <textarea name="cast" rows="2" required><?php echo isset($edit_movie['Cast']) ? htmlspecialchars($edit_movie['Cast']) : ''; ?></textarea>
      </div>
      <div class="form-group full">
        <label>Synopsis</label>
        <textarea name="synopsis" rows="5" required><?php echo isset($edit_movie['Synopsis']) ? htmlspecialchars($edit_movie['Synopsis']) : ''; ?></textarea>
      </div>
    </div>

    <div class="form-btn-row">
      <?php if ($edit_mode): ?>
        <input type="submit" name="update" value="Update Movie">
        <a href="manage_movies.php" style="color:#ffa726; font-weight:bold; align-self:center; margin-left: 12px;">Cancel</a>
      <?php else: ?>
        <input type="submit" name="add" value="Add Movie">
      <?php endif; ?>
    </div>
  </form>

  <?php
  echo "<div class='table-card'>";
  echo "<table><tr><th>Title</th><th>Genre</th><th>Director</th><th>Runtime</th><th>Action</th></tr>";
  $res = $conn->query("SELECT * FROM movie_details ORDER BY Title");
  if ($res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
      $id = intval($row['MovieID']);
      echo "<tr>";
      echo "<td>" . htmlspecialchars($row['Title']) . "</td>";
      echo "<td>" . htmlspecialchars($row['Genre']) . "</td>";
      echo "<td>" . htmlspecialchars($row['Director']) . "</td>";
      echo "<td>" . htmlspecialchars($row['Runtime']) . "</td>";
      echo "<td><a href='?edit=$id' class='edit'>Edit</a>";
      echo "<a href='?delete=$id' class='delete' onclick=\"return confirm('Delete movie?');\">Delete</a></td>";
      echo "</tr>";
    }
  } else {
    echo "<tr><td colspan='5'>No movies found.</td></tr>";
  }
  echo "</table></div>";
  $conn->close();
  ?>

</div>
<div id="footer">
  <span><a href="contact_careers.php#contact-us">Contact Us</a></span>
  <span><a href="contact_careers.php#career-opportunities">Career Opportunities</a></span>
  <span><a href="admin_dashboard.php">Admin Panel</a></span>
  <div class="cr">Copyright &copy; IE4727WebDev</div>
</div>
</body>
</html>
