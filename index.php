<?php
session_start();

// Database connection settings
$host = 'localhost'; // change if needed
$user = 'root';      // change if needed
$pass = '';          // change if needed
$dbname = 'pdf_store_v2';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

$errors = [];
$message = '';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Helper functions
function get_cart_count() {
    return count($_SESSION['cart']);
}

function get_courses() {
    return [
        [
            'id' => 1,
            'name' => 'Web Development Fundamentals',
            'desc' => 'Basics of HTML, CSS, JS.',
            'price' => 99,
            'img' => 'images/web_dev.jpg'
        ],
        [
            'id' => 2,
            'name' => 'Graphic Design Principles',
            'desc' => 'Key design concepts.',
            'price' => 199,
            'img' => 'images/graphic_design.jpg'
        ],
        [
            'id' => 3,
            'name' => 'Python for Data Science',
            'desc' => 'Python libraries for data.',
            'price' => 99,
            'img' => 'images/python_ds.jpg'
        ],
        [
            'id' => 4,
            'name' => 'Digital Marketing Mastery',
            'desc' => 'Marketing strategies.',
            'price' => 95,
            'img' => 'images/digital_marketing.jpg'
        ],
        [
            'id' => 5,
            'name' => 'Mobile App Development',
            'desc' => 'Building smartphone apps.',
            'price' => 99,
            'img' => 'images/mobile_app.jpg'
        ],
        [
            'id' => 6,
            'name' => 'Cybersecurity Essentials',
            'desc' => 'Security fundamentals.',
            'price' => 105,
            'img' => 'images/cybersecurity.jpg'
        ],
        [
            'id' => 7,
            'name' => 'Cloud Computing with AWS',
            'desc' => 'AWS services intro.',
            'price' => 709,
            'img' => 'images/aws_cloud.jpg'
        ],
        [
            'id' => 8,
            'name' => 'Project Management',
            'desc' => 'PM methodologies.',
            'price' => 199,
            'img' => 'images/project_management.jpg'
        ],
    ];
}

// Get current view
$view = $_GET['view'] ?? 'courses';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_GET['view']) && $_GET['view'] === 'cart') {
        $action = $_POST['action'] ?? '';
        $id = intval($_POST['id'] ?? 0);
        if ($action === 'add') {
            // Add course to session cart
            $courses = get_courses();
            $course = array_filter($courses, fn($c) => $c['id'] == $id);
            if ($course) {
                $_SESSION['cart'][] = array_shift($course);
                echo json_encode(['status'=>'success', 'count'=>get_cart_count()]);
                exit;
            }
        } elseif ($action === 'remove') {
            // Remove course from cart
            foreach ($_SESSION['cart'] as $index => $item) {
                if ($item['id'] == $id) {
                    unset($_SESSION['cart'][$index]);
                }
            }
            $_SESSION['cart'] = array_values($_SESSION['cart']);
            echo json_encode(['status'=>'success', 'count'=>get_cart_count()]);
            exit;
        }
    } elseif (isset($_GET['view']) && $_GET['view'] === 'admin') {
        // Confirm order
        $order_id = intval($_GET['id'] ?? 0);
        if ($_SESSION['role'] ?? '' === 'admin') {
            $stmt = $conn->prepare("UPDATE transactions SET order_status='Confirmed' WHERE id=?");
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            header("Location: index.php?view=admin");
            exit;
        }
    } elseif (isset($_GET['view']) && $_GET['view'] === 'payment') {
        // Handle purchase submission
        $email = trim($_POST['email'] ?? '');
        $trans_id = trim($_POST['transaction_id'] ?? '');
        if (!$email || !$trans_id || empty($_SESSION['cart'])) {
            $errors[] = "All fields required.";
        } else {
            // Save transaction
            $courses_purchased = implode(', ', array_column($_SESSION['cart'], 'name'));
            $total_amount = array_sum(array_column($_SESSION['cart'], 'price'));
            $user_id = $_SESSION['user_id'] ?? null;

            $stmt = $conn->prepare("INSERT INTO transactions (user_id, email, transaction_id, courses_purchased, total_amount, order_status) VALUES (?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param('isssd', $user_id, $email, $trans_id, $courses_purchased, $total_amount);
            $stmt->execute();

            // Clear cart
            $_SESSION['cart'] = [];
            header("Location: index.php?success=1");
            exit;
        }
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($identifier && $password) {
        // Search by email or username
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR username=?");
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                header("Location: index.php");
                exit;
            } else {
                $errors[] = "Incorrect password.";
            }
        } else {
            $errors[] = "User not found.";
        }
    } else {
        $errors[] = "Enter credentials.";
    }
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$username || !$email || !$password || !$confirm) {
        $errors[] = "All fields are required.";
    } elseif ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password too short.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email.";
    } else {
        $pw_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $username, $email, $pw_hash);
        if ($stmt->execute()) {
            $message = "Registration successful. You can now login.";
        } else {
            $errors[] = "Error: " . $conn->error;
        }
    }
}

// Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Check login
$logged_in = isset($_SESSION['user_id']);

// Fetch user info
if ($logged_in) {
    $username = $_SESSION['username'];
    $role = $_SESSION['role'];
    $email = $_SESSION['email'];
} else {
    $username = '';
    $role = '';
    $email = '';
}

// Fetch transactions for admin
$transactions = [];
if ($role === 'admin') {
    $res = $conn->query("SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON t.user_id=u.id");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $transactions[] = $row;
        }
    }
}

// Check for success message
$success_msg = '';
if (isset($_GET['success'])) {
    $success_msg = "Purchase successful!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>UpLearnly PDF Courses</title>
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="style.css" />
</head>
<body>
<header>
  <div class="header-container">
    <img src="images/logo.png" alt="UpLearnly" class="logo"/>
    <h1>UpLearnly</h1>
    <nav>
      <a href="?view=courses">Courses</a>
      <a href="?view=cart" id="cart-link">Cart (<span id="cart-count"><?php echo get_cart_count(); ?></span>)</a>
      <?php if ($logged_in): ?>
        <span>Welcome, <?php echo htmlspecialchars($username); ?></span>
        <a href="?action=logout">Logout</a>
        <?php if ($role==='admin'): ?>
          <a href="?view=admin">Admin</a>
        <?php endif; ?>
      <?php else: ?>
        <a href="?view=login">Login</a>
        <a href="?view=register">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<div class="container">
<?php if ($success_msg): ?>
<div class="message success"><?php echo $success_msg; ?></div>
<?php endif; ?>
<?php if ($errors): ?>
<div class="message error">
  <ul>
  <?php foreach($errors as $err): ?>
    <li><?php echo htmlspecialchars($err); ?></li>
  <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php
// Views
switch ($view) {
    case 'register':
        ?>
        <h2>Register</h2>
        <form method="post" action="?view=register">
          <label>Username</label>
          <input type="text" name="username" required />
          <label>Email</label>
          <input type="email" name="email" required />
          <label>Password</label>
          <input type="password" name="password" required minlength="6"/>
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" required minlength="6"/>
          <button type="submit" name="register" class="btn">Register</button>
        </form>
        <?php
        break;
    case 'login':
        ?>
        <h2>Login</h2>
        <form method="post" action="?view=login">
          <label>Email or Username</label>
          <input type="text" name="identifier" required />
          <label>Password</label>
          <input type="password" name="password" required />
          <button type="submit" name="login" class="btn">Login</button>
        </form>
        <?php
        break;
    case 'cart':
        ?>
        <h2>Your Cart</h2>
        <?php if (empty($_SESSION['cart'])): ?>
          <p>Your cart is empty.</p>
        <?php else: ?>
        <table class="cart-table">
          <thead>
            <tr>
              <th>Image</th><th>Name</th><th>Price</th><th>Remove</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($_SESSION['cart'] as $item): ?>
            <tr>
              <td><img src="<?php echo $item['img']; ?>" width="50" /></td>
              <td><?php echo htmlspecialchars($item['name']); ?></td>
              <td>₹<?php echo number_format($item['price']); ?></td>
              <td><button class="btn" onclick="removeFromCart(<?php echo $item['id']; ?>)">Remove</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p>Total: ₹<?php echo number_format(array_sum(array_column($_SESSION['cart'], 'price'))); ?></p>
        <a href="?view=courses" class="btn">Continue Shopping</a>
        <a href="?view=payment" class="btn">Proceed to Payment</a>
        <?php
        break;
    case 'payment':
        $cart_total = array_sum(array_column($_SESSION['cart'], 'price'));
        ?>
        <h2>Payment</h2>
        <p>Total Amount: ₹<?php echo number_format($cart_total); ?></p>
        <p>Scan the QR code below to pay (Simulation):</p>
        <img src="images/qr_code.png" alt="QR Code" style="max-width:200px;"/>
        <form method="post" action="?view=payment">
          <label>Email for PDF delivery</label>
          <input type="email" name="email" required value="<?php echo htmlspecialchars($email); ?>"/>
          <label>Transaction ID</label>
          <input type="text" name="transaction_id" required />
          <button type="submit" class="btn">Confirm Purchase</button>
        </form>
        <a href="?view=cart" class="btn">Back to Cart</a>
        <?php
        break;
    case 'register':
        // Registration form handled above
        break;
    case 'login':
        // Login form handled above
        break;
    case 'admin':
        if ($role !== 'admin') {
            echo "<p>Access denied.</p>";
        } else {
            ?>
            <h2>Admin Panel - Transactions</h2>
            <table class="admin-table">
              <thead>
                <tr>
                  <th>ID</th><th>User</th><th>Email</th><th>Courses</th><th>Total</th><th>Status</th><th>Time</th><th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                  <td><?php echo $tx['id']; ?></td>
                  <td><?php echo htmlspecialchars($tx['username'] ?? 'Guest'); ?></td>
                  <td><?php echo htmlspecialchars($tx['email']); ?></td>
                  <td><?php echo htmlspecialchars($tx['courses_purchased']); ?></td>
                  <td>₹<?php echo number_format($tx['total_amount'],2); ?></td>
                  <td><?php echo $tx['order_status']; ?></td>
                  <td><?php echo $tx['purchase_timestamp']; ?></td>
                  <td>
                    <?php if ($tx['order_status'] === 'Pending'): ?>
                      <a href="?view=admin&id=<?php echo $tx['id']; ?>" class="btn">Confirm</a>
                    <?php else: ?>
                      Confirmed
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php
        }
        break;
    case 'courses':
    default:
        // Main courses page with hero slider and course list
        ?>
        <!-- Hero Slider -->
        <div id="hero-slider">
          <div class="slide" style="background-image:url('https://images.unsplash.com/photo-1508780709619-79562169bc64?fit=crop&w=1600&q=80');">
            <div class="slide-content">
              <h2>Learn Web Development</h2>
              <p>Start your coding journey today.</p>
              <a href="#course-1" class="btn">View Course</a>
            </div>
          </div>
          <div class="slide" style="background-image:url('https://images.unsplash.com/photo-1498050108023-c5249f4df085?fit=crop&w=1600&q=80');">
            <div class="slide-content">
              <h2>Design Like a Pro</h2>
              <p>Master graphic design principles.</p>
              <a href="#course-2" class="btn">View Course</a>
            </div>
          </div>
          <div class="slide" style="background-image:url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?fit=crop&w=1600&q=80');">
            <div class="slide-content">
              <h2>Data Science with Python</h2>
              <p>Unlock insights from data.</p>
              <a href="#course-3" class="btn">View Course</a>
            </div>
          </div>
        </div>
        <!-- Course Grid -->
        <h2 id="courses-section">Our Courses</h2>
        <div class="courses-grid">
          <?php
          $courses = get_courses();
          foreach ($courses as $c):
          ?>
          <div class="course-card fade-in-section" id="course-<?php echo $c['id']; ?>">
            <img src="<?php echo $c['img']; ?>" alt="<?php echo htmlspecialchars($c['name']); ?>"/>
            <h3><?php echo htmlspecialchars($c['name']); ?></h3>
            <p><?php echo htmlspecialchars($c['desc']); ?></p>
            <p>₹<?php echo number_format($c['price']); ?></p>
            <button class="btn" onclick="addToCart(<?php echo $c['id']; ?>)">Add to Cart</button>
          </div>
          <?php endforeach; ?>
        </div>
        <?php
        break;
}
?>

</div>

<!-- Scripts -->
<script src="script.js"></script>

</body>
</html>