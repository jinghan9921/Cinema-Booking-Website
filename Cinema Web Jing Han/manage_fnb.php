<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'moviedb');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin_dashboard.php");
    exit();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_dashboard.php");
    exit();
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'items';

function uploadFile($fileInputName, $targetFolder) {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == UPLOAD_ERR_OK) {
        // Create directory if it doesn't exist
        if (!file_exists($targetFolder)) {
            mkdir($targetFolder, 0777, true);
        }
        
        $filename = basename($_FILES[$fileInputName]['name']);
        $targetPath = $targetFolder . $filename;
        
        if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetPath)) {
            return $targetPath;
        }
    }
    return null;
}

// ITEMS HANDLERS
if (isset($_POST['add_item'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $image = uploadFile('image', 'images/');
    $image_escaped = $image ? $conn->real_escape_string($image) : '';
    $conn->query("INSERT INTO items (Name, Image) VALUES ('$name', '$image_escaped')");
    header("Location: manage_fnb.php?tab=items");
    exit;
}

if (isset($_POST['update_item'])) {
    $id = intval($_POST['item_id']);
    $name = $conn->real_escape_string($_POST['name']);
    
    // Check if new image was uploaded
    $newImage = uploadFile('image', 'images/');
    
    if ($newImage) {
        // New image uploaded - update both name and image
        $newImage_escaped = $conn->real_escape_string($newImage);
        $conn->query("UPDATE items SET Name='$name', Image='$newImage_escaped' WHERE id=$id");
    } else {
        // No new image - only update name
        $conn->query("UPDATE items SET Name='$name' WHERE id=$id");
    }
    
    header("Location: manage_fnb.php?tab=items#edit-form");
    exit;
}

if (isset($_GET['delete_item'])) {
    $id = intval($_GET['delete_item']);
    $conn->query("DELETE FROM sizes WHERE item_id=$id");
    $conn->query("DELETE FROM flavors WHERE item_id=$id");
    $conn->query("DELETE FROM items WHERE id=$id");
    header("Location: manage_fnb.php?tab=items");
    exit;
}

if (isset($_GET['toggle_item_soldout'])) {
    $id = intval($_GET['toggle_item_soldout']);
    $conn->query("UPDATE items SET sold_out = NOT COALESCE(sold_out, 0) WHERE id=$id");
    header("Location: manage_fnb.php?tab=items");
    exit;
}

// SIZES HANDLERS
if (isset($_POST['add_size'])) {
    $item_id = intval($_POST['item_id']);
    $size = $conn->real_escape_string($_POST['size']);
    $price = floatval($_POST['price']);
    $conn->query("INSERT INTO sizes (item_id, size, price) VALUES ($item_id, '$size', $price)");
    header("Location: manage_fnb.php?tab=sizes");
    exit;
}

if (isset($_POST['update_size'])) {
    $id = intval($_POST['size_id']);
    $item_id = intval($_POST['item_id']);
    $size = $conn->real_escape_string($_POST['size']);
    $price = floatval($_POST['price']);
    $conn->query("UPDATE sizes SET item_id=$item_id, size='$size', price=$price WHERE size_id=$id");
    header("Location: manage_fnb.php?tab=sizes#edit-form");
    exit;
}

if (isset($_GET['delete_size'])) {
    $id = intval($_GET['delete_size']);
    $conn->query("DELETE FROM sizes WHERE size_id=$id");
    header("Location: manage_fnb.php?tab=sizes");
    exit;
}

if (isset($_GET['toggle_size_soldout'])) {
    $id = intval($_GET['toggle_size_soldout']);
    $conn->query("UPDATE sizes SET sold_out = NOT COALESCE(sold_out, 0) WHERE size_id=$id");
    header("Location: manage_fnb.php?tab=sizes");
    exit;
}

// FLAVORS HANDLERS
if (isset($_POST['add_flavor'])) {
    $item_id = intval($_POST['item_id']);
    $flavor_name = $conn->real_escape_string($_POST['flavor_name']);
    $flavor_type = strtolower($conn->real_escape_string($_POST['flavor_type']));
    $conn->query("INSERT INTO flavors (item_id, flavor_name, flavor_type) VALUES ($item_id, '$flavor_name', '$flavor_type')");
    header("Location: manage_fnb.php?tab=flavors");
    exit;
}

if (isset($_POST['update_flavor'])) {
    $id = intval($_POST['flavor_id']);
    $item_id = intval($_POST['item_id']);
    $flavor_name = $conn->real_escape_string($_POST['flavor_name']);
    $flavor_type = strtolower($conn->real_escape_string($_POST['flavor_type']));
    $conn->query("UPDATE flavors SET item_id=$item_id, flavor_name='$flavor_name', flavor_type='$flavor_type' WHERE id=$id");
    header("Location: manage_fnb.php?tab=flavors#edit-form");
    exit;
}

if (isset($_GET['delete_flavor'])) {
    $id = intval($_GET['delete_flavor']);
    $conn->query("DELETE FROM flavors WHERE id=$id");
    header("Location: manage_fnb.php?tab=flavors");
    exit;
}

if (isset($_GET['toggle_flavor_soldout'])) {
    $id = intval($_GET['toggle_flavor_soldout']);
    $conn->query("UPDATE flavors SET sold_out = NOT COALESCE(sold_out, 0) WHERE id=$id");
    header("Location: manage_fnb.php?tab=flavors");
    exit;
}

$items_list = $conn->query("SELECT * FROM items ORDER BY Name");
$edit_item = isset($_GET['edit_item']) ? $conn->query("SELECT * FROM items WHERE id=" . intval($_GET['edit_item']))->fetch_assoc() : null;
$edit_size = isset($_GET['edit_size']) ? $conn->query("SELECT * FROM sizes WHERE size_id=" . intval($_GET['edit_size']))->fetch_assoc() : null;
$edit_flavor = isset($_GET['edit_flavor']) ? $conn->query("SELECT * FROM flavors WHERE id=" . intval($_GET['edit_flavor']))->fetch_assoc() : null;

include 'sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="stylesheet" href="sidebarandfooter.css">
  <title>Manage F&B - Admin Panel</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body { 
      background: #191a1c; 
      font-family: 'Segoe UI', Arial, sans-serif; 
      color: #eee; margin: 0; 
    }
    #content { 
      max-width: 1700px; 
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
    
    /* Tab Navigation */
    .tab-nav { 
      display: flex; 
      gap: 10px; 
      margin: 25px 0; 
      flex-wrap: wrap; 
    }
    .tab-btn { 
      padding: 12px 24px; 
      background: #2a2d35; 
      color: #ffa726; 
      border: 2px solid #ffa726; 
      border-radius: 8px; 
      cursor: pointer; 
      text-decoration: none; 
      font-weight: bold; 
      transition: all 0.3s; 
    }
    .tab-btn:hover, .tab-btn.active { 
      background: #ffa726; 
      color: #1a1d23; 
    }
    
    .content-section { 
      background: #2a2d35; 
      padding: 30px; 
      border-radius: 12px; 
      box-shadow: 0 4px 20px rgba(0,0,0,0.4); 
    }
    .data-table { 
      width: 100%; 
      border-collapse: collapse; 
      margin-top: 20px; 
    }
    .data-table th { 
      background: #212f49; 
      color: #ffa726; 
      padding: 12px; 
      text-align: left; 
      font-weight: bold; 
    }
    .data-table td { 
      padding: 12px; 
      border-bottom: 1px solid #34495e; 
      color: #ddd; 
    }
    .data-table tr:hover { 
      background: rgba(255, 167, 38, 0.1); 
    }
    .btn-edit, .btn-delete, .btn-toggle { 
      padding: 6px 12px; 
      border-radius: 5px; 
      text-decoration: none; 
      font-size: 0.9em; 
      margin-right: 5px; 
      display: inline-block; 
    }
    .btn-edit { 
      background: #3498db; 
      color: white; 
    }
    .btn-edit:hover { 
      background: #2980b9; 
    }
    .btn-delete { 
      background: #e74c3c; 
      color: white; 
    }
    .btn-delete:hover { 
      background: #c0392b; 
    }
    .btn-toggle { 
      background: #f39c12; 
      color: white; 
    }
    .btn-toggle:hover { 
      background: #e67e22; 
    }
    .form-section { 
      background: #34495e; 
      padding: 25px; 
      border-radius: 10px; 
      margin-top: 25px; 
      scroll-margin-top: 20px;
    }
    .form-section h3 { 
      color: #ffa726; 
      margin-bottom: 20px; 
    }
    .admin-form label { 
      display: block; 
      color: #ffa726; 
      font-weight: bold; 
      margin-top: 15px; 
      margin-bottom: 5px; 
    }
    .admin-form input[type="text"], 
    .admin-form input[type="number"], 
    .admin-form input[type="file"], 
    .admin-form select { 
      width: 100%; 
      padding: 10px; 
      background: #2a2d35; 
      border: 2px solid #555; 
      border-radius: 6px; 
      color: #eee; 
      font-size: 1em; 
    }
    .admin-form input:focus, 
    .admin-form select:focus { 
      outline: none; 
      border-color: #ffa726; 
    }
    .btn-add, .btn-update, .btn-cancel { 
      padding: 12px 24px; 
      border: none; 
      border-radius: 8px; 
      cursor: pointer; 
      font-weight: bold; 
      font-size: 1em; 
      margin-top: 20px; 
      margin-right: 10px; 
      transition: all 0.3s; 
    }
    .btn-add { 
      background: #27ae60; 
      color: white; 
    }
    .btn-add:hover { 
      background: #229954; 
      transform: translateY(-2px); 
    }
    .btn-update { 
      background: #3498db; 
      color: white; 
    }
    .btn-update:hover { 
      background: #2980b9; 
      transform: translateY(-2px); 
    }
    .btn-cancel { 
      background: #95a5a6; 
      color: white; 
      text-decoration: none; 
      display: inline-block; 
    }
    .btn-cancel:hover { 
      background: #7f8c8d; 
    }
    
    /* Image preview for edit form */
    .current-image-preview {
      margin-top: 10px;
      max-width: 150px;
      border: 2px solid #555;
      border-radius: 8px;
    }
  </style>
</head>
<body>

<div id="content">
  
  <!-- Header Section -->
  <div class="header-section">
    <h1>🍿 Food & Beverages Management</h1>
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

  <!-- Tab Navigation -->
  <div class="tab-nav">
    <a href="?tab=items" class="tab-btn <?= $tab == 'items' ? 'active' : '' ?>">Items</a>
    <a href="?tab=sizes" class="tab-btn <?= $tab == 'sizes' ? 'active' : '' ?>">Sizes & Prices</a>
    <a href="?tab=flavors" class="tab-btn <?= $tab == 'flavors' ? 'active' : '' ?>">Flavors</a>
  </div>

<!-- ITEMS TAB -->
<?php if ($tab == 'items'): ?>
<div class="content-section">
  <h2 style="color:#ffa726; margin-bottom:20px;">📦 Items Management</h2>
  <table class="data-table">
    <tr><th>Name</th><th>Image</th><th>Status</th><th>Actions</th></tr>
    <?php
    $items_result = $conn->query("SELECT * FROM items ORDER BY id");
    if ($items_result && $items_result->num_rows > 0):
      while ($row = $items_result->fetch_assoc()):
        $status_badge = $row['sold_out'] ? '<span style="color:#f44336;">❌ Sold Out</span>' : '<span style="color:#4CAF50;">✅ Available</span>';
        $toggle_text = $row['sold_out'] ? 'Mark Available' : 'Mark Sold Out';
    ?>
    <tr>
      <td><?= htmlspecialchars($row['Name']) ?></td>
      <td><?= $row['Image'] ? '<img src="' . htmlspecialchars($row['Image']) . '" style="width:60px; height:60px; object-fit:cover; border-radius:5px;">' : 'No Image' ?></td>
      <td><?= $status_badge ?></td>
      <td>
        <a href="?tab=items&edit_item=<?= $row['id'] ?>#edit-form" class="btn-edit">Edit</a>
        <a href="?toggle_item_soldout=<?= $row['id'] ?>" class="btn-toggle"><?= $toggle_text ?></a>
        <a href="?delete_item=<?= $row['id'] ?>" class="btn-delete" onclick="return confirm('Delete this item?')">Delete</a>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="4" style="text-align:center;">No items found.</td></tr>
    <?php endif; ?>
  </table>

  <?php if ($edit_item): ?>
  <div class="form-section" id="edit-form">
    <h3>Edit Item</h3>
    <form method="POST" enctype="multipart/form-data" class="admin-form">
      <input type="hidden" name="item_id" value="<?= $edit_item['id'] ?>">
      <label>Item Name:</label>
      <input type="text" name="name" value="<?= htmlspecialchars($edit_item['Name']) ?>" required>
      
      <?php if ($edit_item['Image']): ?>
        <label>Current Image:</label>
        <img src="<?= htmlspecialchars($edit_item['Image']) ?>" alt="Current Image" class="current-image-preview">
      <?php endif; ?>
      
      <label>Replace Image (optional):</label>
      <input type="file" name="image" accept="image/*">
      <p style="color:#999; font-size:0.9em; margin-top:5px;">Leave empty to keep current image</p>
      
      <button type="submit" name="update_item" class="btn-update">Update Item</button>
      <a href="?tab=items" class="btn-cancel">Cancel</a>
    </form>
  </div>
  <?php else: ?>
  <div class="form-section" id="add-form">
    <h3>Add New Item</h3>
    <form method="POST" enctype="multipart/form-data" class="admin-form">
      <label>Item Name:</label>
      <input type="text" name="name" required>
      <label>Image:</label>
      <input type="file" name="image" accept="image/*">
      <button type="submit" name="add_item" class="btn-add">Add Item</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- SIZES TAB -->
<?php if ($tab == 'sizes'): ?>
<div class="content-section">
  <h2 style="color:#ffa726; margin-bottom:20px;">📏 Sizes & Prices Management</h2>
  <table class="data-table">
    <tr><th>Item</th><th>Size</th><th>Price</th><th>Status</th><th>Actions</th></tr>
    <?php
    $sizes_result = $conn->query("SELECT sizes.*, items.Name AS item_name FROM sizes LEFT JOIN items ON sizes.item_id = items.id ORDER BY items.Name, sizes.price");
    if ($sizes_result && $sizes_result->num_rows > 0):
      while ($row = $sizes_result->fetch_assoc()):
        $status_badge = $row['sold_out'] ? '<span style="color:#f44336;">❌ Sold Out</span>' : '<span style="color:#4CAF50;">✅ Available</span>';
        $toggle_text = $row['sold_out'] ? 'Mark Available' : 'Mark Sold Out';
    ?>
    <tr>
      <td><?= htmlspecialchars($row['item_name']) ?></td>
      <td><?= htmlspecialchars($row['size']) ?></td>
      <td>$<?= number_format($row['price'], 2) ?></td>
      <td><?= $status_badge ?></td>
      <td>
        <a href="?tab=sizes&edit_size=<?= $row['size_id'] ?>#edit-form" class="btn-edit">Edit</a>
        <a href="?toggle_size_soldout=<?= $row['size_id'] ?>" class="btn-toggle"><?= $toggle_text ?></a>
        <a href="?delete_size=<?= $row['size_id'] ?>" class="btn-delete" onclick="return confirm('Delete this size?')">Delete</a>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="5" style="text-align:center;">No sizes found.</td></tr>
    <?php endif; ?>
  </table>

  <?php if ($edit_size): ?>
  <div class="form-section" id="edit-form">
    <h3>Edit Item</h3>
    <form method="POST" class="admin-form">
      <input type="hidden" name="size_id" value="<?= $edit_size['size_id'] ?>">
      <label>Item:</label>
      <select name="item_id" required>
        <?php $items_list->data_seek(0); while ($item = $items_list->fetch_assoc()): $selected = ($item['id'] == $edit_size['item_id']) ? 'selected' : ''; ?>
          <option value="<?= $item['id'] ?>" <?= $selected ?>><?= htmlspecialchars($item['Name']) ?></option>
        <?php endwhile; ?>
      </select>
      <label>Size Name:</label>
      <input type="text" name="size" value="<?= htmlspecialchars($edit_size['size']) ?>" required>
      <label>Price:</label>
      <input type="number" step="0.01" name="price" value="<?= $edit_size['price'] ?>" required>
      <button type="submit" name="update_size" class="btn-update">Update</button>
      <a href="?tab=sizes" class="btn-cancel">Cancel</a>
    </form>
  </div>
  <?php else: ?>
  <div class="form-section" id="add-form">
    <h3>Add New Size</h3>
    <form method="POST" class="admin-form">
      <label>Item:</label>
      <select name="item_id" required>
        <option value="">-- Select Item --</option>
        <?php $items_list->data_seek(0); while ($item = $items_list->fetch_assoc()): ?>
          <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['Name']) ?></option>
        <?php endwhile; ?>
      </select>
      <label>Size Name:</label>
      <input type="text" name="size" placeholder="e.g., Small, Medium, Large" required>
      <label>Price:</label>
      <input type="number" step="0.01" name="price" placeholder="0.00" required>
      <button type="submit" name="add_size" class="btn-add">Add Size</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- FLAVORS TAB -->
<?php if ($tab == 'flavors'): ?>
<div class="content-section">
  <h2 style="color:#ffa726; margin-bottom:20px;">🍹 Flavors Management</h2>
  <table class="data-table">
    <tr><th>Item</th><th>Flavor</th><th>Type</th><th>Status</th><th>Actions</th></tr>
    <?php
    $flavors_result = $conn->query("SELECT f.id, f.flavor_name, f.flavor_type, f.sold_out, i.Name AS item_name FROM flavors f LEFT JOIN items i ON f.item_id = i.id ORDER BY i.Name, f.flavor_type, f.flavor_name");
    if ($flavors_result && $flavors_result->num_rows > 0):
      while ($row = $flavors_result->fetch_assoc()):
        $type_lower = strtolower($row['flavor_type']);
        if ($type_lower == 'popcorn') {
            $type_color = '#ff9800';
        } elseif ($type_lower == 'drink') {
            $type_color = '#2196F3';
        } else {
            $type_color = '#9c27b0';
        }
        $flavor_type_badge = '<span style="background:' . $type_color . '; color:white; padding:3px 8px; border-radius:4px; font-size:0.75em; font-weight:bold;">' . strtoupper($row['flavor_type']) . '</span>';
        $status_badge = $row['sold_out'] ? '<span style="color:#f44336;">❌ Sold Out</span>' : '<span style="color:#4CAF50;">✅ Available</span>';
        $toggle_text = $row['sold_out'] ? 'Mark Available' : 'Mark Sold Out';
    ?>
    <tr>
      <td><?= htmlspecialchars($row['item_name']) ?></td>
      <td><?= htmlspecialchars($row['flavor_name']) ?></td>
      <td><?= $flavor_type_badge ?></td>
      <td><?= $status_badge ?></td>
      <td>
        <a href="?tab=flavors&edit_flavor=<?= $row['id'] ?>#edit-form" class="btn-edit">Edit</a>
        <a href="?toggle_flavor_soldout=<?= $row['id'] ?>" class="btn-toggle"><?= $toggle_text ?></a>
        <a href="?delete_flavor=<?= $row['id'] ?>" class="btn-delete" onclick="return confirm('Delete this flavor?')">Delete</a>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="5" style="text-align:center;">No flavors found.</td></tr>
    <?php endif; ?>
  </table>

  <?php if ($edit_flavor): ?>
  <div class="form-section" id="edit-form">
    <h3>Edit Flavor</h3>
    <form method="POST" class="admin-form">
      <input type="hidden" name="flavor_id" value="<?= $edit_flavor['id'] ?>">
      <label>Item:</label>
      <select name="item_id" required>
        <?php $items_list->data_seek(0); while ($item = $items_list->fetch_assoc()): $selected = ($item['id'] == $edit_flavor['item_id']) ? 'selected' : ''; ?>
          <option value="<?= $item['id'] ?>" <?= $selected ?>><?= htmlspecialchars($item['Name']) ?></option>
        <?php endwhile; ?>
      </select>
      <label>Flavor Name:</label>
      <input type="text" name="flavor_name" value="<?= htmlspecialchars($edit_flavor['flavor_name']) ?>" required>
      <label>Flavor Type:</label>
      <input type="text" name="flavor_type" value="<?= htmlspecialchars($edit_flavor['flavor_type']) ?>" placeholder="e.g., popcorn, drink, snack, candy" required>
      <p style="color:#999; font-size:0.85em; margin-top:5px;">💡 Tip: Use "popcorn" (orange) or "drink" (blue). Other types will use purple.</p>
      <button type="submit" name="update_flavor" class="btn-update">Update Flavor</button>
      <a href="?tab=flavors" class="btn-cancel">Cancel</a>
    </form>
  </div>
  <?php else: ?>
  <div class="form-section" id="add-form">
    <h3>Add New Flavor</h3>
    <form method="POST" class="admin-form">
      <label>Item:</label>
      <select name="item_id" required>
        <option value="">-- Select Item --</option>
        <?php $items_list->data_seek(0); while ($item = $items_list->fetch_assoc()): ?>
          <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['Name']) ?></option>
        <?php endwhile; ?>
      </select>
      <label>Flavor Name:</label>
      <input type="text" name="flavor_name" placeholder="e.g., Salted, Coca-Cola, Cheese" required>
      <label>Flavor Type:</label>
      <input type="text" name="flavor_type" placeholder="e.g., popcorn, drink, snack, candy" required>
      <p style="color:#999; font-size:0.85em; margin-top:5px;">💡 Tip: Use "popcorn" (orange) or "drink" (blue). Other types will use purple.</p>
      <button type="submit" name="add_flavor" class="btn-add">Add Flavor</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

</div>

<div id="footer">
  <span><a href="contact_careers.php#contact-us">Contact Us</a></span>
  <span><a href="contact_careers.php#career-opportunities">Career Opportunities</a></span>
  <span><a href="admin_dashboard.php">Admin Panel</a></span>
  <div class="cr">Copyright &copy; IE4727WebDev</div>
</div>

</body>
</html>
<?php $conn->close(); ?>
