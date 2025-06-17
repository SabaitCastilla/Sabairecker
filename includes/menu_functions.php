<?php
require_once 'db.php'; // Ensures $conn is available

/**
 * Adds a new menu item to the database.
 *
 * @param string $name The name of the menu item.
 * @param float $price The price of the menu item.
 * @param string $category The category of the menu item.
 * @param string|null $image_path The path to the menu item's image (optional).
 * @return bool True on success, false on failure.
 */
function addMenuItem(string $name, float $price, string $category, ?string $image_path = null): bool {
    global $conn; // Access the connection from db.php

    $sql = "INSERT INTO menu_items (name, price, category, image_path) VALUES (?, ?, ?, ?)";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        // Bind parameters: s - string, d - double (for price)
        mysqli_stmt_bind_param($stmt, "sdss", $name, $price, $category, $image_path);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return true;
        } else {
            // Optional: Log error
            // error_log("Error executing addMenuItem statement: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }
    } else {
        // Optional: Log error
        // error_log("Error preparing addMenuItem statement: " . mysqli_error($conn));
        return false;
    }
}

/**
 * Fetches all menu items from the database.
 *
 * @return array An array of menu items (associative arrays), or an empty array on failure or if no items.
 */
function getAllMenuItems(): array {
    global $conn; // Access the connection from db.php

    $sql = "SELECT id, name, price, category, image_path, created_at FROM menu_items ORDER BY category, name";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        // MYSQLI_ASSOC returns an associative array
        $items = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_free_result($result);
        return $items ?: []; // Return items or empty array if null
    } else {
        // Optional: Log error
        // error_log("Error executing getAllMenuItems query: " . mysqli_error($conn));
        return [];
    }
}

/**
 * Fetches a single menu item by its ID.
 *
 * @param int $id The ID of the menu item.
 * @return array|null The menu item as an associative array, or null if not found or on error.
 */
function getMenuItemById(int $id): ?array {
    global $conn; // Access the connection from db.php

    $sql = "SELECT id, name, price, category, image_path, created_at FROM menu_items WHERE id = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        // Bind parameter: i - integer
        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($item = mysqli_fetch_assoc($result)) {
                mysqli_free_result($result);
                mysqli_stmt_close($stmt);
                return $item;
            } else {
                // Item not found
                mysqli_stmt_close($stmt);
                return null;
            }
        } else {
            // Optional: Log error
            // error_log("Error executing getMenuItemById statement: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return null;
        }
    } else {
        // Optional: Log error
        // error_log("Error preparing getMenuItemById statement: " . mysqli_error($conn));
        return null;
    }
}

/**
 * Updates an existing menu item in the database.
 *
 * @param int $id The ID of the menu item to update.
 * @param string $name The new name of the menu item.
 * @param float $price The new price of the menu item.
 * @param string $category The new category of the menu item.
 * @param string|null $image_path The new path to the menu item's image (optional).
 * @return bool True on success, false on failure.
 */
function updateMenuItem(int $id, string $name, float $price, string $category, ?string $image_path = null): bool {
    global $conn;

    $sql = "UPDATE menu_items SET name = ?, price = ?, category = ?, image_path = ? WHERE id = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "sdssi", $name, $price, $category, $image_path, $id);

        if (mysqli_stmt_execute($stmt)) {
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            return $affected_rows > 0; // True if at least one row was updated
        } else {
            // error_log("Error executing updateMenuItem statement: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }
    } else {
        // error_log("Error preparing updateMenuItem statement: " . mysqli_error($conn));
        return false;
    }
}

/**
 * Deletes a menu item from the database.
 *
 * @param int $id The ID of the menu item to delete.
 * @return bool True on success, false on failure.
 */
function deleteMenuItem(int $id): bool {
    global $conn;

    $sql = "DELETE FROM menu_items WHERE id = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            return $affected_rows > 0; // True if at least one row was deleted
        } else {
            // error_log("Error executing deleteMenuItem statement: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }
    } else {
        // error_log("Error preparing deleteMenuItem statement: " . mysqli_error($conn));
        return false;
    }
}
?>
