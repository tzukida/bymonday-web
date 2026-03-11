<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/email_config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name        = sanitize($_POST['full_name']);
    $email            = sanitize($_POST['email']);
    $phone            = sanitize($_POST['phone']);
    $address          = sanitize($_POST['address']);
    $username         = sanitize($_POST['username']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Save filled values so the form can repopulate on error
    $_SESSION['old_input'] = [
        'full_name' => $full_name,
        'email'     => $email,
        'phone'     => $phone,
        'address'   => $address,
        'username'  => $username,
    ];

    function failBack($message) {
        $_SESSION['error'] = $message;
        redirect('customer_signup.php');
    }

    // Validation
    if (empty($full_name) || empty($email) || empty($phone) || empty($address) || empty($username) || empty($password)) {
        failBack('Please fill in all required fields');
    }

    if ($password !== $confirm_password) {
        failBack('Passwords do not match');
    }

    if (strlen($password) < 6) {
        failBack('Password must be at least 6 characters long');
    }

    // Check users table - username
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        failBack('Username already taken. Please choose another.');
    }

    // Check users table - email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        failBack('An account with that email already exists.');
    }

    // Check pending table - remove expired entries first, then check for duplicates
    $conn->query("DELETE FROM pending_verifications WHERE expires_at < NOW()");

    $stmt = $conn->prepare("SELECT id FROM pending_verifications WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        failBack('A verification email was already sent to this email or username. Please check your inbox or wait for it to expire.');
    }

    // All good — clear old input
    unset($_SESSION['old_input']);

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Generate token
    $raw_token  = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $raw_token);
    $expires_at = date('Y-m-d H:i:s', time() + (VERIFICATION_TOKEN_EXPIRY_HOURS * 3600));

    // Save to pending_verifications — NOT users yet
    $stmt = $conn->prepare("
        INSERT INTO pending_verifications (token_hash, full_name, email, phone, address, username, password_hash, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssssss", $token_hash, $full_name, $email, $phone, $address, $username, $hashed_password, $expires_at);

    if ($stmt->execute()) {
        $sent = sendVerificationEmail($email, $full_name, $raw_token);

        if ($sent) {
            $_SESSION['success'] = 'Almost there! We sent a verification link to ' . htmlspecialchars($email) . '. Click it to activate your account. Check your spam folder too!';
        } else {
            // Clean up pending row if email failed
            $conn->query("DELETE FROM pending_verifications WHERE token_hash = '" . $token_hash . "'");
            $_SESSION['error'] = 'Could not send verification email. Please try again.';
            redirect('customer_signup.php');
        }

        redirect('customer_login.php');
    } else {
        failBack('Registration failed. Please try again.');
    }
} else {
    redirect('customer_signup.php');
}
?>