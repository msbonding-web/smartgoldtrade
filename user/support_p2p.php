<?php
require_once 'header.php';
require_once '../db_connect.php';

$ticket_message = '';

// Handle Create Ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $department = $_POST['department'] ?? 'general';
    $priority = $_POST['priority'] ?? 'low';

    if (empty($subject) || empty($message)) {
        $ticket_message = "<div class=\"alert alert-danger\">Subject and Message are required for the ticket.</div>";
    } else {
        $insert_query = "INSERT INTO tickets (user_id, subject, message, department, priority) VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param('issss', $user_id, $subject, $message, $department, $priority);
        if ($insert_stmt->execute()) {
            $ticket_message = "<div class=\"alert alert-success\">Your ticket has been created successfully!</div>";
        } else {
            $ticket_message = "<div class=\"alert alert-danger\">Error creating ticket: " . $insert_stmt->error . "</div>";
        }
        $insert_stmt->close();
    }
}

// Fetch user's P2P trade history
$p2p_trades_query = "
    SELECT pt.*, po.side, po.asset_currency_id, po.price as offer_price, c.code as currency_code
    FROM p2p_trades pt
    JOIN p2p_offers po ON pt.offer_id = po.id
    JOIN currencies c ON pt.currency_id = c.id
    WHERE pt.buyer_id = ? OR pt.seller_id = ?
    ORDER BY pt.created_at DESC
";
$stmt = $conn->prepare($p2p_trades_query);
$stmt->bind_param('ii', $user_id, $user_id);
$stmt->execute();
$p2p_trades_result = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<style>
    .tabs { display: flex; border-bottom: 2px solid #ccc; margin-bottom: 2rem; }
    .tab-link { padding: 1rem 1.5rem; cursor: pointer; border-bottom: 2px solid transparent; }
    .tab-link.active { border-color: var(--primary-color); font-weight: bold; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .alert-success { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    .alert-danger { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
</style>

<div class="card">
    <h3>Support & P2P Trading</h3>

    <div class="tabs">
        <div class="tab-link active" onclick="openTab(event, 'support')">Support Tickets</div>
        <div class="tab-link" onclick="openTab(event, 'p2p-history')">P2P Trading History</div>
        <a href="p2p_offers.php" class="tab-link">P2P Offers</a>
    </div>

    <!-- Support Tickets Tab -->
    <div id="support" class="tab-content active">
        <h4>Create New Support Ticket</h4>
        <?php echo $ticket_message; // Display messages ?>
        <form action="support_p2p.php" method="POST">
            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label for="department">Department</label>
                <select id="department" name="department" class="form-control">
                    <option value="general">General</option>
                    <option value="finance">Finance</option>
                    <option value="trading">Trading</option>
                    <option value="p2p">P2P</option>
                    <option value="technical">Technical</option>
                </select>
            </div>
            <div class="form-group">
                <label for="priority">Priority</label>
                <select id="priority" name="priority" class="form-control">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <button type="submit" name="create_ticket" class="btn btn-primary">Submit Ticket</button>
        </form>
    </div>

    <!-- P2P Trading History Tab -->
    <div id="p2p-history" class="tab-content">
        <h4>Your P2P Trading History</h4>
        <table class="table">
            <thead>
                <tr><th>Trade ID</th><th>Side</th><th>Amount</th><th>Price</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php if ($p2p_trades_result && $p2p_trades_result->num_rows > 0): ?>
                    <?php while($trade = $p2p_trades_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trade['id']); ?></td>
                            <td><?php echo htmlspecialchars($trade['side']); ?></td>
                            <td><?php echo number_format($trade['amount'], 2); ?> <?php echo htmlspecialchars($trade['currency_code']); ?></td>
                            <td><?php echo number_format($trade['price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($trade['status']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($trade['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">No P2P trading history found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) { tabcontent[i].style.display = "none"; }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) { tablinks[i].className = tablinks[i].className.replace(" active", ""); }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}

// Activate the correct tab on page load if a message is present
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('tab')) {
        openTab(event, urlParams.get('tab'));
    }
};
</script>

<?php require_once 'footer.php'; ?>
