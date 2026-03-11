<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

if (!isLoggedIn() || $_SESSION['role'] != 'customer') {
    redirect('customer_login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile.php');
}

$user_id   = $_SESSION['user_id'];
$full_name = trim($_POST['full_name'] ?? '');
$phone     = trim($_POST['phone']     ?? '');
$address   = trim($_POST['address']   ?? '');

if (empty($full_name)) {
    $_SESSION['profile_error'] = 'Full name cannot be empty.';
    redirect('profile.php');
}

// Update users table
$stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
$stmt->bind_param("si", $full_name, $user_id);
$stmt->execute();
$stmt->close();

// Update or insert customers table
$check = $conn->prepare("SELECT id FROM customers WHERE user_id = ?");
$check->bind_param("i", $user_id);
$check->execute();
$exists = $check->get_result()->num_rows > 0;
$check->close();

if ($exists) {
    $stmt = $conn->prepare("UPDATE customers SET phone = ?, address = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $phone, $address, $user_id);
} else {
    $stmt = $conn->prepare("INSERT INTO customers (user_id, phone, address) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $phone, $address);
}
$stmt->execute();
$stmt->close();

// Update session
$_SESSION['full_name'] = $full_name;
$_SESSION['phone']     = $phone;
$_SESSION['address']   = $address;

$_SESSION['profile_success'] = 'Profile updated successfully!';
redirect('profile.php');
?>