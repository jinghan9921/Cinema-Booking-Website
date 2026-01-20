<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'moviedb');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Store customer details in session from checkout form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $_SESSION['customer_name'] = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $_SESSION['customer_email'] = isset($_POST['email']) ? trim($_POST['email']) : '';
    $_SESSION['customer_phone'] = isset($_POST['phone']) ? trim($_POST['phone']) : '';
}

// Get customer information from session
$customerName = isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : '';
$customerPhone = isset($_SESSION['customer_phone']) ? $_SESSION['customer_phone'] : '';
$customerEmail = isset($_SESSION['customer_email']) ? $_SESSION['customer_email'] : '';

// Validate required fields
if (empty($customerName) || empty($customerEmail)) {
    die("Missing required information. Please go back and fill in all fields.");
}

// Get selected items from form (array of indices)
$selectedIndices = isset($_POST['selected_items']) ? $_POST['selected_items'] : [];

if (empty($selectedIndices)) {
    die("No items selected for checkout. Please go back and select items.");
}

$cartItems = [];
$totalAmount = 0;
$bookingFee = 2.00;
$now = time();

// Process only selected items
foreach ($selectedIndices as $index) {
    if (isset($_SESSION['cart'][$index])) {
        $item = $_SESSION['cart'][$index];
        
        // Check if item has expired
        if (isset($item['expires_at']) && $now > $item['expires_at']) {
            die("⚠️ Some selected items have expired. Please refresh and try again.");
        }
        
        $cartItems[] = ['index' => $index, 'item' => $item];
        $totalAmount += $item['subtotal'];
    }
}

$totalAmount += $bookingFee;

// Generate MAIN booking reference for the order
$mainBookingRef = 'BK' . strtoupper(substr(md5(time() . $customerEmail . rand()), 0, 8));

// Store all booking references for email
$allBookingRefs = [];
$movieBookingRefs = []; // Map cart index to booking reference

// Process MOVIE items first and store their references
$itemCounter = 1;
foreach ($cartItems as $cartData) {
    $item = $cartData['item'];
    $cartIndex = $cartData['index'];
    
    if ($item['type'] === 'movie') {
        // Generate UNIQUE booking reference for this movie
        $itemBookingRef = $mainBookingRef . '-' . $itemCounter;
        $allBookingRefs[] = $itemBookingRef;
        $movieBookingRefs[$cartIndex] = $itemBookingRef; // Store reference by cart index
        
        $seatsStr = implode(', ', $item['seats']);
        
        // INSERT BOOKING INTO DATABASE
        $insertBooking = "INSERT INTO bookings (booking_reference, customer_name, customer_email, customer_phone, 
                          movie_title, cinema_name, show_date, show_time, seats, quantity, total_amount, booking_date)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmtBook = $conn->prepare($insertBooking);
        $stmtBook->bind_param("sssssssssid", $itemBookingRef, $customerName, $customerEmail, $customerPhone,
                              $item['title'], $item['cinema'], $item['date'], $item['time'], 
                              $seatsStr, $item['quantity'], $item['subtotal']);
        $stmtBook->execute();
        
        // PERMANENTLY MARK SEATS AS BOOKED
        foreach ($item['seats'] as $seatNum) {
            $updateSeat = "UPDATE seats 
                          SET IsBooked = 1, reserved_until = NULL, reserved_by_session = NULL 
                          WHERE CinemaID = ? AND ShowTimeID = ? AND SeatNumber = ?";
            $stmtSeat = $conn->prepare($updateSeat);
            $stmtSeat->bind_param("iis", $item['cinema_id'], $item['showtime_id'], $seatNum);
            $stmtSeat->execute();
        }
        
        $itemCounter++;
    }
}

// Process FOOD items with their linked movie references
foreach ($cartItems as $cartData) {
    $item = $cartData['item'];
    $cartIndex = $cartData['index'];
    
    if ($item['type'] === 'food') {
        $linkedMovieRef = null;
        
        // Get the reference of the linked movie
        if (isset($item['linked_to_movie']) && isset($movieBookingRefs[$item['linked_to_movie']])) {
            $linkedMovieRef = $movieBookingRefs[$item['linked_to_movie']];
        }
        
        $insertFood = "INSERT INTO food_orders (booking_reference, customer_name, customer_email, 
                      item_name, item_size, quantity, price, subtotal, order_date, linked_movie_reference)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        $stmtFood = $conn->prepare($insertFood);
        $stmtFood->bind_param("ssssiddss", $mainBookingRef, $customerName, $customerEmail,
                     $item['name'], $item['size'], $item['quantity'], 
                     $item['price'], $item['subtotal'], $linkedMovieRef);
        $stmtFood->execute();
    }
}

// Generate items HTML for email (grouped by movie with linked food)
$itemsHtml = '';
$itemNum = 1;

foreach ($cartItems as $cartData) {
    $item = $cartData['item'];
    $cartIndex = $cartData['index'];
    
    if ($item['type'] === 'movie') {
        $seatsDisplay = implode(', ', $item['seats']);
        $itemRef = $movieBookingRefs[$cartIndex];
        
        // Movie ticket
        $itemsHtml .= "
        <div style='background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 10px; border-left: 5px solid #1565c0;'>
          <h4 style='color: #1565c0; margin: 0 0 15px 0; font-size: 1.2em;'>
            🎬 Movie Ticket #$itemNum - Booking Ref: $itemRef
          </h4>
          <div style='margin: 8px 0; color: #333;'>
            <span style='font-weight: bold; color: #0d47a1; display: inline-block; width: 100px;'>Movie:</span>
            <span>{$item['title']}</span>
          </div>
          <div style='margin: 8px 0; color: #333;'>
            <span style='font-weight: bold; color: #0d47a1; display: inline-block; width: 100px;'>Cinema:</span>
            <span>{$item['cinema']}</span>
          </div>
          <div style='margin: 8px 0; color: #333;'>
            <span style='font-weight: bold; color: #0d47a1; display: inline-block; width: 100px;'>Date:</span>
            <span>" . date('l, d F Y', strtotime($item['date'])) . "</span>
          </div>
          <div style='margin: 8px 0; color: #333;'>
            <span style='font-weight: bold; color: #0d47a1; display: inline-block; width: 100px;'>Time:</span>
            <span>" . date('g:i A', strtotime($item['time'])) . "</span>
          </div>
          <div style='margin: 8px 0; color: #333;'>
            <span style='font-weight: bold; color: #0d47a1; display: inline-block; width: 100px;'>Seats:</span>
            <span>$seatsDisplay</span>
          </div>
          <div style='margin: 8px 0; color: #333;'>
            <span style='font-weight: bold; color: #0d47a1; display: inline-block; width: 100px;'>Quantity:</span>
            <span>{$item['quantity']} ticket(s)</span>
          </div>
          <div style='margin: 8px 0; color: #333;'>
            <span style='font-weight: bold; color: #0d47a1; display: inline-block; width: 100px;'>Subtotal:</span>
            <span style='font-weight: bold;'>$" . number_format($item['subtotal'], 2) . "</span>
          </div>";
        
        // Find and add food items linked to THIS movie
        $linkedFoodHtml = '';
        foreach ($cartItems as $foodData) {
            $foodItem = $foodData['item'];
            $foodCartIndex = $foodData['index'];
            
            if ($foodItem['type'] === 'food' && isset($foodItem['linked_to_movie']) && $foodItem['linked_to_movie'] == $cartIndex) {
                $linkedFoodHtml .= "
                <div style='margin: 10px 0; padding: 10px; background: #fff3e0; border-radius: 5px;'>
                  <div style='color: #e65100; font-weight: bold; margin-bottom: 5px;'>🍿 {$foodItem['name']}</div>
                  <div style='font-size: 0.9em; color: #666;'>
                    Size: {$foodItem['size']} | Qty: {$foodItem['quantity']} | \$" . number_format($foodItem['subtotal'], 2) . "
                  </div>
                </div>";
            }
        }
        
        if (!empty($linkedFoodHtml)) {
            $itemsHtml .= "<div style='margin-top: 15px; padding-top: 10px; border-top: 2px dashed #ccc;'>
                          <div style='color: #ff9800; font-weight: bold; margin-bottom: 10px;'>Food & Beverages:</div>
                          $linkedFoodHtml
                          </div>";
        }
        
        $itemsHtml .= "</div>";
        $itemNum++;
    }
}

// Store booking in session
$_SESSION['booking'] = [
    'reference' => $mainBookingRef,
    'all_references' => $allBookingRefs,
    'movie_booking_refs' => $movieBookingRefs,
    'customer_name' => $customerName,
    'customer_email' => $customerEmail,
    'customer_phone' => $customerPhone,
    'items' => $cartItems, 
    'subtotal' => $totalAmount - $bookingFee,
    'booking_fee' => $bookingFee,
    'total' => $totalAmount,
    'booking_date' => date('Y-m-d H:i:s')
];

// Send Email
$to = $customerEmail;
$subject = "IE Theatre - Your Movie E-Tickets (Order: $mainBookingRef)";

$message = "
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
  .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
  .header { background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%); color: white; padding: 30px 20px; text-align: center; }
  .header h1 { margin: 0; font-size: 2em; }
  .section { padding: 20px 30px; border-bottom: 1px solid #e0e0e0; }
  .section h3 { color: #1565c0; margin: 0 0 15px 0; font-size: 1.3em; }
  .info-row { margin: 8px 0; color: #333; }
  .label { font-weight: bold; color: #0d47a1; display: inline-block; width: 100px; }
  .summary { padding: 20px 30px; background: #f8f9fa; }
  .summary-row { display: flex; justify-content: space-between; padding: 10px 0; font-size: 1.1em; }
  .total-row { border-top: 2px solid #1565c0; padding-top: 15px; margin-top: 10px; font-size: 1.3em; font-weight: bold; color: #1565c0; }
  .important { background: #fff3cd; padding: 15px; margin: 20px 30px; border-left: 4px solid #ffa726; color: #856404; }
</style>
</head>
<body>
<div class='container'>
  <div class='header'>
    <h1>🎬 IE Theatre E-Tickets</h1>
    <p style='margin: 10px 0 0 0; font-size: 1.1em;'>Order Reference: <strong>$mainBookingRef</strong></p>
  </div>
  
  <div class='section'>
    <h3>Customer Information</h3>
    <div class='info-row'><span class='label'>Name:</span> $customerName</div>
    <div class='info-row'><span class='label'>Email:</span> $customerEmail</div>
    <div class='info-row'><span class='label'>Phone:</span> $customerPhone</div>
  </div>
  
  <div class='section'>
    <h3>Your Order</h3>
    $itemsHtml
  </div>
  
  <div class='summary'>
    <h3 style='color: #1565c0; margin-top: 0;'>Payment Summary</h3>
    <div class='summary-row'><span>Subtotal:</span><span>$" . number_format($totalAmount - $bookingFee, 2) . "</span></div>
    <div class='summary-row'><span>Booking Fee:</span><span>$$bookingFee</span></div>
    <div class='summary-row total-row'><span>Total Paid:</span><span>$" . number_format($totalAmount, 2) . "</span></div>
  </div>
  
  <div class='important'>
    <strong>⚠️ Important:</strong> Please arrive 15 minutes before showtime. Present this email or your booking reference at the counter.
  </div>
</div>
</body>
</html>
";

$headers = 'From: noreply@ietheatre.com' . "\r\n" .
           'Reply-To: noreply@ietheatre.com' . "\r\n" .
           'X-Mailer: PHP/' . phpversion() . "\r\n" .
           'MIME-Version: 1.0' . "\r\n" .
           'Content-type: text/html; charset=utf-8';

$mailSent = @mail($to, $subject, $message, $headers, '-fnoreply@ietheatre.com');

if ($mailSent) {
    $_SESSION['email_sent'] = true;
    $_SESSION['email_status'] = "✅ Email sent successfully to $customerEmail";
} else {
    $_SESSION['email_sent'] = false;
    $_SESSION['email_status'] = "⚠️ Email sending attempted. Please check Mercury mail server.";
}

// Remove purchased items from cart
foreach (array_reverse($selectedIndices) as $index) {
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
    }
}
$_SESSION['cart'] = array_values($_SESSION['cart']);

header("Location: eticket.php");
exit();

$conn->close();
?>
