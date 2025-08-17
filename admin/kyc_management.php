<?php
require_once 'header.php';
require_once '../db_connect.php';

// Fetch all pending KYC documents
$query = "
    SELECT 
        k.id, k.doc_type, k.created_at,
        u.username, u.email
    FROM kyc_documents k
    JOIN users u ON k.user_id = u.id
    WHERE k.status = 'pending'
    ORDER BY k.created_at ASC
";
$result = $conn->query($query);

?>

<div class="card">
    <h3>Pending KYC Submissions</h3>
    <p>The following documents have been submitted by users and are awaiting review.</p>

    <table class="table">
        <thead>
            <tr>
                <th>User</th>
                <th>Document Type</th>
                <th>Submitted At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($doc = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($doc['username']); ?></strong><br>
                            <small><?php echo htmlspecialchars($doc['email']); ?></small>
                        </td>
                        <td style="text-transform: capitalize;"><?php echo str_replace('_', ' ', htmlspecialchars($doc['doc_type'])); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($doc['created_at'])); ?></td>
                        <td>
                            <a href="kyc_review.php?id=<?php echo $doc['id']; ?>" class="btn btn-primary btn-sm">Review</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center;">No pending KYC submissions found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$conn->close();
require_once 'footer.php';
?>
