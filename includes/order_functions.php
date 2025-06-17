<?php
require_once 'db.php'; // Ensures $conn is available
require_once 'menu_functions.php'; // For getMenuItemById()
require_once 'table_functions.php'; // For updateTableStatus()

/**
 * Creates a new order for a given table.
 *
 * @global mysqli $conn The database connection object.
 * @param int $table_id The ID of the table for which the order is created.
 * @return int|false The ID of the newly created order, or false on failure.
 */
function createOrder(int $table_id): int|false {
    global $conn;
    $sql = "INSERT INTO orders (table_id, status, total_amount) VALUES (?, 'open', 0.00)";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $table_id);
        if (mysqli_stmt_execute($stmt)) {
            $order_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            // Optionally update table status to 'occupied'
            updateTableStatus($table_id, 'occupied'); // From table_functions.php
            return $order_id;
        } else {
            // error_log("Error executing createOrder statement: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        }
    } else {
        // error_log("Error preparing createOrder statement: " . mysqli_error($conn));
    }
    return false;
}

/**
 * Adds or updates an item in an order. If item exists, quantity is updated.
 * Also updates the total amount of the order.
 *
 * @global mysqli $conn The database connection object.
 * @param int $order_id The ID of the order.
 * @param int $menu_item_id The ID of the menu item.
 * @param int $quantity The quantity of the menu item. Must be > 0.
 * @return bool True on success, false on failure.
 */
function addOrderItem(int $order_id, int $menu_item_id, int $quantity): bool {
    global $conn;

    if ($quantity <= 0) {
        // error_log("Quantity must be positive for addOrderItem.");
        return false;
    }

    $menu_item = getMenuItemById($menu_item_id); // From menu_functions.php
    if (!$menu_item) {
        // error_log("Menu item ID {$menu_item_id} not found for addOrderItem.");
        return false; // Menu item not found
    }
    $price = (float)$menu_item['price'];
    $subtotal = $price * $quantity;

    // Check if item already exists in order_items for this order
    $check_sql = "SELECT id, quantity FROM order_items WHERE order_id = ? AND menu_item_id = ?";
    $existing_item_id = null;
    $new_quantity = $quantity;

    if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
        mysqli_stmt_bind_param($check_stmt, "ii", $order_id, $menu_item_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $existing_item_id = $row['id'];
            $new_quantity = $row['quantity'] + $quantity; // Add to existing quantity
            $subtotal = $price * $new_quantity; // Recalculate subtotal
        }
        mysqli_stmt_close($check_stmt);
    }

    mysqli_autocommit($conn, false); // Start transaction

    if ($existing_item_id) {
        // Update existing item
        $sql = "UPDATE order_items SET quantity = ?, subtotal = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "idi", $new_quantity, $subtotal, $existing_item_id);
            if (!mysqli_stmt_execute($stmt)) {
                // error_log("Error updating order item: " . mysqli_stmt_error($stmt));
                mysqli_rollback($conn);
                mysqli_autocommit($conn, true);
                return false;
            }
            mysqli_stmt_close($stmt);
        } else {
            // error_log("Error preparing update order_items statement: " . mysqli_error($conn));
            mysqli_rollback($conn);
            mysqli_autocommit($conn, true);
            return false;
        }
    } else {
        // Insert new item
        $sql = "INSERT INTO order_items (order_id, menu_item_id, quantity, subtotal) VALUES (?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iiid", $order_id, $menu_item_id, $quantity, $subtotal);
            if (!mysqli_stmt_execute($stmt)) {
                // error_log("Error inserting new order item: " . mysqli_stmt_error($stmt));
                mysqli_rollback($conn);
                mysqli_autocommit($conn, true);
                return false;
            }
            mysqli_stmt_close($stmt);
        } else {
            // error_log("Error preparing insert order_items statement: " . mysqli_error($conn));
            mysqli_rollback($conn);
            mysqli_autocommit($conn, true);
            return false;
        }
    }

    if (updateOrderTotalAmount($order_id)) {
        mysqli_commit($conn);
        mysqli_autocommit($conn, true);
        return true;
    } else {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        // error_log("Failed to update order total amount for order ID {$order_id}.");
        return false;
    }
}


/**
 * Fetches details of a specific order, including its items.
 *
 * @global mysqli $conn The database connection object.
 * @param int $order_id The ID of the order.
 * @return array|null An associative array with order details and items, or null if not found/error.
 *                    Example: ['id' => 1, 'table_id' => 2, 'status' => 'open', ..., 'items' => [[...], ...]]
 */
function getOrderDetails(int $order_id): ?array {
    global $conn;
    $order_details = null;

    // Fetch main order details
    $sql_order = "SELECT o.id, o.table_id, t.table_number, o.order_time, o.status, o.total_amount, o.created_at, o.updated_at
                  FROM orders o
                  LEFT JOIN tables t ON o.table_id = t.id
                  WHERE o.id = ?";
    if ($stmt_order = mysqli_prepare($conn, $sql_order)) {
        mysqli_stmt_bind_param($stmt_order, "i", $order_id);
        if (mysqli_stmt_execute($stmt_order)) {
            $result_order = mysqli_stmt_get_result($stmt_order);
            if ($order_data = mysqli_fetch_assoc($result_order)) {
                $order_details = $order_data;
            }
            mysqli_free_result($result_order);
        } else {
            // error_log("Error executing getOrderDetails (order) statement: " . mysqli_stmt_error($stmt_order));
            mysqli_stmt_close($stmt_order);
            return null;
        }
        mysqli_stmt_close($stmt_order);
    } else {
        // error_log("Error preparing getOrderDetails (order) statement: " . mysqli_error($conn));
        return null;
    }

    if (!$order_details) {
        return null; // Order not found
    }

    // Fetch order items
    $sql_items = "SELECT oi.id, oi.menu_item_id, mi.name as menu_item_name, mi.price as item_price, oi.quantity, oi.subtotal
                  FROM order_items oi
                  JOIN menu_items mi ON oi.menu_item_id = mi.id
                  WHERE oi.order_id = ?";
    $items = [];
    if ($stmt_items = mysqli_prepare($conn, $sql_items)) {
        mysqli_stmt_bind_param($stmt_items, "i", $order_id);
        if (mysqli_stmt_execute($stmt_items)) {
            $result_items = mysqli_stmt_get_result($stmt_items);
            while ($item_row = mysqli_fetch_assoc($result_items)) {
                $items[] = $item_row;
            }
            mysqli_free_result($result_items);
        } else {
            // error_log("Error executing getOrderDetails (items) statement: " . mysqli_stmt_error($stmt_items));
            // Continue to return order details even if items fail, or handle differently
        }
        mysqli_stmt_close($stmt_items);
    } else {
        // error_log("Error preparing getOrderDetails (items) statement: " . mysqli_error($conn));
    }

    $order_details['items'] = $items;
    return $order_details;
}

/**
 * Updates the status of an order.
 * Optionally updates table status if order is closed or paid.
 *
 * @global mysqli $conn The database connection object.
 * @param int $order_id The ID of the order to update.
 * @param string $status The new status (e.g., 'open', 'closed', 'paid', 'cancelled').
 * @return bool True on success, false on failure.
 */
function updateOrderStatus(int $order_id, string $status): bool {
    global $conn;

    if (!in_array($status, ['open', 'closed', 'paid', 'cancelled'])) {
        // error_log("Invalid status value for updateOrderStatus: " . $status);
        return false;
    }

    // Fetch table_id first if we need to update table status
    $table_id = null;
    if (in_array($status, ['closed', 'paid'])) {
        $order_data_sql = "SELECT table_id FROM orders WHERE id = ?";
        if ($stmt_get_table = mysqli_prepare($conn, $order_data_sql)) {
            mysqli_stmt_bind_param($stmt_get_table, "i", $order_id);
            if (mysqli_stmt_execute($stmt_get_table)) {
                $result_table = mysqli_stmt_get_result($stmt_get_table);
                if ($row = mysqli_fetch_assoc($result_table)) {
                    $table_id = $row['table_id'];
                }
            }
            mysqli_stmt_close($stmt_get_table);
        }
    }

    $sql = "UPDATE orders SET status = ? WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "si", $status, $order_id);
        if (mysqli_stmt_execute($stmt)) {
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affected_rows > 0 && $table_id && in_array($status, ['closed', 'paid'])) {
                // Check if there are other 'open' orders for this table before setting to 'available'
                $open_orders_sql = "SELECT COUNT(*) as open_count FROM orders WHERE table_id = ? AND status = 'open'";
                $is_table_still_occupied = false;
                if($open_stmt = mysqli_prepare($conn, $open_orders_sql)) {
                    mysqli_stmt_bind_param($open_stmt, "i", $table_id);
                    if(mysqli_stmt_execute($open_stmt)){
                        $open_res = mysqli_stmt_get_result($open_stmt);
                        if($open_row = mysqli_fetch_assoc($open_res)){
                            if($open_row['open_count'] > 0) {
                                $is_table_still_occupied = true;
                            }
                        }
                    }
                    mysqli_stmt_close($open_stmt);
                }

                if(!$is_table_still_occupied){
                    updateTableStatus($table_id, 'available'); // From table_functions.php
                }
            }
            return $affected_rows >= 0; // True if update was successful (0 rows affected is also a success if status was already the same)
        } else {
            // error_log("Error executing updateOrderStatus statement: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        }
    } else {
        // error_log("Error preparing updateOrderStatus statement: " . mysqli_error($conn));
    }
    return false;
}

/**
 * Recalculates and updates the total_amount for a given order based on its items.
 *
 * @global mysqli $conn The database connection object.
 * @param int $order_id The ID of the order to update.
 * @return bool True on success, false on failure.
 */
function updateOrderTotalAmount(int $order_id): bool {
    global $conn;
    $sql_sum = "SELECT SUM(subtotal) AS total FROM order_items WHERE order_id = ?";
    $total_amount = 0.00;

    if ($stmt_sum = mysqli_prepare($conn, $sql_sum)) {
        mysqli_stmt_bind_param($stmt_sum, "i", $order_id);
        if (mysqli_stmt_execute($stmt_sum)) {
            $result_sum = mysqli_stmt_get_result($stmt_sum);
            if ($row = mysqli_fetch_assoc($result_sum)) {
                $total_amount = $row['total'] ?: 0.00; // Use 0.00 if sum is null (no items)
            }
        } else {
            // error_log("Error executing sum for updateOrderTotalAmount: " . mysqli_stmt_error($stmt_sum));
            mysqli_stmt_close($stmt_sum);
            return false;
        }
        mysqli_stmt_close($stmt_sum);
    } else {
        // error_log("Error preparing sum statement for updateOrderTotalAmount: " . mysqli_error($conn));
        return false;
    }

    $sql_update = "UPDATE orders SET total_amount = ? WHERE id = ?";
    if ($stmt_update = mysqli_prepare($conn, $sql_update)) {
        mysqli_stmt_bind_param($stmt_update, "di", $total_amount, $order_id);
        if (mysqli_stmt_execute($stmt_update)) {
            mysqli_stmt_close($stmt_update);
            return true;
        } else {
            // error_log("Error executing update for updateOrderTotalAmount: " . mysqli_stmt_error($stmt_update));
            mysqli_stmt_close($stmt_update);
        }
    } else {
        // error_log("Error preparing update statement for updateOrderTotalAmount: " . mysqli_error($conn));
    }
    return false;
}

/**
 * Removes an item from an order and updates the order's total amount.
 *
 * @global mysqli $conn The database connection object.
 * @param int $order_item_id The ID of the order_item entry to remove.
 * @return bool True on success, false on failure.
 */
function removeOrderItem(int $order_item_id): bool {
    global $conn;

    // First, get order_id to update total amount later
    $order_id = null;
    $get_order_id_sql = "SELECT order_id FROM order_items WHERE id = ?";
    if ($stmt_get = mysqli_prepare($conn, $get_order_id_sql)) {
        mysqli_stmt_bind_param($stmt_get, "i", $order_item_id);
        if (mysqli_stmt_execute($stmt_get)) {
            $result = mysqli_stmt_get_result($stmt_get);
            if ($row = mysqli_fetch_assoc($result)) {
                $order_id = $row['order_id'];
            }
        }
        mysqli_stmt_close($stmt_get);
    }

    if (!$order_id) {
        // error_log("Could not find order_id for order_item_id {$order_item_id}");
        return false;
    }

    mysqli_autocommit($conn, false); // Start transaction

    $sql = "DELETE FROM order_items WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $order_item_id);
        if (mysqli_stmt_execute($stmt)) {
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            if ($affected_rows > 0) {
                if (updateOrderTotalAmount($order_id)) {
                    mysqli_commit($conn);
                    mysqli_autocommit($conn, true);
                    return true;
                } else {
                    // error_log("Failed to update order total after removing item {$order_item_id}. Rolling back.");
                    mysqli_rollback($conn);
                    mysqli_autocommit($conn, true);
                    return false;
                }
            } else {
                // No rows deleted, item might not exist or already deleted
                mysqli_rollback($conn); // or commit if no change is not an error
                mysqli_autocommit($conn, true);
                return false; // Or true, depending on desired behavior for non-existent item
            }
        } else {
            // error_log("Error executing removeOrderItem statement: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        }
    } else {
        // error_log("Error preparing removeOrderItem statement: " . mysqli_error($conn));
    }

    mysqli_rollback($conn);
    mysqli_autocommit($conn, true);
    return false;
}

?>
