<?php
// No database connection needed for file deletion

// Check if file name is provided
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header("Location: system_settings.php?tab=backup-restore&error=invalid_file");
    exit();
}

$file_name = basename($_GET['file']); // Sanitize input to prevent directory traversal
$backup_dir = '../backups/';
$file_path = $backup_dir . $file_name;

if (file_exists($file_path) && pathinfo($file_path, PATHINFO_EXTENSION) === 'sql') {
    if (unlink($file_path)) {
        header("Location: system_settings.php?tab=backup-restore&delete=success");
    } else {
        header("Location: system_settings.php?tab=backup-restore&error=delete_failed");
    }
} else {
    header("Location: system_settings.php?tab=backup-restore&error=file_not_found");
}

exit();
?>
