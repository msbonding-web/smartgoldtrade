<?php
require_once 'header.php';
require_once '../db_connect.php';

$message = '';
$ticket_id = $_GET['id'] ?? null;

if (!$ticket_id) {
    header("Location: support_tickets.php");
    exit();
}

// Ensure the user owns this ticket
$ticket_stmt = $conn->prepare("SELECT subject, status FROM tickets WHERE id = ? AND user_id = ?");
$ticket_stmt->bind_param('ii', $ticket_id, $user_id);
$ticket_stmt->execute();
$ticket = $ticket_stmt->get_result()->fetch_assoc();
$ticket_stmt->close();

if (!$ticket) {
    // Redirect if ticket doesn't exist or doesn't belong to the user
    header("Location: support_tickets.php");
    exit();
}

// Handle New Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reply'])) {
    $reply_message = trim($_POST['message']);

    if (empty($reply_message)) {
        $message = '<div class="alert alert-danger">Your reply cannot be empty.</div>';
    } elseif ($ticket['status'] === 'closed') {
        $message = '<div class="alert alert-danger">You cannot reply to a closed ticket.</div>';
    } else {
        $conn->begin_transaction();
        try {
            // 1. Add the new reply
            $reply_stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())");
            $reply_stmt->bind_param('iis', $ticket_id, $user_id, $reply_message);
            if (!$reply_stmt->execute()) {
                throw new Exception("Error saving reply: " . $reply_stmt->error);
            }
            $reply_stmt->close();

            // 2. Update the parent ticket's status
            $update_stmt = $conn->prepare("UPDATE tickets SET status = 'user-reply' WHERE id = ?");
            $update_stmt->bind_param('i', $ticket_id);
            if (!$update_stmt->execute()) {
                throw new Exception("Error updating ticket status: " . $update_stmt->error);
            }
            $update_stmt->close();

            $conn->commit();
            $message = '<div class="alert alert-success">Your reply has been submitted.</div>';
            // Refresh ticket status after update
            $ticket['status'] = 'user-reply';

        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert alert-danger">An error occurred: ' . $e->getMessage() . '</div>';
        }
    }
}

// Handle Close Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_ticket'])) {
    if ($ticket['status'] !== 'closed') {
        $close_stmt = $conn->prepare("UPDATE tickets SET status = 'closed' WHERE id = ?");
        $close_stmt->bind_param('i', $ticket_id);
        if ($close_stmt->execute()) {
            $message = '<div class="alert alert-info">You have closed this ticket.</div>';
            $ticket['status'] = 'closed';
        } else {
            $message = '<div class="alert alert-danger">Error closing ticket.</div>';
        }
        $close_stmt->close();
    }
}


// Fetch all replies for this ticket, joining with user table to get usernames and roles
$replies_stmt = $conn->prepare("SELECT r.message, r.created_at, u.username FROM ticket_replies r JOIN users u ON r.user_id = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
$replies_stmt->bind_param('i', $ticket_id);
$replies_stmt->execute();
$replies_result = $replies_stmt->get_result();
$replies_stmt->close();

$conn->close();
?>

<style>
    .chat-container { border: 1px solid #ddd; border-radius: 5px; padding: 1rem; display: flex; flex-direction: column; gap: 10px; }
    .chat-bubble { padding: 1rem; border-radius: 10px; max-width: 80%; word-wrap: break-word; }
    .chat-bubble.user { background-color: #e1f5fe; align-self: flex-end; }
    .chat-bubble.admin { background-color: #f1f8e9; align-self: flex-start; }
    .chat-meta { font-size: 0.8rem; color: #666; margin-top: 5px; }
</style>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3>Ticket #<?php echo $ticket_id; ?>: <?php echo htmlspecialchars($ticket['subject']); ?></h3>
        <a href="support_tickets.php" class="btn">&larr; Back to All Tickets</a>
    </div>

    <?php echo $message; ?>

    <div class="chat-container">
        <?php while($reply = $replies_result->fetch_assoc()): ?>
            <div class="chat-bubble <?php echo ($reply['role'] === 'admin') ? 'admin' : 'user'; ?>">
                <p><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
                <div class="chat-meta">
                    <strong><?php echo htmlspecialchars($reply['username']); ?></strong>
                    - <?php echo date('Y-m-d H:i', strtotime($reply['created_at'])); ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <?php if ($ticket['status'] !== 'closed'): ?>
        <hr>
        <h4>Add Your Reply</h4>
        <form action="ticket_details.php?id=<?php echo $ticket_id; ?>" method="POST">
            <div class="form-group">
                <textarea name="message" class="form-control" rows="5" required></textarea>
            </div>
            <button type="submit" name="add_reply" class="btn btn-primary">Submit Reply</button>
        </form>

        <hr>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $ticket_id); ?>" method="POST" style="text-align: right;">
            <button type="submit" name="close_ticket" class="btn btn-danger" onclick="return confirm('Are you sure you want to close this ticket?');">Close This Ticket</button>
        </form>
    <?php else: ?>
        <div class="alert alert-info" style="margin-top: 2rem; text-align: center;">This ticket has been closed.</div>
    <?php endif; ?>

</div>

<?php require_once 'footer.php'; ?>