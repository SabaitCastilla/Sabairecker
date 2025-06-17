<?php
require_once 'db.php'; // Ensures $conn is available

/**
 * Adds a new table to the database.
 *
 * @global mysqli $conn The database connection object from db.php
 * @param string $table_number The unique number or identifier for the table.
 * @param string $status The initial status of the table (e.g., 'available', 'occupied', 'reserved').
 * @return bool True on success, false on failure.
 */
function addTable(string $table_number, string $status = 'available'): bool {
    global $conn;
    $sql = "INSERT INTO tables (table_number, status) VALUES (?, ?)";

    if (!in_array($status, ['available', 'occupied', 'reserved'])) {
        // error_log("Invalid status value provided for addTable: " . $status);
        return false; // Invalid status
    }

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $table_number, $status);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return true;
        } else {
            // error_log("Error executing addTable statement: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }
    } else {
        // error_log("Error preparing addTable statement: " . mysqli_error($conn));
        return false;
    }
}

/**
 * Fetches all tables from the database.
 *
 * @global mysqli $conn The database connection object from db.php
 * @return array An array of tables (associative arrays), ordered by table_number, or an empty array on failure or if no tables.
 */
function getAllTables(): array {
    global $conn;
    $sql = "SELECT id, table_number, status, created_at FROM tables ORDER BY table_number ASC";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $tables = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_free_result($result);
        return $tables ?: [];
    } else {
        // error_log("Error executing getAllTables query: " . mysqli_error($conn));
        return [];
    }
}

/**
 * Fetches a single table by its ID.
 *
 * @global mysqli $conn The database connection object from db.php
 * @param int $id The ID of the table.
 * @return array|null The table as an associative array, or null if not found or on error.
 */
function getTableById(int $id): ?array {
    global $conn;
    $sql = "SELECT id, table_number, status, created_at FROM tables WHERE id = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($table = mysqli_fetch_assoc($result)) {
                mysqli_free_result($result);
                mysqli_stmt_close($stmt);
                return $table;
            } else {
                mysqli_stmt_close($stmt);
                return null; // Not found
            }
        } else {
            // error_log("Error executing getTableById statement: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return null;
        }
    } else {
        // error_log("Error preparing getTableById statement: " . mysqli_error($conn));
        return null;
    }
}

/**
 * Updates the status of a specific table.
 *
 * @global mysqli $conn The database connection object from db.php
 * @param int $id The ID of the table to update.
 * @param string $status The new status of the table (e.g., 'available', 'occupied', 'reserved').
 * @return bool True on successful update, false otherwise.
 */
function updateTableStatus(int $id, string $status): bool {
    global $conn;

    if (!in_array($status, ['available', 'occupied', 'reserved'])) {
        // error_log("Invalid status value provided for updateTableStatus: " . $status);
        return false; // Invalid status
    }

    $sql = "UPDATE tables SET status = ? WHERE id = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "si", $status, $id);
        if (mysqli_stmt_execute($stmt)) {
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            return $affected_rows >= 0; // True if update was successful (0 rows affected is also a success if status was already the same)
        } else {
            // error_log("Error executing updateTableStatus statement: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }
    } else {
        // error_log("Error preparing updateTableStatus statement: " . mysqli_error($conn));
        return false;
    }
}

/**
 * Deletes a table from the database.
 * Note: Consider implications if tables are linked to orders.
 * It might be better to mark a table as 'decommissioned' or 'archived'
 * instead of outright deleting if historical data integrity is crucial.
 *
 * @global mysqli $conn The database connection object from db.php
 * @param int $id The ID of the table to delete.
 * @return bool True on successful deletion, false otherwise.
 */
function deleteTable(int $id): bool {
    global $conn;
    // Before deleting, check if the table is referenced in the 'orders' table
    // This is a simple check. For production, you might want more robust handling
    // or rely on database foreign key constraints (ON DELETE RESTRICT).
    $check_sql = "SELECT COUNT(*) as order_count FROM orders WHERE table_id = ?";
    if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
        mysqli_stmt_bind_param($check_stmt, "i", $id);
        if (mysqli_stmt_execute($check_stmt)) {
            $result = mysqli_stmt_get_result($check_stmt);
            $row = mysqli_fetch_assoc($result);
            if ($row['order_count'] > 0) {
                // error_log("Attempt to delete table ID {$id} that has associated orders.");
                mysqli_stmt_close($check_stmt);
                return false; // Table has orders, cannot delete
            }
        }
        mysqli_stmt_close($check_stmt);
    }


    $sql = "DELETE FROM tables WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            return $affected_rows > 0;
        } else {
            // error_log("Error executing deleteTable statement: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }
    } else {
        // error_log("Error preparing deleteTable statement: " . mysqli_error($conn));
        return false;
    }
}
?>
