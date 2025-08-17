<?php
require_once 'header.php';
require_once '../db_connect.php';

$kyc_message = '';

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $doc_type = $_POST['doc_type'] ?? '';
    $file = $_FILES['document'];

    // Basic validation
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $max_size = 5 * 1024 * 1024; // 5 MB

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (empty($doc_type)) {
        $kyc_message = '<div class="alert alert-danger">Please select a document type.</div>';
    } else if ($file['error'] !== UPLOAD_ERR_OK) {
        $kyc_message = '<div class="alert alert-danger">File upload error. Please try again.</div>';
    } else if (!in_array($file['type'], $allowed_types) || !in_array($file_extension, $allowed_extensions)) {
        $kyc_message = '<div class="alert alert-danger">Invalid file type. Only JPG, PNG, and PDF are allowed.</div>';
    } else if ($file['size'] > $max_size) {
        $kyc_message = '<div class="alert alert-danger">File is too large. Maximum size is 5 MB.</div>';
    } else {
        // Create a unique filename and path
        $upload_dir = '../uploads/kyc/' . $user_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $filename = $doc_type . '-' . time() . '.' . $file_extension;
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Save to database
            $stmt = $conn->prepare("INSERT INTO kyc_documents (user_id, doc_type, file_path, status) VALUES (?, ?, ?, 'pending')");
            $db_filepath = 'uploads/kyc/' . $user_id . '/' . $filename; // Store relative path for admin access
            $stmt->bind_param('iss', $user_id, $doc_type, $db_filepath);
            if ($stmt->execute()) {
                $kyc_message = '<div class="alert alert-success">Document uploaded successfully and is pending review.</div>';
            } else {
                $kyc_message = '<div class="alert alert-danger">Database error. Could not save submission.</div>';
            }
            $stmt->close();
        } else {
            $kyc_message = '<div class="alert alert-danger">Could not move uploaded file.</div>';
        }
    }
}

// Fetch user's overall KYC status and document history
$kyc_status = 'Not Verified';
$docs_stmt = $conn->prepare("SELECT doc_type, status, created_at, remarks FROM kyc_documents WHERE user_id = ? ORDER BY created_at DESC");
if ($docs_stmt === false) {
    die("Error preparing KYC query: " . $conn->error);
}
$docs_stmt->bind_param('i', $user_id);
$docs_stmt->execute();
$documents = $docs_stmt->get_result();
$docs_stmt->close();

// Determine overall status
$has_approved = false;
$has_pending = false;
if ($documents->num_rows > 0) {
    foreach ($documents as $doc) {
        if ($doc['status'] === 'approved') $has_approved = true;
        if ($doc['status'] === 'pending') $has_pending = true;
    }
    if ($has_approved && !$has_pending) {
        $kyc_status = 'Approved';
    } elseif ($has_pending) {
        $kyc_status = 'Pending Review';
    } else {
        $kyc_status = 'Rejected'; // If no approved or pending, but has docs, must be rejected
    }
}

$conn->close();
?>

<div class="grid-container" style="grid-template-columns: 1fr 2fr; gap: 2rem;">
    <div class="card">
        <h4>Your KYC Status</h4>
        <p style="font-size: 1.5rem; font-weight: bold; color: <?php 
            if ($kyc_status === 'Approved') echo 'var(--success)'; 
            elseif ($kyc_status === 'Pending Review') echo 'var(--warning)'; 
            else echo 'var(--danger)'; 
        ?>;"><?php echo $kyc_status; ?></p>
        <p>Please upload the required documents to get your account fully verified.</p>
    </div>

    <div class="card">
        <h4>Upload Documents</h4>
        <?php echo $kyc_message; ?>
        <form action="kyc.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="doc_type">Document Type</label>
                <select name="doc_type" id="doc_type" class="form-control" required>
                    <option value="">-- Select Type --</option>
                    <option value="national_id">National ID</option>
                    <option value="passport">Passport</option>
                    <option value="drivers_license">Driver's License</option>
                    <option value="proof_of_address">Proof of Address (e.g., Utility Bill)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="document">Document File</label>
                <input type="file" name="document" id="document" class="form-control" required>
                <small>Accepted formats: JPG, PNG, PDF. Max size: 5MB.</small>
            </div>
            <button type="submit" class="btn btn-primary">Upload Document</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top: 2rem;">
    <h4>Submission History</h4>
    <table class="table">
        <thead>
            <tr>
                <th>Document Type</th>
                <th>Submitted At</th>
                <th>Status</th>
                <th>Admin Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($documents->num_rows > 0): ?>
                <?php $documents->data_seek(0); // Reset pointer after status check ?>
                <?php while($doc = $documents->fetch_assoc()): ?>
                    <tr>
                        <td style="text-transform: capitalize;"><?php echo str_replace('_', ' ', htmlspecialchars($doc['doc_type'])); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($doc['created_at'])); ?></td>
                        <td>
                            <span style="font-weight: bold; color: <?php 
                                if ($doc['status'] === 'approved') echo 'var(--success)'; 
                                elseif ($doc['status'] === 'pending') echo 'var(--warning)'; 
                                else echo 'var(--danger)'; 
                            ?>;"><?php echo ucfirst($doc['status']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($doc['remarks'] ?? '--'); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align: center;">You have not submitted any documents yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>