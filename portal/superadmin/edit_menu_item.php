<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';

requireSuperAdmin();

$errors = [];
$menu_item_id = (int)($_GET['id'] ?? 0);

if ($menu_item_id === 0) {
    $_SESSION['error_message'] = 'Invalid menu item ID';
    header('Location: menu_management.php');
    exit;
}

$conn = getDBConnection();

// Get menu item
$stmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ?");
$stmt->bind_param("i", $menu_item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    $_SESSION['error_message'] = 'Menu item not found';
    header('Location: menu_management.php');
    exit;
}

// Handle remove image request
if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
    // Delete old image if exists
    if (!empty($item['image_url']) && file_exists(BASE_PATH . $item['image_url'])) {
        unlink(BASE_PATH . $item['image_url']);
    }

    $stmt = $conn->prepare("UPDATE menu_items SET image_url = NULL WHERE id = ?");
    $stmt->bind_param("i", $menu_item_id);

    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'Remove Menu Item Image', "Removed image from menu item: {$item['name']}");
        $_SESSION['success_message'] = "Image removed successfully!";
        header('Location: edit_menu_item.php?id=' . $menu_item_id);
        exit;
    } else {
        $errors[] = 'Failed to remove image';
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['remove_image'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $image_url = sanitizeInput($_POST['image_url'] ?? '');
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    // Keep existing image URL
    if (empty($image_url)) {
        $image_url = $item['image_url'];
    }

    // Validation
    if (empty($name)) {
        $errors[] = 'Menu item name is required';
    }
    if (empty($category)) {
        $errors[] = 'Category is required';
    }
    if ($price <= 0) {
        $errors[] = 'Price must be greater than 0';
    }

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = BASE_PATH . '/uploads/menu/';

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid('menu_') . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if (!empty($item['image_url']) && file_exists(BASE_PATH . $item['image_url'])) {
                    unlink(BASE_PATH . $item['image_url']);
                }
                $image_url = '/uploads/menu/' . $new_filename;
            } else {
                $errors[] = 'Failed to upload image';
            }
        } else {
            $errors[] = 'Invalid image format. Allowed: JPG, PNG, GIF, WEBP';
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE menu_items
            SET name = ?, description = ?, category = ?, price = ?, image_url = ?, is_available = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssdsii", $name, $description, $category, $price, $image_url, $is_available, $menu_item_id);

        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'Update Menu Item', "Updated menu item: $name");

            $_SESSION['success_message'] = "Menu item '$name' updated successfully!";
            header('Location: menu_management.php');
            exit;
        } else {
            $errors[] = 'Failed to update menu item: ' . $conn->error;
        }

        $stmt->close();
    }
}

// Refresh item data if image was just removed
if (isset($_SESSION['success_message'])) {
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ?");
    $stmt->bind_param("i", $menu_item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
}

$page_title = 'Edit Menu Item';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h3 mb-0" style="color: #4a301f;">
                        <i class="fas fa-edit me-2"></i>Edit Menu Item
                    </h3>
                    <p class="text-muted mb-0">Update menu item details for <strong class="text-brown"><?php echo htmlspecialchars($item['name']); ?></strong></p>
                </div>
                <a href="menu_management.php" class="btn btn-outline-brown">
                    <i class="fas fa-arrow-left me-2"></i>Back to Menu
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Error!</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-utensils me-2 icon-brown"></i>Menu Item Details
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-tag me-1"></i>Item Name <span class="text-brown">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       name="name"
                                       value="<?php echo htmlspecialchars($item['name']); ?>"
                                       placeholder="e.g., Chicken Adobo"
                                       required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-layer-group me-1"></i>Category <span class="text-brown">*</span>
                                </label>
                                <select class="form-select" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Desserts" <?php echo $item['category'] === 'Desserts' ? 'selected' : ''; ?>>Desserts</option>
                                    <option value="Drinks" <?php echo $item['category'] === 'Drinks' ? 'selected' : ''; ?>>Drinks</option>
                                    <option value="Cookies" <?php echo $item['category'] === 'Cookies' ? 'selected' : ''; ?>>Cookies</option>
                                    <option value="Waffles" <?php echo $item['category'] === 'Waffles' ? 'selected' : ''; ?>>Waffles</option>
                                    <option value="Muffins" <?php echo $item['category'] === 'Muffins' ? 'selected' : ''; ?>>Muffins</option>
                                    <option value="Pasta" <?php echo $item['category'] === 'Pasta' ? 'selected' : ''; ?>>Pasta</option>
                                    <option value="Rice Bowls" <?php echo $item['category'] === 'Rice Bowls' ? 'selected' : ''; ?>>Rice Bowls</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-align-left me-1"></i>Description <small class="text-muted">(Optional)</small>
                            </label>
                            <textarea class="form-control"
                                      name="description"
                                      rows="3"
                                      placeholder="Brief description of the item..."><?php echo htmlspecialchars($item['description']); ?></textarea>
                            <small class="text-muted">Provide details about this menu item</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-peso-sign me-1"></i>Price <span class="text-brown">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number"
                                       class="form-control"
                                       name="price"
                                       step="0.01"
                                       min="0.01"
                                       value="<?php echo htmlspecialchars($item['price']); ?>"
                                       placeholder="0.00"
                                       required>
                            </div>
                            <small class="text-muted">Current selling price</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-image me-1"></i>Item Image
                            </label>

                            <?php if (!empty($item['image_url'])): ?>
                                <div class="mb-3 position-relative" id="currentImageContainer">
                                    <div class="current-image-wrapper p-3 border rounded bg-light">
                                        <div class="d-flex align-items-start gap-3">
                                            <img src="<?php echo htmlspecialchars(rtrim(BASE_URL, '/') . '/' . ltrim($item['image_url'], '/')); ?>"
                                                 alt="Current image"
                                                 class="current-menu-image"
                                                 onerror="this.src='<?php echo BASE_URL; ?>/assets/img/no-image.png';">
                                            <div class="flex-grow-1">
                                                <p class="text-muted small mb-1"><i class="fas fa-check-circle text-success me-1"></i><strong>Current image</strong></p>
                                                <p class="text-muted small mb-2">This image is currently displayed for this menu item</p>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmRemoveImage()">
                                                    <i class="fas fa-trash me-1"></i>Remove Image
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <input type="file"
                                   class="form-control"
                                   name="image"
                                   accept="image/*"
                                   id="imageInput">
                            <small class="text-muted">
                                <?php echo !empty($item['image_url']) ? 'Upload a new image to replace the current one' : 'Upload an image for this menu item'; ?>
                                (JPG, PNG, GIF, WEBP - Max 5MB)
                            </small>

                            <!-- Image Preview -->
                            <div id="imagePreview" class="mt-3" style="display: none;">
                                <div class="p-3 border rounded bg-light">
                                    <p class="small mb-2"><i class="fas fa-eye text-brown me-1"></i><strong>New image preview:</strong></p>
                                    <img id="previewImg" src="" alt="Preview" class="preview-menu-image">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-link me-1"></i>Or Enter Image URL
                            </label>
                            <input type="url"
                                   class="form-control"
                                   name="image_url"
                                   value="<?php echo htmlspecialchars($item['image_url'] ?? ''); ?>"
                                   placeholder="https://example.com/image.jpg">
                            <small class="text-muted">If you prefer to use an external image URL</small>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="is_available"
                                       name="is_available"
                                       <?php echo $item['is_available'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_available">
                                    <i class="fas fa-eye me-1"></i>Item is available for sale
                                </label>
                            </div>
                            <small class="text-muted">Unchecking this will hide the item from POS and customer ordering</small>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex gap-2 justify-content-between align-items-center flex-wrap">
                            <a href="manage_recipe.php?menu_id=<?php echo $menu_item_id; ?>" class="btn btn-outline-warning">
                                <i class="fas fa-book me-2"></i>Manage Recipe
                            </a>
                            <div class="d-flex gap-2">
                                <a href="menu_management.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-brown">
                                    <i class="fas fa-save me-2"></i>Update Item
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Info Sidebar -->
        <div class="col-lg-4">
            <!-- Item Status Card -->
            <div class="card mb-3">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2 text-info"></i>Item Status
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                        <div>
                            <p class="text-muted small mb-0">Availability</p>
                            <p class="mb-0 fw-bold">
                                <?php if ($item['is_available']): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i>Available
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times-circle me-1"></i>Unavailable
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="mb-3 pb-3 border-bottom">
                        <p class="text-muted small mb-1">Category</p>
                        <p class="mb-0 fw-bold text-brown"><?php echo htmlspecialchars($item['category']); ?></p>
                    </div>

                    <div class="mb-3 pb-3 border-bottom">
                        <p class="text-muted small mb-1">Current Price</p>
                        <p class="mb-0 fw-bold" style="font-size: 1.25rem; color: #4a301f;">₱<?php echo number_format($item['price'], 2); ?></p>
                    </div>

                    <div>
                        <p class="text-muted small mb-1">Created</p>
                        <p class="mb-0 small"><?php echo date('M d, Y', strtotime($item['created_at'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card mb-3">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0">
                        <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="manage_recipe.php?menu_id=<?php echo $menu_item_id; ?>" class="btn btn-outline-brown btn-sm">
                            <i class="fas fa-list me-2"></i>View/Edit Recipe
                        </a>
                        <button type="button" class="btn btn-outline-brown btn-sm" onclick="toggleAvailability()">
                            <i class="fas fa-toggle-on me-2"></i>Toggle Availability
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tips Card -->
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb me-2 text-warning"></i>Tips
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-brown me-2"></i>
                            Keep item names clear and descriptive
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-brown me-2"></i>
                            Use high-quality images for better appeal
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-brown me-2"></i>
                            Update prices regularly based on costs
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-check-circle text-brown me-2"></i>
                            Disable items that are temporarily out of stock
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Remove Image Confirmation Form (Hidden) -->
<form method="POST" id="removeImageForm" style="display: none;">
    <input type="hidden" name="remove_image" value="1">
</form>

<style>
body {
    background: linear-gradient(135deg, #f5f0eb 0%, #e8ddd4 100%);
    min-height: 100vh;
}

.icon-brown {
    color: #4a301f;
}

.text-brown {
    color: #4a301f !important;
}

.btn-brown {
    background-color: #382417;
    border-color: #382417;
    color: white;
}

.btn-brown:hover {
    background-color: #4d3420;
    border-color: #4d3420;
    color: white;
}

.btn-outline-brown {
    color: #382417;
    border-color: #382417;
    background-color: transparent;
}

.btn-outline-brown:hover {
    background-color: #382417;
    border-color: #382417;
    color: white;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.form-label {
    font-weight: 500;
    color: #495057;
}

.form-control:focus,
.form-select:focus {
    border-color: #654529;
    box-shadow: 0 0 0 0.2rem rgba(101, 69, 41, 0.25);
}

.form-switch .form-check-input {
    width: 3rem;
    height: 1.5rem;
}

.form-switch .form-check-input:checked {
    background-color: #382417;
    border-color: #382417;
}

.form-switch .form-check-input:focus {
    border-color: #654529;
    box-shadow: 0 0 0 0.2rem rgba(101, 69, 41, 0.25);
}

.current-menu-image {
    max-width: 200px;
    height: auto;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 2px solid #dee2e6;
}

.preview-menu-image {
    max-width: 300px;
    height: auto;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 2px solid #dee2e6;
}

.current-image-wrapper {
    background-color: #fffbf5;
    border-color: #e0d4c3 !important;
}

.badge {
    font-size: 0.8rem;
    padding: 0.4rem 0.6rem;
}
</style>

<script>
// Image preview
document.getElementById('imageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
        }
        reader.readAsDataURL(file);
    } else {
        document.getElementById('imagePreview').style.display = 'none';
    }
});

// Confirm remove image
function confirmRemoveImage() {
    if (confirm('Are you sure you want to remove this image? This action cannot be undone.')) {
        document.getElementById('removeImageForm').submit();
    }
}

// Toggle availability shortcut
function toggleAvailability() {
    const checkbox = document.getElementById('is_available');
    checkbox.checked = !checkbox.checked;

    // Highlight the switch
    checkbox.parentElement.classList.add('bg-light', 'p-2', 'rounded');
    setTimeout(() => {
        checkbox.parentElement.classList.remove('bg-light', 'p-2', 'rounded');
    }, 1000);

    // Scroll to switch
    checkbox.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
