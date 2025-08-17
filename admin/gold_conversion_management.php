<?php
require_once 'header.php';
require_once '../db_connect.php';

$rate_message = '';
$conversion_request_message = '';

// Handle Save Gold Rate submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gold_rate'])) {
    $gram_price_usd = $_POST['gram_price_usd'] ?? 0;

    if (!is_numeric($gram_price_usd) || $gram_price_usd <= 0) {
        $rate_message = "<div class=\"alert alert-danger\">Invalid gold price.</div>";
    } else {
        $query = "INSERT INTO gold_rates (gram_price_usd, source) VALUES (?, 'manual') ON DUPLICATE KEY UPDATE gram_price_usd = VALUES(gram_price_usd), updated_at = NOW()";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('d', $gram_price_usd);
        if ($stmt->execute()) {
            $rate_message = "<div class=\"alert alert-success\">Gold rate updated successfully!</div>";
        } else {
            $rate_message = "<div class=\"alert alert-danger\">Error updating gold rate: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// Fetch current gold rate to pre-fill form
$current_gold_rate = '';
$query = "SELECT gram_price_usd FROM gold_rates ORDER BY updated_at DESC LIMIT 1";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $current_gold_rate = $row['gram_price_usd'];
}

// Fetch pending conversion requests
$pending_requests_query = "
    SELECT cr.*, u.username, fc.code as from_currency_code, tc.code as to_currency_code
    FROM conversion_requests cr
    JOIN users u ON cr.user_id = u.id
    JOIN currencies fc ON cr.from_currency_id = fc.id
    JOIN currencies tc ON cr.to_currency_id = tc.id
    WHERE cr.status = 'pending'
    ORDER BY cr.requested_at DESC
";
$pending_requests_result = $conn->query($pending_requests_query);

// Fetch all conversion history
$history_query = "
    SELECT cr.*, u.username, fc.code as from_currency_code, tc.code as to_currency_code
    FROM conversion_requests cr
    JOIN users u ON cr.user_id = u.id
    JOIN currencies fc ON cr.from_currency_id = fc.id
    JOIN currencies tc ON cr.to_currency_id = tc.id
    ORDER BY cr.requested_at DESC
";
$history_result = $conn->query($history_query);

$conn->close();
?>

<style>
    .tabs { display: flex; border-bottom: 2px solid #ccc; margin-bottom: 2rem; }
    .tab-link { padding: 1rem 1.5rem; cursor: pointer; border-bottom: 2px solid transparent; }
    .tab-link.active { border-color: var(--accent-color); font-weight: bold; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .alert-success { background-color: var(--success); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
    .alert-danger { background-color: var(--danger); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
</style>

<div class="card">
    <h3>Gold Conversion Management</h3>

    <div class="tabs">
        <div class="tab-link active" onclick="openTab(event, 'set-rates')">Set Rates</div>
        <div class="tab-link" onclick="openTab(event, 'conversion-requests')">Conversion Requests</div>
        <div class="tab-link" onclick="openTab(event, 'conversion-history')">Conversion History</div>
    </div>

    <!-- Set Rates Tab -->
    <div id="set-rates" class="tab-content active">
        <h4>Set Gold Price (per gram in USD)</h4>
        <?php echo $rate_message; // Display messages ?>
        <form action="gold_conversion_management.php" method="POST">
            <div class="form-group">
                <label>Gold Price per Gram (USD)</label>
                <input type="number" step="0.000001" class="form-control" name="gram_price_usd" value="<?php echo htmlspecialchars($current_gold_rate); ?>" required>
            </div>
            <button type="submit" name="save_gold_rate" class="btn btn-primary">Save Gold Rate</button>
        </form>

        <h4 style="margin-top: 2rem;">Other Rate Settings</h4>
        <p>Auto Rate Update via API: <em>(Feature coming soon)</em></p>
    </div>

    <!-- Conversion Requests Tab -->
    <div id="conversion-requests" class="tab-content">
        <h4>Pending Conversion Requests</h4>
        <?php echo $conversion_request_message; // Display messages ?>
        <table class="table">
            <thead>
                <tr><th>User</th><th>From</th><th>To</th><th>Amount</th><th>Requested At</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($pending_requests_result && $pending_requests_result->num_rows > 0): ?>
                    <?php while($request = $pending_requests_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['username']); ?></td>
                            <td><?php echo number_format($request['amount'], 8); ?> <?php echo htmlspecialchars($request['from_currency_code']); ?></td>
                            <td><?php echo htmlspecialchars($request['to_currency_code']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($request['requested_at'])); ?></td>
                            <td>
                                <a href="process_conversion_request.php?id=<?php echo $request['id']; ?>" class="btn btn-primary btn-sm">Process</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">No pending conversion requests found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Conversion History Tab -->
    <div id="conversion-history" class="tab-content">
        <h4>Conversion History & Reports</h4>
        <table class="table">
            <thead>
                <tr><th>User</th><th>From</th><th>To</th><th>Amount</th><th>Converted</th><th>Rate</th><th>Status</th><th>Requested At</th><th>Processed At</th></tr>
            </thead>
            <tbody>
                <?php if ($history_result && $history_result->num_rows > 0): ?>
                    <?php while($item = $history_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['username']); ?></td>
                            <td><?php echo number_format($item['amount'], 8); ?> <?php echo htmlspecialchars($item['from_currency_code']); ?></td>
                            <td><?php echo number_format($item['converted_amount'], 8); ?> <?php echo htmlspecialchars($item['to_currency_code']); ?></td>
                            <td><?php echo number_format($item['rate_at_conversion'], 8); ?></td>
                            <td style="text-transform: capitalize;"><?php echo htmlspecialchars($item['status']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($item['requested_at'])); ?></td>
                            <td><?php echo $item['processed_at'] ? date('Y-m-d H:i', strtotime($item['processed_at'])) : 'N/A'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9" style="text-align: center;">No conversion history found.</td></tr>
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
    if (urlParams.has('rate_saved')) {
        openTab(event, 'set-rates');
    } else if (urlParams.has('request_processed')) {
        openTab(event, 'conversion-requests');
    } else if (urlParams.has('history_view')) {
        openTab(event, 'conversion-history');
    }
};
</script>

<?php
require_once 'footer.php';
?>