<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

if (!isLoggedIn() || $_SESSION['role'] != 'customer') {
    redirect('customer_login.php');
}

$user_id = $_SESSION['user_id'];

// Fetch latest data from DB
$stmt = $conn->prepare("
    SELECT u.full_name, u.email, u.username, c.phone, c.address
    FROM users u
    LEFT JOIN customers c ON u.id = c.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Generate initials
$initials = '';
$name_parts = explode(' ', trim($user['full_name']));
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
    if (strlen($initials) >= 2) break;
}

$success = $_SESSION['profile_success'] ?? '';
$error   = $_SESSION['profile_error']   ?? '';
unset($_SESSION['profile_success'], $_SESSION['profile_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Coffee by Monday Mornings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #F7F2EC;
            color: #1a0f08;
            min-height: 100vh;
            padding-bottom: 60px;
        }

        /* ── Navbar ── */
        .navbar {
            position: sticky;
            top: 0;
            background: rgba(26,15,8,0.97);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(201,123,43,0.15);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            z-index: 100;
        }

        .back-btn {
            background: rgba(201,123,43,0.15);
            border: 1.5px solid rgba(201,123,43,0.3);
            color: #C97B2B;
            width: 38px; height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 15px;
            transition: all .2s ease;
            flex-shrink: 0;
        }

        .back-btn:hover { background: rgba(201,123,43,0.3); }

        .navbar-title {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            font-weight: 700;
            color: #f7f2ec;
        }

        /* ── Profile header ── */
        .profile-header {
            background: #2e1c0e;
            padding: 36px 24px 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            text-align: center;
        }

        .avatar {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #C97B2B, #E09A4A);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 900;
            color: #fff;
            letter-spacing: 1px;
            box-shadow: 0 4px 20px rgba(201,123,43,0.4);
            /* ready for photo upload later */
            overflow: hidden;
            position: relative;
        }

        .avatar-placeholder {
            /* will be replaced by <img> when photo upload is added */
        }

        .profile-name {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            font-weight: 900;
            color: #f7f2ec;
        }

        .profile-email {
            font-size: 13px;
            color: #9b7e60;
            margin-top: -8px;
        }

        .profile-badge {
            background: rgba(201,123,43,0.2);
            border: 1px solid rgba(201,123,43,0.35);
            color: #C97B2B;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 50px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ── Content ── */
        .content {
            padding: 24px;
            max-width: 700px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* ── Toast ── */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(34,197,94,0.12);
            border: 1px solid rgba(34,197,94,0.25);
            color: #15803d;
        }

        .alert-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            color: #b91c1c;
        }

        /* ── Cards ── */
        .card {
            background: #fff;
            border: 1px solid rgba(201,123,43,0.15);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(26,15,8,0.07);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(201,123,43,0.1);
        }

        .card-header-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: rgba(201,123,43,0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #C97B2B;
            font-size: 13px;
        }

        .card-header span {
            font-size: 13px;
            font-weight: 700;
            color: #1a0f08;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* ── Form fields ── */
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #9b7e60;
        }

        .field input,
        .field textarea {
            background: #F7F2EC;
            border: 1.5px solid rgba(201,123,43,0.2);
            border-radius: 10px;
            padding: 12px 14px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: #1a0f08;
            transition: border-color .2s ease;
            outline: none;
            width: 100%;
        }

        .field input:focus,
        .field textarea:focus {
            border-color: #C97B2B;
            box-shadow: 0 0 0 3px rgba(201,123,43,0.12);
        }

        .field textarea {
            resize: vertical;
            min-height: 80px;
        }

        .field input[readonly] {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .field-hint {
            font-size: 11px;
            color: #9b7e60;
        }

        /* ── Buttons ── */
        .btn-save {
            background: linear-gradient(135deg, #C97B2B, #E09A4A);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 14px 24px;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 6px 18px rgba(201,123,43,0.35);
            transition: all .25s ease;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(201,123,43,0.45);
        }

        .btn-reset {
            background: #fff;
            color: #7a5c3a;
            border: 1.5px solid rgba(201,123,43,0.25);
            border-radius: 12px;
            padding: 13px 24px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all .2s ease;
        }

        .btn-reset:hover {
            border-color: #C97B2B;
            color: #C97B2B;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <a href="menu.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
    <span class="navbar-title">My Profile</span>
</nav>

<!-- Profile Header -->
<div class="profile-header">
    <div class="avatar avatar-placeholder">
        <?= htmlspecialchars($initials) ?>
    </div>
    <div>
        <div class="profile-name"><?= htmlspecialchars($user['full_name']) ?></div>
        <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
    </div>
    <div class="profile-badge">Customer</div>
</div>

<!-- Content -->
<div class="content">

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-circle-check"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Personal Information -->
    <form action="save_profile.php" method="POST">
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon"><i class="fas fa-user"></i></div>
                <span>Personal Information</span>
            </div>
            <div class="card-body">
                <div class="field">
                    <label>Full Name</label>
                    <input type="text" name="full_name"
                           value="<?= htmlspecialchars($user['full_name']) ?>"
                           placeholder="Your full name" required>
                </div>
                <div class="field">
                    <label>Email Address</label>
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>"
                           readonly>
                    <span class="field-hint">Email cannot be changed.</span>
                </div>
            </div>
        </div>

        <!-- Delivery Info -->
        <div class="card" style="margin-top:16px;">
            <div class="card-header">
                <div class="card-header-icon"><i class="fas fa-location-dot"></i></div>
                <span>Delivery Details</span>
            </div>
            <div class="card-body">
                <div class="field">
                    <label>Phone Number</label>
                    <input type="text" name="phone"
                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                           placeholder="e.g. 09171234567">
                </div>
                <div class="field">
                    <label>Delivery Address</label>
                    <textarea name="address"
                              placeholder="Your full delivery address"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-save" style="margin-top:16px;">
            <i class="fas fa-check"></i> Save Changes
        </button>
    </form>

    <!-- Reset Password -->
    <button class="btn-reset" onclick="alert('Reset password feature coming soon!')">
        <i class="fas fa-lock"></i> Reset Password
    </button>

</div>

<?php require_once BASE_PATH . '/includes/order_notif.php'; ?>
</body>
</html>