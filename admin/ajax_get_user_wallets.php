<?php
header('Content-Type: application/json');
require_once '../db_connect.php';

$response = ['success' => false, 'wallets' => [], 'message' => 'An error occurred.'];

if (!isset($_GET['user_id'])) {
    $response['message'] = 'User ID is required.';
    echo json_encode($response);
    exit;
}

$user_id = intval($_GET['user_id']);

try {
    $query = "
        SELECT 
            w.id, 
            w.balance, 
            wt.name as wallet_type_name, 
            c.code as currency_code
        FROM wallets w
        JOIN wallet_types wt ON w.wallet_type_id = wt.id
        JOIN currencies c ON w.currency_id = c.id
        WHERE w.user_id = ? AND wt.slug = 'main' AND c.code = 'USD'
        ORDER BY wt.name, c.code
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $wallets = [];
    while ($row = $result->fetch_assoc()) {
        $wallets[] = $row;
    }
    $stmt->close();

    $response['success'] = true;
    $response['wallets'] = $wallets;
    $response['message'] = 'Wallets fetched successfully.';

} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>