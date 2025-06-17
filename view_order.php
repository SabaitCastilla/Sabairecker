<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/order_functions.php';
require_once 'includes/menu_functions.php';
// table_functions.php might be needed if we do direct table operations not covered by order_functions

$feedback_message = $_SESSION['feedback_message'] ?? null;
$feedback_type = $_SESSION['feedback_type'] ?? 'info';
unset($_SESSION['feedback_message'], $_SESSION['feedback_type']);

$order_id = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT);

if (!$order_id) {
    $_SESSION['feedback_message'] = 'No order ID specified or invalid ID.';
    $_SESSION['feedback_type'] = 'error';
    header("Location: tables.php"); // Or an orders_list.php if that exists
    exit;
}

$order_details = getOrderDetails($order_id); // This should join with tables and menu_items
$all_menu_items = getAllMenuItems(); // For the "Add Item" dropdown

if (!$order_details) {
    $_SESSION['feedback_message'] = "Order with ID {$order_id} not found.";
    $_SESSION['feedback_type'] = 'error';
    header("Location: tables.php"); // Or an orders_list.php
    exit;
}

$current_order_status = $order_details['status'] ?? 'unknown';
$is_order_editable = in_array($current_order_status, ['open']); // Only 'open' orders can have items added/removed/qty changed

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Order #<?php echo $order_id; ?> - PHP POS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
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
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="menu.php">Menu</a></li>
                <li class="nav-item"><a class="nav-link" href="tables.php">Tables</a></li>
                <li class="nav-item active"><a class="nav-link" href="#">Orders <span class="sr-only">(current)</span></a></li>
            </ul>
        </div>
    </nav>

    <div class="container mt-5">
        <?php if ($feedback_message): ?>
            <div class="alert alert-<?php echo $feedback_type === 'success' ? 'success' : ($feedback_type === 'error' ? 'danger' : 'info'); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($feedback_message); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>

        <h2>Order #<?php echo $order_id; ?> Details</h2>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Order Information</h5>
                <p><strong>Table:</strong> <?php echo htmlspecialchars($order_details['table_number'] ?? $order_details['table_id'] ?? 'N/A'); ?></p>
                <p><strong>Status:</strong> <span class="badge badge-<?php
                    switch($current_order_status) {
                        case 'open': echo 'primary'; break;
                        case 'paid': echo 'success'; break;
                        case 'closed': echo 'secondary'; break;
                        case 'cancelled': echo 'danger'; break;
                        default: echo 'light';
                    }
                ?>"><?php echo htmlspecialchars(ucfirst($current_order_status)); ?></span></p>
                <p><strong>Total Amount:</strong> €<?php echo htmlspecialchars(number_format($order_details['total_amount'] ?? 0, 2)); ?></p>
                <p><strong>Order Time:</strong> <?php echo htmlspecialchars($order_details['order_time'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <?php if ($is_order_editable): ?>
        <div class="card mb-4">
            <div class="card-header">Add Item to Order</div>
            <div class="card-body">
                <form method="POST" action="handle_order_item.php" class="form-inline">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <input type="hidden" name="action" value="add_item">
                    <div class="form-group mx-sm-3 mb-2">
                        <label for="menu_item_id" class="sr-only">Menu Item</label>
                        <select name="menu_item_id" id="menu_item_id" class="form-control" required>
                            <option value="">Select Item...</option>
                            <?php foreach($all_menu_items as $item): ?>
                                <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?> (€<?php echo htmlspecialchars(number_format($item['price'], 2)); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mx-sm-3 mb-2">
                        <label for="quantity" class="sr-only">Quantity</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" value="1" min="1" required>
                    </div>
                    <button type="submit" class="btn btn-primary mb-2">Add Item</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <h3>Order Items</h3>
        <?php if (empty($order_details['items'])): ?>
            <div class="alert alert-info">No items in this order yet.</div>
        <?php else: ?>
            <table class="table table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>Item Name</th>
                        <th>Unit Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <?php if ($is_order_editable): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($order_details['items'] as $order_item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order_item['menu_item_name']); ?></td>
                        <td>€<?php echo htmlspecialchars(number_format($order_item['item_price'], 2)); ?></td>
                        <td>
                            <?php if ($is_order_editable): ?>
                            <form method="POST" action="handle_order_item.php" class="form-inline d-inline-block">
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <input type="hidden" name="order_item_id" value="<?php echo $order_item['id']; // This is order_items.id ?>">
                                <input type="hidden" name="action" value="update_qty">
                                <input type="number" name="quantity" value="<?php echo htmlspecialchars($order_item['quantity']); ?>" min="1" class="form-control form-control-sm" style="width: 70px;">
                                <button type="submit" class="btn btn-sm btn-info ml-1">Update</button>
                            </form>
                            <?php else: ?>
                                <?php echo htmlspecialchars($order_item['quantity']); ?>
                            <?php endif; ?>
                        </td>
                        <td>€<?php echo htmlspecialchars(number_format($order_item['subtotal'], 2)); ?></td>
                        <?php if ($is_order_editable): ?>
                        <td>
                            <a href="handle_order_item.php?action=remove_item&order_item_id=<?php echo $order_item['id']; ?>&order_id=<?php echo $order_id; ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Are you sure you want to remove <?php echo htmlspecialchars(addslashes($order_item['menu_item_name'])); ?> from the order?');">Remove</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($is_order_editable): ?>
        <div class="card mt-4">
            <div class="card-header">Manage Order Status</div>
            <div class="card-body">
                <p>Current Status: <span class="badge badge-primary"><?php echo htmlspecialchars(ucfirst($current_order_status)); ?></span></p>
                <a href="handle_order_status.php?action=update_status&order_id=<?php echo $order_id; ?>&new_status=paid" class="btn btn-success">Mark as Paid</a>
                <a href="handle_order_status.php?action=update_status&order_id=<?php echo $order_id; ?>&new_status=closed" class="btn btn-secondary ml-2">Mark as Closed</a>
                <a href="handle_order_status.php?action=update_status&order_id=<?php echo $order_id; ?>&new_status=cancelled" class="btn btn-danger ml-2" onclick="return confirm('Are you sure you want to cancel this order? This action cannot be undone easily.');">Cancel Order</a>
            </div>
        </div>
        <?php elseif ($current_order_status !== 'unknown'): ?>
        <div class="alert alert-info mt-4">
            This order is currently <strong><?php echo htmlspecialchars($current_order_status); ?></strong> and can no longer be modified.
        </div>
        <?php endif; ?>
         <div class="mt-4">
            <a href="tables.php" class="btn btn-outline-secondary">&laquo; Back to Tables</a>
            <!-- Add a link to a general orders list page if it exists -->
            <!-- <a href="orders_list.php" class="btn btn-outline-info ml-2">View All Orders</a> -->
        </div>
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
