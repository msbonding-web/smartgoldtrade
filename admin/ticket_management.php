<?php
require_once 'header.php';
require_once '../db_connect.php';

// Fetch filter values
$status_filter = $_GET['status'] ?? '';
$department_filter = $_GET['department'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

// Fetch Tickets
$tickets_query = "
    SELECT t.*, u.username, u.email
    FROM tickets t
    JOIN users u ON t.user_id = u.id
";

$where_clauses = [];
$params = [];
$types = '';

if (!empty($status_filter)) {
    $where_clauses[] = "t.status = ?";
    $params[] = &$status_filter;
    $types .= 's';
}
if (!empty($department_filter)) {
    $where_clauses[] = "t.department = ?";
    $params[] = &$department_filter;
    $types .= 's';
}
if (!empty($priority_filter)) {
    $where_clauses[] = "t.priority = ?";
    $params[] = &$priority_filter;
    $types .= 's';
}

if (!empty($where_clauses)) {
    $tickets_query .= " WHERE " . implode(' AND ', $where_clauses);
}

$tickets_query .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($tickets_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$tickets_result = $stmt->get_result();

// Fetch data for filters (departments, priorities)
$departments_for_filter = $conn->query("SELECT DISTINCT department FROM tickets ORDER BY department");
$priorities_for_filter = $conn->query("SELECT DISTINCT priority FROM tickets ORDER BY priority");

?>

<style>
    .alert-success { background-color: var(--success); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
    .alert-danger { background-color: var(--danger); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
</style>

<div class="card">
    <h3>Support Ticket Management</h3>

    <div style="margin-bottom: 1rem;">
        <form action="ticket_management.php" method="GET">
            <div class="grid-container" style="grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="open" <?php if ($status_filter === 'open') echo 'selected'; ?>>Open</option>
                    <option value="in_progress" <?php if ($status_filter === 'in_progress') echo 'selected'; ?>>In Progress</option>
                    <option value="closed" <?php if ($status_filter === 'closed') echo 'selected'; ?>>Closed</option>
                </select>
                <select name="department" class="form-control">
                    <option value="">All Departments</option>
                    <?php while($dept = $departments_for_filter->fetch_assoc()): ?>
                        <option value="<?php echo $dept['department']; ?>" <?php if ($department_filter === $dept['department']) echo 'selected'; ?>><?php echo htmlspecialchars($dept['department']); ?></option>
                    <?php endwhile; ?>
                </select>
                <select name="priority" class="form-control">
                    <option value="">All Priorities</option>
                    <?php while($prio = $priorities_for_filter->fetch_assoc()): ?>
                        <option value="<?php echo $prio['priority']; ?>" <?php if ($priority_filter === $prio['priority']) echo 'selected'; ?>><?php echo htmlspecialchars($prio['priority']); ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>

    <table class="table">
        <thead>
            <tr><th>Ticket ID</th><th>User</th><th>Subject</th><th>Department</th><th>Priority</th><th>Status</th><th>Created At</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php if ($tickets_result && $tickets_result->num_rows > 0): ?>
                <?php while($ticket = $tickets_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ticket['id']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['username']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['department']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['priority']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['status']); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                        <td>
                            <a href="ticket_details.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" style="text-align: center;">No support tickets found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$conn->close();
require_once 'footer.php';
?>
