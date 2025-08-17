<?php
// This script is intended to be run by a cron job on a regular basis (e.g., once every hour or once a day).
// It will find all active investments that have reached their maturity date and process their payouts.

require_once __DIR__ . '/../db_connect.php';

echo "Starting automatic payout process...\n";

// 1. Find all matured investments
$matured_investments_query = "SELECT id FROM investments WHERE status = 'active' AND ends_at <= NOW()";
$result = $conn->query($matured_investments_query);

if (!$result) {
    die("Error fetching matured investments: " . $conn->error . "\n");
}

if ($result->num_rows === 0) {
    echo "No matured investments to process.\n";
    exit();
}

$matured_ids = [];
while ($row = $result->fetch_assoc()) {
    $matured_ids[] = $row['id'];
}

echo "Found " . count($matured_ids) . " matured investment(s) to process.\n";

$processed_count = 0;
$failed_count = 0;

// 2. Loop through and process each one
foreach ($matured_ids as $investment_id) {
    echo "Processing investment #$investment_id... ";
    
    $conn->begin_transaction();

    try {
        // Fetch investment details and lock the row
        $inv_stmt = $conn->prepare("SELECT user_id, amount, plan_id, status FROM investments WHERE id = ? FOR UPDATE");
        $inv_stmt->bind_param('i', $investment_id);
        $inv_stmt->execute();
        $investment = $inv_stmt->get_result()->fetch_assoc();
        $inv_stmt->close();

        if (!$investment || $investment['status'] !== 'active') {
            throw new Exception("Investment not found or not active.");
        }

        // Fetch plan details
        $plan_stmt = $conn->prepare("SELECT profit_value FROM investment_plans WHERE id = ?");
        $plan_stmt->bind_param('i', $investment['plan_id']);
        $plan_stmt->execute();
        $plan = $plan_stmt->get_result()->fetch_assoc();
        $plan_stmt->close();

        if (!$plan) {
            throw new Exception("Plan not found.");
        }

        // Calculate payout
        $profit = $investment['amount'] * ($plan['profit_value'] / 100);
        $payout = $investment['amount'] + $profit;

        // Find user's main wallet and lock it
        $wallet_stmt = $conn->prepare("SELECT w.id, w.balance FROM wallets w JOIN wallet_types wt ON w.wallet_type_id = wt.id JOIN currencies c ON w.currency_id = c.id WHERE w.user_id = ? AND wt.slug = 'main' AND c.code = 'USD' FOR UPDATE");
        $wallet_stmt->bind_param('i', $investment['user_id']);
        $wallet_stmt->execute();
        $wallet = $wallet_stmt->get_result()->fetch_assoc();
        $wallet_stmt->close();

        if (!$wallet) {
            throw new Exception("User wallet not found.");
        }

        // Update wallet balance
        $update_wallet_stmt = $conn->prepare("UPDATE wallets SET balance = balance + ?, available = available + ? WHERE id = ?");
        $update_wallet_stmt->bind_param('ddi', $payout, $payout, $wallet['id']);
        $update_wallet_stmt->execute();
        $update_wallet_stmt->close();

        // Update investment status
        $update_inv_stmt = $conn->prepare("UPDATE investments SET status = 'completed', total_profit = ?, payout_at = NOW() WHERE id = ?");
        $update_inv_stmt->bind_param('di', $profit, $investment_id);
        $update_inv_stmt->execute();
        $update_inv_stmt->close();

        // Record wallet transaction (using the version of the query that we know works)
        $balance_after = $wallet['balance'] + $payout;
        $remarks = "Automatic payout for investment #" . $investment_id;
        $direction = 'credit';
        $ref_type = 'investment_payout';
        $trans_stmt = $conn->prepare("INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $trans_stmt->bind_param('isddiss', $wallet['id'], $direction, $payout, $wallet['balance'], $balance_after, $ref_type, $remarks);
        $trans_stmt->execute();
        $trans_stmt->close();

        $conn->commit();
        echo "Success.\n";
        $processed_count++;

    } catch (Exception $e) {
        $conn->rollback();
        echo "Failed: " . $e->getMessage() . "\n";
        $failed_count++;
    }
}

echo "------------------------------------\n";
echo "Payout process finished. Processed: $processed_count, Failed: $failed_count\n";

$conn->close();
?>