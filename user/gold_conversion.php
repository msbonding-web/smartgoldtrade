<?php
require_once 'header.php';
require_once '../db_connect.php';

$conversion_message = '';

// Fetch current gold rate (assuming XAU/USD for simplicity)
$gold_price_per_gram_usd = 0;
$gold_rate_query = "SELECT gram_price_usd FROM gold_rates ORDER BY updated_at DESC LIMIT 1";
$gold_rate_result = $conn->query($gold_rate_query);
if ($gold_rate_result && $gold_rate_result->num_rows > 0) {
    $gold_price_per_gram_usd = $gold_rate_result->fetch_assoc()['gram_price_usd'];
}

// Fetch user's wallets (specifically XAU and USD for conversion)
$user_wallets_query = "
    SELECT w.id, w.balance, w.available, wt.name as wallet_type_name, c.code as currency_code, c.id as currency_id
    FROM wallets w
    JOIN wallet_types wt ON w.wallet_type_id = wt.id
    JOIN currencies c ON w.currency_id = c.id
    WHERE w.user_id = ? AND (c.code = 'XAU' OR c.code = 'USD')
";
$stmt = $conn->prepare($user_wallets_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_wallets_result = $stmt->get_result();
$stmt->close();

// Handle Gold Conversion submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_gold'])) {
    $amount_to_convert = $_POST['amount_to_convert'] ?? 0;
    $from_currency_id = $_POST['from_currency_id'] ?? 0;
    $to_currency_id = $_POST['to_currency_id'] ?? 0;

    if (!is_numeric($amount_to_convert) || $amount_to_convert <= 0 || empty($from_currency_id) || empty($to_currency_id)) {
        $conversion_message = "<div class=\"alert alert-danger\">Invalid conversion details.</div>";
    } else {
        $conn->begin_transaction();
        try {
            // Fetch current gold rate (re-fetch to ensure latest)
            $gold_rate_query_re = "SELECT gram_price_usd FROM gold_rates ORDER BY updated_at DESC LIMIT 1";
            $gold_rate_result_re = $conn->query($gold_rate_query_re);
            $current_gold_price_per_gram_usd = $gold_rate_result_re->fetch_assoc()['gram_price_usd'] ?? 0;

            if ($current_gold_price_per_gram_usd <= 0) {
                throw new Exception("Gold price not set. Cannot perform conversion.");
            }

            // Fetch currency codes
            $currencies_query = "SELECT id, code FROM currencies WHERE id IN (?, ?)";
            $currencies_stmt = $conn->prepare($currencies_query);
            if ($currencies_stmt === false) throw new Exception('Currencies query failed: ' . $conn->error);
            $currencies_stmt->bind_param('ii', $from_currency_id, $to_currency_id);
            $currencies_stmt->execute();
            $currencies_result = $currencies_stmt->get_result();
            $currency_codes = [];
            while($c = $currencies_result->fetch_assoc()) {
                $currency_codes[$c['id']] = $c['code'];
            }
            $currencies_stmt->close();

            $from_code = $currency_codes[$from_currency_id] ?? null;
            $to_code = $currency_codes[$to_currency_id] ?? null;

            if(!$from_code || !$to_code) throw new Exception("Invalid currency selected.");

            // Determine conversion logic
            $converted_amount = 0;
            $rate_used = 0;

            if ($from_code === 'XAU' && $to_code === 'USD') {
                $converted_amount = $amount_to_convert * $current_gold_price_per_gram_usd;
                $rate_used = $current_gold_price_per_gram_usd;
            } elseif ($from_code === 'USD' && $to_code === 'XAU') {
                if ($current_gold_price_per_gram_usd == 0) throw new Exception("Gold price is zero. Cannot convert USD to XAU.");
                $converted_amount = $amount_to_convert / $current_gold_price_per_gram_usd;
                $rate_used = 1 / $current_gold_price_per_gram_usd;
            } else {
                throw new Exception("Unsupported conversion pair. Please select Gold to USD or USD to Gold.");
            }

            // Check user's balance in from_currency wallet
            $from_wallet_query = "SELECT id, balance, available FROM wallets WHERE user_id = ? AND currency_id = ?";
            $from_wallet_stmt = $conn->prepare($from_wallet_query);
            if ($from_wallet_stmt === false) throw new Exception('From wallet query failed: ' . $conn->error);
            $from_wallet_stmt->bind_param('ii', $user_id, $from_currency_id);
            $from_wallet_stmt->execute();
            $from_wallet = $from_wallet_stmt->get_result()->fetch_assoc();
            $from_wallet_stmt->close();

            if (!$from_wallet || $from_wallet['available'] < $amount_to_convert) {
                throw new Exception("Insufficient funds in your " . $from_code . " wallet.");
            }

            // Debit from source wallet
            $update_from_wallet_query = "UPDATE wallets SET balance = balance - ?, available = available - ? WHERE id = ?";
            $update_from_wallet_stmt = $conn->prepare($update_from_wallet_query);
            if ($update_from_wallet_stmt === false) throw new Exception('Debit wallet query failed: ' . $conn->error);
            $update_from_wallet_stmt->bind_param('ddi', $amount_to_convert, $amount_to_convert, $from_wallet['id']);
            if (!$update_from_wallet_stmt->execute()) {
                throw new Exception("Error debiting source wallet.");
            }
            $update_from_wallet_stmt->close();

            // Credit to destination wallet
            $to_wallet_query = "SELECT id, balance FROM wallets WHERE user_id = ? AND currency_id = ? AND wallet_type_id = (SELECT id FROM wallet_types WHERE slug = 'main')";
            $to_wallet_stmt = $conn->prepare($to_wallet_query);
            if ($to_wallet_stmt === false) throw new Exception('To wallet query failed: ' . $conn->error);
            $to_wallet_stmt->bind_param('ii', $user_id, $to_currency_id);
            $to_wallet_stmt->execute();
            $to_wallet = $to_wallet_stmt->get_result()->fetch_assoc();
            $to_wallet_stmt->close();

            $to_wallet_id = $to_wallet['id'] ?? null;

            // If destination wallet doesn't exist, create it
            if (!$to_wallet_id) {
                $main_wallet_type_query = "SELECT id FROM wallet_types WHERE slug = 'main' LIMIT 1";
                $main_wallet_type_result = $conn->query($main_wallet_type_query);
                $main_wallet_type_id = $main_wallet_type_result->fetch_assoc()['id'] ?? null;
                if (!$main_wallet_type_id) throw new Exception("Main wallet type not found.");

                $insert_wallet_query = "INSERT INTO wallets (user_id, wallet_type_id, currency_id, balance, available) VALUES (?, ?, ?, ?, ?)";
                $insert_wallet_stmt = $conn->prepare($insert_wallet_query);
                if ($insert_wallet_stmt === false) throw new Exception('Insert wallet query failed: ' . $conn->error);
                $insert_wallet_stmt->bind_param('iiddd', $user_id, $main_wallet_type_id, $to_currency_id, $converted_amount, $converted_amount);
                if (!$insert_wallet_stmt->execute()) {
                    throw new Exception("Error creating destination wallet.");
                }
                $to_wallet_id = $conn->insert_id;
                $insert_wallet_stmt->close();
            } else {
                $update_to_wallet_query = "UPDATE wallets SET balance = balance + ?, available = available + ? WHERE id = ?";
                $update_to_wallet_stmt = $conn->prepare($update_to_wallet_query);
                if ($update_to_wallet_stmt === false) throw new Exception('Credit wallet query failed: ' . $conn->error);
                $update_to_wallet_stmt->bind_param('ddi', $converted_amount, $converted_amount, $to_wallet_id);
                if (!$update_to_wallet_stmt->execute()) {
                    throw new Exception("Error crediting destination wallet.");
                }
                $update_to_wallet_stmt->close();
            }

            // Record conversion request
            $insert_request_query = "INSERT INTO conversion_requests (user_id, from_currency_id, to_currency_id, amount, converted_amount, rate_at_conversion, status, processed_at) VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())";
            $insert_request_stmt = $conn->prepare($insert_request_query);
            if ($insert_request_stmt === false) throw new Exception('Insert request query failed: ' . $conn->error);
            $insert_request_stmt->bind_param('iiidd', $user_id, $from_currency_id, $to_currency_id, $amount_to_convert, $converted_amount, $rate_used);
            if (!$insert_request_stmt->execute()) {
                throw new Exception("Error recording conversion request.");
            }
            $request_id = $conn->insert_id;
            $insert_request_stmt->close();

            // Record wallet transactions
            $from_wallet_balance_after = $from_wallet['balance'] - $amount_to_convert;
            $transaction_debit_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, 'debit', ?, ?, ?, 'conversion', ?, ?)";
            $transaction_debit_stmt = $conn->prepare($transaction_debit_query);
            if ($transaction_debit_stmt === false) throw new Exception('Debit transaction query failed: ' . $conn->error);
            $remarks_debit = 'Conversion to ' . $to_code;
            $transaction_debit_stmt->bind_param('idddis', $from_wallet['id'], $amount_to_convert, $from_wallet['balance'], $from_wallet_balance_after, $request_id, $remarks_debit);
            if (!$transaction_debit_stmt->execute()) throw new Exception("Error recording debit transaction.");
            $transaction_debit_stmt->close();

            $to_wallet_balance_after = ($to_wallet['balance'] ?? 0) + $converted_amount;
            $transaction_credit_query = "INSERT INTO wallet_transactions (wallet_id, direction, amount, balance_before, balance_after, ref_type, ref_id, remarks) VALUES (?, 'credit', ?, ?, ?, 'conversion', ?, ?)";
            $transaction_credit_stmt = $conn->prepare($transaction_credit_query);
            if ($transaction_credit_stmt === false) throw new Exception('Credit transaction query failed: ' . $conn->error);
            $remarks_credit = 'Conversion from ' . $from_code;
            $transaction_credit_stmt->bind_param('idddis', $to_wallet_id, $converted_amount, ($to_wallet['balance'] ?? 0), $to_wallet_balance_after, $request_id, $remarks_credit);
            if (!$transaction_credit_stmt->execute()) throw new Exception("Error recording credit transaction.");
            $transaction_credit_stmt->close();

            $conn->commit();
            $conversion_message = "<div class=\"alert alert-success\">Conversion successful!</div>";

            // Refresh wallet info after successful conversion
            $stmt = $conn->prepare($user_wallets_query);
            if ($stmt === false) die("Error preparing user wallet query: " . $conn->error);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $user_wallets_result = $stmt->get_result();
            $stmt->close();

        } catch (Exception $e) {
            $conn->rollback();
            $conversion_message = "<div class=\"alert alert-danger\">Conversion failed: " . $e->getMessage() . "</div>";
        }
    }
}


$conn->close();
?>

<div class="card">
    <h3>Gold Conversion</h3>
    <?php echo $conversion_message; // Display messages ?>

    <div class="grid-container" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <div>
            <h4>Current Gold Price</h4>
            <p style="font-size: 1.5rem; font-weight: bold;">1 Gram Gold = $<?php echo number_format($gold_price_per_gram_usd, 2); ?> USD</p>
            <p><small>Last updated: <?php echo date('Y-m-d H:i'); ?></small></p>
        </div>
        <div>
            <h4>Convert Your Gold/Funds</h4>
            <form action="gold_conversion.php" method="POST">
                <div class="form-group">
                    <label for="amount_to_convert">Amount to Convert</label>
                    <input type="number" step="0.000001" id="amount_to_convert" name="amount_to_convert" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="from_currency_id">From Currency</label>
                    <select id="from_currency_id" name="from_currency_id" class="form-control" required>
                        <option value="">Select Source Currency</option>
                        <?php 
                        // Reset pointer for user wallets result
                        if ($user_wallets_result) $user_wallets_result->data_seek(0);
                        while($wallet = $user_wallets_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $wallet['currency_id']; ?>"><?php echo htmlspecialchars($wallet['currency_code']); ?> (Balance: <?php echo number_format($wallet['available'], 2); ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="to_currency_id">To Currency</label>
                    <select id="to_currency_id" name="to_currency_id" class="form-control" required>
                        <option value="">Select Target Currency</option>
                        <?php 
                        // Reset pointer for user wallets result
                        if ($user_wallets_result) $user_wallets_result->data_seek(0);
                        while($wallet = $user_wallets_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $wallet['currency_id']; ?>"><?php echo htmlspecialchars($wallet['currency_code']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" name="convert_gold" class="btn btn-primary">Convert Now</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
