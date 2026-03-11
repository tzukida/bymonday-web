<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = 'Please fill in all fields';
        redirect('customer_login.php');
    }

    // Check if user exists and is a customer
    $stmt = $conn->prepare("
        SELECT u.*, c.phone, c.address
        FROM users u
        LEFT JOIN customers c ON u.id = c.user_id
        WHERE (u.username = ? OR u.email = ?) AND u.role = 'customer'
        LIMIT 1
    ");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id']            = $user['id'];
            $_SESSION['username']           = $user['username'];
            $_SESSION['email']              = $user['email'];
            $_SESSION['full_name']          = $user['full_name'];
            $_SESSION['role']               = $user['role'];
            $_SESSION['phone']              = $user['phone'];
            $_SESSION['address']            = $user['address'];
            $_SESSION['logged_in_customer'] = true;

            // Redirect to checkout or menu
            if (isset($_SESSION['checkout_after_login']) && $_SESSION['checkout_after_login']) {
                unset($_SESSION['checkout_after_login']);
                redirect('checkout.php');
            } else {
                redirect('menu.php');
            }
        } else {
            $_SESSION['error'] = 'Invalid username or password';
            redirect('customer_login.php');
        }
    } else {
        $_SESSION['error'] = 'Invalid username or password';
        redirect('customer_login.php');
    }
} else {
    redirect('customer_login.php');
}
?>