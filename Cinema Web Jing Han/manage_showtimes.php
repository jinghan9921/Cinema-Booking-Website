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

function addSeatsForShowtime($conn, $cinemaID, $showtimeID, $theatreID = 1) {
    $rows = ['A', 'B', 'C', 'D', 'E', 'F','G','H'];
    $seatsPerRow = 12;
    foreach ($rows as $row) {
        for ($number = 1; $number <= $seatsPerRow; $number++) {
            $seatNum = $row . $number;
            $sql = "INSERT INTO seats (CinemaID, TheatreID, ShowTimeID, SeatNumber, SeatType, IsBooked)
                    VALUES ($cinemaID, $theatreID, $showtimeID, '$seatNum', 'Regular', 0)";
            $conn->query($sql);
        }
    }
}

function sanitizeDate($date) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
}

if (isset($_POST["add_showtimes"])) {
    $movie = intval($_POST['movie']);
    $cinema = intval($_POST['cinema']);
    $theatreID = 1;
    
    // Get dates array
    $dates = isset($_POST['dates']) ? $_POST['dates'] : array();
    
    // Process each date and its times
    foreach ($dates as $index => $date) {
        // Get times for this specific date
        $timeKey = 'times_' . $index;
        $times = isset($_POST[$timeKey]) ? $_POST[$timeKey] : array();
        
        // Validate date format and ensure it's not in the past
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $selectedDate = new DateTime($date);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            
            // Only add if date is today or future
            if ($selectedDate >= $today) {
                foreach ($times as $time) {
                    $safe_time = $conn->real_escape_string($time);
                    $safe_date = $conn->real_escape_string($date);
                    
                    $conn->query("INSERT INTO movie_showtimes (MovieID, CinemaID, TheatreID, ShowDate, ShowTime) 
                     VALUES ($movie, $cinema, $theatreID, '$safe_date', '$safe_time')");

                    
                    $showtimeID = $conn->insert_id;
                    addSeatsForShowtime($conn, $cinema, $showtimeID, $theatreID);
                }
            }
        }
    }
    header("Location: manage_showtimes.php");
    exit;
}

if (isset($_POST['update'])) {
    $id = intval($_POST['showtime_id']);
    $movie = intval($_POST['movie']);
    $cinema = intval($_POST['cinema']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    
    // Validate date is not in the past
    $selectedDate = new DateTime($date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($selectedDate >= $today) {
        $conn->query("UPDATE movie_showtimes SET MovieID=$movie, CinemaID=$cinema, TheatreID=1, ShowDate='$date', ShowTime='$time' WHERE ShowtimeID=$id");
    }
    header("Location: manage_showtimes.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM seats WHERE ShowTimeID = $id");
    $conn->query("DELETE FROM movie_showtimes WHERE ShowtimeID = $id");
    header("Location: manage_showtimes.php");
    exit;
}

if (isset($_POST['bulk_delete']) && !empty($_POST['delete_ids'])) {
    $ids = array_map('intval', $_POST['delete_ids']);
    $idlist = implode(',', $ids);
    $conn->query("DELETE FROM seats WHERE ShowTimeID IN ($idlist)");
    $conn->query("DELETE FROM movie_showtimes WHERE ShowtimeID IN ($idlist)");
    header("Location: manage_showtimes.php");
    exit;
}

$edit_mode = false;
$edit_showtime = null;
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM movie_showtimes WHERE ShowtimeID = $id");
    if ($res->num_rows > 0) {
        $edit_showtime = $res->fetch_assoc();
    } else {
        $edit_mode = false;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="sidebarandfooter.css">
    <title>Showtime Scheduling</title>
    <style>
        body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #191a1c; color: #eee; }
        #content { 
            max-width: 1800px;
            margin: 0 auto;
            padding: 40px 16px 120px 220px;
            min-height: 100vh;
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
        
        /* BLACK time picker icon for visibility */
        input[type="time"]::-webkit-calendar-picker-indicator {
            filter: invert(0);
            cursor: pointer;
        }

        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(0);
            cursor: pointer;
        }

        
        .row-flex { display: flex; align-items: flex-start; gap: 24px; margin-bottom:9px;}
        .select-block { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .calendar-box { background:#fff; color:#333; padding:10px; border-radius:9px; display:inline-block; box-shadow:0 2px 16px #6674d033; margin-bottom:0px; min-width:200px; }
        .calendar-controls { display: flex; align-items: center; margin-bottom:4px;}
        .cal-btn { background:none; border:none; color:#23325d; font-size:1em; font-weight:bold; cursor:pointer; width:21px; height:21px; transition:color 0.13s;}
        .cal-btn:hover { color: #ffa726; }
        .cal-month-label { flex:1; text-align:center; font-size:0.97em; font-weight:bold;}
        .calendar-table { border-collapse: collapse; background:transparent;}
        .calendar-table td { width:21px;height:21px;text-align:center; }
        .calendar-table td label { display:block; border-radius:4px; cursor:pointer; font-size:11px; padding: 0 0 1px 0; transition:background 0.1s, color 0.12s;}
        .calendar-table td label.selected, .calendar-table td input[type=checkbox]:checked + label { background:#ffa726; color:#23325d; border-radius:4px; }
        .calendar-table input[type=checkbox] { display:none; }
        .calendar-table td { background: #fff;}
        .calendar-table td label:hover { background:#eaeaf6; }
        .disabled-day { color: #bbb !important; background:#f4f4f4; cursor: not-allowed !important;}
        .disabled-day label { cursor: not-allowed !important; pointer-events: none;}
        .calendar-table .today-label label { border:1.5px solid #e12814ff; }
        .times-block { margin:11px 0 11px 7px; background:#b6d8fd; border-radius:7px; display:inline-block; min-width:140px; padding:9px 12px 6px 12px; }
        .time-entry { margin: 0 3px 5px 0; }
        .date-label { font-weight:bold; letter-spacing:.01em;color:#000000;margin-right:8px; font-size:0.99em; background:none;}
        .time-entry input[type="time"] {margin-right:4px;font-size:0.99em;padding:4px;background:#fff;color:#333;border:1px solid #a7b8e2;border-radius:4px;}
        .add-time-btn, .remove-time-btn { background:#fff; border:1px solid #2291ee; color:#2291ee; font-weight:700; border-radius:3px; width:22px; height:22px; cursor:pointer; font-size:0.94em;}
        .add-time-btn:hover, .remove-time-btn:hover { background:#eee; }
        .remove-time-btn {border:1px solid #ff5252;color:#ff5252;}
        select { padding:8px 10px; margin:0 5px 5px 0; font-size:0.98em; border-radius:5px; border:1px solid #b5bef1; background:#191b20; color:#eee;}        
        a.delete { color: #ff5252; text-decoration: none; font-weight: 600;}
        a.edit { color: #4FC3F7; text-decoration: none; font-weight: 600; margin-right: 13px;}
        
        .table-card {
            background: #23242a;
            border-radius: 10px;
            box-shadow: 0 2px 10px #0002;
            margin-top: 26px;
            padding-bottom: 10px;
            overflow-x: auto;
        }
        
        table.showtimes { 
            width:100%; 
            font-size:15.7px; 
            background: none;
            border-collapse: collapse;
        }
        table.showtimes th, table.showtimes td { 
            padding:13px 18px;
            border-bottom: 1px solid #232330;
        }
        table.showtimes th {
            background:#212f49;
            color:#ffa726;
            text-align: left;
        }
        table.showtimes tr { transition: background .14s; }
        table.showtimes tr:hover { background:#232c43; }
        
        .action-flex { display:flex; align-items:center; justify-content:center; gap:16px; }
        .centered-cell { text-align:center; }
        .bulkdelete-btn { background: #ff5252; color: #fff; border: none; border-radius: 8px; padding: 10px 20px; font-size:15px; font-weight: bold; cursor:pointer; margin: 18px 0; transition: all 0.3s ease;}
        .bulkdelete-btn:hover { background:#e3210e; transform: translateY(-2px);}
        input[type="checkbox"].bulkdelbox { transform:scale(1.25); margin-right:8px; vertical-align:middle;}
        
        .section-box {
            background:#23242a; 
            border-radius: 14px; 
            padding: 34px 36px 18px 36px; 
            margin-bottom:38px; 
            box-shadow:0 2px 12px #0002;
        }
        .form-row {margin-bottom:5px;}
        .btn-main {
            background:#ffa726;
            padding:12px 28px;
            border-radius:8px;
            color:#23325d;
            font-weight:bold;
            border:none; 
            font-size:15px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 167, 38, 0.3);
        }

        .btn-main:hover { 
            background:#ff9800; 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 167, 38, 0.4);
        }
        .edit-cancel { color:#ffa726; font-weight:bold; margin-left: 15px; text-decoration:none; line-height: 32px;}
        .edit-cancel:hover { color:#ff9800; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div id="content">

    <div class="header-section">
        <h1>Showtime Scheduling</h1>
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

    <!-- FORM FOR ADD OR EDIT -->
    <?php if ($edit_mode && $edit_showtime): ?>
    <form method="POST" id="updateForm" autocomplete="off">
    <div class="section-box">
        <input type="hidden" name="showtime_id" value="<?php echo intval($edit_showtime['ShowtimeID']); ?>">
        <div class="row-flex">
            <div class="select-block">
                <label for="movie"><b>Movie</b>:</label>
                <select id="movie" name="movie" required>
                    <option value="">Select Movie</option>
                    <?php
                    $res = $conn->query("SELECT MovieID, Title FROM movie_details");
                    while ($row = $res->fetch_assoc()) {
                        $selected = ($edit_showtime['MovieID'] == $row['MovieID']) ? "selected" : "";
                        echo "<option value='{$row['MovieID']}' $selected>{$row['Title']}</option>";
                    }
                    ?>
                </select>
                <label for="cinema"><b>Cinema</b>:</label>
                <select id="cinema" name="cinema" required>
                    <option value="">Select Cinema</option>
                    <?php
                    $res = $conn->query("SELECT CinemaID, CinemaName FROM cinemas");
                    while ($row = $res->fetch_assoc()) {
                        $selected = ($edit_showtime['CinemaID'] == $row['CinemaID']) ? "selected" : "";
                        echo "<option value='{$row['CinemaID']}' $selected>{$row['CinemaName']}</option>";
                    }
                    ?>
                </select>
                <label for="date"><b>Date</b>:</label>
                <input type="date" id="edit-date" name="date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($edit_showtime['ShowDate']); ?>" required>
                <label for="time"><b>Time</b>:</label>
                <input type="time" id="edit-time" name="time" value="<?php echo htmlspecialchars($edit_showtime['ShowTime']); ?>" required>
                <input type="submit" name="update" value="Update Showtime" class="btn-main">
                <a href="manage_showtimes.php" class="edit-cancel">Cancel</a>
            </div>
        </div>
    </div>
    </form>
    <?php else: ?>
    <form method="POST" id="showtimeForm" autocomplete="off">
    <div class="section-box">
        <div class="row-flex">
            <div class="select-block">
                <label for="movie"><b>Movie</b>:</label>
                <select id="movie" name="movie" required>
                    <option value="">Select Movie</option>
                    <?php $res = $conn->query("SELECT MovieID, Title FROM movie_details");
                    while ($row = $res->fetch_assoc()) {
                        echo "<option value='{$row['MovieID']}'>{$row['Title']}</option>";
                    } ?>
                </select>
                <label for="cinema"><b>Cinema</b>:</label>
                <select id="cinema" name="cinema" required>
                    <option value="">Select Cinema</option>
                    <?php $res = $conn->query("SELECT CinemaID, CinemaName FROM cinemas");
                    while ($row = $res->fetch_assoc()) {
                        echo "<option value='{$row['CinemaID']}'>{$row['CinemaName']}</option>";
                    } ?>
                </select>
                <div class="calendar-box">
                    <div class="calendar-controls" style="margin-bottom:8px;">
                        <button type="button" class="cal-btn" id="prevMonthBtn">&#60;</button>
                        <span class="cal-month-label" id="monthLabel"></span>
                        <button type="button" class="cal-btn" id="nextMonthBtn">&#62;</button>
                    </div>
                    <table class="calendar-table" id="calendarTable" style="font-size:0.96em;"></table>
                </div>
            </div>
        </div>
        <div class="form-row" style="margin-top:13px;">
            <div id="timesArea"></div>
        </div>
        <div id="hiddenFormFields"></div>
        <input type="submit" name="add_showtimes" class="btn-main" value="Add Showtimes">
    </div>
    </form>
    <script>
    const timesArea = document.getElementById('timesArea');
    function addTimesBlock(date) {
        let div = document.createElement('div');
        div.className = 'times-block';
        div.id = `tblock-${date}`;
        div.innerHTML = `<span class="date-label">${date}</span>
            <span class="time-entry"><input type="time" name="time-${date}[]" required>
            <button type="button" class="add-time-btn" onclick="addTimeInput('${date}', this)">+</button></span>`;
        timesArea.appendChild(div);
    }
    function removeTimesBlock(date) {
        let block = document.getElementById(`tblock-${date}`);
        if (block) block.remove();
    }
    function addTimeInput(date, btn) {
        let span = document.createElement('span');
        span.className = 'time-entry';
        span.innerHTML = `<input type="time" name="time-${date}[]" required>
        <button type="button" class="remove-time-btn" onclick="this.parentNode.remove()">-</button>`;
        btn.parentNode.parentNode.appendChild(span);
    }
    
    // Add validation for time inputs to prevent past times on today's date
    function validateTimeInput(timeInput, dateString) {
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = String(now.getMonth() + 1).padStart(2, '0');
        const currentDay = String(now.getDate()).padStart(2, '0');
        const todayStr = currentYear + '-' + currentMonth + '-' + currentDay;
        
        // Only validate if the selected date is TODAY
        if (dateString === todayStr) {
            const currentHour = String(now.getHours()).padStart(2, '0');
            const currentMinute = String(now.getMinutes()).padStart(2, '0');
            const currentTime = currentHour + ':' + currentMinute;
            
            if (timeInput.value && timeInput.value < currentTime) {
                alert('Cannot select past times for today. Current time is ' + currentTime);
                timeInput.value = '';
                return false;
            }
        }
        return true;
    }
    
    // Attach event listeners to all time inputs
    document.addEventListener('change', function(e) {
        if (e.target.type === 'time' && e.target.closest('.times-block')) {
            const timesBlock = e.target.closest('.times-block');
            const dateString = timesBlock.id.replace('tblock-', '');
            validateTimeInput(e.target, dateString);
        }
    });
    
    document.getElementById('showtimeForm').addEventListener('submit', function(e) {
        let entries = [];
        let hasError = false;
        
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = String(now.getMonth() + 1).padStart(2, '0');
        const currentDay = String(now.getDate()).padStart(2, '0');
        const todayStr = currentYear + '-' + currentMonth + '-' + currentDay;
        const currentHour = String(now.getHours()).padStart(2, '0');
        const currentMinute = String(now.getMinutes()).padStart(2, '0');
        const currentTime = currentHour + ':' + currentMinute;
        
        document.querySelectorAll('.times-block').forEach(div => {
            let date = div.id.replace('tblock-',''); 
            let times = [];
            
            div.querySelectorAll('input[type="time"]').forEach(t => { 
                if (t.value) {
                    // Check if time is in the past for today's date
                    if (date === todayStr && t.value < currentTime) {
                        alert('Cannot add past times for today (' + date + '). Time ' + t.value + ' is before current time ' + currentTime);
                        hasError = true;
                        t.focus();
                        return;
                    }
                    times.push(t.value);
                }
            });
            
            if (times.length) entries.push({date:date, times:times});
        });
        
        if (hasError) {
            e.preventDefault();
            return;
        }
        
        if (entries.length === 0) { 
            alert("Please select at least one date and time."); 
            e.preventDefault(); 
            return;
        }
        
        let container = document.getElementById('hiddenFormFields');
        container.innerHTML = '';
        
        for (let i = 0; i < entries.length; i++) {
            let entry = entries[i];
            
            // Add hidden field for date
            let dateInput = document.createElement('input');
            dateInput.type = 'hidden';
            dateInput.name = 'dates[]';
            dateInput.value = entry.date;
            container.appendChild(dateInput);
            
            // Add hidden fields for times
            for (let j = 0; j < entry.times.length; j++) {
                let timeInput = document.createElement('input');
                timeInput.type = 'hidden';
                timeInput.name = 'times_' + i + '[]';
                timeInput.value = entry.times[j];
                container.appendChild(timeInput);
            }
        }
    });
    
    let today = new Date();
    let showMonth = today.getMonth();
    let showYear = today.getFullYear();
    const monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];
    
    document.getElementById("prevMonthBtn").onclick = function(){ 
        showMonth--; 
        if(showMonth<0){showMonth=11;showYear--;} 
        renderCalendar(); 
    };
    
    document.getElementById("nextMonthBtn").onclick = function(){ 
        showMonth++; 
        if(showMonth>11){showMonth=0;showYear++;} 
        renderCalendar(); 
    };
    
    function pad(n) { return n<10 ? '0'+n : n; }
    
    function renderCalendar() {
        let f = new Date(showYear, showMonth,1); 
        let fd = f.getDay();
        let t = new Date(showYear, showMonth+1, 0); 
        let td = t.getDate();
        let html = "";
        
        // Get today's date at midnight for comparison (using local timezone)
        let todayDate = new Date();
        todayDate.setHours(0, 0, 0, 0);
        // Use local date string instead of ISO to avoid timezone issues
        let todayYear = todayDate.getFullYear();
        let todayMonth = pad(todayDate.getMonth() + 1);
        let todayDay = pad(todayDate.getDate());
        let todayStr = todayYear + "-" + todayMonth + "-" + todayDay;
        
        let col = fd;
        for(let x=0;x<fd;x++) html+="<td class='disabled-day'></td>";
        
        for(let d=1;d<=td;d++) {
            let dtStr = showYear+"-"+pad(showMonth+1)+"-"+pad(d);
            let cellDate = new Date(showYear, showMonth, d);
            cellDate.setHours(0, 0, 0, 0);
            
            let cellClass = "";
            let isBeforeToday = (cellDate < todayDate);
            
            if (dtStr == todayStr) cellClass += " today-label";
            if (isBeforeToday) cellClass += " disabled-day";
            
            html += `<td class="${cellClass}">` +
            `<input type="checkbox" id="cb_${dtStr}" class="datecheck" value="${dtStr}" ${isBeforeToday?'disabled':''} onchange="onDayClick(this)">` +
            `<label for="cb_${dtStr}">${d}</label></td>`;
            col++;
            if (col%7==0 && d!=td) html+="</tr><tr>";
        }
        
        for (let x=col; x%7!=0; x++) html+="<td class='disabled-day'></td>";
        document.getElementById('calendarTable').innerHTML = "<tr>"+html+"</tr>";
        document.getElementById('monthLabel').innerText = monthNames[showMonth]+" "+showYear;
    }
    
    window.onDayClick = function(cb) {
        const date = cb.value;
        let label = cb.parentNode.querySelector('label');
        if (cb.checked) {
            label.classList.add("selected");
            addTimesBlock(date);
        } else {
            label.classList.remove("selected");
            removeTimesBlock(date);
        }
    };
    
    renderCalendar();
</script>

    <?php endif; ?>

    <!-- TABLE AND BULK DELETE UI -->
    <form method="POST" id="bulkDeleteForm">
    <div class="table-card">
    <table class="showtimes">
        <tr>
            <th>Movie</th>
            <th>Cinema</th>
            <th>Date</th>
            <th>Time</th>
            <th class="centered-cell">Action</th>
        </tr>
        <?php
        $res = $conn->query("SELECT st.ShowtimeID, md.Title, ci.CinemaName, st.ShowDate, st.ShowTime 
            FROM movie_showtimes st 
            JOIN movie_details md ON st.MovieID = md.MovieID 
            JOIN cinemas ci ON st.CinemaID = ci.CinemaID 
            ORDER BY st.ShowDate, st.ShowTime");
        if ($res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $id = intval($row['ShowtimeID']);
                $date = date('d-m-Y', strtotime($row['ShowDate']));
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['Title']) . "</td>";
                echo "<td>" . htmlspecialchars($row['CinemaName']) . "</td>";
                echo "<td>" . htmlspecialchars($date) . "</td>";
                echo "<td>" . htmlspecialchars($row['ShowTime']) . "</td>";
                echo '<td class="centered-cell"><div class="action-flex">';
                echo "<input type='checkbox' name='delete_ids[]' value='$id' class='bulkdelbox'>";
                echo "<a href='?edit=$id' class='edit'>Edit</a>";
                echo "<a href='?delete=$id' class='delete' onclick=\"return confirm('Delete showtime?');\">Delete</a>";
                echo "</div></td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No showtimes scheduled.</td></tr>";
        }
        ?>
    </table>
    </div>
    <div style="margin-top:8px;">
        <input type="submit" name="bulk_delete" value="Delete Selected" class="bulkdelete-btn" onclick="return confirm('Delete selected showtimes?');">
    </div>
    </form>
</div>

<div id="footer">
  <span><a href="contact_careers.php#contact-us">Contact Us</a></span>
  <span><a href="contact_careers.php#career-opportunities">Career Opportunities</a></span>
  <span><a href="admin_dashboard.php">Admin Panel</a></span>
  <div class="cr">Copyright &copy; IE4727WebDev</div>
</div>
<script>
// Validate edit form to prevent past dates and times - NO EXTERNAL LIBRARIES
const updateForm = document.getElementById('updateForm');
if (updateForm) {
    const dateInput = updateForm.querySelector('#edit-date');
    const timeInput = updateForm.querySelector('#edit-time');
    
    if (dateInput) {
        // Get today's date in YYYY-MM-DD format
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const todayString = `${year}-${month}-${day}`;
        
        // Force set the min attribute
        dateInput.min = todayString;
        
        // Function to validate time for today
        function validateEditTime() {
            if (dateInput.value === todayString && timeInput && timeInput.value) {
                const now = new Date();
                const currentHour = String(now.getHours()).padStart(2, '0');
                const currentMinute = String(now.getMinutes()).padStart(2, '0');
                const currentTime = currentHour + ':' + currentMinute;
                
                if (timeInput.value < currentTime) {
                    alert('Cannot select past times for today. Current time is ' + currentTime);
                    timeInput.value = '';
                    return false;
                }
            }
            return true;
        }
        
        // Check on date input change
        dateInput.addEventListener('input', function() {
            if (this.value < todayString) {
                alert('Cannot select past dates. Please choose today or a future date.');
                this.value = todayString;
            }
        });
        
        // Check on date blur
        dateInput.addEventListener('blur', function() {
            if (this.value < todayString) {
                alert('Cannot select past dates. Please choose today or a future date.');
                this.value = todayString;
            }
        });
        
        // Validate time when it changes
        if (timeInput) {
            timeInput.addEventListener('change', validateEditTime);
            timeInput.addEventListener('blur', validateEditTime);
        }
    }
    
    // Form submit validation
    updateForm.addEventListener('submit', function(e) {
        const dateInput = this.querySelector('#edit-date');
        const timeInput = this.querySelector('#edit-time');
        
        if (dateInput) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const selectedDate = new Date(dateInput.value);
            selectedDate.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                alert('Cannot select past dates. Please choose today or a future date.');
                e.preventDefault();
                return false;
            }
            
            // Check time if date is today
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const todayString = `${year}-${month}-${day}`;
            
            if (dateInput.value === todayString && timeInput && timeInput.value) {
                const now = new Date();
                const currentHour = String(now.getHours()).padStart(2, '0');
                const currentMinute = String(now.getMinutes()).padStart(2, '0');
                const currentTime = currentHour + ':' + currentMinute;
                
                if (timeInput.value < currentTime) {
                    alert('Cannot select past times for today. Current time is ' + currentTime);
                    e.preventDefault();
                    return false;
                }
            }
        }
    });
}
</script>

</body>
</html>

