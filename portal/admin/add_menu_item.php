<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';

requireAdmin();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $image_url = sanitizeInput($_POST['image_url'] ?? '');

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

        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid('menu_') . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image_url = '/uploads/menu/' . $new_filename;
            } else {
                $errors[] = 'Failed to upload image';
            }
        } else {
            $errors[] = 'Invalid image format. Allowed: JPG, PNG, GIF, WEBP';
        }
    }

    if (empty($errors)) {
        $conn = getDBConnection();

        $stmt = $conn->prepare("
            INSERT INTO menu_items (name, description, category, price, image_url, is_available, created_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->bind_param("sssds", $name, $description, $category, $price, $image_url);

        if ($stmt->execute()) {
            $menu_item_id = $conn->insert_id;

            // Log activity
            logActivity($_SESSION['user_id'], 'Add Menu Item', "Added menu item: $name");

            $_SESSION['success_message'] = "Menu item '$name' added successfully!";
            header('Location: manage_recipe.php?menu_id=' . $menu_item_id);
            exit;
        } else {
            $errors[] = 'Failed to add menu item: ' . $conn->error;
        }

        $stmt->close();
        $conn->close();
    }
}

$page_title = 'Add Menu Item';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h3 mb-0" style="color: #3b2008;">
                        <i class="fas fa-plus-circle me-2"></i>Add Menu Item
                    </h3>
                    <p class="text-muted mb-0">Create a new menu item</p>
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
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                       placeholder="e.g., Chicken Adobo"
                                       required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-layer-group me-1"></i>Category <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Rice Meals" <?php echo ($_POST['category'] ?? '') === 'Rice Meals' ? 'selected' : ''; ?>>Rice Meals</option>
                                    <option value="Desserts" <?php echo ($_POST['category'] ?? '') === 'Desserts' ? 'selected' : ''; ?>>Desserts</option>
                                    <option value="Beverages" <?php echo ($_POST['category'] ?? '') === 'Beverages' ? 'selected' : ''; ?>>Beverages</option>
                                    <option value="Snacks" <?php echo ($_POST['category'] ?? '') === 'Snacks' ? 'selected' : ''; ?>>Snacks</option>
                                    <option value="Combo Meals" <?php echo ($_POST['category'] ?? '') === 'Combo Meals' ? 'selected' : ''; ?>>Combo Meals</option>
                                </select>
                                <small class="text-muted">Or type a new category</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-align-left me-1"></i>Description
                            </label>
                            <textarea class="form-control"
                                      name="description"
                                      rows="3"
                                      placeholder="Brief description of the item"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
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
                                       value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                                       placeholder="0.00"
                                       required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-image me-1"></i>Item Image
                            </label>
                            <input type="file"
                                   class="form-control"
                                   name="image"
                                   accept="image/*"
                                   id="imageInput">
                            <small class="text-muted">Accepted formats: JPG, PNG, GIF, WEBP (Max 5MB)</small>

                            <!-- Image Preview -->
                            <div id="imagePreview" class="mt-3" style="display: none;">
                                <img id="previewImg" src="" alt="Preview" style="max-width: 300px; height: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-link me-1"></i>Or Enter Image URL
                            </label>
                            <input type="url"
                                   class="form-control"
                                   name="image_url"
                                   value="<?php echo htmlspecialchars($_POST['image_url'] ?? ''); ?>"
                                   placeholder="https://example.com/image.jpg">
                            <small class="text-muted">If you prefer to use an external image URL</small>
                        </div>

                        <div class="alert alert-warning" style="background-color: #fff3e0; border-color: #ffcc80; color: #3b2008;">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> After adding this menu item, you'll be redirected to set up the recipe (ingredients required).
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="menu_management.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-brown">
                                <i class="fas fa-save me-2"></i>Save & Setup Recipe
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

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

.btn-brown {
    background-color: #3b2008;
    border-color: #3b2008;
    color: #fff;
}

.btn-brown:hover, .btn-brown:active, .btn-brown:focus {
    background-color: #2a1505;
    border-color: #2a1505;
    color: #fff;
}

.form-select:focus,
.form-control:focus,
.form-check-input:focus {
  border-color: #3b2008 !important;
  box-shadow: 0 0 0 0.2rem rgba(59, 32, 8, 0.25) !important;
  outline: none !important;
}
.form-select { accent-color: #3b2008; }
option:checked, option:hover { background-color: #3b2008 !important; color: #fff !important; }
.dropdown-item:active, .dropdown-item.active { background-color: #3b2008 !important; color: #fff !important; }

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
    }
});

// Make category field editable
const categorySelect = document.querySelector('select[name="category"]');
categorySelect.addEventListener('change', function() {
    if (this.value === '') {
        const newCategory = prompt('Enter new category name:');
        if (newCategory) {
            const option = document.createElement('option');
            option.value = newCategory;
            option.text = newCategory;
            option.selected = true;
            this.add(option);
        }
    }
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
