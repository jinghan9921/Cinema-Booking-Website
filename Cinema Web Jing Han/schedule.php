<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'moviedb');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all upcoming showtimes grouped by movie and date
$sql = "SELECT 
            md.MovieID,
            md.Title,
            md.`Img Path` as PosterPath,
            st.ShowDate,
            st.ShowTime,
            st.ShowTimeID,
            c.CinemaName,
            c.CinemaID
        FROM movie_showtimes st
        INNER JOIN movie_details md ON st.MovieID = md.MovieID
        INNER JOIN cinemas c ON st.CinemaID = c.CinemaID
        WHERE st.ShowDate >= CURDATE()
        ORDER BY md.Title, st.ShowDate, c.CinemaName, st.ShowTime";

$result = $conn->query($sql);

// Group showtimes by movie
$movieSchedules = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $movieId = $row['MovieID'];
        if (!isset($movieSchedules[$movieId])) {
            $movieSchedules[$movieId] = [
                'title' => $row['Title'],
                'poster' => $row['PosterPath'],
                'dates' => []
            ];
        }
        
        $date = $row['ShowDate'];
        if (!isset($movieSchedules[$movieId]['dates'][$date])) {
            $movieSchedules[$movieId]['dates'][$date] = [];
        }
        
        $cinema = $row['CinemaName'];
        if (!isset($movieSchedules[$movieId]['dates'][$date][$cinema])) {
            $movieSchedules[$movieId]['dates'][$date][$cinema] = [];
        }
        
        $movieSchedules[$movieId]['dates'][$date][$cinema][] = [
            'time' => $row['ShowTime'],
            'showtime_id' => $row['ShowTimeID'],
            'cinema_id' => $row['CinemaID']
        ];
    }
}

include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Movie Schedule - IE Theatre</title>
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

        .schedule-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }
        
        h1 {
            color: #e96b39;
            text-align: center;
            font-size: 2.5em;
            margin: 20px 0 40px 0;
            font-weight: bold;
        }
        
        .movie-schedule {
            background: #2a2d35;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .movie-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ffa726;
        }
        
        .movie-poster {
            width: 100px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.4);
        }
        
        .movie-title {
            font-size: 1.8em;
            color: #ffa726;
            font-weight: bold;
            margin: 0;
        }
        
        .date-section {
            margin-bottom: 25px;
        }
        
        .date-header {
            font-size: 1.3em;
            color: #ffa726;
            font-weight: bold;
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(255,167,38,0.1);
            border-left: 4px solid #ffa726;
            border-radius: 4px;
        }
        
        .cinema-group {
            margin-bottom: 15px;
            padding: 15px;
            background: rgba(0,0,0,0.2);
            border-radius: 8px;
        }
        
        .cinema-name {
            font-size: 1.1em;
            color: #fff;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .time-slots {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .time-button {
            display: inline-block;
            padding: 10px 18px;
            background: #21344b;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            font-size: 0.95em;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        
        .time-button:hover {
            background: #e96b39;
            border-color: #ffa726;
            transform: scale(1.05);
        }
        
        .no-schedule {
            text-align: center;
            color: #999;
            padding: 60px 20px;
            font-size: 1.2em;
        }
        
        @media (max-width: 768px) {
            body {
                padding-left: 0;
            }
            
            .movie-header {
                flex-direction: column;
                text-align: center;
            }
            
            .schedule-container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<div class="schedule-container">
    <h1>🎬 Movie Schedule</h1>
    
    <?php if (!empty($movieSchedules)): ?>
        <?php foreach ($movieSchedules as $movieId => $movieData): ?>
            <div class="movie-schedule">
                <div class="movie-header">
                    <img src="<?= htmlspecialchars($movieData['poster']) ?>" 
                         alt="<?= htmlspecialchars($movieData['title']) ?>" 
                         class="movie-poster">
                    <h2 class="movie-title"><?= htmlspecialchars($movieData['title']) ?></h2>
                </div>
                
                <?php foreach ($movieData['dates'] as $date => $cinemas): ?>
                    <div class="date-section">
                        <div class="date-header">
                            📅 <?= date('l, F j, Y', strtotime($date)) ?>
                        </div>
                        
                        <?php foreach ($cinemas as $cinemaName => $times): ?>
                            <div class="cinema-group">
                                <div class="cinema-name">📍 <?= htmlspecialchars($cinemaName) ?></div>
                                <div class="time-slots">
                                    <?php foreach ($times as $timeData): ?>
                                        <a href="movie.php?movie_id=<?= $movieId ?>&date=<?= $date ?>&cinema=<?= $timeData['cinema_id'] ?>&time=<?= $timeData['showtime_id'] ?>#seats" 
                                           class="time-button">
                                            <?= date('g:i A', strtotime($timeData['time'])) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-schedule">
            <p style="font-size: 3em; margin: 0;">🎬</p>
            <p>No upcoming showtimes available</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

</body>
</html>
<?php $conn->close(); ?>
