<?php
require_once 'header.php';
require_once '../db_connect.php';

$investment_message = '';

// Handle Invest Now submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invest_now'])) {
    $plan_id = $_POST['plan_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    $wallet_id = $_POST['wallet_id'] ?? 0;

    if (!is_numeric($plan_id) || !is_numeric($amount) || $amount <= 0 || !is_numeric($wallet_id)) {
        $investment_message = "<div class=\"alert alert-danger\">Invalid investment details.</div>";
    } else {
        $conn->begin_transaction();
        try {
            // Fetch plan details
            $plan_query = "SELECT * FROM investment_plans WHERE id = ? AND is_active = 1";
            $plan_stmt = $conn->prepare($plan_query);
            if ($plan_stmt === false) throw new Exception('Plan query failed: ' . $conn->error);
            $plan_stmt->bind_param('i', $plan_id);
            $plan_stmt->execute();
            $plan = $plan_stmt->get_result()->fetch_assoc();
            $plan_stmt->close();

            if (!$plan) {
                throw new Exception("Investment plan not found or not active.");
            }

            // Check amount against min/max limits
            if ($amount < $plan['min_amount'] || ($plan['max_amount'] > 0 && $amount > $plan['max_amount'])) {
                throw new Exception("Amount is outside the plan's allowed limits.");
            }

            // Fetch wallet balance
            $wallet_query = "SELECT balance, available, id FROM wallets WHERE id = ? AND user_id = ?";
            $wallet_stmt = $conn->prepare($wallet_query);
            if ($wallet_stmt === false) throw new Exception('Wallet query failed: ' . $conn->error);
            $wallet_stmt->bind_param('ii', $wallet_id, $user_id);
            $wallet_stmt->execute();
            $wallet = $wallet_stmt->get_result()->fetch_assoc();
            $wallet_stmt->close();

            if (!$wallet) {
                throw new Exception("Wallet not found or does not belong to the user.");
            }
            if ($wallet['available'] < $amount) {
                throw new Exception("Insufficient funds in selected wallet.");
            }

            // Calculate end date and next payout date
            $started_at = date('Y-m-d H:i:s');
            $ends_at = null;
            if ($plan['duration_value'] > 0) {
                $ends_at = date('Y-m-d H:i:s', strtotime($started_at . ' +' . $plan['duration_value'] . ' ' . $plan['duration_unit'] . 's'));
            }
            $next_payout_at = date('Y-m-d H:i:s', strtotime($started_at . ' +' . $plan['return_period_value'] . ' ' . $plan['return_period_unit'] . 's'));

            // Insert into investments table
            $insert_investment_query = "INSERT INTO investments (user_id, plan_id, wallet_id, amount, status, started_at, ends_at, next_payout_at) VALUES (?, ?, ?, ?, 'active', ?, ?, ?)";
            $insert_investment_stmt = $conn->prepare($insert_investment_query);
            if ($insert_investment_stmt === false) throw new Exception('Insert investment query failed: ' . $conn->error);
            $insert_investment_stmt->bind_param('iiidsss', $user_id, $plan_id, $wallet_id, $amount, $started_at, $ends_at, $next_payout_at);
            if (!$insert_investment_stmt->execute()) {
                throw new Exception("Error creating investment: " . $insert_investment_stmt->error);
            }
            $investment_id = $conn->insert_id;
            $insert_investment_stmt->close();

            // Debit wallet
            $update_wallet_query = "UPDATE wallets SET balance = balance - ?, available = available - ? WHERE id = ?";
            $update_wallet_stmt = $conn->prepare($update_wallet_query);
            if ($update_wallet_stmt === false) throw new Exception('Update wallet query failed: ' . $conn->error);
            $update_wallet_stmt->bind_param('ddi', $amount, $amount, $wallet['id']);
            if (!$update_wallet_stmt->execute()) {
                throw new Exception("Error debiting wallet.");
            }
            $update_wallet_stmt->close();

            // Record wallet transaction
            $wallet_balance_after_debit = $wallet['balance'] - $amount;
            $transaction_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, 'debit', ?, ?, ?, 'investment', ?, ?)";
            $transaction_stmt = $conn->prepare($transaction_query);
            if ($transaction_stmt === false) throw new Exception('Insert transaction query failed: ' . $conn->error);
            $remarks = 'Investment Made';
            $transaction_stmt->bind_param('idddis', $wallet['id'], $amount, $wallet['balance'], $wallet_balance_after_debit, $investment_id, $remarks);
            if (!$transaction_stmt->execute()) {
                throw new Exception("Error recording investment transaction.");
            }
            $transaction_stmt->close();

            $conn->commit();
            $investment_message = "<div class=\"alert alert-success\">Investment successful!</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $investment_message = "<div class=\"alert alert-danger\">Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch all active investment plans
$plans_query = "SELECT * FROM investment_plans WHERE is_active = 1 ORDER BY display_order";
$plans_result = $conn->query($plans_query);

// Fetch user's wallets for selection
$user_wallets_query = "
    SELECT w.id, w.balance, w.available, wt.name as wallet_type_name, c.code as currency_code
    FROM wallets w
    JOIN wallet_types wt ON w.wallet_type_id = wt.id
    JOIN currencies c ON w.currency_id = c.id
    WHERE w.user_id = ? AND c.code = 'USD' "; // Assuming investments are made in USD from main wallet
$stmt = $conn->prepare($user_wallets_query);
if ($stmt === false) {
    die("Error preparing user wallet query: " . $conn->error);
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_wallets_result = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<div class="card">
    <h3>Investment Plans</h3>
    <?php echo $investment_message; // Display messages ?>

    <div class="grid-container">
        <?php if ($plans_result && $plans_result->num_rows > 0): ?>
            <?php while($plan = $plans_result->fetch_assoc()): ?>
                <div class="card">
                    <h4><?php echo htmlspecialchars($plan['name']); ?></h4>
                    <p><strong>Amount:</strong> $<?php echo number_format($plan['min_amount']); ?> <?php echo ($plan['max_amount'] > 0 && $plan['max_amount'] != $plan['min_amount']) ? ' - $' . number_format($plan['max_amount']) : ''; ?></p>
                    <p><strong>Profit:</strong> <?php echo htmlspecialchars($plan['profit_value']); ?><?php echo ($plan['profit_type'] === 'percent') ? '%' : ' USD'; ?></p>
                    <p><strong>Duration:</strong> <?php echo htmlspecialchars($plan['duration_value']); ?> <?php echo htmlspecialchars($plan['duration_unit']); ?>(s)</p>
                    <p><strong>Returns:</strong> <?php echo htmlspecialchars($plan['number_of_returns']); ?> times every <?php echo htmlspecialchars($plan['return_period_value']); ?> <?php echo htmlspecialchars($plan['return_period_unit']); ?>(s)</p>
                    <p><strong>Capital Back:</strong> <?php echo ($plan['capital_back']) ? 'Yes' : 'No'; ?></p>
                    <button class="btn btn-primary" onclick="openInvestModal(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name']); ?>', <?php echo $plan['min_amount']; ?>, <?php echo $plan['max_amount']; ?>)">Invest Now</button>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No active investment plans available.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Invest Now Modal -->
<div id="investModal" style="display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
  <div class="card" style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px;">
    <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;" onclick="closeInvestModal()">&times;</span>
    <h4>Invest in <span id="modalPlanName"></span></h4>
    <form action="investment_plans.php" method="POST">
        <input type="hidden" name="plan_id" id="modalPlanId">
        <div class="form-group">
            <label for="modalAmount">Amount ($<span id="modalMinAmount"></span> - $<span id="modalMaxAmount"></span>)</label>
            <input type="number" step="0.01" id="modalAmount" name="amount" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="modalWallet">Select Wallet</label>
            <select id="modalWallet" name="wallet_id" class="form-control" required>
                <?php if ($user_wallets_result && $user_wallets_result->num_rows > 0): ?>
                    <?php while($wallet = $user_wallets_result->fetch_assoc()): ?>
                        <option value="<?php echo $wallet['id']; ?>"><?php echo htmlspecialchars($wallet['wallet_type_name']); ?> (<?php echo htmlspecialchars($wallet['currency_code']); ?>) - $<?php echo number_format($wallet['available'], 2); ?> Available</option>
                    <?php endwhile; ?>
                <?php else: ?>
                    <option value="">No USD wallets found.</option>
                <?php endif; ?>
            </select>
        </div>
        <button type="submit" name="invest_now" class="btn btn-primary">Confirm & Proceed</button>
    </form>
  </div>
</div>

<script>
var investModal = document.getElementById('investModal');
var modalPlanName = document.getElementById('modalPlanName');
var modalPlanId = document.getElementById('modalPlanId');
var modalMinAmount = document.getElementById('modalMinAmount');
var modalMaxAmount = document.getElementById('modalMaxAmount');
var modalAmount = document.getElementById('modalAmount');

function openInvestModal(planId, planName, minAmount, maxAmount) {
    modalPlanId.value = planId;
    modalPlanName.textContent = planName;
    modalMinAmount.textContent = minAmount;
    modalMaxAmount.textContent = maxAmount > 0 ? maxAmount : 'Unlimited';
    modalAmount.min = minAmount;
    if (maxAmount > 0) {
        modalAmount.max = maxAmount;
    }
    modalAmount.value = minAmount; // Pre-fill with min amount
    investModal.style.display = "block";
}

function closeInvestModal() {
    investModal.style.display = "none";
}

window.onclick = function(event) {
    if (event.target == investModal) {
        investModal.style.display = "none";
    }
}
</script>

<?php require_once 'footer.php'; ?>
