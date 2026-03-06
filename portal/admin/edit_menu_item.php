<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';

requireAdmin();


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
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h3 mb-0" style="color: #3b2008;">
                        <i class="fas fa-edit me-2"></i>Edit Menu Item
                    </h3>
                    <p class="text-muted mb-0">Update menu item details</p>
                </div>
                <a href="menu_management.php" class="btn btn-outline-secondary">
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
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header text-white" style="background-color: #3b2008;">
                    <h5 class="mb-0"><i class="fas fa-utensils me-2"></i>Menu Item Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-tag me-1"></i>Item Name <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       name="name"
                                       value="<?php echo htmlspecialchars($item['name']); ?>"
                                       placeholder="e.g., Chicken Adobo"
                                       required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-layer-group me-1"></i>Category <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Rice Meals" <?php echo $item['category'] === 'Rice Meals' ? 'selected' : ''; ?>>Rice Meals</option>
                                    <option value="Desserts" <?php echo $item['category'] === 'Desserts' ? 'selected' : ''; ?>>Desserts</option>
                                    <option value="Beverages" <?php echo $item['category'] === 'Beverages' ? 'selected' : ''; ?>>Beverages</option>
                                    <option value="Snacks" <?php echo $item['category'] === 'Snacks' ? 'selected' : ''; ?>>Snacks</option>
                                    <option value="Combo Meals" <?php echo $item['category'] === 'Combo Meals' ? 'selected' : ''; ?>>Combo Meals</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-align-left me-1"></i>Description
                            </label>
                            <textarea class="form-control"
                                      name="description"
                                      rows="3"
                                      placeholder="Brief description of the item"><?php echo htmlspecialchars($item['description']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-peso-sign me-1"></i>Price <span class="text-danger">*</span>
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
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-image me-1"></i>Item Image
                            </label>

                            <?php if (!empty($item['image_url'])): ?>
                                <div class="mb-3 position-relative" id="currentImageContainer">
                                    <div class="d-flex align-items-start gap-3">
                                        <img src="<?php echo htmlspecialchars(rtrim(BASE_URL, '/') . '/' . ltrim($item['image_url'], '/')); ?>"
                                             alt="Current image"
                                             class="current-menu-image"
                                             onerror="this.src='<?php echo BASE_URL; ?>/assets/img/no-image.png';">
                                        <div>
                                            <p class="text-muted small mb-2">Current image</p>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmRemoveImage()">
                                                <i class="fas fa-trash me-1"></i>Remove Image
                                            </button>
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
                            </small>

                            <!-- Image Preview -->
                            <div id="imagePreview" class="mt-3" style="display: none;">
                                <p class="fw-bold text-muted small">New image preview:</p>
                                <img id="previewImg" src="" alt="Preview" class="preview-menu-image">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">
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
                                <label class="form-check-label fw-bold" for="is_available">
                                    <i class="fas fa-eye me-1"></i>Item is available for sale
                                </label>
                            </div>
                            <small class="text-muted">Unchecking this will hide the item from POS</small>
                        </div>

                        <div class="d-flex gap-2 justify-content-between">
                            <a href="manage_recipe.php?menu_id=<?php echo $menu_item_id; ?>" class="btn btn-outline-warning">
                                <i class="fas fa-book me-2"></i>Manage Recipe
                            </a>
                            <div class="d-flex gap-2">
                                <a href="menu_management.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-save me-2"></i>Update Item
                                </button>
                            </div>
                        </div>
                    </form>
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
.text-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.card {
    border: none;
}

.shadow-sm {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
}

.btn-danger {
    background-color: #3b2008;
    border-color: #3b2008;
}

.btn-danger:hover {
    background-color: #2a1505;
    border-color: #2a1505;
}

.form-switch .form-check-input {
    width: 3rem;
    height: 1.5rem;
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
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
