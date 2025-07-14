<?php
session_start();

// DB config
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'pdf_store_v2');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Helper: flash messages
function flash($name) {
    if (!empty($_SESSION[$name])) {
        echo '<div style="background:#def;padding:1em;margin:1em 0;text-align:center;">'.$_SESSION[$name].'</div>';
        unset($_SESSION[$name]);
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act == 'register') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $pwd = $_POST['password'];
        $repwd = $_POST['confirm_password'];
        $errors = [];

        if (!$username || !$email || !$pwd || !$repwd) $errors[] = 'All fields required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalid.';
        if ($pwd != $repwd) $errors[] = 'Passwords do not match.';
        if (strlen($pwd) < 6) $errors[] = 'Password too short.';

        $stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $stmt->bind_param("ss",$username,$email); $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) $errors[] = 'Username/email taken.';
        $stmt->close();

        if (!$errors) {
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username,email,password_hash) VALUES (?,?,?)");
            $stmt->bind_param("sss",$username,$email,$hash);
            $stmt->execute(); $stmt->close();
            $_SESSION['flash'] = 'Registration successful! Please login.';
            header("Location: ?view=login"); exit();
        } else $_SESSION['flash'] = implode(' ', $errors);
    }

    if ($act == 'login') {
        $id = trim($_POST['identifier']);
        $pwd = $_POST['password'];
        $stmt = $conn->prepare("SELECT id,username,email,password_hash FROM users WHERE username=? OR email=?");
        $stmt->bind_param("ss",$id,$id); $stmt->execute();
        $res = $stmt->get_result();
        if ($user = $res->fetch_assoc()) {
            if (password_verify($pwd,$user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['flash'] = 'Welcome, '.$user['username'];
                header("Location: index.php"); exit();
            } else $_SESSION['flash']='Wrong password.';
        } else $_SESSION['flash']='User not found.';
        $stmt->close();
    }

    if ($act == 'add_to_cart') {
        $id = $_POST['course_id']; $name = $_POST['course_name']; $price = $_POST['course_price'];
        $image = $_POST['course_image'];
        $_SESSION['cart'][$id] = ['id'=>$id,'name'=>$name,'price'=>$price,'image'=>$image];
        $_SESSION['flash'] = 'Added to cart!';
    }

    if ($act == 'remove_from_cart') {
        $id = $_POST['course_id'];
        unset($_SESSION['cart'][$id]);
        $_SESSION['flash'] = 'Removed from cart.';
    }

    if ($act == 'confirm_purchase') {
        $user_id = $_SESSION['user_id'] ?? null;
        $payer_email = trim($_POST['payer_email']);
        $transaction_id = trim($_POST['transaction_id']);
        $total = $_POST['total_amount'];
        $names = implode(', ', array_map(fn($x)=>$x['name'],$_SESSION['cart']??[]));
        $stmt = $conn->prepare("INSERT INTO transactions (user_id,email,transaction_id,courses_purchased,total_amount,order_status) VALUES (?,?,?,?,?,'Pending')");
        $stmt->bind_param("isssd",$user_id,$payer_email,$transaction_id,$names,$total);
        $stmt->execute(); $stmt->close();
        unset($_SESSION['cart']);
        $_SESSION['flash'] = 'Purchase confirmed!';
        header("Location: index.php"); exit();
    }
}

// Simple course list
$courses = [
    ['id'=>1,'name'=>'Web Dev','desc'=>'HTML/CSS/JS','price'=>99,'image'=>'https://via.placeholder.com/150'],
    ['id'=>2,'name'=>'Python DS','desc'=>'Data Science','price'=>199,'image'=>'https://via.placeholder.com/150'],
];

// Views
$view = $_GET['view'] ?? 'home';
?>
<!DOCTYPE html>
<html>
<head><title>UpLearnly Simple</title></head>
<body style="font-family:sans-serif;max-width:700px;margin:auto;">
<h1>UpLearnly Courses</h1>
<nav>
    <a href="index.php">Home</a> |
    <a href="?view=cart">Cart (<?php echo count($_SESSION['cart']??[]); ?>)</a> |
    <?php if (isset($_SESSION['user_id'])): ?>
        <span>Hi, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
        <a href="?action=logout_now">Logout</a>
    <?php else: ?>
        <a href="?view=login">Login</a> | <a href="?view=register">Register</a>
    <?php endif; ?>
</nav>
<?php flash('flash'); ?>

<?php
if ($view=='register'): ?>
    <h2>Register</h2>
    <form method="POST">
        <input type="hidden" name="action" value="register">
        Username: <input name="username" required><br>
        Email: <input type="email" name="email" required><br>
        Password: <input type="password" name="password" required><br>
        Confirm: <input type="password" name="confirm_password" required><br>
        <button type="submit">Register</button>
    </form>
<?php
elseif ($view=='login'): ?>
    <h2>Login</h2>
    <form method="POST">
        <input type="hidden" name="action" value="login">
        Email/Username: <input name="identifier" required><br>
        Password: <input type="password" name="password" required><br>
        <button type="submit">Login</button>
    </form>
<?php
elseif ($view=='cart'):
    $cart = $_SESSION['cart'] ?? [];
    $total = array_sum(array_column($cart,'price'));
?>
    <h2>Your Cart</h2>
    <?php if (!$cart): ?>
        <p>Cart empty.</p>
    <?php else: ?>
        <ul>
        <?php foreach ($cart as $item): ?>
            <li><?php echo htmlspecialchars($item['name']); ?> - ₹<?php echo $item['price']; ?>
                <form style="display:inline" method="POST">
                    <input type="hidden" name="action" value="remove_from_cart">
                    <input type="hidden" name="course_id" value="<?php echo $item['id']; ?>">
                    <button type="submit">Remove</button>
                </form>
            </li>
        <?php endforeach; ?>
        </ul>
        <p><b>Total: ₹<?php echo $total; ?></b></p>
        <a href="?view=payment">Proceed to Payment</a>
    <?php endif; ?>
<?php
elseif ($view=='payment'):
    $cart = $_SESSION['cart'] ?? [];
    $total = array_sum(array_column($cart,'price'));
    if (!$cart) { echo '<p>Cart empty.</p>'; }
    else:
?>
    <h2>Payment</h2>
    <p>Scan QR or pay ₹<?php echo $total; ?> to UPI and enter details:</p>
    <form method="POST">
        <input type="hidden" name="action" value="confirm_purchase">
        <input type="hidden" name="total_amount" value="<?php echo $total; ?>">
        Email: <input name="payer_email" value="<?php echo htmlspecialchars($_SESSION['email']??''); ?>" required><br>
        Transaction ID: <input name="transaction_id" required><br>
        <button type="submit">Confirm Purchase</button>
    </form>
<?php
else: // home
    echo '<h2>Courses</h2><ul>';
    foreach ($courses as $c):
?>
        <li>
            <b><?php echo htmlspecialchars($c['name']); ?></b> (₹<?php echo $c['price']; ?>)<br>
            <?php echo htmlspecialchars($c['desc']); ?><br>
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="add_to_cart">
                <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                <input type="hidden" name="course_name" value="<?php echo htmlspecialchars($c['name']); ?>">
                <input type="hidden" name="course_price" value="<?php echo $c['price']; ?>">
                <input type="hidden" name="course_image" value="<?php echo $c['image']; ?>">
                <button type="submit">Add to Cart</button>
            </form>
        </li>
<?php
    endforeach; echo '</ul>';
endif;

// Logout
if (isset($_GET['action']) && $_GET['action']=='logout_now') {
    session_unset(); session_destroy();
    header('Location: index.php');
    exit();
}
$conn->close();
?>
</body>
</html>
