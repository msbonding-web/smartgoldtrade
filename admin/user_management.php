<?php 
require_once 'header.php'; 
require_once '../db_connect.php';

$message = '';

// Handle Delete User Action
if (isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['user_id'])) {
    $user_id_to_delete = intval($_GET['user_id']);
    // It's safer to "soft delete" by changing status
    $delete_stmt = $conn->prepare("UPDATE users SET status = 'deleted' WHERE id = ?");
    $delete_stmt->bind_param('i', $user_id_to_delete);
    if ($delete_stmt->execute()) {
        $message = '<div class="alert alert-success">User has been marked as deleted.</div>';
    } else {
        $message = '<div class="alert alert-danger">Error deleting user.</div>';
    }
    $delete_stmt->close();
}

// Handle Add Funds Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_funds'])) {
    $user_id = intval($_POST['user_id']);
    $wallet_id = intval($_POST['wallet_id']);
    $amount = floatval($_POST['amount']);
    $remarks = trim($_POST['remarks']);

    if ($amount > 0 && !empty($remarks)) {
        $conn->begin_transaction();
        try {
            // Get current balance
            $wallet_stmt = $conn->prepare("SELECT balance FROM wallets WHERE id = ? FOR UPDATE");
            $wallet_stmt->bind_param('i', $wallet_id);
            $wallet_stmt->execute();
            $wallet_result = $wallet_stmt->get_result();
            $wallet = $wallet_result->fetch_assoc();
            $balance_before = $wallet['balance'];
            $wallet_stmt->close();

            $balance_after = $balance_before + $amount;

            // Update wallet
            $update_wallet_stmt = $conn->prepare("UPDATE wallets SET balance = balance + ?, available = available + ? WHERE id = ?");
            $update_wallet_stmt->bind_param('ddi', $amount, $amount, $wallet_id);
            $update_wallet_stmt->execute();
            $update_wallet_stmt->close();

            // Record transaction
            $trans_stmt = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, remarks) VALUES (?, 'credit', ?, ?, ?, 'manual_adjustment', ?)");
            $trans_stmt->bind_param('iddds', $wallet_id, $amount, $balance_before, $balance_after, $remarks);
            $trans_stmt->execute();
            $trans_stmt->close();

            $conn->commit();
            $message = '<div class="alert alert-success">Successfully added funds to the user\'s wallet.</div>';
        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert alert-danger">Failed to add funds: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Please enter a valid amount and remarks.</div>';
    }
}


// Get filter values

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$kyc_filter = $_GET['kyc_status'] ?? '';

// Base query with a subquery to determine KYC status
$query = "
    SELECT 
        u.id, u.username, u.email, u.status, u.created_at,
        (CASE
            WHEN EXISTS (SELECT 1 FROM kyc_documents k WHERE k.user_id = u.id AND k.status = 'pending') THEN 'Pending'
            WHEN EXISTS (SELECT 1 FROM kyc_documents k WHERE k.user_id = u.id AND k.status = 'rejected') THEN 'Rejected'
            WHEN EXISTS (SELECT 1 FROM kyc_documents k WHERE k.user_id = u.id) AND NOT EXISTS (SELECT 1 FROM kyc_documents k WHERE k.user_id = u.id AND k.status != 'approved') THEN 'Approved'
            ELSE 'Not Submitted'
        END) as kyc_status
    FROM users u
";

// Dynamically build WHERE and HAVING clauses
$where_clauses = ["u.status != 'deleted'"];
$having_clauses = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_clauses[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = &$search_param;
    $params[] = &$search_param;
    $types .= 'ss';
}

if (!empty($status_filter)) {
    $where_clauses[] = "u.status = ?";
    $params[] = &$status_filter;
    $types .= 's';
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(' AND ', $where_clauses);
}

if (!empty($kyc_filter)) {
    $having_clauses[] = "kyc_status = ?";
    $params[] = &$kyc_filter;
    $types .= 's';
}

if (!empty($having_clauses)) {
    $query .= " HAVING " . implode(' AND ', $having_clauses);
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h3>User Management</h3>
        <a href="add_user.php" class="btn btn-primary">Add New User</a>
    </div>

    <form action="user_management.php" method="GET">
        <div class="grid-container" style="grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; align-items: center;">
            <div class="form-group">
                <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-group">
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="active" <?php if ($status_filter === 'active') echo 'selected'; ?>>Active</option>
                    <option value="suspended" <?php if ($status_filter === 'suspended') echo 'selected'; ?>>Suspended</option>
                    <option value="pending" <?php if ($status_filter === 'pending') echo 'selected'; ?>>Pending</option>
                </select>
            </div>
            <div class="form-group">
                <select name="kyc_status" class="form-control">
                    <option value="">All KYC Statuses</option>
                    <option value="Approved" <?php if ($kyc_filter === 'Approved') echo 'selected'; ?>>Approved</option>
                    <option value="Pending" <?php if ($kyc_filter === 'Pending') echo 'selected'; ?>>Pending</option>
                    <option value="Rejected" <?php if ($kyc_filter === 'Rejected') echo 'selected'; ?>>Rejected</option>
                    <option value="Not Submitted" <?php if ($kyc_filter === 'Not Submitted') echo 'selected'; ?>>Not Submitted</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </div>
    </form>

    <table class="table">
        <thead>
            <tr><th>User</th><th>KYC Status</th><th>Status</th><th>Join Date</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($user = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong><br><small><?php echo htmlspecialchars($user['email']); ?></small></td>
                        <td>
                            <?php 
                            $kyc_status = $user['kyc_status'];
                            $kyc_color = 'var(--gray)';
                            if ($kyc_status === 'Approved') $kyc_color = 'var(--success)';
                            if ($kyc_status === 'Pending') $kyc_color = 'var(--warning)';
                            if ($kyc_status === 'Rejected') $kyc_color = 'var(--danger)';
                            ?>
                            <span style="color: <?php echo $kyc_color; ?>; font-weight: bold;"><?php echo $kyc_status; ?></span>
                        </td>
                        <td>
                            <?php 
                            $status = htmlspecialchars($user['status']);
                            $color = 'var(--gray)';
                            if ($status === 'active') $color = 'var(--success)';
                            if ($status === 'suspended') $color = 'var(--danger)';
                            ?>
                            <span style="color: <?php echo $color; ?>; text-transform: capitalize;"><?php echo $status; ?></span>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                        <td>
                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                            <button class="btn btn-success btn-sm add-funds-btn" data-userid="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>">Add Funds</button>
                            <a href="suspend_user.php?id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm">Suspend</a>
                            <a href="user_management.php?action=delete_user&user_id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm delete-user-btn">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align: center;">No users found matching your criteria.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add Funds Modal -->
<div id="addFundsModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color:#222; margin:10% auto; padding:20px; border:1px solid #888; width:80%; max-width:500px; border-radius: 5px;">
        <span class="close-btn" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
        <form action="user_management.php" method="POST">
            <h4 id="modalTitle">Add Funds</h4>
            <input type="hidden" name="user_id" id="modalUserId">
            <div class="form-group">
                <label for="modalWalletId">Select Wallet</label>
                <select name="wallet_id" id="modalWalletId" class="form-control" required>
                    <!-- Options will be loaded via AJAX -->
                </select>
            </div>
            <div class="form-group">
                <label for="modalAmount">Amount</label>
                <input type="number" step="0.01" name="amount" id="modalAmount" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="modalRemarks">Remarks</label>
                <input type="text" name="remarks" id="modalRemarks" class="form-control" required>
            </div>
            <button type="submit" name="add_funds" class="btn btn-primary">Confirm Add Funds</button>
        </form>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete confirmation
    const deleteButtons = document.querySelectorAll('.delete-user-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this user? This action is not easily reversible.')) {
                e.preventDefault();
            }
        });
    });

    // Modal handling
    const modal = document.getElementById('addFundsModal');
    const closeBtn = modal.querySelector('.close-btn');
    const addFundsButtons = document.querySelectorAll('.add-funds-btn');
    const modalTitle = document.getElementById('modalTitle');
    const modalUserId = document.getElementById('modalUserId');
    const modalWalletSelect = document.getElementById('modalWalletId');

    addFundsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userid;
            const username = this.dataset.username;

            modalTitle.textContent = 'Add Funds to ' + username;
            modalUserId.value = userId;
            
            // Fetch user wallets via AJAX
            fetch('ajax_get_user_wallets.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    modalWalletSelect.innerHTML = ''; // Clear previous options
                    if (data.success && data.wallets.length > 0) {
                        data.wallets.forEach(wallet => {
                            const option = document.createElement('option');
                            option.value = wallet.id;
                            option.textContent = wallet.wallet_type_name + ' (' + wallet.currency_code + ') - ' + wallet.balance;
                            modalWalletSelect.appendChild(option);
                        });
                    } else {
                        modalWalletSelect.innerHTML = '<option value="">No wallets found for this user.</option>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching wallets:', error);
                    modalWalletSelect.innerHTML = '<option value="">Error loading wallets.</option>';
                });

            modal.style.display = 'block';
        });
    });

    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
});
</script>

<?php 
$stmt->close();
$conn->close();
require_once 'footer.php'; 
?>