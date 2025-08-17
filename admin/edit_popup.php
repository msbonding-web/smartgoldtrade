<?php
require_once 'header.php';
require_once '../db_connect.php';

$error_message = '';
$success_message = '';
$popup_id = $_GET['id'] ?? 0;

if (!is_numeric($popup_id) || $popup_id <= 0) {
    die("Invalid Popup ID.");
}

// Handle form submission for updating the popup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $type = $_POST['type'] ?? 'popup';
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($title) || empty($content)) {
        $error_message = "Title and Content are required.";
    } else {
        $query = "UPDATE popups SET title = ?, content = ?, type = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sssssii', $title, $content, $type, $start_date, $end_date, $is_active, $popup_id);

        if ($stmt->execute()) {
            $success_message = "Popup/Announcement '" . htmlspecialchars($title) . "' updated successfully! You will be redirected shortly.";
            echo "<meta http-equiv='refresh' content='3;url=cms_management.php?tab=popups'>";
        } else {
            $error_message = "Error updating popup/announcement: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch current popup details to populate the form
$query = "SELECT * FROM popups WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $popup_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $popup = $result->fetch_assoc();
} else {
    die("Popup/Announcement not found.");
}
$stmt->close();
$conn->close();
?>

<div class="card">
    <h3>Edit Popup/Announcement: <?php echo htmlspecialchars($popup['title']); ?></h3>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="edit_popup.php?id=<?php echo $popup_id; ?>" method="POST">
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($popup['title']); ?>" required>
        </div>
        <div class="form-group">
            <label for="content">Content</label>
            <textarea id="content" name="content" class="form-control" rows="5" required><?php echo htmlspecialchars($popup['content']); ?></textarea>
        </div>
        <div class="form-group">
            <label for="type">Type</label>
            <select id="type" name="type" class="form-control">
                <option value="popup" <?php if($popup['type'] === 'popup') echo 'selected'; ?>>Popup</option>
                <option value="announcement" <?php if($popup['type'] === 'announcement') echo 'selected'; ?>>Announcement</option>
            </select>
        </div>
        <div class="form-group">
            <label for="start_date">Start Date (optional)</label>
            <input type="datetime-local" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars(str_replace(' ', 'T', $popup['start_date'] ?? '')); ?>">
        </div>
        <div class="form-group">
            <label for="end_date">End Date (optional)</label>
            <input type="datetime-local" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars(str_replace(' ', 'T', $popup['end_date'] ?? '')); ?>">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_active" value="1" <?php if($popup['is_active']) echo 'checked'; ?>> Is Active</label>
        </div>
        <button type="submit" class="btn btn-primary">Update Popup/Announcement</button>
        <a href="cms_management.php?tab=popups" class="btn" style="background-color: var(--gray);">Cancel</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
