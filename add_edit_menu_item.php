<?php
require_once 'includes/db.php';
require_once 'includes/menu_functions.php';

$pageTitle = "Add New Menu Item";
$formAction = "handle_menu_item.php"; // Target script for form submission
$submitButtonText = "Save Item";
$formActionType = 'add'; // Hidden input to tell handler what to do

$itemName = '';
$itemPrice = '';
$itemCategory = '';
$itemImagePath = '';
$itemId = null;

// Check if an ID is provided for editing
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $itemId = (int)$_GET['id'];
    $item = getMenuItemById($itemId); // Function from menu_functions.php

    if ($item) {
        $pageTitle = "Edit Menu Item (ID: " . $itemId . ")";
        $submitButtonText = "Update Item";
        $formActionType = 'edit';

        $itemName = $item['name'];
        $itemPrice = $item['price'];
        $itemCategory = $item['category'];
        $itemImagePath = $item['image_path'] ?? ''; // Handle null image_path
    } else {
        // Item not found, redirect to menu page with an error message
        header("Location: menu.php?status=error&message=" . urlencode("Menu item with ID {$itemId} not found."));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - PHP POS</title>
    <!-- Bootstrap CSS CDN -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="index.php">PHP POS</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <li class="nav-item active">
                    <a class="nav-link" href="menu.php">Menu Management</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="tables.php">Table Management</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders.php">Orders</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container mt-5">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Error: <?php echo isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'An unknown error occurred.'; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo $formAction; ?>">
            <?php if ($itemId !== null): ?>
                <input type="hidden" name="id" value="<?php echo $itemId; ?>">
            <?php endif; ?>
            <input type="hidden" name="action" value="<?php echo $formActionType; ?>">

            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($itemName); ?>" required>
            </div>
            <div class="form-group">
                <label for="price">Price (€)</label>
                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($itemPrice); ?>" required>
            </div>
            <div class="form-group">
                <label for="category">Category</label>
                <input type="text" class="form-control" id="category" name="category" value="<?php echo htmlspecialchars($itemCategory); ?>">
            </div>
            <div class="form-group">
                <label for="image_path">Image Path (e.g., images/burger.jpg)</label>
                <input type="text" class="form-control" id="image_path" name="image_path" value="<?php echo htmlspecialchars($itemImagePath); ?>" placeholder="Optional">
                <small class="form-text text-muted">Relative path to an image. Ensure the image exists in the specified location.</small>
            </div>

            <button type="submit" class="btn btn-primary"><?php echo $submitButtonText; ?></button>
            <a href="menu.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>

    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">PHP POS - &copy; <?php echo date("Y"); ?></span>
        </div>
    </footer>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
