<?php
require_once 'header.php';
require_once '../db_connect.php';

$message = '';

// Handle New Ticket Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $subject = trim($_POST['subject']);
    $initial_message = trim($_POST['message']);

    if (empty($subject) || empty($initial_message)) {
        $message = '<div class="alert alert-danger">Subject and message cannot be empty.</div>';
    } else {
        $conn->begin_transaction();
        try {
            // 1. Create the main ticket record
            $stmt = $conn->prepare("INSERT INTO tickets (user_id, subject, status, created_at) VALUES (?, ?, 'open', NOW())");
            $stmt->bind_param('is', $user_id, $subject);
            if (!$stmt->execute()) {
                throw new Exception("Error creating ticket: " . $stmt->error);
            }
            $ticket_id = $conn->insert_id;
            $stmt->close();

            // 2. Add the first reply
            $reply_stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())");
            $reply_stmt->bind_param('iis', $ticket_id, $user_id, $initial_message);
            if (!$reply_stmt->execute()) {
                throw new Exception("Error saving ticket message: " . $reply_stmt->error);
            }
            $reply_stmt->close();

            $conn->commit();
            $message = '<div class="alert alert-success">Your support ticket has been created successfully. You will be notified of any replies.</div>';

        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert alert-danger">An error occurred: ' . $e->getMessage() . '</div>';
        }
    }
}

// Fetch user's tickets
$tickets_stmt = $conn->prepare("SELECT id, subject, status, created_at FROM tickets WHERE user_id = ? ORDER BY created_at DESC");
$tickets_stmt->bind_param('i', $user_id);
$tickets_stmt->execute();
$tickets_result = $tickets_stmt->get_result();
$tickets_stmt->close();

$conn->close();
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3>My Support Tickets</h3>
        <button id="show-create-form" class="btn btn-primary">Create New Ticket</button>
    </div>
    <p style="margin-bottom: 1rem; color: #555;">To reply to an existing ticket, please click the "View Ticket" button next to it.</p>

    <?php echo $message; ?>

    <!-- New Ticket Form (Initially Hidden) -->
    <div id="create-ticket-form" class="card" style="display: none; margin-bottom: 2rem; background-color: #f8f9fa;">
        <h4>Create a New Support Ticket</h4>
        <form action="support_tickets.php" method="POST">
            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
            </div>
            <button type="submit" name="create_ticket" class="btn btn-primary">Submit Ticket</button>
            <button type="button" id="cancel-create-form" class="btn">Cancel</button>
        </form>
    </div>

    <h4>My Tickets</h4>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($tickets_result && $tickets_result->num_rows > 0): ?>
                <?php while($ticket = $tickets_result->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $ticket['id']; ?></td>
                        <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                        <td>
                            <?php 
                            $status = htmlspecialchars($ticket['status']);
                            $color = 'var(--gray)';
                            if ($status === 'open') $color = 'var(--success)';
                            if ($status === 'answered') $color = 'var(--primary-color)';
                            if ($status === 'user-reply') $color = 'var(--warning)';
                            if ($status === 'closed') $color = 'var(--danger)';
                            ?>
                            <span style="color: <?php echo $color; ?>; text-transform: capitalize;"><?php echo $status; ?></span>
                        </td>
                        <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                        <td>
                            <a href="ticket_details.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm">View Ticket</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">You have not created any support tickets.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.getElementById('show-create-form').addEventListener('click', function() {
    document.getElementById('create-ticket-form').style.display = 'block';
    this.style.display = 'none';
});
document.getElementById('cancel-create-form').addEventListener('click', function() {
    document.getElementById('create-ticket-form').style.display = 'none';
    document.getElementById('show-create-form').style.display = 'block';
});
</script>

<?php require_once 'footer.php'; ?>
