<?php
require_once 'header.php';
require_once '../db_connect.php';

// Fetch user's notifications
$notifications_query = "
    SELECT n.*, u.username
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    WHERE n.user_id = ? OR n.user_id IS NULL -- // Notifications for specific user or all users
    ORDER BY n.created_at DESC
";
$stmt = $conn->prepare($notifications_query);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$notifications_result = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<div class="card">
    <h3>Your Notifications</h3>

    <table class="table">
        <thead>
            <tr><th>Subject</th><th>Message</th><th>Date</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php if ($notifications_result && $notifications_result->num_rows > 0): ?>
                <?php while($notification = $notifications_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($notification['title']); ?></td>
                        <td><?php echo htmlspecialchars($notification['message']); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($notification['created_at'])); ?></td>
                        <td><?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align: center;">No notifications found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>
