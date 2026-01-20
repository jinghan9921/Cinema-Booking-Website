<?php
session_start();

// CRITICAL: Hide ALL PHP errors from displaying on the page
error_reporting(0);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Set timezone consistently
date_default_timezone_set('Asia/Singapore'); 

$conn = new mysqli('localhost', 'root', '', 'moviedb');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['session_id'])) {
    $_SESSION['session_id'] = session_id();
}

// Cleanup expired cart items and release seats
if (!empty($_SESSION['cart'])) {
    $now = time();
    foreach ($_SESSION['cart'] as $key => $item) {
        if (isset($item['expires_at']) && $now > $item['expires_at']) {
            if ($item['type'] == 'movie') {
                // Release seats in database
                foreach ($item['seats'] as $seat) {
                    $releaseSQL = "UPDATE seats 
                        SET reserved_until = NULL, reserved_by_session = NULL 
                        WHERE CinemaID=? AND TheatreID=? AND ShowTimeID=? AND SeatNumber=? AND IsBooked=0";
                    $stmt = $conn->prepare($releaseSQL);
                    $stmt->bind_param("iiis", $item['cinema_id'], $item['theatre_id'], $item['showtime_id'], $seat);
                    $stmt->execute();
                }
                
                // Remove all food items linked to this expired movie
                $itemsToDelete = [];
                foreach ($_SESSION['cart'] as $idx => $cartItem) {
                    if ($cartItem['type'] == 'food') {
                        if (isset($cartItem['linked_to_movie']) && 
                            (int)$cartItem['linked_to_movie'] == (int)$key) {
                            $itemsToDelete[] = $idx;
                        }
                    }
                }
                
                // Delete marked food items in reverse order
                rsort($itemsToDelete);
                foreach ($itemsToDelete as $idx) {
                    unset($_SESSION['cart'][$idx]);
                }
            }
            unset($_SESSION['cart'][$key]);
        }
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']);
}


// Handle item removal from cart
if (isset($_GET['remove']) && isset($_SESSION['cart'][$_GET['remove']])) {
    $removeIndex = intval($_GET['remove']);
    
    // Check if item exists
    if (!isset($_SESSION['cart'][$removeIndex])) {
        header("Location: checkout.php");
        exit();
    }
    
    $itemToRemove = $_SESSION['cart'][$removeIndex];
    
    // Only process if it's a movie
    if (isset($itemToRemove['type']) && $itemToRemove['type'] == 'movie') {
        // Release seats
        if (isset($itemToRemove['seats'])) {
            foreach ($itemToRemove['seats'] as $seat) {
                $releaseSQL = "UPDATE seats SET reserved_until = NULL, reserved_by_session = NULL 
                    WHERE CinemaID = ? AND TheatreID = ? AND ShowTimeID = ? AND SeatNumber = ? AND IsBooked = 0";
                $stmt = $conn->prepare($releaseSQL);
                $stmt->bind_param("iiis", $itemToRemove['cinema_id'], $itemToRemove['theatre_id'], $itemToRemove['showtime_id'], $seat);
                $stmt->execute();
            }
        }
        
        // REMOVE LINKED FOOD - Loop through ALL items
        $itemsToDelete = [];
        foreach ($_SESSION['cart'] as $idx => $cartItem) {
            if ($cartItem['type'] === 'food') {
                if (isset($cartItem['linked_to_movie'])) {
                    if ((int)$cartItem['linked_to_movie'] === (int)$removeIndex) {
                        $itemsToDelete[] = $idx;
                    }
                }
            }
        }
        
        // Delete marked items in reverse order
        rsort($itemsToDelete);
        foreach ($itemsToDelete as $idx) {
            unset($_SESSION['cart'][$idx]);
        }
    }
    
    // Remove main item
    unset($_SESSION['cart'][$removeIndex]);
    
    // Re-index
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    
    header("Location: checkout.php");
    exit();
}



// Add to cart: reserve seats
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    if (isset($_POST['seats']) && !empty($_POST['seats'])) {
        $cinema = intval($_POST['cinema']);
        $theatre = intval($_POST['theatre']);
        $time = intval($_POST['time']);
        $seats = $_POST['seats'];

        // Get movie info for cart
        $infoSQL = "SELECT md.Title, md.MovieID, st.ShowDate, st.ShowTime, c.CinemaName FROM movie_showtimes st
            INNER JOIN movie_details md ON st.MovieID = md.MovieID
            INNER JOIN cinemas c ON st.CinemaID = c.CinemaID
            WHERE st.ShowTimeID = ? AND st.CinemaID = ? AND st.TheatreID = ?";
        $stmt = $conn->prepare($infoSQL);
        $stmt->bind_param("iii", $time, $cinema, $theatre);
        $stmt->execute();
        $movieInfo = $stmt->get_result()->fetch_assoc();

        if ($movieInfo) {
            // Dynamic pricing: $10 for weekdays, $15 for weekends
            $showDate = $movieInfo['ShowDate'];
            $dayOfWeek = date('N', strtotime($showDate));
            
            // Check if it's a weekend (Saturday = 6, Sunday = 7)
            if ($dayOfWeek == 6 || $dayOfWeek == 7) {
                $ticketPrice = 15.00;
                $ticketType = 'Weekend Ticket';
            } else {
                $ticketPrice = 10.00;
                $ticketType = 'Standard Ticket';
            }
            
            $expiresAt = time() + (10 * 60);
            $sessionId = $_SESSION['session_id'];
            
            // Reserve seats in the database
            foreach ($seats as $seat) {
                $reserveSQL = "UPDATE seats 
                  SET reserved_until = DATE_ADD(NOW(), INTERVAL 10 MINUTE), 
                      reserved_by_session = ?
                  WHERE CinemaID=? AND TheatreID=? AND ShowTimeID=? 
                  AND SeatNumber=? AND IsBooked=0";
                $stmt = $conn->prepare($reserveSQL);
                $stmt->bind_param("siiis", $sessionId, $cinema, $theatre, $time, $seat);
                $stmt->execute();
            }

            $_SESSION['cart'][] = [
                'type' => 'movie',
                'movie_id' => $movieInfo['MovieID'],
                'title' => $movieInfo['Title'],
                'ticket_type' => $ticketType,
                'cinema' => $movieInfo['CinemaName'],
                'cinema_id' => $cinema,
                'theatre_id' => $theatre,
                'showtime_id' => $time,
                'date' => $movieInfo['ShowDate'],
                'time' => $movieInfo['ShowTime'],
                'seats' => $seats,
                'quantity' => count($seats),
                'price' => $ticketPrice,
                'subtotal' => $ticketPrice * count($seats),
                'expires_at' => $expiresAt,
                'added_at' => time()
            ];
        }
    }
    header("Location: checkout.php");
    exit();
}

// Remove item from cart and unreserve seats
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_item'])) {
    if (isset($_POST['item_index'])) {
        $itemIndex = intval($_POST['item_index']);
        if (isset($_SESSION['cart'][$itemIndex])) {
            $item = $_SESSION['cart'][$itemIndex];
            
            // If it's a movie, release seats AND remove linked food
            if ($item['type'] == 'movie') {
                // Release seats
                foreach ($item['seats'] as $seat) {
                    $releaseSQL = "UPDATE seats SET reserved_until = NULL, reserved_by_session = NULL 
                        WHERE CinemaID = ? AND TheatreID = ? AND ShowTimeID = ? AND SeatNumber = ? AND IsBooked = 0";
                    $stmt = $conn->prepare($releaseSQL);
                    $stmt->bind_param("iiis", $item['cinema_id'], $item['theatre_id'], $item['showtime_id'], $seat);
                    $stmt->execute();
                }
                
                // Remove linked food items
                $itemsToDelete = [];
                foreach ($_SESSION['cart'] as $idx => $cartItem) {
                    if ($cartItem['type'] === 'food') {
                        if (isset($cartItem['linked_to_movie'])) {
                            if ((int)$cartItem['linked_to_movie'] === (int)$itemIndex) {
                                $itemsToDelete[] = $idx;
                            }
                        }
                    }
                }
                
                // Delete marked food items
                rsort($itemsToDelete);
                foreach ($itemsToDelete as $idx) {
                    unset($_SESSION['cart'][$idx]);
                }
            }
            
            // Remove main item
            unset($_SESSION['cart'][$itemIndex]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
        }
    }
    header("Location: checkout.php");
    exit();
}



// Earliest expiration timer (not used)
$earliestExpiry = null;
foreach ($_SESSION['cart'] as $item) {
    if (isset($item['expires_at'])) {
        if ($earliestExpiry === null || $item['expires_at'] < $earliestExpiry) {
            $earliestExpiry = $item['expires_at'];
        }
    }
}

// Add food to cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_food'])) {
    $foodName = trim($_POST['food_name']);
    $foodSize = trim($_POST['food_size']);
    $foodFlavor = isset($_POST['food_flavor']) ? trim($_POST['food_flavor']) : '';
    $foodQuantity = intval($_POST['food_quantity']);
    $foodPrice = floatval($_POST['food_price']);
    
    // Get which movie to link to
    $linkedToMovie = isset($_POST['link_to_movie']) ? intval($_POST['link_to_movie']) : null;
    

    if ($foodQuantity > 0) {
        // Build display name with size and flavor
        $displayName = $foodName;
        
        if (!empty($foodSize)) {
            $displayName .= ' - ' . $foodSize;
        }
        
        if (!empty($foodFlavor)) {
            $displayName .= ' - ' . $foodFlavor;
        }
        
        $_SESSION['cart'][] = [
            'type' => 'food',
            'name' => $displayName,
            'base_name' => $foodName,
            'size' => $foodSize,
            'flavor' => $foodFlavor,
            'quantity' => $foodQuantity,
            'price' => $foodPrice,
            'subtotal' => $foodPrice * $foodQuantity,  
            'linked_to_movie' => $linkedToMovie
        ];
    }
    header("Location: checkout.php");
    exit();
}

include 'sidebar.php';
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <title>Checkout - IE Theatre</title>
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

        .checkout-container {
            max-width: 1000px;
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
        
        /* Timer Section */
        .timer-section {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        }
        
        .timer-text {
            font-size: 1em;
            margin-bottom: 10px;
        }
        
        .timer-display {
            font-size: 2.5em;
            font-weight: bold;
            font-family: 'Courier New', monospace;
        }
        .item-timer {
            color: #dc3545;
            font-size: 1.3em !important;
            font-weight: bold;
            margin-top: 8px;
            font-family: 'Courier New', monospace;
        }
        .cart-section {
            background: #2a2d35;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .section-header {
            font-size: 1.5em;
            color: #ffa726;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ffa726;
            font-weight: bold;
        }
        
        .cart-item {
            background: rgba(0,0,0,0.2);
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            border-left: 4px solid #ffa726;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .item-title {
            font-size: 1.4em;
            color: #ffa726;
            font-weight: bold;
        }

        
        .item-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.2s;
        }
        
        .item-remove:hover {
            background: #c82333;
        }
        
        .item-details {
            color: #ddd;
            line-height: 2;
            margin-bottom: 10px;
            font-size: 1.05em;
        }
        
        .item-price {
            font-size: 1.2em;
            color: #28a745;
            font-weight: bold;
            text-align: right;
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-cart-icon {
            font-size: 5em;
            margin-bottom: 20px;
        }
        
        .summary-section {
            background: #2a2d35;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            margin-bottom: 25px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-size: 1.1em;
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .summary-total {
            font-size: 1.5em;
            color: #ffa726;
            font-weight: bold;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #ffa726;
        }

        #selected-items-list {
            color: #ddd;
            font-size: 1em;
            margin-top: 10px;
            line-height: 1.8;
        }
                
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #ffa726;
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 1em;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0,0,0,0.3);
            border: 2px solid #555;
            border-radius: 6px;
            color: #fff;
            font-size: 1em;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #ffa726;
            background: rgba(0,0,0,0.4);
        }
        
        .form-input::placeholder {
            color: #999;
        }
        
        .checkout-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: block;
        }
        
        .btn-continue {
            background: #6c757d;
            color: white;
        }
        
        .btn-continue:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-payment {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .btn-payment:hover {
            background: linear-gradient(135deg, #218838 0%, #1ba87e 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }
        .food-scroll-container {
            overflow-x: auto;
            white-space: nowrap;
            padding: 20px 0;
            margin: 0 -10px;
        }

        .food-scroll-container::-webkit-scrollbar {
            height: 8px;
        }

        .food-scroll-container::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.3);
            border-radius: 4px;
        }

        .food-scroll-container::-webkit-scrollbar-thumb {
            background: #ffa726;
            border-radius: 4px;
        }

        .food-item-card {
            display: inline-block;
            width: 200px;
            margin: 0 10px;
            vertical-align: top;
            background: #42454e;
            border-radius: 15px;
            overflow: visible;  /* ✅ Changed from hidden */
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            white-space: normal;
            padding: 15px;  /* ✅ Added padding for border effect */
        }

        .food-item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(255,167,38,0.3);
        }

        .food-item-image {
            width: 100%;
            height: 150px;
            object-fit: contain;
            cursor: pointer;
            background: #ffffff;  /* ✅ White background */
            border-radius: 10px;
            display: block;  /* ✅ Changed from flex */
            margin: 0 auto;  /* ✅ Centers the image */
        }

        .food-item-name {
            padding: 15px 10px 5px 10px;  /* ✅ Adjusted padding */
            text-align: center;
            color: #ffa726;
            font-weight: bold;
            font-size: 1.1em;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
        }

        .modal-content {
            background: #2a2d35;
            margin: 5% auto;
            padding: 30px;
            border: 2px solid #ffa726;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .close-modal {
            color: #999;
            float: right;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            line-height: 20px;
        }

        .close-modal:hover {
            color: #fff;
        }

        .modal-food-title {
            color: #ffa726;
            font-size: 1.8em;
            margin: 20px 0;
            text-align: center;
        }

        .option-group {
            margin: 20px 0;
        }

        .option-label {
            color: #ffa726;
            font-weight: bold;
            display: block;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .option-button {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background: rgba(0,0,0,0.4);
            border: 2px solid #555;
            border-radius: 8px;
            color: #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .option-button:hover {
            background: rgba(255,167,38,0.2);
            border-color: #ffa726;
        }

        .option-button.selected {
            background: #ffa726;
            border-color: #ffa726;
            color: #000;
            font-weight: bold;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }

        .qty-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #555;
            border: none;
            color: #fff;
            font-size: 1.5em;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .qty-btn:hover {
            background: #ffa726;
            color: #000;
        }

        .qty-display {
            font-size: 1.5em;
            color: #fff;
            min-width: 40px;
            text-align: center;
        }

        .add-to-cart-btn {
            width: 100%;
            padding: 15px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .add-to-cart-btn:hover {
            background: #218838;
        }

        /* Sold Out Styling */
        .sold-out-card {
        position: relative;
        pointer-events: none;
        }

        .sold-out-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 10;
        width: 100%;
        text-align: center;
        }

        .sold-out-badge {
        display: inline-block;
        background: rgba(220, 53, 69, 0.95);
        color: white;
        padding: 12px 30px;
        border-radius: 8px;
        font-size: 1.3em;
        font-weight: bold;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.5);
        border: 3px solid #fff;
        }

        .sold-out-card .item-image {
        filter: grayscale(100%) brightness(0.6);
        }

        .sold-out-card .item-name {
        opacity: 0.5;
        }

        @media (max-width: 768px) {
            body {
                padding-left: 0;
            }
            
            .checkout-container {
                padding: 15px;
            }
            
            .checkout-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="checkout-container">
    <h1>🛒 Checkout</h1>
    
    <?php if (!empty($_SESSION['cart'])): ?>
        <?php
        $movieTotal = 0;
        $foodTotal = 0;
        $movieItems = [];
        $foodItems = [];
        foreach ($_SESSION['cart'] as $index => $item) {
            if ($item['type'] == 'movie') {
                $movieItems[] = ['index' => $index, 'item' => $item];
                $movieTotal += $item['subtotal'];
            } elseif ($item['type'] == 'food') {
                $foodItems[] = ['index' => $index, 'item' => $item];
                $foodTotal += $item['subtotal'];
            }
        }
        ?>
        
        <!-- Movie Tickets Section -->
        <?php if (!empty($movieItems)): ?>
        <div class="cart-section">
            <div class="section-header">🎬 Movie Tickets</div>
            <?php foreach ($movieItems as $itemData): 
                $index = $itemData['index'];
                $item = $itemData['item'];
                $expiresAt = isset($item['expires_at']) ? $item['expires_at'] : null;
            ?>
                <div class="cart-item">
                    <div class="item-header">
                        <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                           <input type="checkbox"
                                class="item-checkbox"
                                data-type="movie"
                                data-index="<?= $index ?>"
                                data-price="<?= $item['subtotal'] ?>"
                                data-name="<?= htmlspecialchars($item['title']) ?>"
                                id="item-<?= $index ?>"
                                name="selected_items[]"
                                value="<?= $index ?>"
                                <?= $isChecked ? 'checked' : '' ?>
                                style="width: 20px; height: 20px; cursor: pointer;"
                            >



                            <div style="flex: 1;">
                                <div class="item-title">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </div>

                                <?php if ($expiresAt): ?>
                                <div class="item-timer" style="color: #dc3545; font-size: 0.9em; margin-top: 5px;">
                                    ⏰ Expires in: <span class="timer-countdown" data-expires="<?= $expiresAt ?>">10:00</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="item_index" value="<?= $index ?>">
                            <button type="submit" name="remove_item" class="item-remove">Remove</button>
                        </form>
                    </div>
                    <div class="item-details">
                        <strong>Cinema:</strong> <?= htmlspecialchars($item['cinema']) ?><br>
                        <strong>Date:</strong> <?= date('l, F j, Y', strtotime($item['date'])) ?><br>
                        <strong>Time:</strong> <?= date('g:i A', strtotime($item['time'])) ?><br>
                        <strong>Seats:</strong> <?= implode(', ', $item['seats']) ?><br>
                        <strong>Quantity:</strong> <?php echo $item['quantity']; ?> × $<?php echo number_format($item['price'], 2); ?> 
                        (<?php echo htmlspecialchars($item['ticket_type']); ?>)<br>
                    </div>
                    <div class="item-price">$<?= number_format($item['subtotal'], 2) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
<!-- Food & Beverages Section -->
<?php if (!empty($foodItems)): ?>
<div class="cart-section">
    <div class="section-header">🍿 Food & Beverages</div>
    <?php foreach ($foodItems as $itemData): 
        $index = $itemData['index'];
        $item = $itemData['item'];
    ?>
        <div class="cart-item">
            <div class="item-header">
                <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                  <input type="checkbox"
                        class="item-checkbox"
                        data-type="food"
                        data-linkedmovie="<?= $item['linked_to_movie'] ?>"
                        data-index="<?= $index ?>"
                        data-price="<?= $item['subtotal'] ?>"
                        data-name="<?= htmlspecialchars($item['name']) ?>"
                        id="item-<?= $index ?>"
                        name="selected_items[]"
                        value="<?= $index ?>"
                        <?= $isChecked ? 'checked' : '' ?>
                        style="width: 20px; height: 20px; cursor: pointer;"
                    >



                    <div class="item-title"><?= htmlspecialchars($item['name']) ?></div>
                </div>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="item_index" value="<?= $index ?>">
                    <button type="submit" name="remove_item" class="item-remove">Remove</button>
                </form>
            </div>
            <div class="item-details">
                <strong>Size:</strong> <?php echo htmlspecialchars($item['size']); ?><br>
                <?php if (!empty($item['flavor'])): ?>
                    <strong>Flavor:</strong> <?php echo htmlspecialchars($item['flavor']); ?><br>
                <?php endif; ?>
                <strong>Quantity:</strong> <?php echo $item['quantity']; ?> × $<?php echo number_format($item['price'], 2); ?><br>
                
                <!-- Show which movie this food is linked to -->
              <?php if (isset($item['linked_to_movie']) && isset($_SESSION['cart'][$item['linked_to_movie']])): 
                    $linkedMovie = $_SESSION['cart'][$item['linked_to_movie']];
                ?>
                <strong style="color: #4CAF50;">🎬 Linked to:</strong> 
                <?php echo htmlspecialchars($linkedMovie['title']); ?> - 
                <?php echo date('D, M j, Y', strtotime($linkedMovie['date'])); ?>, 
                <?php echo date('g:i A', strtotime($linkedMovie['time'])); ?>
                <?php endif; ?>

            </div>
            <div class="item-price">$<?= number_format($item['subtotal'], 2) ?></div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

        
        <!-- Add Food Items Section -->
        <div class="cart-section">
            <div class="section-header">🍿 Add Food & Beverages</div>
            <div class="food-scroll-container">
                <?php 
            $foodQuery = "SELECT i.id, i.Name, i.Image, i.sold_out,
                GROUP_CONCAT(DISTINCT CONCAT(s.size, ':', s.price) ORDER BY s.price SEPARATOR ',') as sizes,
                GROUP_CONCAT(DISTINCT CONCAT(f.flavor_name) ORDER BY f.flavor_name SEPARATOR ',') as flavors
                FROM items i
                LEFT JOIN sizes s ON i.id = s.item_id AND (s.sold_out IS NULL OR s.sold_out = 0)
                LEFT JOIN flavors f ON i.id = f.item_id AND (f.sold_out IS NULL OR f.sold_out = 0)
                WHERE 1=1
                GROUP BY i.id, i.Name, i.Image
                ORDER BY 
                    CASE 
                        WHEN i.Name = 'Popcorn' THEN 1
                        WHEN i.Name LIKE '%Chicken%' OR i.Name LIKE '%Nugget%' THEN 2
                        WHEN i.Name = 'Soda' THEN 3
                        WHEN i.Name LIKE '%Small%' THEN 4
                        WHEN i.Name LIKE '%Medium%' THEN 5
                        WHEN i.Name LIKE '%Large%' THEN 6
                        ELSE 7
                    END,
                    i.Name ASC";
            $foodResult = $conn->query($foodQuery);

            if ($foodResult && $foodResult->num_rows > 0):
            while ($row = $foodResult->fetch_assoc()): 
                $isSoldOut = isset($row['sold_out']) && $row['sold_out'] == 1;
                $dbFood = [
                    'id' => $row['id'],
                    'name' => $row['Name'],
                    'image' => $row['Image'],
                    'sizes' => $row['sizes'] ? $row['sizes'] : 'Regular:5.00',
                    'flavors' => $row['flavors'] ? $row['flavors'] : '',
                    'sold_out' => $isSoldOut
                ];
            ?>

            <div class="food-item-card <?= $isSoldOut ? 'sold-out-card' : '' ?>" 
                onclick='<?= !$isSoldOut ? "openFoodModal(" . json_encode($dbFood) . ")" : "" ?>'
                style="<?= $isSoldOut ? 'cursor: not-allowed;' : '' ?>">
                
                <img src="<?= htmlspecialchars($dbFood['image']) ?>" 
                    alt="<?= htmlspecialchars($dbFood['name']) ?>" 
                    class="food-item-image" 
                    style="<?= $isSoldOut ? 'opacity: 0.5; filter: grayscale(100%);' : '' ?>"
                    onerror="this.src='images/placeholder.jpg'">
                
                <?php if ($isSoldOut): ?>
                    <div class="sold-out-overlay">
                        <span class="sold-out-badge">🚫 SOLD OUT</span>
                    </div>
                <?php endif; ?>
                
                <div class="food-item-name"><?= htmlspecialchars($dbFood['name']) ?></div>
            </div>
        <?php 
            endwhile;
        endif; 
        ?>
            </div>
        </div>

       <!-- Food Modal -->
        <div id="foodModal" class="modal">
            <div class="modal-content">
                <span class="close-modal" onclick="closeFoodModal()">&times;</span>
                <h2 class="modal-food-title" id="modal-food-name"></h2>
                
                <form method="POST" id="food-form" onsubmit="return saveCheckedItemsBeforeSubmit()">
                    <input type="hidden" name="add_food" value="1">
                    <input type="hidden" name="food_item_id" id="modal-food-id">
                    <input type="hidden" name="food_name" id="hidden-food-name">
                    <input type="hidden" name="food_size" id="hidden-food-size">
                    <input type="hidden" name="food_price" id="hidden-food-price">
                    <input type="hidden" name="food_flavor" id="hidden-food-flavor" value="">
                    
                <!--Movie Selection Dropdown -->
                    <div class="option-group">
                        <label class="option-label">🎬 Link to Movie Ticket: *</label>
                        <select name="link_to_movie" required>
                            <option value="">-- Select Movie Ticket --</option>
                            <?php foreach ($_SESSION['cart'] as $idx => $cartItem): ?>
                            <?php if ($cartItem['type'] === 'movie'): ?>
                                <option value="<?= $idx ?>">
                                    <?= htmlspecialchars($cartItem['title']); ?> (<?= date('l, F j, Y', strtotime($cartItem['date'])) ?> 
                                    @ <?= date('g:i A', strtotime($cartItem['time'])) ?>)
                                </option>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </select>

                        <small style="color: #999; display: block; margin-top: 5px;">
                            <span id="dropdown-message">This food will be included with the selected movie ticket.</span>
                        </small>
                    </div>

                    <div class="option-group" id="size-group">
                        <label class="option-label">Select Size:</label>
                        <div id="size-options"></div>
                    </div>
                    
                    <div class="option-group" id="flavor-group" style="display:none;">
                        <label class="option-label">Select Flavor:</label>
                        <div id="flavor-options"></div>
                    </div>
                    
                    <div class="option-group">
                        <label class="option-label">Quantity:</label>
                        <div class="quantity-control">
                            <button type="button" class="qty-btn" onclick="changeQuantity(-1)">-</button>
                            <span class="qty-display" id="quantity-display">1</span>
                            <button type="button" class="qty-btn" onclick="changeQuantity(1)">+</button>
                        </div>
                        <input type="hidden" name="food_quantity" id="hidden-quantity" value="1">
                    </div>
                    
                    <button type="submit" class="add-to-cart-btn">Add to Cart</button>
                </form>
            </div>
        </div>


        <!-- Order Summary -->
        <div class="summary-section">
            <div class="section-header">📋 Order Summary</div>
            <div id="selected-items-list" style="margin-bottom: 20px; padding: 15px; background: rgba(0,0,0,0.2); border-radius: 8px; min-height: 50px;">
                <em style="color: #999;">No items selected</em>
            </div>
            <div class="summary-row">
                <span>Subtotal:</span>
                <span id="subtotal-amount">$0.00</span>
            </div>
            <div class="summary-row">
                <span>Booking Fee:</span>
                <span>$2.00</span>
            </div>
            <div class="summary-row summary-total">
                <span>Total:</span>
                <span id="selected-total">$2.00</span>
            </div>
        </div>
        
        <!-- Personal Details Form -->
<div class="summary-section">
    <div class="section-header">👤 Personal Details</div>
    <form method="POST" action="payment.php" onsubmit="return validateForm()">
        <?php 
        // Generate hidden inputs for ALL cart items
        foreach ($_SESSION['cart'] as $index => $item): 
        ?>
            <input type="hidden" name="selected_items[]" value="<?php echo $index; ?>" class="selected-item-<?php echo $index; ?>" disabled>
            
            <?php if ($item['type'] == 'movie'): ?>
                <?php foreach ($item['seats'] as $seat): ?>
                    <input type="hidden" name="movie_seats_<?php echo $index; ?>[]" value="<?php echo htmlspecialchars($seat); ?>" class="selected-item-<?php echo $index; ?>" disabled>
                <?php endforeach; ?>
                <input type="hidden" name="movie_cinema_<?php echo $index; ?>" value="<?php echo $item['cinema_id']; ?>" class="selected-item-<?php echo $index; ?>" disabled>
                <input type="hidden" name="movie_theatre_<?php echo $index; ?>" value="<?php echo $item['theatre_id']; ?>" class="selected-item-<?php echo $index; ?>" disabled>
                <input type="hidden" name="movie_showtime_<?php echo $index; ?>" value="<?php echo $item['showtime_id']; ?>" class="selected-item-<?php echo $index; ?>" disabled>
            <?php elseif ($item['type'] == 'food'): ?>
                <input type="hidden" name="food_item_<?php echo $index; ?>" value="<?php echo htmlspecialchars($item['name']); ?>" class="selected-item-<?php echo $index; ?>" disabled>
                <input type="hidden" name="food_size_<?php echo $index; ?>" value="<?php echo htmlspecialchars($item['size']); ?>" class="selected-item-<?php echo $index; ?>" disabled>
                <input type="hidden" name="food_flavor_<?php echo $index; ?>" value="<?php echo htmlspecialchars($item['flavor']); ?>" class="selected-item-<?php echo $index; ?>" disabled>
                <input type="hidden" name="food_quantity_<?php echo $index; ?>" value="<?php echo $item['quantity']; ?>" class="selected-item-<?php echo $index; ?>" disabled>
                <input type="hidden" name="food_price_<?php echo $index; ?>" value="<?php echo $item['price']; ?>" class="selected-item-<?php echo $index; ?>" disabled>
            <?php endif; ?>
        <?php endforeach; ?>

                    
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" class="form-input" 
                            placeholder="Enter your full name"
                            value="<?= isset($_SESSION['customer_name']) ? htmlspecialchars($_SESSION['customer_name']) : '' ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="text" id="email" name="email" class="form-input" 
                            placeholder="your.email@example.com"
                            value="<?= isset($_SESSION['customer_email']) ? htmlspecialchars($_SESSION['customer_email']) : '' ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" class="form-input" 
                            placeholder="+65 1234 5678"
                            value="<?= isset($_SESSION['customer_phone']) ? htmlspecialchars($_SESSION['customer_phone']) : '' ?>"
                            required>
                    </div>
                    <div class="checkout-actions">
                        <a href="index.php" class="btn btn-continue">Continue Shopping</a>
                        <button type="submit" class="btn btn-payment" id="checkout-btn">Proceed to Payment</button>
                    </div>
                </form>
        </div>
        
    <?php else: ?>
        <div class="cart-section">
            <div class="empty-cart">
                <div class="empty-cart-icon">🛒</div>
                <h2 style="color: #999;">Your cart is empty</h2>
                <p>Start by selecting a movie or browsing our food menu!</p>
                <div style="margin-top: 30px;">
                    <a href="index.php" class="btn btn-continue" style="max-width: 300px; margin: 0 auto;">Browse Movies</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
<script>
var currentQuantity = 1;

function saveCheckedItemsBeforeSubmit() {
    var checkedItems = [];
    var checkboxes = document.querySelectorAll('.item-checkbox:checked');
    
    checkboxes.forEach(function(checkbox) {
        checkedItems.push(checkbox.getAttribute('data-index'));
    });
    
    // Save to sessionStorage (persists across page refresh)
    sessionStorage.setItem('checkedItems', JSON.stringify(checkedItems));
    
    return true; // Allow form submission
}


// Update movie dropdown to show only checked tickets
function updateMovieDropdown() {
    var dropdown = document.getElementById('movie-link-dropdown');
    if (!dropdown) return;
    
    // Clear existing options
    dropdown.innerHTML = '<option value="">-- Select Movie Ticket --</option>';
    
    // Get all checked movie tickets
    var checkedMovies = [];
    var checkboxes = document.querySelectorAll('.item-checkbox');
    
    checkboxes.forEach(function(checkbox) {
        if (checkbox.checked && checkbox.getAttribute('data-type') === 'movie') {
            var index = checkbox.getAttribute('data-index');
            var name = checkbox.getAttribute('data-name');
            
            // Get additional movie info from the cart item
            var movieElement = checkbox.closest('.cart-item');
            var dateElement = movieElement.querySelector('.item-details strong:nth-of-type(2)');
            var timeElement = movieElement.querySelector('.item-details strong:nth-of-type(3)');
            
            var dateText = dateElement ? dateElement.nextSibling.textContent.trim() : '';
            var timeText = timeElement ? timeElement.nextSibling.textContent.trim() : '';
            
            checkedMovies.push({
                index: index,
                name: name,
                date: dateText,
                time: timeText
            });
        }
    });
    
    // Populate dropdown with checked movies
    if (checkedMovies.length > 0) {
        checkedMovies.forEach(function(movie) {
            var option = document.createElement('option');
            option.value = movie.index;
            option.textContent = movie.name + ' (' + movie.date + ' @ ' + movie.time + ')';
            dropdown.appendChild(option);
        });
        
        // Update message
        document.getElementById('dropdown-message').textContent = 
            'This food will be included with the selected movie ticket.';
        dropdown.disabled = false;
    } else {
        // No movies selected
        var option = document.createElement('option');
        option.value = '';
        option.textContent = '-- No movie tickets selected --';
        dropdown.appendChild(option);
        
        document.getElementById('dropdown-message').innerHTML = 
            '<span style="color: #ff6b6b;">⚠️ Please select at least one movie ticket in your cart first.</span>';
        dropdown.disabled = true;
    }
}


function openFoodModal(foodData) {
    updateMovieDropdown();
    currentQuantity = 1;
    document.getElementById('modal-food-name').textContent = foodData.name;
    document.getElementById('hidden-food-name').value = foodData.name;
    document.getElementById('quantity-display').textContent = '1';
    document.getElementById('hidden-quantity').value = '1';
    
    var sizesDiv = document.getElementById('size-options');
    sizesDiv.innerHTML = '';
    var sizes = foodData.sizes.split(',');
    for (var i = 0; i < sizes.length; i++) {
        var parts = sizes[i].split(':');
        var sizeName = parts[0];
        var sizePrice = parts[1];
        var btn = document.createElement('span');
        btn.className = 'option-button' + (i === 0 ? ' selected' : '');
        btn.textContent = sizeName + ' - $' + sizePrice;
        btn.setAttribute('data-size', sizeName);
        btn.setAttribute('data-price', sizePrice);
        btn.onclick = function() { selectSize(this); };
        sizesDiv.appendChild(btn);
    }
    
    if (sizes.length > 0) {
        var firstParts = sizes[0].split(':');
        document.getElementById('hidden-food-size').value = firstParts[0];
        document.getElementById('hidden-food-price').value = firstParts[1];
    }
    
    if (foodData.flavors && foodData.flavors.trim() !== '') {
        document.getElementById('flavor-group').style.display = 'block';
        var flavorDiv = document.getElementById('flavor-options');
        flavorDiv.innerHTML = '';
        var flavors = foodData.flavors.split(',');
        for (var j = 0; j < flavors.length; j++) {
            var flavor = flavors[j];
            var flavorBtn = document.createElement('span');
            flavorBtn.className = 'option-button' + (j === 0 ? ' selected' : '');
            flavorBtn.textContent = flavor;
            flavorBtn.setAttribute('data-flavor', flavor);
            flavorBtn.onclick = function() { selectFlavor(this); };
            flavorDiv.appendChild(flavorBtn);
        }
        document.getElementById('hidden-food-flavor').value = flavors[0];
    } else {
        document.getElementById('flavor-group').style.display = 'none';
        document.getElementById('hidden-food-flavor').value = '';
    }
    
    document.getElementById('foodModal').style.display = 'block';
}

function selectSize(element) {
    var buttons = document.querySelectorAll('#size-options .option-button');
    for (var i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove('selected');
    }
    element.classList.add('selected');
    document.getElementById('hidden-food-size').value = element.getAttribute('data-size');
    document.getElementById('hidden-food-price').value = element.getAttribute('data-price');
}

function selectFlavor(element) {
    var buttons = document.querySelectorAll('#flavor-options .option-button');
    for (var i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove('selected');
    }
    element.classList.add('selected');
    document.getElementById('hidden-food-flavor').value = element.getAttribute('data-flavor');
}

function changeQuantity(delta) {
    currentQuantity += delta;
    if (currentQuantity < 1) currentQuantity = 1;
    document.getElementById('quantity-display').textContent = currentQuantity;
    document.getElementById('hidden-quantity').value = currentQuantity;
}

function closeFoodModal() {
    document.getElementById('foodModal').style.display = 'none';
}

window.onclick = function(event) {
    var modal = document.getElementById('foodModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// Individual countdown timers for each movie ticket
function updateAllTimers() {
    var timers = document.querySelectorAll('.timer-countdown');
    var now = Math.floor(Date.now() / 1000);
    var shouldReload = false;
    
    timers.forEach(function(timer) {
        var expires = parseInt(timer.getAttribute('data-expires'));
        var remaining = expires - now;
        
        if (remaining <= 0) {
            shouldReload = true;
            timer.textContent = '0:00';
        } else {
            var minutes = Math.floor(remaining / 60);
            var seconds = remaining % 60;
            timer.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
        }
    });
    
    if (shouldReload) {
        location.reload();
    }
}

// Start timer updates if there are any countdowns
if (document.querySelectorAll('.timer-countdown').length > 0) {
    setInterval(updateAllTimers, 1000);
    updateAllTimers();
}


// Update order summary based on checkbox selections
function updateOrderSummary() {
    var checkboxes = document.querySelectorAll('.item-checkbox');
    var subtotal = 0;
    var itemsList = [];
    
    checkboxes.forEach(function(checkbox) {
        var index = checkbox.getAttribute('data-index');
        var hiddenInputs = document.querySelectorAll('.selected-item-' + index);
        
        if (checkbox.checked) {
            var price = parseFloat(checkbox.getAttribute('data-price'));
            var name = checkbox.getAttribute('data-name');
            
            if (!isNaN(price)) {
                subtotal += price;
                itemsList.push('• ' + name + ' - $' + price.toFixed(2));
            }
            
            // Enable hidden inputs for this item
            hiddenInputs.forEach(function(input) {
                input.disabled = false;
            });
        } else {
            // Disable hidden inputs for unchecked items
            hiddenInputs.forEach(function(input) {
                input.disabled = true;
            });
        }
    });
    
    var bookingFee = 2.00;
    var total = subtotal + bookingFee;
    
    // Update display elements
    var subtotalElement = document.getElementById('subtotal-amount');
    var totalElement = document.getElementById('selected-total');
    var itemsListDiv = document.getElementById('selected-items-list');
    
    if (subtotalElement) subtotalElement.textContent = '$' + subtotal.toFixed(2);
    if (totalElement) totalElement.textContent = '$' + total.toFixed(2);
    
    if (itemsListDiv) {
        if (itemsList.length > 0) {
            itemsListDiv.innerHTML = itemsList.join('<br>');
        } else {
            itemsListDiv.innerHTML = '<em style="color: #999;">No items selected</em>';
        }
    }
     updateMovieDropdown(); 
}

// Initialize checkout page
function initializeCheckout() {
    var checkboxes = document.querySelectorAll('.item-checkbox');
    
    // RESTORE CHECKED STATE FROM PREVIOUS SESSION
    var savedCheckedItems = sessionStorage.getItem('checkedItems');
    if (savedCheckedItems) {
        var checkedIndices = JSON.parse(savedCheckedItems);
        
        checkboxes.forEach(function(checkbox) {
            var index = checkbox.getAttribute('data-index');
            // Check if this item was previously checked
            if (checkedIndices.includes(index)) {
                checkbox.checked = true;
            } else {
                checkbox.checked = false;
            }
            
            // Add event listener for changes
            checkbox.addEventListener('change', updateOrderSummary);
        });
        
        // Clear saved state after restoring
        sessionStorage.removeItem('checkedItems');
    } else {
        // No saved state - default to unchecked
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = false;
            checkbox.addEventListener('change', updateOrderSummary);
        });
    }
    
    // Initial summary update
    setTimeout(function() {
        updateOrderSummary();
    }, 100);
}


// Run initialization when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCheckout);
} else {
    initializeCheckout();
}

// Form validation before submission
function validateForm() {
    var checkboxes = document.querySelectorAll('.item-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('Please select at least one item to proceed with payment.');
        return false;
    }
    
    // Save checked items before proceeding to payment
    var checkedItems = [];
    checkboxes.forEach(function(checkbox) {
        checkedItems.push(checkbox.getAttribute('data-index'));
    });
    sessionStorage.setItem('checkedItems', JSON.stringify(checkedItems));
    
   // Validate name - only letters and spaces
    var nameField = document.getElementById('full_name');
    var name = nameField.value.trim();
    var nameRegex = /^[a-zA-Z\s]+$/;
    if (!nameRegex.test(name)) {
        alert('Name can only contain letters and spaces.');
        nameField.focus();
        return false;
    }
    
    var name = nameField.value.trim();
    var nameRegex = /^[a-zA-Z\s]+$/;
    if (!nameRegex.test(name)) {
        alert('Name can only contain letters and spaces, no symbols or numbers.');
        return false;
    }
    
    // Validate email - must contain @
    var emailField = document.getElementById('email');
    if (!emailField) return true;
    
    var email = emailField.value.trim();
    if (!email.includes('@')) {
        alert('Please enter a valid email address with @');
        return false;
    }
    
    // Validate phone - exactly 8 digits
    var phoneField = document.getElementById('phone');
    var phone = phoneField.value.trim();
    var phoneRegex = /^\d{8}$/;
    if (!phoneRegex.test(phone)) {
        alert('Phone number must be exactly 8 digits.');
        phoneField.focus();
        return false;
    }
    
    return true;
}

 document.querySelectorAll('.item-checkbox[data-type="food"]').forEach(function(foodBox) {
  foodBox.addEventListener('change', function(e) {
    var linked = foodBox.getAttribute('data-linkedmovie');
    var linkedMovieBox = document.querySelector('.item-checkbox[data-type="movie"][data-index="' + linked + '"]');
    if (foodBox.checked && linkedMovieBox && !linkedMovieBox.checked) {
      alert('Please select the movie that this food item is linked to first!');
      foodBox.checked = false;
      if (linkedMovieBox) linkedMovieBox.focus();
      e.preventDefault();
      return false;
    }
  });
});

</script>

</body>

</html>