<?php
$conn = new mysqli('localhost', 'root', '', 'moviedb');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Get filter values from GET parameters
$date = isset($_GET['date']) ? $_GET['date'] : '';
$movieId = isset($_GET['movie_id']) ? intval($_GET['movie_id']) : 0;
$cinemaId = isset($_GET['cinema_id']) ? intval($_GET['cinema_id']) : 0;

// Get all movies for dropdown
$moviesSql = "SELECT DISTINCT MovieID, Title FROM movie_details ORDER BY Title";
$moviesResult = $conn->query($moviesSql);

// Get all cinemas for dropdown
$cinemasSql = "SELECT DISTINCT CinemaID, CinemaName FROM cinemas ORDER BY CinemaName";
$cinemasResult = $conn->query($cinemasSql);

// Get available dates
$datesSql = "SELECT DISTINCT ShowDate FROM movie_showtimes WHERE ShowDate >= CURDATE() ORDER BY ShowDate LIMIT 7";
$datesResult = $conn->query($datesSql);

// Build filtered query
if ($date || $movieId || $cinemaId) {
    $sql = "SELECT DISTINCT md.* FROM movie_details md 
            INNER JOIN movie_showtimes st ON md.MovieID = st.MovieID 
            WHERE 1=1";
    
    if ($date) {
        $sql .= " AND st.ShowDate = '" . $conn->real_escape_string($date) . "'";
    }
    if ($movieId) {
        $sql .= " AND md.MovieID = $movieId";
    }
    if ($cinemaId) {
        $sql .= " AND st.CinemaID = $cinemaId";
    }
} else {
    $sql = "SELECT * FROM movie_details";
}

$result = $conn->query($sql);

include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>IE Theatre | Movies</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Link to Combined External CSS -->
  <link rel="stylesheet" href="sidebarandfooter.css">
  
  <style>
    body {
      background: #191a1c;
      margin: 0;
      font-family: 'Segoe UI', Arial, sans-serif;
      color: #eee;
      padding-bottom: 70px;
      padding-top: 70px;
      padding-left: 200px;
    }
    
    /* Filter Bar - CENTERED WITH CLEAR BUTTON */
    .filter-bar {
      position: fixed;
      top: 0;
      left: 200px;
      right: 0;
      background: #2a2d35;
      padding: 15px 20px;
      display: flex;
      justify-content: center;
      align-items: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
      z-index: 5;
      height: 60px;
    }
    
    .filter-bar form {
      display: flex;
      gap: 60px; 
      align-items: center;
      justify-content: center;
    }
    
    .filter-bar select {
      padding: 10px 20px; 
      background: #21344b;
      color: #fff;
      border: 1px solid #555;
      border-radius: 8px; 
      font-size: 1em;
      cursor: pointer;
      min-width: 200px; 
      transition: all 0.3s ease;
    }
    
    .filter-bar select:hover {
      border-color: #e96b39;
      transform: translateY(-2px);
    }
    
    .filter-bar select:focus {
      outline: none;
      border-color: #ffa726;
      box-shadow: 0 0 10px rgba(255, 167, 38, 0.3);
    }
    
    .clear-btn {
      padding: 10px 25px;
      background: #dc3545;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1em;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-block;
    }
    
    .clear-btn:hover {
      background: #c82333;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
    }
    
    /* Content Container */
    #content {
      min-height: calc(100vh - 140px);
    }
    
    /* Movies Grid */
    .movies-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 30px;
      padding: 40px;
      max-width: 1600px;
      margin: 0 auto;
      justify-content: center;
    }
    
    .movie-card {
      background: #2a2d35;
      border-radius: 10px;
      overflow: hidden;
      transition: transform 0.2s, box-shadow 0.2s;
      width: 280px;
      flex-shrink: 0;
    }
    
    .movie-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(233, 107, 57, 0.3);
    }
    
    .movie-card img {
      width: 100%;
      height: 380px;
      object-fit: cover;
    }
    
    .movie-info {
      padding: 15px;
    }
    
    .movie-title {
      font-size: 1.2em;
      font-weight: bold;
      color: #ffa726;
      margin: 0 0 8px 0;
      min-height: 55px;
      display: flex;
      align-items: center;
    }
    
    .movie-runtime {
      color: #aaa;
      font-size: 0.95em;
      margin-bottom: 12px;
    }
    
    .buy-btn {
      display: block;
      background: #e96b39;
      color: white;
      padding: 12px 20px;
      border-radius: 5px;
      text-decoration: none;
      font-weight: bold;
      transition: background 0.2s;
      border: none;
      cursor: pointer;
      text-align: center;
    }
    
    .buy-btn:hover {
      background: #d55a28;
    }
    
    .no-results {
      width: 100%;
      color: #faa726;
      font-size: 1.3em;
      text-align: center;
      padding: 60px 20px;
    }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
      body {
        padding-left: 0;
        padding-top: 140px; 
      }
      
      .filter-bar {
        left: 0;
        height: auto;
        padding: 15px;
      }
      
      .filter-bar form {
        flex-direction: column;
        width: 100%;
        gap: 12px;
      }
      
      .filter-bar select,
      .clear-btn {
        width: 100%;
        min-width: auto;
      }
      
      .movies-grid {
        padding: 20px;
        gap: 20px;
      }
      
      .movie-card {
        width: 100%;
        max-width: 320px;
      }
    }
  </style>
</head>
<body>

<div id="content">
  <!-- FILTER BAR WITH CLEAR BUTTON -->
  <div class="filter-bar">
    <form id="filter-form" method="GET" action="index.php">
      <select id="date-filter" name="date" onchange="this.form.submit()">
        <option value="">All Dates</option>
        <?php 
        $datesResult->data_seek(0);
        while ($dateRow = $datesResult->fetch_assoc()): 
        ?>
          <option value="<?= $dateRow['ShowDate'] ?>" 
              <?= ($date == $dateRow['ShowDate']) ? 'selected' : '' ?>>
            <?= date('D, d M Y', strtotime($dateRow['ShowDate'])) ?>
          </option>
        <?php endwhile; ?>
      </select>

      <select id="movie-filter" name="movie_id" onchange="this.form.submit()">
        <option value="">All Movies</option>
        <?php 
        $moviesResult->data_seek(0);
        while ($movieRow = $moviesResult->fetch_assoc()): 
        ?>
          <option value="<?= $movieRow['MovieID'] ?>"
              <?= ($movieId == $movieRow['MovieID']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($movieRow['Title']) ?>
          </option>
        <?php endwhile; ?>
      </select>

      <select id="cinema-filter" name="cinema_id" onchange="this.form.submit()">
        <option value="">All Cinemas</option>
        <?php 
        $cinemasResult->data_seek(0);
        while ($cinemaRow = $cinemasResult->fetch_assoc()): 
        ?>
          <option value="<?= $cinemaRow['CinemaID'] ?>"
              <?= ($cinemaId == $cinemaRow['CinemaID']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($cinemaRow['CinemaName']) ?>
          </option>
        <?php endwhile; ?>
      </select>
      
      <!-- CLEAR FILTERS BUTTON -->
      <?php if ($date || $movieId || $cinemaId): ?>
      <a href="index.php" class="clear-btn">✕ Clear Filters</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Movies Grid -->
  <div class="movies-grid">
    <?php
    if ($result && $result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
        echo "<div class='movie-card'>";
        echo "<img src='" . htmlspecialchars($row["Img Path"]) . "' alt='" . htmlspecialchars($row["Title"]) . "'>";
        echo "<div class='movie-info'>";
        echo "<div class='movie-title'>" . htmlspecialchars($row["Title"]) . "</div>";
        echo "<div class='movie-runtime'>" . htmlspecialchars($row["Runtime"]) . "</div>";
        echo "<a href='movie.php?movie_id=" . $row['MovieID'] . "' class='buy-btn'>Buy Tickets</a>";
        echo "</div>";
        echo "</div>";
      }
    } else {
      echo "<div class='no-results'>No movies found. <a href='index.php' style='color:#e96b39; text-decoration:underline;'>Clear filters</a></div>";
    }
    ?>
  </div>
</div>

<?php include 'footer.php'; ?>

<script>
window.addEventListener('DOMContentLoaded', function() {
    console.log('Index page loaded - Filter system active');
});
</script>

</body>
</html>
<?php $conn->close(); ?>

