<?php
session_start();
date_default_timezone_set('Asia/Singapore');

$conn = new mysqli('localhost', 'root', '', 'moviedb');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if (!isset($_SESSION['session_id'])) {
    $_SESSION['session_id'] = session_id();
}

$cinemaId = isset($_GET['cinema']) ? intval($_GET['cinema']) : 0;
$theatreId = isset($_GET['theatre']) ? intval($_GET['theatre']) : 0;
$showtimeId = isset($_GET['time']) ? intval($_GET['time']) : 0;

if (!$cinemaId || !$showtimeId) {
    echo "Please select cinema and showtime.";
    exit();
}

// Clean up expired reservations
$cleanupSQL = "UPDATE seats 
               SET reserved_until = NULL, reserved_by_session = NULL 
               WHERE ShowTimeID = ? AND reserved_until < NOW() AND IsBooked = 0";
$cleanupStmt = $conn->prepare($cleanupSQL);
$cleanupStmt->bind_param("i", $showtimeId);
$cleanupStmt->execute();

// Fetch seats with full debugging info
$seatsSql = "SELECT SeatNumber, IsBooked, reserved_until, reserved_by_session, 
             UNIX_TIMESTAMP(reserved_until) as reserved_unix,
             UNIX_TIMESTAMP(NOW()) as now_unix,
             CinemaID, TheatreID, ShowTimeID
             FROM seats 
             WHERE CinemaID = ? AND ShowTimeID = ? 
             ORDER BY SUBSTRING(SeatNumber, 1, 1), CAST(SUBSTRING(SeatNumber, 2) AS UNSIGNED)";
$stmt = $conn->prepare($seatsSql);
$stmt->bind_param("ii", $cinemaId, $showtimeId);
$stmt->execute();
$seatsResult = $stmt->get_result();
$seats = [];
$debugData = [];

while ($row = $seatsResult->fetch_assoc()) {
    // Determine seat status
    if ($row['IsBooked']) {
        $status = 'booked';
    } elseif (!empty($row['reserved_until']) && $row['reserved_unix'] > $row['now_unix']) {
        $status = 'selected'; // Orange for ALL reservations
    } else {
        $status = 'available';
    }
    
    $seats[$row['SeatNumber']] = $status;
    
    // Collect debug data for ALL seats with reservation info
    if (!empty($row['reserved_until'])) {
        $debugData[] = [
            'seat' => $row['SeatNumber'],
            'status' => $status,
            'cinema' => $row['CinemaID'],
            'theatre' => $row['TheatreID'],
            'showtime' => $row['ShowTimeID'],
            'reserved_until' => $row['reserved_until'],
            'reserved_unix' => $row['reserved_unix'],
            'now_unix' => $row['now_unix'],
            'is_future' => ($row['reserved_unix'] > $row['now_unix']),
            'session' => $row['reserved_by_session']
        ];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Seats</title>
    <style>
        body {
            background: #191a1c;
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #eee;
            padding: 20px;
        }
        .main-content {
            max-width: 900px;
            margin: 0 auto;
        }
        h1 {
            color: #ffa726;
            text-align: center;
        }
        .seat-container {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 10px;
            max-width: 800px;
            margin: 20px auto;
        }
        .seat {
            width: 50px;
            height: 50px;
            border: 2px solid #333;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            transition: all 0.2s;
        }
        .seat.available {
            background: #4CAF50;
            color: white;
        }
        .seat.available:hover {
            background: #45a049;
            transform: scale(1.1);
        }
        .seat.selected {
            background: #FF9800;
            color: white;
            cursor: not-allowed;
        }
        .seat.booked {
            background: #dc3545;
            color: white;
            cursor: not-allowed;
        }
        .screen {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background: #333;
            color: white;
            border-radius: 5px;
            font-size: 1.2em;
        }
        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .legend-box {
            width: 30px;
            height: 30px;
            border-radius: 5px;
        }
        .booking-summary {
            text-align: center;
            margin: 30px 0;
        }
        #checkoutBtn {
            background: #28a745;
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 1.1em;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 20px;
        }
        #checkoutBtn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        #checkoutBtn:hover:not(:disabled) {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <h1>Select Your Seats</h1>
        <div class="legend">
            <div class="legend-item">
                <div class="legend-box" style="background: #4CAF50;"></div>
                <span>Available</span>
            </div>
            <div class="legend-item">
                <div class="legend-box" style="background: #FF9800;"></div>
                <span>Reserved</span>
            </div>
            <div class="legend-item">
                <div class="legend-box" style="background: #dc3545;"></div>
                <span>Booked</span>
            </div>
        </div>
        <div class="screen">🎬 SCREEN</div>
        <form method="POST" action="checkout.php" id="seatForm">
            <input type="hidden" name="cinema" value="<?php echo $cinemaId; ?>">
            <input type="hidden" name="theatre" value="<?php echo $theatreId; ?>">
            <input type="hidden" name="time" value="<?php echo $showtimeId; ?>">
            <input type="hidden" name="add_to_cart" value="1">
            <div id="selectedSeatsInput"></div>
            <div class="seat-container">
                <?php
                $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                $cols = 12;
                foreach ($rows as $row) {
                    for ($col = 1; $col <= $cols; $col++) {
                        $seatNumber = $row . $col;
                        $seatStatus = isset($seats[$seatNumber]) ? $seats[$seatNumber] : 'available';
                        echo "<div class='seat $seatStatus' data-seat='$seatNumber' onclick='toggleSeat(this)'>$seatNumber</div>";
                    }
                }
                ?>
            </div>
            <div class="booking-summary">
                <h3>Selected Seats: <span id="selectedSeatsDisplay">None</span></h3>
                <button type="submit" id="checkoutBtn" disabled>Add to Cart</button>
            </div>
        </form>
    </div>
    <script>
        // DEBUG OUTPUT TO CONSOLE
        console.log('=== SEAT RESERVATION DEBUG ===');
        console.log('Looking for: CinemaID=<?php echo $cinemaId; ?>, TheatreID=<?php echo $theatreId; ?>, ShowTimeID=<?php echo $showtimeId; ?>');
        console.log('Your Session ID: <?php echo $_SESSION['session_id']; ?>');
        console.log('Reserved seats found in database:');
        console.table(<?php echo json_encode($debugData); ?>);
        console.log('Total reserved seats: <?php echo count($debugData); ?>');
        
        let selectedSeats = [];
        function toggleSeat(element) {
            if (element.classList.contains('booked') || element.classList.contains('selected')) {
                console.log('Cannot select seat ' + element.dataset.seat + ' - already booked or reserved');
                return;
            }
            const seat = element.dataset.seat;
            if (element.classList.contains('selecting')) {
                element.classList.remove('selecting');
                element.classList.add('available');
                selectedSeats = selectedSeats.filter(s => s !== seat);
            } else {
                element.classList.remove('available');
                element.classList.add('selecting');
                selectedSeats.push(seat);
            }
            updateDisplay();
        }
        function updateDisplay() {
            document.getElementById('selectedSeatsDisplay').textContent = 
                selectedSeats.length > 0 ? selectedSeats.sort().join(', ') : 'None';
            document.getElementById('checkoutBtn').disabled = selectedSeats.length === 0;
            const inputContainer = document.getElementById('selectedSeatsInput');
            inputContainer.innerHTML = '';
            selectedSeats.forEach(seat => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'seats[]';
                input.value = seat;
                inputContainer.appendChild(input);
            });
        }
    </script>
</body>
</html>
