<?php
session_start();
require_once 'includes/db.php'; // For $conn and basic DB setup
require_once 'includes/order_functions.php'; // Contains createOrder()
// table_functions.php is already included within order_functions.php if createOrder calls updateTableStatus,
// but including it here explicitly isn't harmful if direct calls were ever made.
// However, based on previous design, order_functions.php handles its own dependencies.

// Default error feedback (should be overwritten by more specific messages)
$_SESSION['feedback_message'] = 'An unexpected error occurred while trying to create the order.';
$_SESSION['feedback_type'] = 'error';

$table_id = filter_var($_GET['table_id'] ?? null, FILTER_VALIDATE_INT);

if ($table_id === false || $table_id === null) {
    $_SESSION['feedback_message'] = 'Invalid or missing table ID specified for creating a new order.';
    $_SESSION['feedback_type'] = 'error';
    header("Location: tables.php");
    exit;
}

// Before creating an order, it's good practice to check if the table is actually available,
// even if the UI should prevent this. This is a server-side validation.
// The createOrder function itself might also do this or rely on updateTableStatus to fail gracefully.
// For now, we assume createOrder handles the logic of table availability or that updateTableStatus within it does.
// A more robust check here could be:
/*
require_once 'includes/table_functions.php'; // Explicitly include for getTableById if needed
$table_info = getTableById($table_id);
if (!$table_info) {
    $_SESSION['feedback_message'] = "Table with ID {$table_id} not found.";
    $_SESSION['feedback_type'] = 'error';
    header("Location: tables.php");
    exit;
}
if ($table_info['status'] !== 'available') {
    $_SESSION['feedback_message'] = "Table ".htmlspecialchars($table_info['table_number'])." is currently " . htmlspecialchars($table_info['status']) . " and cannot have a new order started.";
    $_SESSION['feedback_type'] = 'error';
    header("Location: tables.php");
    exit;
}
*/

// Attempt to create the order.
// The createOrder function (from order_functions.php) is expected to:
// 1. Insert a new order into the 'orders' table with 'open' status.
// 2. Call updateTableStatus($table_id, 'occupied') (from table_functions.php).
// 3. Return the new order_id on success, or false on failure.
$order_id = createOrder($table_id);

if ($order_id !== false && $order_id > 0) {
    // Successfully created the order.
    // Redirect to a page where items can be added to this new order.
    // We can also set a success message if view_order.php is designed to show it.
    $_SESSION['feedback_message'] = 'New order (ID: ' . $order_id . ') created successfully for table ID ' . $table_id . '. Add items to the order.';
    $_SESSION['feedback_type'] = 'success'; // view_order.php should display this
    header("Location: view_order.php?order_id=" . $order_id);
    exit;
} else {
    // Order creation failed. createOrder should ideally log more specific errors.
    // The feedback message from createOrder (if any was set to session) might be overwritten here.
    // For now, a generic message.
    $_SESSION['feedback_message'] = "Failed to create a new order for table ID {$table_id}. The table might have become unavailable, or an internal error occurred.";
    $_SESSION['feedback_type'] = 'error';
    header("Location: tables.php"); // Redirect back to the tables page
    exit;
}
?>
