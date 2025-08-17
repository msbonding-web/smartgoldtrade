<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$faq_id = $_GET['id'] ?? 0;

if (!is_numeric($faq_id) || $faq_id <= 0) {
    die("Invalid FAQ ID.");
}

// Handle form submission for updating the FAQ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = $_POST['question'] ?? '';
    $answer = $_POST['answer'] ?? '';
    $display_order = $_POST['display_order'] ?? 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($question) || empty($answer)) {
        $error_message = "Question and Answer are required.";
    } else {
        $query = "UPDATE faqs SET question = ?, answer = ?, display_order = ?, is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssiii', $question, $answer, $display_order, $is_active, $faq_id);

        if ($stmt->execute()) {
            $success_message = "FAQ updated successfully! You will be redirected shortly.";
            echo "<meta http-equiv='refresh' content='3;url=cms_management.php?tab=faq'>";
        } else {
            $error_message = "Error updating FAQ: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch current FAQ details to populate the form
$query = "SELECT * FROM faqs WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $faq_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $faq = $result->fetch_assoc();
} else {
    die("FAQ not found.");
}
$stmt->close();
$conn->close();
?>

<div class="card">
    <h3>Edit FAQ: <?php echo htmlspecialchars($faq['question']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_faq.php?id=<?php echo $faq_id; ?>" method="POST">
        <div class="form-group">
            <label for="question">Question</label>
            <input type="text" id="question" name="question" class="form-control" value="<?php echo htmlspecialchars($faq['question']); ?>" required>
        </div>
        <div class="form-group">
            <label for="answer">Answer</label>
            <textarea id="answer" name="answer" class="form-control" rows="5" required><?php echo htmlspecialchars($faq['answer']); ?></textarea>
        </div>
        <div class="form-group">
            <label for="display_order">Display Order</label>
            <input type="number" id="display_order" name="display_order" class="form-control" value="<?php echo htmlspecialchars($faq['display_order']); ?>">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_active" value="1" <?php if($faq['is_active']) echo 'checked'; ?>> Is Active</label>
        </div>
        <button type="submit" class="btn btn-primary">Update FAQ</button>
        <a href="cms_management.php?tab=faq" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
