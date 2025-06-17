<?php
session_start(); // Start session for flash messages (optional, but good for PRG pattern)
require_once 'includes/db.php';
require_once 'includes/table_functions.php';

$feedback_message = '';
$feedback_type = ''; // 'success' or 'error'

// Handle Add Table Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_table') {
    $table_number = trim($_POST['table_number'] ?? '');

    if (!empty($table_number)) {
        // Basic check if table number already exists to prevent DB unique constraint error being the first user feedback
        $allTablesForCheck = getAllTables(); // In a very high-traffic system, this might be too much, but fine for typical POS
        $exists = false;
        foreach ($allTablesForCheck as $existingTable) {
            if (strcasecmp($existingTable['table_number'], $table_number) === 0) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            $_SESSION['feedback_message'] = "Table number '{$table_number}' already exists.";
            $_SESSION['feedback_type'] = 'error';
        } elseif (addTable($table_number)) { // Default status 'available' is handled by addTable function
            $_SESSION['feedback_message'] = "Table '{$table_number}' added successfully!";
            $_SESSION['feedback_type'] = 'success';
        } else {
            $_SESSION['feedback_message'] = "Failed to add table '{$table_number}'. Database error or invalid input.";
            $_SESSION['feedback_type'] = 'error';
        }
    } else {
        $_SESSION['feedback_message'] = "Table number cannot be empty.";
        $_SESSION['feedback_type'] = 'error';
    }
    // Redirect to the same page to prevent form resubmission (PRG pattern)
    header("Location: tables.php");
    exit;
}

// Retrieve feedback messages from session if any
if (isset($_SESSION['feedback_message'])) {
    $feedback_message = $_SESSION['feedback_message'];
    $feedback_type = $_SESSION['feedback_type'] ?? 'info';
    unset($_SESSION['feedback_message']);
    unset($_SESSION['feedback_type']);
} elseif (isset($_GET['status'])) { // Handle status from URL (e.g., after status update)
    if ($_GET['status'] === 'success' && isset($_GET['message'])) {
        $feedback_message = htmlspecialchars(urldecode($_GET['message']));
        $feedback_type = 'success';
    } elseif ($_GET['status'] === 'error' && isset($_GET['message'])) {
        $feedback_message = htmlspecialchars(urldecode($_GET['message']));
        $feedback_type = 'error';
    }
}


// Fetch all tables for display
$tables = getAllTables();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Table Management - PHP POS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .table-card { margin-bottom: 20px; }
        .status-actions .btn { margin-top: 5px; }
    </style>
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
                <li class="nav-item">
                    <a class="nav-link" href="menu.php">Menu Management</a>
                </li>
                <li class="nav-item active">
                    <a class="nav-link" href="tables.php">Table Management <span class="sr-only">(current)</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders.php">Orders</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container mt-5">
        <h1>Table Management</h1>

        <?php if ($feedback_message): ?>
            <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($feedback_message); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Add New Table Form -->
        <div class="card mb-4">
            <div class="card-header">Add New Table</div>
            <div class="card-body">
                <form method="POST" action="tables.php" class="form-inline">
                    <input type="hidden" name="action" value="add_table">
                    <div class="form-group mx-sm-3 mb-2">
                        <label for="table_number" class="sr-only">Table Number</label>
                        <input type="text" class="form-control" id="table_number" name="table_number" placeholder="Enter Table Number/Name" required>
                    </div>
                    <button type="submit" class="btn btn-primary mb-2">Add Table</button>
                </form>
            </div>
        </div>

        <!-- Display Tables -->
        <h2>Current Tables</h2>
        <?php if (empty($tables)): ?>
            <div class="alert alert-info">No tables found. Please add some using the form above.</div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($tables as $table): ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="card table-card">
                            <div class="card-body">
                                <h5 class="card-title">Table: <?php echo htmlspecialchars($table['table_number']); ?> (ID: <?php echo $table['id']; ?>)</h5>
                                <?php
                                $status = htmlspecialchars($table['status']);
                                $badgeClass = 'badge-secondary'; // Default
                                if ($status === 'available') $badgeClass = 'badge-success';
                                elseif ($status === 'occupied') $badgeClass = 'badge-danger';
                                elseif ($status === 'reserved') $badgeClass = 'badge-warning';
                                ?>
                                <p class="card-text">Status: <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($status); ?></span></p>

                                <div class="status-actions">
                                    <a href="handle_table_action.php?action=update_status&id=<?php echo $table['id']; ?>&status=available" class="btn btn-sm btn-success <?php if ($status === 'available') echo 'disabled'; ?>">Available</a>
                                    <a href="handle_table_action.php?action=update_status&id=<?php echo $table['id']; ?>&status=occupied" class="btn btn-sm btn-danger <?php if ($status === 'occupied') echo 'disabled'; ?>">Occupied</a>
                                    <a href="handle_table_action.php?action=update_status&id=<?php echo $table['id']; ?>&status=reserved" class="btn btn-sm btn-warning <?php if ($status === 'reserved') echo 'disabled'; ?>">Reserved</a>
                                </div>

                                <?php if ($status === 'available'): ?>
                                    <a href="create_order.php?table_id=<?php echo $table['id']; ?>" class="btn btn-sm btn-primary mt-2">Start New Order</a>
                                <?php else: ?>
                                     <!-- Optionally, show active order details or link to existing order for this table -->
                                     <?php
                                        // This is a placeholder for a more advanced feature.
                                        // You would need a function to quickly check active orders for a table.
                                        // For now, just disable "Start New Order" if not available.
                                     ?>
                                     <a href="#" class="btn btn-sm btn-primary mt-2 disabled" aria-disabled="true" title="Table is not available">Start New Order</a>
                                <?php endif; ?>
                                  <a href="handle_table_action.php?action=delete_table&id=<?php echo $table['id']; ?>" class="btn btn-sm btn-outline-danger mt-2" onclick="return confirm('Are you sure you want to delete table <?php echo htmlspecialchars(addslashes($table['table_number'])); ?>? This might fail if there are orders associated with it.');">Delete Table</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">PHP POS - &copy; <?php echo date("Y"); ?></span>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
