<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($full_name) || empty($email) || empty($phone) || empty($address) || empty($username) || empty($password)) {
        $_SESSION['error'] = 'Please fill in all required fields';
        redirect('customer_signup.php');
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = 'Passwords do not match';
        redirect('customer_signup.php');
    }

    if (strlen($password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters long';
        redirect('customer_signup.php');
    }

    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = 'Username already exists';
        redirect('customer_signup.php');
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = 'Email already exists';
        redirect('customer_signup.php');
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'customer')");
    $stmt->bind_param("ssss", $username, $email, $hashed_password, $full_name);

    if ($stmt->execute()) {
        $user_id = $conn->insert_id;

        // Insert customer details
        $stmt = $conn->prepare("INSERT INTO customers (user_id, phone, address) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $phone, $address);
        $stmt->execute();

        $_SESSION['success'] = 'Account created successfully! Please login.';
        redirect('customer_login.php');
    } else {
        $_SESSION['error'] = 'Registration failed. Please try again.';
        redirect('customer_signup.php');
    }
} else {
    redirect('customer_signup.php');
}
?>
