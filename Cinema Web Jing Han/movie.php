<?php
session_start();
date_default_timezone_set('Asia/Singapore'); // Set timezone

$conn = new mysqli('localhost', 'root', '', 'moviedb');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$movieId = isset($_GET['movie_id']) ? intval($_GET['movie_id']) : 0;
$selectedDate = isset($_GET['date']) ? $_GET['date'] : '';
$selectedCinema = isset($_GET['cinema']) ? intval($_GET['cinema']) : 0;
$selectedTheatre = isset($_GET['theatre']) ? intval($_GET['theatre']) : 0;
$selectedTime = isset($_GET['time']) ? intval($_GET['time']) : 0;

if ($movieId == 0) {
    header("Location: index.php");
    exit();
}

$sql = "SELECT * FROM movie_details WHERE MovieID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $movieId);
$stmt->execute();
$movie = $stmt->get_result()->fetch_assoc();
if (!$movie) {
    header("Location: index.php");
    exit();
}

$datesSql = "SELECT DISTINCT ShowDate FROM movie_showtimes WHERE MovieID = ? AND ShowDate >= CURDATE() ORDER BY ShowDate LIMIT 7";
$stmt = $conn->prepare($datesSql);
$stmt->bind_param("i", $movieId);
$stmt->execute();
$datesResult = $stmt->get_result();
$availableDates = [];
while ($row = $datesResult->fetch_assoc()) {
    $availableDates[] = $row['ShowDate'];
}
if (empty($selectedDate) && !empty($availableDates)) {
    $selectedDate = $availableDates[0];
}

$showtimes = [];
if (!empty($selectedDate)) {
    $showtimesSql = "SELECT st.ShowTimeID, st.ShowTime, st.CinemaID, st.TheatreID, c.CinemaName
                     FROM movie_showtimes st
                     INNER JOIN cinemas c ON st.CinemaID = c.CinemaID
                     WHERE st.MovieID = ? AND st.ShowDate = ?
                     ORDER BY c.CinemaName, st.ShowTime";
    $stmt = $conn->prepare($showtimesSql);
    $stmt->bind_param("is", $movieId, $selectedDate);
    $stmt->execute();
    $showtimesResult = $stmt->get_result();
    while ($row = $showtimesResult->fetch_assoc()) {
        $showtimes[] = $row;
    }
}   

$seats = [];
$showtimeInfo = null;
if ($selectedCinema > 0 && $selectedTheatre > 0 && $selectedTime > 0) {
    $showtimeInfoSql = "SELECT st.ShowTime, st.ShowDate, c.CinemaName 
                        FROM movie_showtimes st
                        INNER JOIN cinemas c ON st.CinemaID = c.CinemaID
                        WHERE st.ShowTimeID = ? AND st.CinemaID = ? AND st.TheatreID = ?";
    $stmt = $conn->prepare($showtimeInfoSql);
    $stmt->bind_param("iii", $selectedTime, $selectedCinema, $selectedTheatre);
    $stmt->execute();
    $showtimeInfo = $stmt->get_result()->fetch_assoc();

    // Cleanup expired reservations
    $cleanupSQL = "UPDATE seats SET reserved_until = NULL, reserved_by_session = NULL
                   WHERE CinemaID = ? AND TheatreID = ? AND ShowTimeID = ? AND reserved_until < NOW()";
    $cleanupStmt = $conn->prepare($cleanupSQL);
    $cleanupStmt->bind_param("iii", $selectedCinema, $selectedTheatre, $selectedTime);
    $cleanupStmt->execute();

    // fetch seats for seat map
    $seatsSql = "SELECT SeatNumber, IsBooked, reserved_until, reserved_by_session,
             UNIX_TIMESTAMP(reserved_until) as reserved_unix,
             UNIX_TIMESTAMP(NOW()) as now_unix
             FROM seats
             WHERE CinemaID = ? AND TheatreID = ? AND ShowTimeID = ?
             ORDER BY SUBSTRING(SeatNumber,1,1), CAST(SUBSTRING(SeatNumber,2) AS UNSIGNED)";
      $stmt = $conn->prepare($seatsSql);
      $stmt->bind_param("iii", $selectedCinema, $selectedTheatre, $selectedTime);
      $stmt->execute();
      $seatsResult = $stmt->get_result();

while ($row = $seatsResult->fetch_assoc()) {
    // Mark as reserved if ANYONE has reserved it (including you) and it's still valid
    $isReserved = !empty($row['reserved_until']) && $row['reserved_unix'] > $row['now_unix'];
    
    $seats[] = [
        'SeatNumber' => $row['SeatNumber'],
        'IsBooked' => $row['IsBooked'],
        'is_reserved' => $isReserved
    ];
}
}

include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title><?= htmlspecialchars($movie['Title']) ?> - IE Theatre</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="sidebarandfooter.css">
  
  <style>
    body {
      background: #191a1c;
      margin: 0;
      font-family: 'Segoe UI', Arial, sans-serif;
      color: #eee;
      padding-bottom: 70px;
      padding-left: 200px;
      padding-top: 20px;
    }

    .movie-container {
      max-width: 1400px;
      margin: 20px auto;
      padding: 20px;
    }
    
    .movie-header {
      display: flex;
      gap: 25px;
      margin-bottom: 40px;
      background: #2a2d35;
      padding: 28px;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.4);
      align-items: center;
    }
    
    .movie-poster {
      flex-shrink: 0;
      width: 360px;
    }
    
    .movie-poster img {
      width: 100%;
      height: 540px;
      object-fit: cover;
      border-radius: 8px;
      box-shadow: 0 6px 25px rgba(0,0,0,0.6);
    }
    
    .movie-info {
      flex: 1;
      display: flex;
      flex-direction: column;
      max-width: 850px;
    }
    
    .movie-title {
      font-size: 2em;
      color: #ffa726;
      margin: 0 0 15px 0;
      font-weight: bold;
      line-height: 1.2;
    }
    
    .trailer-section video {
        width: 100%;
        height: 300px;
        display: block;
        object-fit: cover;
    }

   
    
    .details-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
      margin: 14px 0;
    }
    
    .details-column {
      background: rgba(0,0,0,0.3);
      padding: 13px;
      border-radius: 8px;
      border-left: 3px solid #ffa726;
    }
    
    .details-row {
      display: flex;
      margin-bottom: 8px;
      padding-bottom: 8px;
      border-bottom: 1px solid rgba(255,167,38,0.12);
    }
    
    .details-row:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }
    
    .detail-label {
      color: #ffa726;
      font-weight: bold;
      font-size: 0.85em;
      min-width: 95px;
      flex-shrink: 0;
    }
    
    .detail-value {
      color: #ddd;
      font-size: 0.85em;
      line-height: 1.3;
    }
    
    .synopsis-section {
      margin-top: 14px;
      padding: 14px;
      background: rgba(0,0,0,0.3);
      border-radius: 8px;
      border-left: 3px solid #e96b39;
    }
    
    .synopsis-title {
      color: #ffa726;
      font-size: 1em;
      font-weight: bold;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .synopsis-text {
      line-height: 1.6;
      color: #ccc;
      font-size: 0.88em;
    }
    
    .showtimes-section {
      background: #2a2d35;
      padding: 32px;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.4);
      margin-bottom: 30px;
    }
    
    .section-title {
      font-size: 1.85em;
      color: #ffa726;
      margin: 0 0 24px 0;
      font-weight: bold;
      border-bottom: 3px solid #e96b39;
      padding-bottom: 10px;
    }
    
    .date-tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 28px;
      flex-wrap: wrap;
    }
    
    .date-tab {
      padding: 11px 22px;
      background: #21344b;
      color: #fff;
      text-decoration: none;
      border-radius: 6px;
      font-weight: bold;
      font-size: 0.92em;
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }
    
    .date-tab:hover {
      background: #2d4a6b;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(233,107,57,0.3);
    }
    
    .date-tab.active {
      background: #e96b39;
      border-color: #ffa726;
    }
    
    .showtimes-grid {
      display: grid;
      gap: 18px;
    }
    
    .cinema-group {
      background: rgba(0,0,0,0.3);
      padding: 20px;
      border-radius: 8px;
      border-left: 4px solid #ffa726;
    }
    
    .cinema-name {
      font-size: 1.2em;
      color: #ffa726;
      font-weight: bold;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 7px;
    }
    
    .time-slots {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    
    .time-slot {
      display: inline-block;
      padding: 11px 20px;
      background: #21344b;
      color: #fff;
      text-decoration: none;
      border-radius: 6px;
      font-weight: bold;
      font-size: 0.92em;
      transition: all 0.2s;
      border: 2px solid transparent;
    }
      .time-slot.disabled {
      background: #1a1a1a;
      color: #666;
      cursor: not-allowed;
      opacity: 0.5;
      text-decoration: line-through;
      border: 2px solid #2a2a2a;
      pointer-events: none;
      }

    .time-slot.disabled:hover {
        background: #1a1a1a;
        transform: none;
        box-shadow: none;
     }

    
    .time-slot:hover {
      background: #2d4a6b;
      transform: scale(1.05);
      box-shadow: 0 4px 12px rgba(233,107,57,0.3);
    }
    
    .time-slot.selected {
      background: #e96b39;
      border-color: #ffa726;
    }
    
    .seats-section {
      background: #2a2d35;
      padding: 32px;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    }
    
    .showtime-info {
      background: rgba(255,167,38,0.1);
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 25px;
      border-left: 4px solid #ffa726;
    }
    
    .showtime-info-text {
      color: #ddd;
      font-size: 1.05em;
      line-height: 1.6;
    }
    
    .screen {
      background: linear-gradient(to bottom, #555, #333);
      color: #fff;
      text-align: center;
      padding: 12px;
      margin: 20px auto 35px;
      border-radius: 8px 8px 0 0;
      font-weight: bold;
      font-size: 1.1em;
      max-width: 700px;
    }
    
    .seats-grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 12px;
      max-width: 850px;
      margin: 0 auto 30px;
      justify-items: center;
    }
    
    .seat {
      width: 45px;
      height: 45px;
      background: #21344b;
      border: 2px solid #3a5a7a;
      border-radius: 8px 8px 0 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.85em;
      font-weight: bold;
      color: #fff;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .seat:hover {
      background: #2d4a6b;
      transform: scale(1.1);
    }
    
    .seat.booked {
      background: #dc3545;
      border-color: #c82333;
      cursor: not-allowed;
    }
    
    .seat.reserved {
      background: #ffc107;
      border-color: #e0a800;
      cursor: not-allowed;
    }
    
    .seat.booked:hover,
    .seat.reserved:hover {
      transform: none;
    }
    
    .seat input[type="checkbox"] {
      display: none;
    }
    
    .seat input[type="checkbox"]:checked + label {
      background: #28a745;
      border-color: #20c997;
    }
    
    .seat label {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }
    
    .legend {
      display: flex;
      gap: 25px;
      justify-content: center;
      margin: 25px 0;
      flex-wrap: wrap;
    }
    
    .legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.95em;
    }
    
    .legend-box {
      width: 30px;
      height: 30px;
      border-radius: 5px;
      border: 2px solid;
    }
    
    .legend-box.available {
      background: #21344b;
      border-color: #3a5a7a;
    }
    
    .legend-box.selected {
      background: #28a745;
      border-color: #20c997;
    }
    
    .legend-box.booked {
      background: #dc3545;
      border-color: #c82333;
    }
    
    .legend-box.reserved {
      background: #ffc107;
      border-color: #e0a800;
    }
    
    .proceed-btn {
      display: block;
      width: 100%;
      max-width: 400px;
      margin: 0 auto;
      padding: 15px;
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 18px;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .proceed-btn:hover {
      background: linear-gradient(135deg, #218838 0%, #1ba87e 100%);
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
    }
    
    .no-showtimes {
      text-align: center;
      color: #999;
      padding: 38px;
      font-size: 1.05em;
    }
    
    @media (max-width: 968px) {
      body {
        padding-left: 0;
      }
      .movie-header {
        flex-direction: column;
        padding: 22px;
      }
      .movie-poster {
        width: 100%;
      }
      .movie-poster img {
        width: 100%;
        height: auto;
        max-width: 360px;
        margin: 0 auto;
        display: block;
      }
      .movie-info {
        max-width: 100%;
      }
      .details-grid {
        grid-template-columns: 1fr;
      }
      .trailer-section video {
        max-height: 240px;
      }
      .seats-grid {
        grid-template-columns: repeat(6, 1fr);
        gap: 8px;
      }
      .seat {
        width: 40px;
        height: 40px;
      }
    }
  </style>
</head>
<body>

<div class="movie-container">
  <div class="movie-header">
    <div class="movie-poster">
      <?php
      $posterPath = isset($movie['Img Path']) ? $movie['Img Path'] : 'images/no-poster.jpg';
      ?>
      <img src="<?= htmlspecialchars($posterPath) ?>" alt="<?= htmlspecialchars($movie['Title']) ?>">
    </div>
    <div class="movie-info">
      <h1 class="movie-title"><?= htmlspecialchars($movie['Title']) ?></h1>
      <?php if (!empty($movie['Vid Path'])): ?>
        <div class="trailer-section">
          <video controls>
            <source src="<?= htmlspecialchars($movie['Vid Path']) ?>" type="video/mp4">
            Your browser does not support the video tag.
          </video>
        </div>
      <?php endif; ?>
      <div class="details-grid">
        <div class="details-column">
          <div class="details-row">
            <div class="detail-label">Runtime:</div>
            <div class="detail-value"><?= htmlspecialchars($movie['Runtime']) ?></div>
          </div>
          <div class="details-row">
            <div class="detail-label">Genre:</div>
            <div class="detail-value"><?= htmlspecialchars($movie['Genre']) ?></div>
          </div>
          <div class="details-row">
            <div class="detail-label">Language:</div>
            <div class="detail-value"><?= htmlspecialchars($movie['Language']) ?></div>
          </div>
          <div class="details-row">
            <div class="detail-label">Subtitles:</div>
            <div class="detail-value"><?= htmlspecialchars($movie['Subtitles']) ?></div>
          </div>
        </div>
        <div class="details-column">
          <div class="details-row">
            <div class="detail-label">Director:</div>
            <div class="detail-value"><?= htmlspecialchars($movie['Director']) ?></div>
          </div>
          <div class="details-row">
            <div class="detail-label">Cast:</div>
            <div class="detail-value"><?= htmlspecialchars($movie['Cast']) ?></div>
          </div>
          <div class="details-row">
            <div class="detail-label">Release Date:</div>
            <div class="detail-value">
              <?php 
              $releaseDate = isset($movie['Release Date']) ? $movie['Release Date'] : 'N/A';
              echo $releaseDate != 'N/A' ? date('F j, Y', strtotime($releaseDate)) : 'N/A';
              ?>
            </div>
          </div>
          <div class="details-row">
            <div class="detail-label">Age Rating:</div>
            <div class="detail-value">
              <?php echo isset($movie['Age Rating']) ? htmlspecialchars($movie['Age Rating']) : 'N/A'; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="synopsis-section">
        <div class="synopsis-title">📖 Synopsis</div>
        <div class="synopsis-text"><?= nl2br(htmlspecialchars($movie['Synopsis'])) ?></div>
      </div>
    </div>
  </div>
  
  <div class="showtimes-section" id="showtimes">
    <h2 class="section-title">🎬 Select Showtime</h2>
    <?php if (!empty($availableDates)): ?>
      <div class="date-tabs">
          <?php foreach ($availableDates as $date): ?>
            <a href="movie.php?movie_id=<?= $movieId ?>&date=<?= $date ?>#showtimes" 
              class="date-tab <?= ($date == $selectedDate) ? 'active' : '' ?>">
              <?= date('D, M j', strtotime($date)) ?>
            </a>
          <?php endforeach; ?>
        </div>

      <?php if (!empty($showtimes)): ?>
        <div class="showtimes-grid">
          <?php
          $groupedShowtimes = [];
          foreach ($showtimes as $showtime) {
              $cinemaName = $showtime['CinemaName'];
              if (!isset($groupedShowtimes[$cinemaName])) {
                  $groupedShowtimes[$cinemaName] = [];
              }
              $groupedShowtimes[$cinemaName][] = $showtime;
          }
          foreach ($groupedShowtimes as $cinemaName => $times):
          ?>
            <div class="cinema-group">
              <div class="cinema-name">📍 <?= htmlspecialchars($cinemaName) ?></div>
              
              <div class="time-slots">
                <?php 
                foreach ($times as $time): 
                    // Get timezone for accurate comparison
                    date_default_timezone_set('Asia/Singapore');
                    
                    // Create datetime objects for accurate comparison
                    $showtimeDateTime = new DateTime($selectedDate . ' ' . $time['ShowTime']);
                    $currentDateTime = new DateTime();
                    
                    // Show is expired if current time is past showtime
                    $isExpired = $currentDateTime >= $showtimeDateTime;
                ?>
                  <?php if ($isExpired): ?>
                    <!-- Disabled expired showtime -->
                    <span class="time-slot disabled" title="Show has already started">
                      <?= date('g:i A', strtotime($time['ShowTime'])) ?>
                    </span>
                  <?php else: ?>
                    <!-- Active showtime -->
                    <a href="movie.php?movie_id=<?= $movieId ?>&date=<?= $selectedDate ?>&cinema=<?= $time['CinemaID'] ?>&theatre=<?= $time['TheatreID'] ?>&time=<?= $time['ShowTimeID'] ?>#seats" 
                      class="time-slot <?= ($time['ShowTimeID'] == $selectedTime && $time['TheatreID'] == $selectedTheatre) ? 'selected' : '' ?>">
                      <?= date('g:i A', strtotime($time['ShowTime'])) ?>
                    </a>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>

            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="no-showtimes">No showtimes available for this date.</div>
      <?php endif; ?>
    <?php else: ?>
      <div class="no-showtimes">No upcoming showtimes available for this movie.</div>
    <?php endif; ?>
  </div>
  
  <?php if (!empty($seats) && $showtimeInfo): ?>
  <div class="seats-section" id="seats">
    <h2 class="section-title">🪑 Select Seats</h2>
    <div class="showtime-info">
      <div class="showtime-info-text">
        <strong style="color: #ffa726;"><?= htmlspecialchars($movie['Title']) ?></strong><br>
        <?= htmlspecialchars($showtimeInfo['CinemaName']) ?> | 
        <?= date('l, F j, Y', strtotime($showtimeInfo['ShowDate'])) ?> at 
        <?= date('g:i A', strtotime($showtimeInfo['ShowTime'])) ?>
      </div>
    </div>
    <div class="screen">🎬 SCREEN</div>
    <form method="POST" action="checkout.php" onsubmit="return validateSeats()">
      <input type="hidden" name="cinema" value="<?= $selectedCinema ?>">
      <input type="hidden" name="theatre" value="<?= $selectedTheatre ?>">
      <input type="hidden" name="time" value="<?= $selectedTime ?>">
      <input type="hidden" name="add_to_cart" value="1">
      <div class="seats-grid">
        
      <?php foreach ($seats as $seat): ?>
        <div class="seat <?php 
          if ($seat['IsBooked'] == 1) {
              echo 'booked';
          } elseif ($seat['is_reserved']) {
              echo 'reserved';
          }
        ?>">
          <?php if ($seat['IsBooked'] == 0 && !$seat['is_reserved']): ?>
            <input type="checkbox" 
                   name="seats[]" 
                   value="<?= htmlspecialchars($seat['SeatNumber']) ?>" 
                   id="seat-<?= htmlspecialchars($seat['SeatNumber']) ?>">
            <label for="seat-<?= htmlspecialchars($seat['SeatNumber']) ?>">
              <?= htmlspecialchars($seat['SeatNumber']) ?>
            </label>
          <?php else: ?>
            <?= htmlspecialchars($seat['SeatNumber']) ?>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
      <div class="legend">
        <div class="legend-item">
          <div class="legend-box available"></div>
          <span>Available</span>
        </div>
        <div class="legend-item">
          <div class="legend-box selected"></div>
          <span>Selected</span>
        </div>
        <div class="legend-item">
          <div class="legend-box booked"></div>
          <span>Booked</span>
        </div>
        <div class="legend-item">
          <div class="legend-box reserved"></div>
          <span>Reserved</span>
        </div>
      </div>
      <button type="submit" class="proceed-btn">
        Proceed to Checkout
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>


<script>
function validateSeats() {
    var checkedSeats = document.querySelectorAll('input[name="seats[]"]:checked');
    
    if (checkedSeats.length === 0) {
        alert('Please select at least one seat!');
        return false;
    }
    
    // Extract seat numbers and sort them
    var selectedSeats = [];
    checkedSeats.forEach(function(checkbox) {
        selectedSeats.push(checkbox.value);
    });
    
    // Check if seats are consecutive (no gaps)
    if (!areSeatsConsecutive(selectedSeats)) {
        alert('⚠️ Please select consecutive seats without leaving gaps!\n\nYou cannot leave empty seats between your selections.');
        return false;
    }
    
    return true;
}

function areSeatsConsecutive(seats) {
    // Group seats by row
    var seatsByRow = {};
    
    for (var i = 0; i < seats.length; i++) {
        var seat = seats[i];
        var row = seat.charAt(0); // A, B, C, etc.
        var col = parseInt(seat.substring(1)); // 1, 2, 3, etc.
        
        if (!seatsByRow[row]) {
            seatsByRow[row] = [];
        }
        seatsByRow[row].push(col);
    }
    
    // Check each row for consecutive seats
    for (var row in seatsByRow) {
        var columns = seatsByRow[row].sort(function(a, b) { return a - b; });
        
        // Check if columns are consecutive
        for (var i = 1; i < columns.length; i++) {
            if (columns[i] !== columns[i-1] + 1) {
                return false; // Gap found
            }
        }
    }
    
    return true;
}
</script>
<div id="footer">
  <span><a href="contact_careers.php#contact-us">Contact Us</a></span>
  <span><a href="contact_careers.php#career-opportunities">Career Opportunities</a></span>
  <span><a href="admin_dashboard.php">Admin Panel</a></span>
  <div class="cr">Copyright &copy; IE4727WebDev</div>
</div>
</body>
</html>
<?php $conn->close(); ?>