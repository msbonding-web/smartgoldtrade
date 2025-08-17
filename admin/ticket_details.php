<?php
require_once 'header.php';
require_once '../db_connect.php';

$ticket_id = $_GET['id'] ?? 0;

if (!is_numeric($ticket_id) || $ticket_id <= 0) {
    die("Invalid Ticket ID.");
}

$process_message = '';

// Handle ticket status/department update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    $new_status = $_POST['new_status'] ?? '';
    $new_department = $_POST['new_department'] ?? '';

    $update_query = "UPDATE tickets SET status = ?, department = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('ssi', $new_status, $new_department, $ticket_id);
    if ($update_stmt->execute()) {
        $process_message = "<div class=\"alert alert-success\">Ticket updated successfully!</div>";
    } else {
        $process_message = "<div class=\"alert alert-danger\">Error updating ticket: " . $update_stmt->error . "</div>";
    }
    $update_stmt->close();
}

// Handle new reply/note submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reply'])) {
    $message = $_POST['message'] ?? '';
    $user_id = 1; // Placeholder for admin user ID

    if (!empty($message)) {
        $insert_reply_query = "INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)";
        $insert_reply_stmt = $conn->prepare($insert_reply_query);
        $insert_reply_stmt->bind_param('iis', $ticket_id, $user_id, $message);
        if ($insert_reply_stmt->execute()) {
            $process_message = "<div class=\"alert alert-success\">Reply/Note added successfully!</div>";
        } else {
            $process_message = "<div class=\"alert alert-danger\">Error adding reply/note: " . $insert_reply_stmt->error . "</div>";
        }
        $insert_reply_stmt->close();
    } else {
        $process_message = "<div class=\"alert alert-danger\">Reply/Note cannot be empty.</div>";
    }
}

// Fetch ticket details
$ticket_query = "
    SELECT t.*, u.username, u.email
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
";
$ticket_stmt = $conn->prepare($ticket_query);
$ticket_stmt->bind_param('i', $ticket_id);
$ticket_stmt->execute();
$ticket_result = $ticket_stmt->get_result();
if ($ticket_result->num_rows === 1) {
    $ticket = $ticket_result->fetch_assoc();
} else {
    die("Ticket not found.");
}
$ticket_stmt->close();

// Fetch ticket replies
$replies_query = "
    SELECT tr.*, u.username as replier_username
    FROM ticket_replies tr
    LEFT JOIN users u ON tr.user_id = u.id
    WHERE tr.ticket_id = ?
    ORDER BY tr.created_at ASC
";
$replies_stmt = $conn->prepare($replies_query);
$replies_stmt->bind_param('i', $ticket_id);
$replies_stmt->execute();
$replies_result = $replies_stmt->get_result();
$replies_stmt->close();

$conn->close();
?>

<a href="ticket_management.php" class="btn" style="margin-bottom: 1rem; background-color: var(--gray);">← Back to Tickets</a>

<div class="card">
    <h3>Ticket Details: #<?php echo htmlspecialchars($ticket['id']); ?> - <?php echo htmlspecialchars($ticket['subject']); ?></h3>

    <?php echo $process_message; // Display messages ?>

    <div class="grid-container" style="grid-template-columns: 1fr 1fr; align-items: flex-start;">
        <div>
            <h4>Ticket Information</h4>
            <p><strong>User:</strong> <?php echo htmlspecialchars($ticket['username']); ?> (<?php echo htmlspecialchars($ticket['email']); ?>)</p>
            <p><strong>Department:</strong> <?php echo htmlspecialchars($ticket['department']); ?></p>
            <p><strong>Priority:</strong> <?php echo htmlspecialchars($ticket['priority']); ?></p>
            <p><strong>Status:</strong> <span style="font-weight: bold; text-transform: capitalize;"><?php echo htmlspecialchars($ticket['status']); ?></span></p>
            <p><strong>Created At:</strong> <?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></p>
            <p><strong>Message:</strong></p>
            <div style="border: 1px solid #eee; padding: 1rem; border-radius: 5px; background-color: #f9f9f9;">
                <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
            </div>

            <h4 style="margin-top: 2rem;">Ticket Replies & Internal Notes</h4>
            <div style="max-height: 300px; overflow-y: scroll; border: 1px solid #eee; padding: 1rem; border-radius: 5px;">
                <?php if ($replies_result->num_rows > 0): ?>
                    <?php while($reply = $replies_result->fetch_assoc()): ?>
                        <p><strong><?php echo htmlspecialchars($reply['replier_username'] ?? 'User'); ?>:</strong> <?php echo nl2br(htmlspecialchars($reply['message'])); ?><br><small><?php echo date('Y-m-d H:i', strtotime($reply['created_at'])); ?></small></p>
                        <hr style="border-top: 1px dashed #eee;">
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No replies or notes yet.</p>
                <?php endif; ?>
            </div>

            <h4 style="margin-top: 2rem;">Add Reply / Internal Note</h4>
            <form action="ticket_details.php?id=<?php echo $ticket_id; ?>" method="POST">
                <div class="form-group">
                    <textarea name="message" class="form-control" rows="4" placeholder="Type your reply or internal note here..."></textarea>
                </div>
                <button type="submit" name="add_reply" class="btn btn-primary">Add Reply/Note</button>
            </form>
        </div>

        <div>
            <h4>Update Ticket Status & Assignment</h4>
            <form action="ticket_details.php?id=<?php echo $ticket_id; ?>" method="POST">
                <div class="form-group">
                    <label for="new_status">Status</label>
                    <select name="new_status" id="new_status" class="form-control">
                        <option value="open" <?php if($ticket['status'] === 'open') echo 'selected'; ?>>Open</option>
                        <option value="in_progress" <?php if($ticket['status'] === 'in_progress') echo 'selected'; ?>>In Progress</option>
                        <option value="closed" <?php if($ticket['status'] === 'closed') echo 'selected'; ?>>Closed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new_department">Assign to Department</label>
                    <select name="new_department" id="new_department" class="form-control">
                        <option value="general" <?php if($ticket['department'] === 'general') echo 'selected'; ?>>General</option>
                        <option value="finance" <?php if($ticket['department'] === 'finance') echo 'selected'; ?>>Finance</option>
                        <option value="trading" <?php if($ticket['department'] === 'trading') echo 'selected'; ?>>Trading</option>
                        <option value="p2p" <?php if($ticket['department'] === 'p2p') echo 'selected'; ?>>P2P</option>
                        <option value="technical" <?php if($ticket['department'] === 'technical') echo 'selected'; ?>>Technical</option>
                    </select>
                </div>
                <button type="submit" name="update_ticket" class="btn btn-primary">Update Ticket</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
