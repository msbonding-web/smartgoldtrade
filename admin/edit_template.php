<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$template_id = $_GET['id'] ?? 0;

if (!is_numeric($template_id) || $template_id <= 0) {
    die("Invalid Template ID.");
}

// Handle form submission for updating the template
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $body = $_POST['body'] ?? '';
    $type = $_POST['type'] ?? 'email';
    $variables = $_POST['variables'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name) || empty($subject) || empty($body)) {
        $error_message = "Name, Subject, and Body are required for the template.";
    } else {
        // Check if name already exists (excluding current template)
        $check_query = "SELECT id FROM notification_templates WHERE name = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('si', $name, $template_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "Template name already exists.";
        } else {
            $query = "UPDATE notification_templates SET name = ?, subject = ?, body = ?, type = ?, variables = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('sssssii', $name, $subject, $body, $type, $variables, $is_active, $template_id);

            if ($stmt->execute()) {
                $success_message = "Template '" . htmlspecialchars($name) . "' updated successfully! You will be redirected shortly.";
                echo "<meta http-equiv='refresh' content='3;url=notification_management.php?tab=templates'>";
            } else {
                $error_message = "Error updating template: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Fetch current template details to populate the form
$query = "SELECT * FROM notification_templates WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $template_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $template = $result->fetch_assoc();
} else {
    die("Template not found.");
}
$stmt->close();
$conn->close();
?>

<div class="card">
    <h3>Edit Notification Template: <?php echo htmlspecialchars($template['name']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_template.php?id=<?php echo $template_id; ?>" method="POST">
        <div class="form-group">
            <label for="name">Template Name</label>
            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($template['name']); ?>" required>
        </div>
        <div class="form-group">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" class="form-control" value="<?php echo htmlspecialchars($template['subject']); ?>" required>
        </div>
        <div class="form-group">
            <label for="body">Body</label>
            <textarea id="body" name="body" class="form-control" rows="5" required><?php echo htmlspecialchars($template['body']); ?></textarea>
        </div>
        <div class="form-group">
            <label for="type">Type</label>
            <select id="type" name="type" class="form-control">
                <option value="email" <?php if($template['type'] === 'email') echo 'selected'; ?>>Email</option>
                <option value="sms" <?php if($template['type'] === 'sms') echo 'selected'; ?>>SMS</option>
                <option value="push" <?php if($template['type'] === 'push') echo 'selected'; ?>>Push</option>
            </select>
        </div>
        <div class="form-group">
            <label for="variables">Variables (comma-separated, e.g., {name}, {amount})</label>
            <input type="text" id="variables" name="variables" class="form-control" value="<?php echo htmlspecialchars($template['variables']); ?>">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_active" value="1" <?php if($template['is_active']) echo 'checked'; ?>> Is Active</label>
        </div>
        <button type="submit" class="btn btn-primary">Update Template</button>
        <a href="notification_management.php?tab=templates" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
