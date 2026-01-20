<?php
$conn = new mysqli("localhost", "root", "", "moviedb");
if ($conn->connect_error) die("Connection failed");

$date = isset($_GET['date']) ? $_GET['date'] : '';
$movieId = isset($_GET['movie_id']) ? intval($_GET['movie_id']) : 0;
$cinemaId = isset($_GET['cinema_id']) ? intval($_GET['cinema_id']) : 0;

// Build query based on filters
if ($date || $movieId || $cinemaId) {
    $sql = "SELECT DISTINCT md.* FROM movie_details md
            INNER JOIN movie_showtimes st ON md.MovieID = st.MovieID
            WHERE 1=1";
    
    if ($date) {
        $sql .= " AND st.ShowDate = '$date'";
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

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<div class='movie-card' data-movie-id='{$row['MovieID']}'>";
        echo "<img src='" . htmlspecialchars($row["Img Path"]) . "' alt='Poster'>";
        echo "<div class='movie-title'>" . htmlspecialchars($row["Title"]) . "</div>";
        echo "<div class='movie-runtime'>" . htmlspecialchars($row["Runtime"]) . "</div>";
        echo "<a href='movie.php?movie_id=" . $row['MovieID'] . "' class='buy-btn'>Buy Tickets</a>";
        echo "</div>";
    }
} else {
    echo "<div class='no-results'>😔 No movies found matching your filters.<br><br>Try adjusting your search criteria.</div>";
}

$conn->close();
?>
