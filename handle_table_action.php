<?php
session_start();
require_once 'includes/db.php';        // For $conn
require_once 'includes/table_functions.php'; // For table manipulation functions

// Default feedback
$_SESSION['feedback_message'] = 'Invalid request or action specified.';
$_SESSION['feedback_type'] = 'error';

$action = $_GET['action'] ?? null;
$table_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$table_id) {
    $_SESSION['feedback_message'] = 'Table ID is missing or invalid.';
    $_SESSION['feedback_type'] = 'error';
    header("Location: tables.php");
    exit;
}

// --- Process 'update_status' action ---
if ($action === 'update_status') {
    $new_status = $_GET['status'] ?? '';
    $allowed_statuses = ['available', 'occupied', 'reserved'];

    if (in_array($new_status, $allowed_statuses)) {
        // Additional check: Prevent setting to 'available' if there are 'open' orders for this table.
        // This logic might be better within updateTableStatus or a dedicated business logic layer,
        // but can be here for now.
        if ($new_status === 'available') {
            $check_open_orders_sql = "SELECT COUNT(*) as open_order_count FROM orders WHERE table_id = ? AND status = 'open'";
            if ($stmt_check = mysqli_prepare($conn, $check_open_orders_sql)) {
                mysqli_stmt_bind_param($stmt_check, "i", $table_id);
                mysqli_stmt_execute($stmt_check);
                $result_check = mysqli_stmt_get_result($stmt_check);
                $row_check = mysqli_fetch_assoc($result_check);
                mysqli_stmt_close($stmt_check);

                if ($row_check && $row_check['open_order_count'] > 0) {
                    $_SESSION['feedback_message'] = "Cannot mark table as 'available' as it has {$row_check['open_order_count']} open order(s). Please close or cancel them first.";
                    $_SESSION['feedback_type'] = 'error';
                    header("Location: tables.php");
                    exit;
                }
            }
        }


        if (updateTableStatus($table_id, $new_status)) {
            $_SESSION['feedback_message'] = "Table #{$table_id} status successfully updated to '" . htmlspecialchars(ucfirst($new_status)) . "'.";
            $_SESSION['feedback_type'] = 'success';
        } else {
            // The function updateTableStatus returning false could mean DB error or no change needed.
            // For simplicity, we'll assume it's an error if user explicitly clicked a button.
            $_SESSION['feedback_message'] = "Failed to update status for table #{$table_id}. The status might be unchanged or a database error occurred.";
            $_SESSION['feedback_type'] = 'error';
        }
    } else {
        $_SESSION['feedback_message'] = 'Invalid new status provided for table update.';
        $_SESSION['feedback_type'] = 'error';
    }

// --- Process 'delete_table' action ---
} elseif ($action === 'delete_table') {
    // The deleteTable() function in table_functions.php already includes a check for associated orders.
    if (deleteTable($table_id)) { // deleteTable returns true on success, false if orders exist or DB error
        $_SESSION['feedback_message'] = "Table #{$table_id} deleted successfully.";
        $_SESSION['feedback_type'] = 'success';
    } else {
        // This message is generic. deleteTable logs specific reason (e.g., has orders).
        // We can retrieve a more specific message if deleteTable was designed to return one, or set one in session from there.
        $_SESSION['feedback_message'] = "Failed to delete table #{$table_id}. It might have associated orders, or a database error occurred.";
        $_SESSION['feedback_type'] = 'error';
    }
} else {
    // Action was not 'update_status' or 'delete_table', or was missing
    $_SESSION['feedback_message'] = 'Unknown or unspecified action for table management.';
    $_SESSION['feedback_type'] = 'error';
}

header("Location: tables.php");
exit;
?>
