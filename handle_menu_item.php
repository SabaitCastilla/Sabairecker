<?php
// Start session if you plan to use session-based flash messages (optional)
// session_start();

require_once 'includes/db.php'; // Establishes $conn
require_once 'includes/menu_functions.php'; // Provides menu item functions

$action = '';
// Default redirect for errors or unspecified actions
$redirectURL = 'menu.php?status=error&message=' . urlencode('Invalid request or unspecified action.');

// Determine action from POST (for add/edit) or GET (for delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
}

// --- Process ADD action ---
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $price_str = trim($_POST['price'] ?? '');
    $category = trim($_POST['category'] ?? '');
    // Handle empty string for image_path as null
    $image_path = trim($_POST['image_path'] ?? '');
    if (empty($image_path)) {
        $image_path = null;
    }

    // Basic Validation
    $errors = [];
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    if (!is_numeric($price_str) || (float)$price_str < 0) {
        $errors[] = "Price must be a non-negative number.";
    } else {
        $price = (float)$price_str; // Convert to float after validation
    }
    // Category can be empty, image_path can be null.

    if (empty($errors)) {
        if (addMenuItem($name, $price, $category, $image_path)) {
            $redirectURL = 'menu.php?status=success&message=' . urlencode('Menu item added successfully!');
        } else {
            // Check mysqli_error($conn) if you want more specific DB error, but careful with exposing too much.
            $redirectURL = 'menu.php?status=error&message=' . urlencode('Failed to add menu item. Database error or duplicate entry possible.');
        }
    } else {
        // If validation fails, redirect back to form, ideally with errors and old input.
        // For simplicity now, just a generic error. A more advanced setup would repopulate the form.
        $error_message = implode(' ', $errors);
        // Redirect to add_edit_menu_item.php with error.
        // To repopulate, you'd pass parameters or use session.
        $redirectURL = 'add_edit_menu_item.php?status=error&message=' . urlencode($error_message);
        // Optionally, you can append old input to the URL if not too much data, but session is cleaner for this.
        // Example: $redirectURL .= "&name=" . urlencode($name) . "&price=" . urlencode($price_str) ...;
    }

// --- Process EDIT action ---
} elseif ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $price_str = trim($_POST['price'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $image_path = trim($_POST['image_path'] ?? '');
    if (empty($image_path)) {
        $image_path = null;
    }

    $errors = [];
    if ($id === false || $id === null) {
        $errors[] = "Invalid item ID for update.";
    }
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    if (!is_numeric($price_str) || (float)$price_str < 0) {
        $errors[] = "Price must be a non-negative number.";
    } else {
        $price = (float)$price_str;
    }

    if (empty($errors)) {
        if (updateMenuItem($id, $name, $price, $category, $image_path)) {
            $redirectURL = 'menu.php?status=success&message=' . urlencode('Menu item (ID: ' . $id . ') updated successfully!');
        } else {
            // Check if any rows were actually changed. updateMenuItem might return false if data is identical.
            // Or it could be a DB error.
            $redirectURL = 'menu.php?status=error&message=' . urlencode('Failed to update menu item (ID: ' . $id . '). Data might be unchanged or a database error occurred.');
        }
    } else {
        $error_message = implode(' ', $errors);
        // Redirect back to the edit form with error
        $redirectURL = 'add_edit_menu_item.php?id=' . $id . '&status=error&message=' . urlencode($error_message);
    }

// --- Process DELETE action ---
} elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'GET') { // Typically from a link
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || $id === null) {
        $redirectURL = 'menu.php?status=error&message=' . urlencode('Invalid or missing ID for delete action.');
    } else {
        if (deleteMenuItem($id)) {
            $redirectURL = 'menu.php?status=success&message=' . urlencode('Menu item (ID: ' . $id . ') deleted successfully!');
        } else {
            $redirectURL = 'menu.php?status=error&message=' . urlencode('Failed to delete menu item (ID: ' . $id . '). It might be in use or a database error occurred.');
        }
    }
}

// Perform the redirect
header("Location: " . $redirectURL);
exit;
?>
