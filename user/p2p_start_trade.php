<?php
session_start();
require_once '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initiate_trade'])) {
    
    $offer_id = $_POST['offer_id'] ?? 0;
    $amount_to_receive = $_POST['amount_to_receive'] ?? 0; // This is the amount in asset

    if ($offer_id <= 0 || $amount_to_receive <= 0) {
        header("Location: p2p_market.php?error=InvalidInput");
        exit();
    }

    // Start a database transaction
    $conn->begin_transaction();

    try {
        // 1. Fetch offer details and lock the row to prevent race conditions
        $offer_stmt = $conn->prepare("SELECT * FROM p2p_offers WHERE id = ? AND status = 'active' FOR UPDATE");
        $offer_stmt->bind_param('i', $offer_id);
        $offer_stmt->execute();
        $offer_result = $offer_stmt->get_result();
        if ($offer_result->num_rows === 0) {
            throw new Exception("Offer is no longer available.");
        }
        $offer = $offer_result->fetch_assoc();
        $offer_stmt->close();

        // 2. Validate the trade amount against offer limits
        if ($amount_to_receive < $offer['min_amount'] || $amount_to_receive > $offer['max_amount']) {
            throw new Exception("Trade amount is outside the offer's limits.");
        }

        // 3. Determine buyer and seller
        $buyer_id = null;
        $seller_id = null;
        if ($offer['side'] === 'sell') { // The offer is to sell asset, so the current user is the buyer
            $buyer_id = $current_user_id;
            $seller_id = $offer['user_id'];
        } else { // The offer is to buy asset, so the current user is the seller
            $buyer_id = $offer['user_id'];
            $seller_id = $current_user_id;
        }

        if ($buyer_id === $seller_id) {
            throw new Exception("You cannot trade with yourself.");
        }

        // 4. If the current user is the seller, check their balance and lock funds
        if ($current_user_id === $seller_id) {
            // Find seller's P2P wallet for the specific asset
            $wallet_stmt = $conn->prepare("SELECT id, available FROM wallets WHERE user_id = ? AND currency_id = ? AND wallet_type_id = (SELECT id FROM wallet_types WHERE slug = 'p2p') FOR UPDATE");
            $wallet_stmt->bind_param('ii', $seller_id, $offer['asset_currency_id']);
            $wallet_stmt->execute();
            $wallet_result = $wallet_stmt->get_result();
            if ($wallet_result->num_rows === 0) {
                throw new Exception("Seller's P2P wallet for this asset not found. Please ensure you have a P2P wallet with funds.");
            }
            $seller_wallet = $wallet_result->fetch_assoc();
            $wallet_stmt->close();

            // Check if seller has enough available balance
            if ($seller_wallet['available'] < $amount_to_receive) {
                throw new Exception("You do not have enough available balance to lock for this trade.");
            }

            // Deduct from available and add to locked balance
            $update_wallet_stmt = $conn->prepare("UPDATE wallets SET available = available - ?, locked = locked + ? WHERE id = ?");
            $update_wallet_stmt->bind_param('ddi', $amount_to_receive, $amount_to_receive, $seller_wallet['id']);
            if (!$update_wallet_stmt->execute()) {
                throw new Exception("Failed to lock funds in your wallet.");
            }
            $update_wallet_stmt->close();
        }

        // 5. Create the trade record in p2p_trades
        $trade_status = 'initiated';
        $fiat_amount = $amount_to_receive * $offer['price'];
        $fiat_currency_id = 1; // Assuming USD

        $insert_trade_stmt = $conn->prepare("INSERT INTO p2p_trades (offer_id, buyer_id, seller_id, amount, price, currency_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insert_trade_stmt->bind_param('iiiddis', $offer_id, $buyer_id, $seller_id, $fiat_amount, $offer['price'], $fiat_currency_id, $trade_status);
        if (!$insert_trade_stmt->execute()) {
            throw new Exception("Failed to create the trade record.");
        }
        $new_trade_id = $conn->insert_id;
        $insert_trade_stmt->close();
        
        // 6. Create the escrow record
        $escrow_status = 'held';
        // Note: The wallet_id here is conceptual. The actual funds are locked in the user's p2p wallet.
        // We need a wallet to represent the escrow holder. For now, let's assume a placeholder or the seller's wallet id.
        $seller_wallet_id_for_escrow = $seller_wallet['id'] ?? null; // This would need a more robust solution for a central escrow system.
        
        $insert_escrow_stmt = $conn->prepare("INSERT INTO p2p_escrows (trade_id, escrow_wallet_id, amount, currency_id, status) VALUES (?, ?, ?, ?, ?)");
        $insert_escrow_stmt->bind_param('iidis', $new_trade_id, $seller_wallet_id_for_escrow, $amount_to_receive, $offer['asset_currency_id'], $escrow_status);
        if (!$insert_escrow_stmt->execute()) {
            throw new Exception("Failed to create escrow record.");
        }
        $insert_escrow_stmt->close();


        // If all steps are successful, commit the transaction
        $conn->commit();

        // Redirect to the active trade page
        header("Location: p2p_chat.php?trade_id=" . $new_trade_id);
        exit();

    } catch (Exception $e) {
        // If any step fails, roll back the transaction
        $conn->rollback();
        // Redirect back with an error message
        header("Location: p2p_view_offer.php?offer_id=" . $offer_id . "&error=" . urlencode($e->getMessage()));
        exit();
    }

} else {
    // If not a POST request, redirect away
    header("Location: p2p_market.php");
    exit();
}
?>
