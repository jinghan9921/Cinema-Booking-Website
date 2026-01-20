<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'moviedb');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Cleanup expired cart items and release seats
if (!empty($_SESSION['cart'])) {
    $now = time();
    foreach ($_SESSION['cart'] as $key => $item) {
        if (isset($item['expires_at']) && $now > $item['expires_at']) {
            if ($item['type'] === 'movie') {
                foreach ($item['seats'] as $seat) {
                    $releaseSQL = "UPDATE seats SET reserved_until = NULL, reserved_by_session = NULL WHERE CinemaID = ? AND TheatreID = ? AND ShowTimeID = ? AND SeatNumber = ? AND IsBooked = 0";
                    $stmt = $conn->prepare($releaseSQL);
                    $stmt->bind_param("iiis", $item['cinema_id'], $item['theatre_id'], $item['showtime_id'], $seat);
                    $stmt->execute();
                }
            }
            unset($_SESSION['cart'][$key]);
        }
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']);
}

// Check if user has at least one movie ticket in cart
$hasMovieTicket = false;
foreach ($_SESSION['cart'] as $cartItem) {
    if ($cartItem['type'] === 'movie') {
        $hasMovieTicket = true;
        break;
    }
}

// Remove food items with unlinked movies
if (!empty($_SESSION['cart'])) {
    $itemsToRemove = [];
    foreach ($_SESSION['cart'] as $idx => $cartItem) {
        if ($cartItem['type'] === 'food' && isset($cartItem['linked_to_movie'])) {
            $linkedMovieExists = isset($_SESSION['cart'][$cartItem['linked_to_movie']]) && $_SESSION['cart'][$cartItem['linked_to_movie']]['type'] === 'movie';
            if (!$linkedMovieExists) $itemsToRemove[] = $idx;
        }
    }
    if (!empty($itemsToRemove)) {
        rsort($itemsToRemove);
        foreach ($itemsToRemove as $idx) unset($_SESSION['cart'][$idx]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
}

// Remove item from cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    $itemIndex = intval($_POST['item_index']);
    if (isset($_SESSION['cart'][$itemIndex])) {
        unset($_SESSION['cart'][$itemIndex]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
    header("Location: FnB.php?removed=1");
    exit();
}

// Add food to cart with validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $hasTicket = false;
    foreach ($_SESSION['cart'] as $cartItem) {
        if ($cartItem['type'] === 'movie') {
            $hasTicket = true;
            break;
        }
    }
    if (!isset($_POST['link_to_movie']) || $_POST['link_to_movie'] === '') {
        header("Location: FnB.php?error=no_movie_link");
        exit();
    }
    if (!$hasTicket) {
        header("Location: FnB.php?error=no_ticket");
        exit();
    }

    $foodName = htmlspecialchars($_POST['item_name']);
    $foodSize = htmlspecialchars($_POST['size']);
    $foodQuantity = intval($_POST['quantity']);
    $foodPrice = floatval($_POST['price']);
    $linkedToMovie = intval($_POST['link_to_movie']);

    $flavorDesc = '';
    if (!empty($_POST['popcorn_flavor']) && strtolower($_POST['popcorn_flavor']) !== 'select') {
        $flavorDesc = htmlspecialchars($_POST['popcorn_flavor']);
    }
    if (!empty($_POST['drink_flavor']) && strtolower($_POST['drink_flavor']) !== 'select') {
        $flavorDesc .= (!empty($flavorDesc) ? ' + ' : '') . htmlspecialchars($_POST['drink_flavor']);
    }
    if (!empty($_POST['others_flavor']) && strtolower($_POST['others_flavor']) !== 'select') {
        $flavorDesc .= (!empty($flavorDesc) ? ' + ' : '') . htmlspecialchars($_POST['others_flavor']);
    }

    $displayName = $foodName;
    if (!empty($foodSize)) $displayName .= ' - ' . $foodSize;
    if (!empty($flavorDesc)) $displayName .= ' - ' . $flavorDesc;

    $_SESSION['cart'][] = [
        'type' => 'food',
        'item_id' => intval($_POST['item_id']),
        'name' => $displayName,
        'base_name' => $foodName,
        'size' => $foodSize,
        'flavor' => $flavorDesc,
        'quantity' => $foodQuantity,
        'price' => $foodPrice,
        'subtotal' => $foodPrice * $foodQuantity,
        'linked_to_movie' => $linkedToMovie,
        'added_at' => time()
    ];

    header("Location: FnB.php?added=1&item=" . urlencode($displayName));
    exit();
}

// Prepare UI messages
$showAddMessage = isset($_GET['added']) && $_GET['added'] == '1';
$showRemoveMessage = isset($_GET['removed']) && $_GET['removed'] == '1';
$showNoTicketError = isset($_GET['error']) && $_GET['error'] == 'no_ticket';
$showNoMovieLinkError = isset($_GET['error']) && $_GET['error'] == 'no_movie_link';
$itemAdded = isset($_GET['item']) ? htmlspecialchars($_GET['item']) : 'Item';

$sql = "SELECT * FROM items ORDER BY id";
$result = $conn->query($sql);

include 'sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="stylesheet" href="sidebarandfooter.css">
  <title>Food & Beverages - IE Theatre</title>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    /* Your full inline CSS from your original file goes here */
    * { box-sizing: border-box; }
    html, body { min-height: 100%; }
    body {
      background: #191a1c;
      margin: 0;
      font-family: 'Segoe UI', Arial, sans-serif;
      color: #eee;
      padding: 20px 450px 150px 220px;
    }
    .side-cart {
      position: fixed;
      right: 0; top: 0;
      width: 420px; height: 100vh;
      background: #2a2d35;
      box-shadow: -4px 0 15px rgba(0,0,0,0.5);
      overflow-y: auto;
      z-index: 999;
      padding: 20px;
    }
    /* ... and so on for all your CSS as before ... */

    .cart-header {
      font-size: 1.5em;
      color: #ffa726;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 2px solid #444;
    }
    .cart-item {
      background: rgba(0, 0, 0, 0.3);
      padding: 12px;
      margin-bottom: 12px;
      border-radius: 6px;
      border-left: 3px solid #28a745;
    }
    .cart-item-name {
      font-weight: bold;
      color: #28a745;
      margin-bottom: 5px;
    }
    .cart-item-details {
      font-size: 0.85em;
      color: #ccc;
      margin-bottom: 8px;
    }
    .cart-item-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .cart-item-price {
      color: #28a745;
      font-weight: bold;
    }
    .btn-remove {
      background: #dc3545;
      color: white;
      border: none;
      padding: 4px 10px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.8em;
    }
    .btn-remove:hover {
      background: #c82333;
    }
    .cart-empty {
      text-align: center;
      padding: 40px 20px;
      color: #666;
    }
    .cart-total {
      margin-top: 20px;
      padding-top: 15px;
      border-top: 2px solid #444;
      font-size: 1.2em;
      color: #28a745;
      font-weight: bold;
      text-align: right;
    }
    .btn-checkout {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #e96b39 0%, #ffa726 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1.1em;
      font-weight: bold;
      cursor: pointer;
      text-decoration: none;
      display: block;
      text-align: center;
      margin-top: 15px;
      transition: all 0.3s;
    }
    .btn-checkout:hover {
      background: linear-gradient(135deg, #d45a28 0%, #ff9100 100%);
      transform: translateY(-2px);
    }
    .container {
      max-width: 1200px;
      margin: 0 auto;
    }
    h1 {
      color: #e96b39;
      text-align: center;
      font-size: 2.5em;
      margin-bottom: 10px;
    }
    .subtitle {
      text-align: center;
      color: #999;
      margin-bottom: 30px;
    }
    .message {
      max-width: 600px;
      margin: 20px auto;
      padding: 15px 20px;
      border-radius: 8px;
      text-align: center;
      font-weight: bold;
    }
    .message.success {
      background: #d4edda;
      color: #155724;
      border: 2px solid #c3e6cb;
    }
    .message.info {
      background: #fff3cd;
      color: #856404;
      border: 2px solid #ffeaa7;
    }
    .items-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 25px;
    }
    .item-card {
      background: #2a2d35;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      transition: transform 0.3s;
    }
    .item-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(233, 107, 57, 0.3);
    }
    .item-image {
      width: 100%;
      height: 200px;
      object-fit: contain;
      cursor: pointer;
      background: #1a1c20;
    }
    .item-image:hover {
      transform: scale(0.95);
    }
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
    .item-content {
      padding: 15px;
    }
    .item-name {
      font-size: 1.3em;
      color: #ffa726;
      margin: 0 0 12px 0;
      font-weight: bold;
      text-align: center;
    }
    .item-details {
      display: none;
      background: rgba(0,0,0,0.3);
      padding: 15px;
      margin-top: 10px;
      border-radius: 8px;
      border: 2px solid #ffa726;
    }
    .item-details.active {
      display: block;
    }
    .section-title {
      color: #ffa726;
      font-weight: bold;
      margin-bottom: 10px;
      font-size: 0.95em;
    }
    .size-options, .flavor-options {
      margin-bottom: 15px;
    }
    .size-option, .flavor-option {
      background: rgba(0,0,0,0.3);
      padding: 10px;
      margin-bottom: 8px;
      border-radius: 6px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border: 2px solid transparent;
      cursor: pointer;
      transition: all 0.3s;
    }
    .size-option:hover, .flavor-option:hover {
      border-color: #ffa726;
    }
    .size-option.selected, .flavor-option.selected {
      border-color: #ffa726;
      background: rgba(255, 167, 38, 0.1);
    }
    .size-name, .flavor-name {
      color: #ffa726;
      font-weight: bold;
    }
    .size-price {
      color: #28a745;
      font-size: 0.9em;
    }
    .quantity-control {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 15px;
    }
    .btn-qty {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      border: 2px solid #ffa726;
      background: transparent;
      color: #ffa726;
      font-size: 1.2em;
      cursor: pointer;
      transition: all 0.3s;
    }
    .btn-qty:hover {
      background: #ffa726;
      color: #fff;
    }
    .qty-display {
      min-width: 40px;
      text-align: center;
      font-weight: bold;
      color: #fff;
      font-size: 1.1em;
    }
    .btn-add-to-cart {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1.1em;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s;
    }
    .btn-add-to-cart:hover {
      background: linear-gradient(135deg, #218838 0%, #1ba87e 100%);
      transform: translateY(-2px);
    }
    #footer {
      right: 420px !important;
    }
    @media (max-width: 1024px) {
      body {
        padding: 20px;
      }
      .side-cart {
        display: none;
      }
      #footer {
        right: 0 !important;
      }
    }
  </style>
</head>
<body>
<div class="side-cart">
  <div class="cart-header">🛒 Your Cart</div>
  <?php if (!empty($_SESSION['cart'])): $total = 0; foreach ($_SESSION['cart'] as $idx => $cartItem): if ($cartItem['type'] === 'food'): $total += $cartItem['subtotal']; ?>
    <div class="cart-item">
      <div class="cart-item-name"><?= htmlspecialchars($cartItem['name']) ?></div>
      <div class="cart-item-details">
        <?= htmlspecialchars($cartItem['size']) ?><?= !empty($cartItem['flavor']) ? ' - ' . htmlspecialchars($cartItem['flavor']) : '' ?> × <?= $cartItem['quantity'] ?>
      </div>
      <?php if (isset($cartItem['linked_to_movie']) && isset($_SESSION['cart'][$cartItem['linked_to_movie']])): ?>
        <?php $linkedMovie = $_SESSION['cart'][$cartItem['linked_to_movie']]; ?>
        <div style="background: rgba(76,175,80,0.15); padding: 10px; margin-top: 8px; border-radius: 5px; border-left: 3px solid #4CAF50;">
          <div style="color: #4CAF50; font-size: 0.8em; font-weight: bold; margin-bottom: 5px;">🎬 LINKED TO:</div>
          <div style="color: #fff; font-size: 1em; font-weight: 600; line-height: 1.4;"><?= htmlspecialchars($linkedMovie['title']) ?></div>
          <div style="color: #aaa; font-size: 0.9em; margin-top: 4px;"><?= date('D, M j, Y', strtotime($linkedMovie['date'])) ?></div>
          <div style="color: #4CAF50; font-size: 0.95em; margin-top: 2px; font-weight: 600;"><?= date('g:i A', strtotime($linkedMovie['time'])) ?></div>
        </div>
      <?php endif; ?>
      <div class="cart-item-footer" style="margin-top: 10px;">
        <span class="cart-item-price">$<?= number_format($cartItem['subtotal'], 2) ?></span>
        <form method="POST" style="margin:0;">
          <input type="hidden" name="item_index" value="<?= $idx ?>">
          <button type="submit" name="remove_item" class="btn-remove">×</button>
        </form>
      </div>
    </div>
  <?php endif; endforeach; ?>
  <div class="cart-total">Total: $<?= number_format($total, 2) ?></div>
  <a href="checkout.php" class="btn-checkout">Proceed to Checkout</a>
  <?php else: ?>
    <div class="cart-empty">Cart is empty</div>
  <?php endif; ?>
</div>

<div class="container">
  <h1>🍿 Food & Beverages</h1>
  <p class="subtitle">Enhance your movie experience!</p>
  <?php if ($showNoTicketError): ?>
    <div class="message" style="background:#f8d7da;color:#721c24;border:2px solid #f5c6cb;">
      ⚠️ You must have at least one movie ticket in your cart before ordering food & beverages!
      <br><a href="index.php" style="color:#721c24;text-decoration:underline;font-weight:bold;">Browse Movies</a>
    </div>
  <?php endif; ?>
  <?php if ($showNoMovieLinkError): ?>
    <div class="message" style="background:#f8d7da;color:#721c24;border:2px solid #f5c6cb;">
      ⚠️ Please select an item in the list!
    </div>
  <?php endif; ?>
  <?php if ($showAddMessage): ?><div class="message success">✅ <?= $itemAdded ?> added to cart!</div><?php endif; ?>
  <?php if ($showRemoveMessage): ?><div class="message info">🗑️ Item removed</div><?php endif; ?>

  <div class="items-grid">
    <?php if ($result && $result->num_rows > 0): while ($item = $result->fetch_assoc()):
      $sizesQuery = $conn->prepare("SELECT * FROM sizes WHERE item_id = ? AND (sold_out IS NULL OR sold_out = 0) ORDER BY price");
      $sizesQuery->bind_param("i", $item['id']);
      $sizesQuery->execute();
      $sizesResult = $sizesQuery->get_result();

      $popcornFlavorsQuery = $conn->prepare("SELECT * FROM flavors WHERE item_id = ? AND LOWER(flavor_type) = 'popcorn' AND (sold_out IS NULL OR sold_out = 0) ORDER BY flavor_name");
      $popcornFlavorsQuery->bind_param("i", $item['id']);
      $popcornFlavorsQuery->execute();
      $popcornFlavorsResult = $popcornFlavorsQuery->get_result();

      $drinkFlavorsQuery = $conn->prepare("SELECT * FROM flavors WHERE item_id = ? AND LOWER(flavor_type) = 'drink' AND (sold_out IS NULL OR sold_out = 0) ORDER BY flavor_name");
      $drinkFlavorsQuery->bind_param("i", $item['id']);
      $drinkFlavorsQuery->execute();
      $drinkFlavorsResult = $drinkFlavorsQuery->get_result();

      $othersFlavorsQuery = $conn->prepare("SELECT * FROM flavors WHERE item_id = ? AND LOWER(flavor_type) NOT IN ('popcorn','drink') AND (sold_out IS NULL OR sold_out = 0) ORDER BY flavor_name");
      $othersFlavorsQuery->bind_param("i", $item['id']);
      $othersFlavorsQuery->execute();
      $othersFlavorsResult = $othersFlavorsQuery->get_result();

      $isSoldOut = isset($item['sold_out']) && $item['sold_out'] == 1;
    ?>
      <div class="item-card <?= ($isSoldOut || !$hasMovieTicket) ? 'sold-out-card' : '' ?>">
        <?php if (!empty($item['Image'])): ?>
          <img src="<?= htmlspecialchars($item['Image']) ?>" alt="<?= htmlspecialchars($item['Name']) ?>" class="item-image" style="<?= ($isSoldOut || !$hasMovieTicket) ? 'cursor: not-allowed; opacity: 0.5; filter: grayscale(100%);' : '' ?>" onclick="<?= (!$isSoldOut && $hasMovieTicket) ? 'toggleDetails(' . $item['id'] . ')' : '' ?>">
        <?php else: ?>
          <div class="item-image" style="background:#444; display:flex; align-items:center; justify-content:center; color:#999; <?= ($isSoldOut || !$hasMovieTicket) ? 'opacity: 0.5; cursor: not-allowed;' : '' ?>" onclick="<?= (!$isSoldOut && $hasMovieTicket) ? 'toggleDetails(' . $item['id'] . ')' : '' ?>">
            📦 No Image
          </div>
        <?php endif; ?>
        <?php if ($isSoldOut): ?>
          <div class="sold-out-overlay">
            <span class="sold-out-badge">🚫 SOLD OUT</span>
          </div>
        <?php elseif (!$hasMovieTicket): ?>
          <div class="sold-out-overlay">
            <span class="sold-out-badge" style="background: rgba(255, 152, 0, 0.95);">🎬 Buy Ticket First</span>
          </div>
        <?php endif; ?>
        <div class="item-content">
          <h3 class="item-name"><?= htmlspecialchars($item['Name'] ?? 'Item') ?></h3>
          <?php if (!$isSoldOut && $hasMovieTicket): ?>
          <div class="item-details" id="details-<?= $item['id'] ?>">
            <form method="POST" id="form-<?= $item['id'] ?>" onsubmit="return validateForm('form-<?= $item['id'] ?>');">
              <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
              <input type="hidden" name="item_name" value="<?= htmlspecialchars($item['Name']) ?>">
              <input type="hidden" name="size" id="size-<?= $item['id'] ?>">
              <input type="hidden" name="popcorn_flavor" id="popcorn-flavor-<?= $item['id'] ?>">
              <input type="hidden" name="drink_flavor" id="drink-flavor-<?= $item['id'] ?>">
              <input type="hidden" name="others_flavor" id="others-flavor-<?= $item['id'] ?>">
              <input type="hidden" name="quantity" id="qty-<?= $item['id'] ?>" value="1">
              <input type="hidden" name="price" id="price-<?= $item['id'] ?>">

              <div class="section-title">🎬 Link to Movie Ticket:</div>
              <select name="link_to_movie" style="width:100%;padding:10px; background: rgba(0,0,0,0.3); color: #fff; border: 2px solid #444; border-radius: 6px; margin-bottom: 15px; cursor: pointer; font-size: 0.95em;">
                <option value="">— Select Movie Ticket —</option>
                <?php foreach ($_SESSION['cart'] as $idx => $cartItem): if ($cartItem['type'] === 'movie'):
                    $movieDate = isset($cartItem['date']) ? date('l, F j, Y', strtotime($cartItem['date'])) : '';
                    $movieTime = isset($cartItem['time']) ? date('g:i A', strtotime($cartItem['time'])) : '';
                    $displayText = htmlspecialchars($cartItem['title']);
                    if ($movieDate && $movieTime)
                        $displayText .= " ({$movieDate} @ {$movieTime})";
                    elseif ($movieTime)
                        $displayText .= " - {$movieTime}";
                ?>
                    <option value="<?= $idx ?>"><?= $displayText ?></option>
                <?php endif; endforeach; ?>
              </select>

              <?php if ($sizesResult->num_rows > 0): ?>
              <div class="section-title">Select Size:</div>
              <div class="size-options">
                <?php $firstSize = true; while ($size = $sizesResult->fetch_assoc()): ?>
                  <div class="size-option <?= $firstSize ? 'selected' : '' ?>" onclick="selectSize(<?= $item['id'] ?>, '<?= htmlspecialchars($size['size']) ?>', <?= $size['price'] ?>)">
                    <div class="size-info">
                      <div class="size-name"><?= htmlspecialchars($size['size']) ?></div>
                      <div class="size-price">$<?= number_format($size['price'], 2) ?></div>
                    </div>
                  </div>
                <?php $firstSize = false; endwhile; ?>
              </div>
              <?php endif; ?>

              <?php if ($popcornFlavorsResult->num_rows > 0): ?>
              <div class="section-title">Select Popcorn Flavor:</div>
              <div class="flavor-options">
                <?php $firstPopcorn = true; while ($popcorn = $popcornFlavorsResult->fetch_assoc()): ?>
                  <div class="flavor-option <?= $firstPopcorn ? 'selected' : '' ?>" onclick="selectPopcornFlavor(<?= $item['id'] ?>, '<?= htmlspecialchars($popcorn['flavor_name']) ?>')">
                    <div class="flavor-info"><div class="flavor-name"><?= htmlspecialchars($popcorn['flavor_name']) ?></div></div>
                  </div>
                <?php $firstPopcorn = false; endwhile; ?>
              </div>
              <?php endif; ?>

              <?php if ($drinkFlavorsResult->num_rows > 0): ?>
              <div class="section-title">Select Drink Flavor:</div>
              <div class="flavor-options">
                <?php while ($drink = $drinkFlavorsResult->fetch_assoc()): ?>
                  <div class="flavor-option" onclick="selectDrinkFlavor(<?= $item['id'] ?>, '<?= htmlspecialchars($drink['flavor_name']) ?>')">
                    <div class="flavor-info"><div class="flavor-name"><?= htmlspecialchars($drink['flavor_name']) ?></div></div>
                  </div>
                <?php endwhile; ?>
              </div>
              <?php endif; ?>

              <?php if ($othersFlavorsResult->num_rows > 0): ?>
              <div class="section-title">Select Flavor:</div>
              <div class="flavor-options">
                <?php $firstOthers = true; while ($others = $othersFlavorsResult->fetch_assoc()): ?>
                  <div class="flavor-option <?= $firstOthers ? 'selected' : '' ?>" onclick="selectOthersFlavor(<?= $item['id'] ?>, '<?= htmlspecialchars($others['flavor_name']) ?>')">
                    <div class="flavor-info"><div class="flavor-name"><?= htmlspecialchars($others['flavor_name']) ?></div></div>
                  </div>
                <?php $firstOthers = false; endwhile; ?>
              </div>
              <?php endif; ?>

              <div class="section-title">Quantity:</div>
              <div class="quantity-control">
                <button type="button" class="btn-qty" onclick="changeQty(<?= $item['id'] ?>, -1)">−</button>
                <span class="qty-display" id="qty-display-<?= $item['id'] ?>">1</span>
                <button type="button" class="btn-qty" onclick="changeQty(<?= $item['id'] ?>, 1)">+</button>
              </div>

              <button type="submit" name="add_to_cart" class="btn-add-to-cart">Add to Cart</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; else: ?>
      <p style="grid-column: 1/-1; text-align:center; color:#666;">No items available</p>
    <?php endif; ?>
  </div>
</div>
<?php include 'footer.php'; ?>

<script>
var openDetails = null;
function toggleDetails(id) {
    var details = document.getElementById('details-' + id);
    if (openDetails === id) {
        details.classList.remove('active');
        openDetails = null;
        return;
    }
    if (openDetails !== null) {
        var prevDetails = document.getElementById('details-' + openDetails);
        if (prevDetails) prevDetails.classList.remove('active');
    }
    details.classList.add('active');
    openDetails = id;
    var selectedSize = details.querySelector('.size-option.selected');
    if (selectedSize) {
        var sizeName = selectedSize.querySelector('.size-name').textContent;
        var priceText = selectedSize.querySelector('.size-price').textContent.replace('$', '').trim();
        document.getElementById('size-' + id).value = sizeName;
        document.getElementById('price-' + id).value = priceText;
    }
    var allSections = details.querySelectorAll('.flavor-options');
    allSections.forEach(function (section, index) {
        var selectedOption = section.querySelector('.flavor-option.selected');
        if (selectedOption) {
            var flavorName = selectedOption.querySelector('.flavor-name').textContent;
            if (index === 0) document.getElementById('popcorn-flavor-' + id).value = flavorName;
            else if (index === 1) document.getElementById('drink-flavor-' + id).value = flavorName;
            else document.getElementById('others-flavor-' + id).value = flavorName;
        }
    });
}

function selectSize(itemId, size, price) {
    var form = document.getElementById('form-' + itemId);
    var options = form.querySelectorAll('.size-option');
    options.forEach((opt) => opt.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    document.getElementById('size-' + itemId).value = size;
    document.getElementById('price-' + itemId).value = price;
}
function selectPopcornFlavor(itemId, flavor) {
    var clickedOption = event.currentTarget;
    var section = clickedOption.closest('.flavor-options');
    section.querySelectorAll('.flavor-option').forEach((opt) => opt.classList.remove('selected'));
    clickedOption.classList.add('selected');
    document.getElementById('popcorn-flavor-' + itemId).value = flavor;
}

function selectDrinkFlavor(itemId, flavor) {
    var clickedOption = event.currentTarget;
    var section = clickedOption.closest('.flavor-options');
    section.querySelectorAll('.flavor-option').forEach((opt) => opt.classList.remove('selected'));
    clickedOption.classList.add('selected');
    document.getElementById('drink-flavor-' + itemId).value = flavor;
}

function selectOthersFlavor(itemId, flavor) {
    var clickedOption = event.currentTarget;
    var section = clickedOption.closest('.flavor-options');
    section.querySelectorAll('.flavor-option').forEach((opt) => opt.classList.remove('selected'));
    clickedOption.classList.add('selected');
    document.getElementById('others-flavor-' + itemId).value = flavor;
}

function changeQty(itemId, delta) {
    var qtyInput = document.getElementById('qty-' + itemId);
    var qtyDisplay = document.getElementById('qty-display-' + itemId);
    var currentQty = parseInt(qtyInput.value);
    var newQty = Math.max(1, Math.min(10, currentQty + delta));
    qtyInput.value = newQty;
    qtyDisplay.textContent = newQty;
}

function validateForm(formId) {
    var form = document.getElementById(formId);
    var movieSelect = form.querySelector('select[name="link_to_movie"]');
    if (!movieSelect.value) {
        alert("Please select an item in the list.");
        movieSelect.focus();
        return false;
    }
    return true;
}
</script>
</body>
</html>
<?php $conn->close(); ?>
