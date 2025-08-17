<?php
require_once 'header.php';
require_once '../db_connect.php';

$doc_id = $_GET['id'] ?? 0;

if (!is_numeric($doc_id) || $doc_id <= 0) {
    die("Invalid Document ID.");
}

// Handle Approve/Reject submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $admin_id = 1; // Placeholder for logged-in admin ID

    if ($status === 'approved' || $status === 'rejected') {
        $query = "UPDATE kyc_documents SET status = ?, remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssii', $status, $remarks, $admin_id, $doc_id);
        
        if ($stmt->execute()) {
            header("Location: kyc_management.php?update=success");
            exit();
        } else {
            $error_message = "Failed to update document status.";
        }
        $stmt->close();
    }
}

// Fetch document details
$query = "
    SELECT k.*, u.username, u.email 
    FROM kyc_documents k
    JOIN users u ON k.user_id = u.id
    WHERE k.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $doc_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $doc = $result->fetch_assoc();
} else {
    die("Document not found.");
}
$stmt->close();
$conn->close();

// IMPORTANT: This assumes your uploads are in a folder structure like /uploads/kyc/
// You may need to adjust this path based on your actual file storage location.
$file_url = '../' . $doc['file_path'];

?>

<a href="kyc_management.php" class="btn" style="margin-bottom: 1rem; background-color: var(--gray);">← Back to All Submissions</a>

<div class="card">
    <h3>Review KYC Document</h3>
    <div class="grid-container" style="grid-template-columns: 2fr 1fr; align-items: flex-start;">
        <div>
            <h4>Submitted Document</h4>
            <p><strong>Document Type:</strong> <?php echo htmlspecialchars($doc['doc_type']); ?></p>
            <p><strong>Document Number:</strong> <?php echo htmlspecialchars($doc['doc_number'] ?? 'N/A'); ?></p>
            <hr>
            <h4>User Information</h4>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($doc['username']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($doc['email']); ?></p>
            <hr>
            <div style="max-width: 100%; overflow: hidden;">
                <?php if (@getimagesize($file_url)): ?>
                    <img src="<?php echo htmlspecialchars($file_url); ?>" alt="KYC Document" style="max-width: 100%; border: 1px solid #ddd; border-radius: 5px;">
                <?php else: ?>
                    <p style="color: var(--danger);"><strong>Error:</strong> Image file not found or path is incorrect. Assumed path: <?php echo htmlspecialchars($file_url); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <h4>Actions</h4>
            <form action="kyc_review.php?id=<?php echo $doc_id; ?>" method="POST">
                <div class="form-group">
                    <label for="remarks">Remarks (Optional)</label>
                    <textarea name="remarks" id="remarks" rows="4" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" name="status" value="approved" class="btn btn-success" style="width: 100%;">Approve</button>
                </div>
                <div class="form-group">
                    <button type="submit" name="status" value="rejected" class="btn btn-danger" style="width: 100%;">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
