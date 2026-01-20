<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'moviedb');
if ($conn->connect_error) die("Connection failed");

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = intval($_POST['item_id']);
    $itemName = $_POST['item_name'];
    $size = $_POST['size'];
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);
    
    // ✅ Add food item with consistent structure
    $_SESSION['cart'][] = [
        'type' => 'food',
        'item_id' => $itemId,
        'name' => $itemName,
        'size' => $size,
        'price' => $price,
        'quantity' => $quantity,
        'subtotal' => $price * $quantity,
        'added_at' => time()
    ];
    
    header("Location: FnB.php?added=1&item=" . urlencode($itemName));
    exit();
}
?>
