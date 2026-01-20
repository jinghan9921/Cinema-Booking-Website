<?php
session_start();

// Check if booking exists in session
if (!isset($_SESSION['booking'])) {
    header("Location: index.php");
    exit();
}

$booking = $_SESSION['booking'];
?>
<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="sidebarandfooter.css">

<title>E-Ticket - IE Theatre</title>
<style>
body { 
  background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
  font-family: Arial, sans-serif; 
  padding: 40px 20px;
  min-height: 100vh;
  margin: 0;
}

.ticket-container {
  max-width: 800px;
  margin: 0 auto;
  background: white;
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(7, 87, 184, 0.3);
  overflow: hidden;
  animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.success-banner {
  background: #4caf50;
  color: white;
  padding: 20px;
  text-align: center;
  font-size: 1.1em;
  font-weight: bold;
}

.ticket-header {
  background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
  color: white;
  padding: 40px;
  text-align: center;
}

.ticket-header h1 {
  margin: 0 0 10px;
  font-size: 2.5em;
}

.ticket-header p {
  margin: 5px 0;
  font-size: 1.1em;
}

.ticket-body {
  padding: 40px;
}

.section {
  margin: 25px 0;
  padding: 25px;
  background: #f8f9fa;
  border-radius: 12px;
  border-left: 5px solid #1565c0;
}

.section h3 {
  color: #1565c0;
  margin-top: 0;
  margin-bottom: 15px;
  font-size: 1.4em;
}

.info-row {
  display: flex;
  padding: 10px 0;
  border-bottom: 1px solid #e0e0e0;
}

.info-row:last-child {
  border-bottom: none;
}

.label {
  font-weight: bold;
  color: #0d47a1;
  width: 120px;
  flex-shrink: 0;
}

.value {
  color: #333;
  flex: 1;
}

/* Item card styling */
.item-card {
  background: #f8f9fa;
  border-left: 5px solid #1565c0;
  border-radius: 10px;
  padding: 20px;
  margin-bottom: 20px;
}

.item-card h4 {
  color: #1565c0;
  margin: 0 0 15px 0;
  font-size: 1.2em;
}

/* Food section nested in movie */
.food-nested {
  margin-top: 20px;
  padding-top: 15px;
  border-top: 2px dashed #ccc;
}

.food-nested h5 {
  color: #ff9800;
  margin: 0 0 10px 0;
  font-size: 1.1em;
}

.food-item-box {
  background: #fff3e0;
  padding: 12px;
  margin: 8px 0;
  border-radius: 8px;
  border-left: 3px solid #ff9800;
}

.food-item-box .food-name {
  color: #e65100;
  font-weight: bold;
  margin-bottom: 5px;
}

.food-item-box .food-details {
  font-size: 0.9em;
  color: #666;
}

.total-row {
  background: #e3f2fd;
  padding: 15px;
  border-radius: 8px;
  margin-top: 15px;
}

.total-row .label {
  font-size: 1.2em;
}

.total-row .value {
  color: #1565c0;
  font-size: 1.4em;
  font-weight: bold;
}

.action-buttons {
  display: flex;
  gap: 15px;
  justify-content: center;
  margin-top: 30px;
  flex-wrap: wrap;
}

.btn {
  padding: 15px 35px;
  border-radius: 10px;
  font-size: 16px;
  font-weight: bold;
  text-decoration: none;
  cursor: pointer;
  border: none;
  transition: all 0.3s ease;
  display: inline-block;
}

.btn-primary {
  background: linear-gradient(135deg, #0757b8 0%, #1565c0 100%);
  color: white;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #064a8a 0%, #0d47a1 100%);
  transform: translateY(-2px);
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background: #545b62;
}

@media print {
  body { background: white; }
  .action-buttons { display: none; }
  .success-banner { display: none; }
}

@media (max-width: 768px) {
  .info-row {
    flex-direction: column;
    gap: 5px;
  }
  .label {
    width: 100%;
  }
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="ticket-container">
  <div class="success-banner">
    ✅ Booking Confirmed! A copy has been sent to your email.
  </div>
  
  <div class="ticket-header">
    <h1>🎬 IE Theatre E-Tickets</h1>
    <p>Order Reference: <strong><?= htmlspecialchars($booking['reference']) ?></strong></p>
  </div>
  
  <div class="ticket-body">
    <!-- Customer Information -->
    <div class="section">
      <h3>Customer Information</h3>
      <div class="info-row">
        <span class="label">Name:</span>
        <span class="value"><?= htmlspecialchars($booking['customer_name']) ?></span>
      </div>
      <div class="info-row">
        <span class="label">Email:</span>
        <span class="value"><?= htmlspecialchars($booking['customer_email']) ?></span>
      </div>
      <div class="info-row">
        <span class="label">Phone:</span>
        <span class="value"><?= htmlspecialchars($booking['customer_phone']) ?></span>
      </div>
    </div>
    
    <!-- Your Order Section -->
    <div style="margin: 25px 0;">
      <h3 style="color: #1565c0; margin: 0 0 20px 0; font-size: 1.4em;">Your Order</h3>
      
      <?php 
      $items = $booking['items'];
      $ticketNum = 1;
      $movieBookingRefs = isset($booking['movie_booking_refs']) ? $booking['movie_booking_refs'] : [];
      
      // Loop through items and display movies with their linked food
      foreach ($booking['items'] as $itemData): 
        // Extract item and its original cart index
        $item = isset($itemData['item']) ? $itemData['item'] : $itemData;
        $originalCartIndex = isset($itemData['index']) ? $itemData['index'] : null;
        
        if ($item['type'] === 'movie'):
          $movieCartIndex = array_search($booking['all_references'][$ticketNum - 1] ?? '', $movieBookingRefs);
      ?>
          <!-- Movie Ticket Card -->
          <div class="item-card">
            <h4>
              🎬 Movie Ticket #<?= $ticketNum ?> - Booking Ref: <?= htmlspecialchars($booking['all_references'][$ticketNum - 1] ?? $booking['reference']) ?>
            </h4>
            
            <div class="info-row">
              <span class="label">Movie:</span>
              <span class="value"><?= htmlspecialchars($item['title']) ?></span>
            </div>
            <div class="info-row">
              <span class="label">Cinema:</span>
              <span class="value"><?= htmlspecialchars($item['cinema']) ?></span>
            </div>
            <div class="info-row">
              <span class="label">Date:</span>
              <span class="value"><?= date('l, d F Y', strtotime($item['date'])) ?></span>
            </div>
            <div class="info-row">
              <span class="label">Time:</span>
              <span class="value"><?= date('g:i A', strtotime($item['time'])) ?></span>
            </div>
            <div class="info-row">
              <span class="label">Seats:</span>
              <span class="value"><?= implode(', ', $item['seats']) ?></span>
            </div>
            <div class="info-row">
              <span class="label">Quantity:</span>
              <span class="value"><?= $item['quantity'] ?> ticket(s)</span>
            </div>
            <div class="info-row">
              <span class="label">Subtotal:</span>
              <span class="value" style="font-weight: bold;">$<?= number_format($item['subtotal'], 2) ?></span>
            </div>
            
            <?php
            // Find and display food items linked to movie
            $linkedFood = [];
            foreach ($booking['items'] as $foodData) {
                $foodItem = isset($foodData['item']) ? $foodData['item'] : $foodData;
                $foodOriginalIndex = isset($foodData['index']) ? $foodData['index'] : null;
                
                // Match food tomovie 
                if ($foodItem['type'] === 'food' && 
                    isset($foodItem['linked_to_movie']) && 
                    $foodItem['linked_to_movie'] == $originalCartIndex) {
                    $linkedFood[] = $foodItem;
                }
            }

            
            if (!empty($linkedFood)):
            ?>
            <div class="food-nested">
              <h5>🍿 Food & Beverages:</h5>
              <?php foreach ($linkedFood as $food): ?>
                <div class="food-item-box">
                  <div class="food-name"><?= htmlspecialchars($food['name']) ?></div>
                  <div class="food-details">
                    Size: <?= htmlspecialchars($food['size']) ?> | Qty: <?= $food['quantity'] ?> | $<?= number_format($food['subtotal'], 2) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
          </div>
          <?php $ticketNum++; ?>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <!-- Payment Summary -->
    <div class="section">
      <h3>Payment Summary</h3>
      <div class="info-row">
        <span class="label">Subtotal:</span>
        <span class="value">$<?= number_format($booking['subtotal'], 2) ?></span>
      </div>
      <div class="info-row">
        <span class="label">Booking Fee:</span>
        <span class="value">$<?= number_format($booking['booking_fee'], 2) ?></span>
      </div>
      <div class="info-row total-row">
        <span class="label">Total Paid:</span>
        <span class="value">$<?= number_format($booking['total'], 2) ?></span>
      </div>
    </div>
    
    <?php if (isset($_SESSION['email_status'])): ?>
    <div style="background: <?= $_SESSION['email_sent'] ? '#d4edda' : '#fff3cd' ?>; color: <?= $_SESSION['email_sent'] ? '#155724' : '#856404' ?>; padding: 20px; margin: 20px 0; border-radius: 10px; text-align: center; border-left: 5px solid <?= $_SESSION['email_sent'] ? '#28a745' : '#ffc107' ?>;">
        <strong>📧 Email Status:</strong><br>
        <?= htmlspecialchars($_SESSION['email_status']) ?>
    </div>
    <?php 
        unset($_SESSION['email_status']);
        unset($_SESSION['email_sent']);
    endif; ?>
    
    <div class="action-buttons">
      <button onclick="window.print()" class="btn btn-primary">🖨️ Print Ticket</button>
      <a href="index.php" class="btn btn-secondary">🏠 Back to Home</a>
    </div>
  </div>
</div>
<div id="footer">
  <span><a href="contact_careers.php#contact-us">Contact Us</a></span>
  <span><a href="contact_careers.php#career-opportunities">Career Opportunities</a></span>
  <span><a href="admin_dashboard.php">Admin Panel</a></span>
  <div class="cr">Copyright &copy; IE4727WebDev</div>
</div>
</body>
</html>
