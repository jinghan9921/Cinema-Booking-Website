<style>
      #sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: 200px;
      height: 100vh;
      background: #21344b;
      padding-top: 24px;
      box-shadow: 2px 0 8px rgba(0,0,0,0.08);
      z-index: 10;
    }
    .sidebar-title {
      font-weight: bold;
      color: #e96b39;
      font-size: 1.6em;
      margin: 0 0 24px 0;
      padding-left: 22px;
      letter-spacing: 1.5px;
    }
    #sidebar ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    #sidebar li a {
      display: block;
      color: #ffffff;
      font-size: 1.2em;
      text-decoration: none;
      padding: 13px;
      border-radius: 7px;
      transition: background 0.1s, color 0.1s;
      margin: auto;
    }
    #sidebar li a.active,
    #sidebar li a:hover {
      background: #e08d0fc5;
      color: #ffa726;
      font-weight: bold;
      cursor: pointer;
    }
   
</style>
<div id="sidebar">
  <div class="sidebar-title">IE Theatre</div>
  <ul>
    <li>
      <a href="index.php"
         <?php if(basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == 'movie.php') echo 'class="active"'; ?>>
        Movies
      </a>
    </li>
    
    <li>
      <a href="FnB.php"
         <?php if(basename($_SERVER['PHP_SELF']) == 'FnB.php') echo 'class="active"'; ?>>
        Food & Beverages
      </a>
    </li>
    
    <li>
      <a href="checkout.php"
         <?php if(basename($_SERVER['PHP_SELF']) == 'checkout.php') echo 'class="active"'; ?>>
        View Cart
      </a>
    </li>
    
    <li>
      <a href="view_ticket.php"
         <?php if(basename($_SERVER['PHP_SELF']) == 'view_ticket.php') echo 'class="active"'; ?>>
        View Tickets
      </a>
    </li>
  </ul>
</div>
