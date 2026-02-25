<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';

requireSuperAdmin();

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
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h3 mb-0" style="color: #4a301f;">
                        <i class="fas fa-plus-circle me-2"></i>Add New Menu Item
                    </h3>
                    <p class="text-muted mb-0">Create a new menu item for your restaurant</p>
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
                    <form method="POST" enctype="multipart/form-data" id="addMenuForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-tag me-1"></i>Item Name <span class="text-brown">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       name="name"
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                       placeholder="e.g., Chicken Adobo"
                                       required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-layer-group me-1"></i>Category <span class="text-brown">*</span>
                                </label>
                                <select class="form-select" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Rice Meals" <?php echo ($_POST['category'] ?? '') === 'Rice Meals' ? 'selected' : ''; ?>>Rice Meals</option>
                                    <option value="Desserts" <?php echo ($_POST['category'] ?? '') === 'Desserts' ? 'selected' : ''; ?>>Desserts</option>
                                    <option value="Beverages" <?php echo ($_POST['category'] ?? '') === 'Beverages' ? 'selected' : ''; ?>>Beverages</option>
                                    <option value="Snacks" <?php echo ($_POST['category'] ?? '') === 'Snacks' ? 'selected' : ''; ?>>Snacks</option>
                                    <option value="Combo Meals" <?php echo ($_POST['category'] ?? '') === 'Combo Meals' ? 'selected' : ''; ?>>Combo Meals</option>
                                </select>
                                <small class="text-muted">Choose from existing categories</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-align-left me-1"></i>Description <small class="text-muted">(Optional)</small>
                            </label>
                            <textarea class="form-control"
                                      name="description"
                                      rows="3"
                                      placeholder="Brief description of the item..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
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
                                       value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                                       placeholder="0.00"
                                       required>
                            </div>
                            <small class="text-muted">Set the selling price for this item</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
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
                            <label class="form-label">
                                <i class="fas fa-link me-1"></i>Or Enter Image URL
                            </label>
                            <input type="url"
                                   class="form-control"
                                   name="image_url"
                                   value="<?php echo htmlspecialchars($_POST['image_url'] ?? ''); ?>"
                                   placeholder="https://example.com/image.jpg">
                            <small class="text-muted">If you prefer to use an external image URL</small>
                        </div>

                        <div class="alert alert-info-brown">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>
                                <strong>Note:</strong> After adding this menu item, you'll be redirected to set up the recipe (ingredients required).
                            </small>
                        </div>

                        <hr class="my-4">

                        <div class="row">
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-brown w-100">
                                    <i class="fas fa-save me-2"></i>Save & Setup Recipe
                                </button>
                            </div>
                            <div class="col-md-6">
                                <a href="menu_management.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Examples Sidebar -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb me-2 text-warning"></i>Popular Menu Items
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Click on an example to quick-fill the form</p>

                    <div class="example-item" data-name="Chicken Adobo" data-category="Rice Meals" data-price="120.00" data-desc="Classic Filipino braised chicken in soy sauce and vinegar">
                        <div class="d-flex align-items-center mb-3 p-3 border rounded hover-shadow cursor-pointer">
                            <div class="example-icon me-3">
                                <i class="fas fa-drumstick-bite fa-2x text-warning"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Chicken Adobo</h6>
                                <small class="text-muted">Rice Meals - ₱120.00</small>
                            </div>
                        </div>
                    </div>

                    <div class="example-item" data-name="Beef Sinigang" data-category="Rice Meals" data-price="150.00" data-desc="Savory and sour beef soup with vegetables">
                        <div class="d-flex align-items-center mb-3 p-3 border rounded hover-shadow cursor-pointer">
                            <div class="example-icon me-3">
                                <i class="fas fa-pepper-hot fa-2x" style="color: #dc2626;"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Beef Sinigang</h6>
                                <small class="text-muted">Rice Meals - ₱150.00</small>
                            </div>
                        </div>
                    </div>

                    <div class="example-item" data-name="Halo-Halo" data-category="Desserts" data-price="80.00" data-desc="Mixed shaved ice dessert with sweet beans, fruits, and ice cream">
                        <div class="d-flex align-items-center mb-3 p-3 border rounded hover-shadow cursor-pointer">
                            <div class="example-icon me-3">
                                <i class="fas fa-ice-cream fa-2x" style="color: #a855f7;"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Halo-Halo</h6>
                                <small class="text-muted">Desserts - ₱80.00</small>
                            </div>
                        </div>
                    </div>

                    <div class="example-item" data-name="Mango Shake" data-category="Beverages" data-price="60.00" data-desc="Refreshing shake made with fresh Philippine mangoes">
                        <div class="d-flex align-items-center mb-3 p-3 border rounded hover-shadow cursor-pointer">
                            <div class="example-icon me-3">
                                <i class="fas fa-glass-martini fa-2x text-warning"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Mango Shake</h6>
                                <small class="text-muted">Beverages - ₱60.00</small>
                            </div>
                        </div>
                    </div>

                    <div class="example-item" data-name="Lumpia Shanghai" data-category="Snacks" data-price="45.00" data-desc="Crispy fried spring rolls with ground pork filling">
                        <div class="d-flex align-items-center mb-3 p-3 border rounded hover-shadow cursor-pointer">
                            <div class="example-icon me-3">
                                <i class="fas fa-bacon fa-2x icon-brown"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Lumpia Shanghai</h6>
                                <small class="text-muted">Snacks - ₱45.00</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tips Card -->
            <div class="card mt-3">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb me-2 text-info"></i>Best Practices
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-brown me-2"></i>
                            Use clear and appetizing item names
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-brown me-2"></i>
                            Add high-quality photos to attract customers
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-brown me-2"></i>
                            Write descriptions that highlight key ingredients
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-check-circle text-brown me-2"></i>
                            Set competitive prices based on costs
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
body {
    background: linear-gradient(135deg, #f5f0eb 0%, #e8ddd4 100%);
    min-height: 100vh;
}

.icon-brown {
    color: #4a301f;
}

.text-brown {
    color: #4a301f;
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

.hover-shadow {
    transition: all 0.3s ease;
}

.hover-shadow:hover {
    box-shadow: 0 0.25rem 0.5rem rgba(74, 48, 31, 0.15);
    transform: translateY(-2px);
    border-color: #654529 !important;
}

.cursor-pointer {
    cursor: pointer;
}

.example-icon {
    width: 50px;
    text-align: center;
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

.example-item.active .hover-shadow {
    background-color: #fff3e0;
    border-color: #654529 !important;
}

.alert-info-brown {
    background-color: #fff3e0;
    border: 1px solid #ffcc80;
    color: #4a301f;
    border-radius: 0.375rem;
}

.alert-info-brown strong {
    color: #382417;
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
    }
});

// Example click handler with jQuery
$(document).ready(function() {
    $('.example-item').on('click', function() {
        const name = $(this).data('name');
        const category = $(this).data('category');
        const price = $(this).data('price');
        const desc = $(this).data('desc');

        $('input[name="name"]').val(name);
        $('select[name="category"]').val(category);
        $('input[name="price"]').val(price);
        $('textarea[name="description"]').val(desc);

        // Visual feedback
        $('.example-item').removeClass('active');
        $(this).addClass('active');

        // Scroll to form
        $('html, body').animate({
            scrollTop: $('#addMenuForm').offset().top - 100
        }, 500);

        // Show success feedback
        $(this).find('.hover-shadow').addClass('bg-light');
        setTimeout(() => {
            $(this).find('.hover-shadow').removeClass('bg-light');
        }, 1000);
    });
});

// Make category field editable (legacy support)
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
