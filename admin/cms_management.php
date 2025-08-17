<?php
require_once 'header.php';
require_once '../db_connect.php';

$banner_message = '';
$popup_message = '';
$faq_message = '';

// Handle Add New Banner submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_banner'])) {
    $title = $_POST['title'] ?? '';
    $link_url = $_POST['link_url'] ?? '';
    $display_order = $_POST['display_order'] ?? 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $file_name = $_FILES['banner_image']['name'] ?? '';
    $file_tmp_name = $_FILES['banner_image']['tmp_name'] ?? '';
    $file_error = $_FILES['banner_image']['error'] ?? UPLOAD_ERR_NO_FILE;

    if (empty($title) || $file_error !== UPLOAD_ERR_OK) {
        $banner_message = "<div class=\"alert alert-danger\">Title and an image are required for the banner.</div>";
    } else {
        $upload_dir = '../uploads/banners/'; // IMPORTANT: Create this directory and ensure it's writable
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($file_ext, $allowed_ext)) {
            $banner_message = "<div class=\"alert alert-danger\">Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.</div>";
        } else {
            $new_file_name = uniqid('banner_') . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp_name, $destination)) {
                $insert_query = "INSERT INTO banners (title, image_path, link_url, display_order, is_active) VALUES (?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param('sssii', $title, $new_file_name, $link_url, $display_order, $is_active);
                if ($insert_stmt->execute()) {
                    $banner_message = "<div class=\"alert alert-success\">Banner '" . htmlspecialchars($title) . "' added successfully!</div>";
                } else {
                    $banner_message = "<div class=\"alert alert-danger\">Error adding banner: " . $insert_stmt->error . "</div>";
                }
                $insert_stmt->close();
            } else {
                $banner_message = "<div class=\"alert alert-danger\">Failed to upload banner image.</div>";
            }
        }
    }
}

// Handle Add New Popup/Announcement submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_popup'])) {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $type = $_POST['type'] ?? 'popup';
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($title) || empty($content)) {
        $popup_message = "<div class=\"alert alert-danger\">Title and Content are required for the popup/announcement.</div>";
    } else {
        $insert_query = "INSERT INTO popups (title, content, type, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param('sssssi', $title, $content, $type, $start_date, $end_date, $is_active);
        if ($insert_stmt->execute()) {
            $popup_message = "<div class=\"alert alert-success\">Popup/Announcement '" . htmlspecialchars($title) . "' added successfully!</div>";
        } else {
            $popup_message = "<div class=\"alert alert-danger\">Error adding popup/announcement: " . $insert_stmt->error . "</div>";
        }
        $insert_stmt->close();
    }
}

// Handle Add New FAQ submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faq'])) {
    $question = $_POST['question'] ?? '';
    $answer = $_POST['answer'] ?? '';
    $display_order = $_POST['display_order'] ?? 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($question) || empty($answer)) {
        $faq_message = "<div class=\"alert alert-danger\">Question and Answer are required for the FAQ.</div>";
    } else {
        $insert_query = "INSERT INTO faqs (question, answer, display_order, is_active) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param('ssii', $question, $answer, $display_order, $is_active);
        if ($insert_stmt->execute()) {
            $faq_message = "<div class=\"alert alert-success\">FAQ added successfully!</div>";
        } else {
            $faq_message = "<div class=\"alert alert-danger\">Error adding FAQ: " . $insert_stmt->error . "</div>";
        }
        $insert_stmt->close();
    }
}

// Fetch CMS Pages
$pages_query = "SELECT * FROM cms_pages ORDER BY title";
$pages_result = $conn->query($pages_query);

// Fetch Blog Posts
$blog_posts_query = "SELECT bp.*, u.username as author_username FROM blog_posts bp LEFT JOIN users u ON bp.author_id = u.id ORDER BY bp.published_at DESC";
$blog_posts_result = $conn->query($blog_posts_query);

// Fetch Banners
$banners_query = "SELECT * FROM banners ORDER BY display_order ASC";
$banners_result = $conn->query($banners_query);

// Fetch Popups
$popups_query = "SELECT * FROM popups ORDER BY created_at DESC";
$popups_result = $conn->query($popups_query);

// Fetch FAQs
$faqs_query = "SELECT * FROM faqs ORDER BY display_order ASC";
$faqs_result = $conn->query($faqs_query);


?>

<style>
    .tabs { display: flex; border-bottom: 2px solid #ccc; margin-bottom: 2rem; }
    .tab-link { padding: 1rem 1.5rem; cursor: pointer; border-bottom: 2px solid transparent; }
    .tab-link.active { border-color: var(--accent-color); font-weight: bold; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .alert-success { background-color: var(--success); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
    .alert-danger { background-color: var(--danger); color: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
</style>

<div class="card">
    <h3>Content Management (CMS)</h3>

    <div class="tabs">
        <div class="tab-link active" onclick="openTab(event, 'pages')">Pages</div>
        <div class="tab-link" onclick="openTab(event, 'blog-posts')">Blog Posts</div>
        <div class="tab-link" onclick="openTab(event, 'banners')">Banners/Sliders</div>
        <div class="tab-link" onclick="openTab(event, 'popups')">Popups/Announcements</div>
        <div class="tab-link" onclick="openTab(event, 'faq')">FAQ</div>
    </div>

    <!-- Pages Tab -->
    <div id="pages" class="tab-content active">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;"><h4>All CMS Pages</h4><a href="add_page.php" class="btn btn-primary">Add New Page</a></div>
        <table class="table">
            <thead>
                <tr><th>Title</th><th>Slug</th><th>Status</th><th>Created At</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($pages_result && $pages_result->num_rows > 0): ?>
                    <?php while($page = $pages_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($page['title']); ?></td>
                            <td><?php echo htmlspecialchars($page['slug']); ?></td>
                            <td><?php echo htmlspecialchars($page['status']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($page['created_at'])); ?></td>
                            <td><a href="edit_page.php?id=<?php echo $page['id']; ?>" class="btn btn-primary btn-sm">Edit</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center;">No CMS pages found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Blog Posts Tab -->
    <div id="blog-posts" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;"><h4>All Blog Posts</h4><a href="add_post.php" class="btn btn-primary">Add New Post</a></div>
        <table class="table">
            <thead>
                <tr><th>Title</th><th>Author</th><th>Status</th><th>Published At</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($blog_posts_result && $blog_posts_result->num_rows > 0): ?>
                    <?php while($post = $blog_posts_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($post['title']); ?></td>
                            <td><?php echo htmlspecialchars($post['author_username']); ?></td>
                            <td><?php echo htmlspecialchars($post['status']); ?></td>
                            <td><?php echo $post['published_at'] ? date('Y-m-d', strtotime($post['published_at'])) : 'N/A'; ?></td>
                            <td><a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn btn-primary btn-sm">Edit</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center;">No blog posts found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Banners/Sliders Tab -->
    <div id="banners" class="tab-content">
        <h4>Home Page Banners & Sliders</h4>
        <?php echo $banner_message; // Display banner messages ?>
        <form action="cms_management.php" method="POST" enctype="multipart/form-data" style="margin-bottom: 2rem;">
            <div class="form-group">
                <label for="banner_title">Title (optional)</label>
                <input type="text" id="banner_title" name="title" class="form-control">
            </div>
            <div class="form-group">
                <label for="banner_image">Banner Image</label>
                <input type="file" id="banner_image" name="banner_image" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="banner_link_url">Link URL (optional)</label>
                <input type="url" id="banner_link_url" name="link_url" class="form-control">
            </div>
            <div class="form-group">
                <label for="banner_display_order">Display Order</label>
                <input type="number" id="banner_display_order" name="display_order" class="form-control" value="0">
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_active" value="1" checked> Is Active</label>
            </div>
            <button type="submit" name="add_banner" class="btn btn-primary">Add Banner</button>
        </form>

        <h4 style="margin-top: 3rem;">Existing Banners</h4>
        <table class="table">
            <thead>
                <tr><th>Image</th><th>Title</th><th>Link</th><th>Order</th><th>Active</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($banners_result && $banners_result->num_rows > 0): ?>
                    <?php while($banner = $banners_result->fetch_assoc()): ?>
                        <tr>
                            <td><img src="../uploads/banners/<?php echo htmlspecialchars($banner['image_path']); ?>" alt="Banner" style="width: 100px; height: auto;"></td>
                            <td><?php echo htmlspecialchars($banner['title']); ?></td>
                            <td><?php echo htmlspecialchars($banner['link_url']); ?></td>
                            <td><?php echo htmlspecialchars($banner['display_order']); ?></td>
                            <td><?php echo $banner['is_active'] ? 'Yes' : 'No'; ?></td>
                            <td>
                                <a href="edit_banner.php?id=<?php echo $banner['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="delete_banner.php?id=<?php echo $banner['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this banner?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">No banners found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Popups/Announcements Tab -->
    <div id="popups" class="tab-content">
        <h4>Popup & Announcement Management</h4>
        <?php echo $popup_message; // Display popup messages ?>
        <form action="cms_management.php" method="POST" style="margin-bottom: 2rem;">
            <div class="form-group">
                <label for="popup_title">Title</label>
                <input type="text" id="popup_title" name="title" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="popup_content">Content</label>
                <textarea id="popup_content" name="content" class="form-control" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label for="popup_type">Type</label>
                <select id="popup_type" name="type" class="form-control">
                    <option value="popup">Popup</option>
                    <option value="announcement">Announcement</option>
                </select>
            </div>
            <div class="form-group">
                <label for="popup_start_date">Start Date (optional)</label>
                <input type="datetime-local" id="popup_start_date" name="start_date" class="form-control">
            </div>
            <div class="form-group">
                <label for="popup_end_date">End Date (optional)</label>
                <input type="datetime-local" id="popup_end_date" name="end_date" class="form-control">
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_active" value="1" checked> Is Active</label>
            </div>
            <button type="submit" name="add_popup" class="btn btn-primary">Add Popup/Announcement</button>
        </form>

        <h4 style="margin-top: 3rem;">Existing Popups/Announcements</h4>
        <table class="table">
            <thead>
                <tr><th>Title</th><th>Type</th><th>Active</th><th>Start Date</th><th>End Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($popups_result && $popups_result->num_rows > 0): ?>
                    <?php while($popup = $popups_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($popup['title']); ?></td>
                            <td><?php echo htmlspecialchars($popup['type']); ?></td>
                            <td><?php echo $popup['is_active'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo $popup['start_date'] ? date('Y-m-d H:i', strtotime($popup['start_date'])) : 'N/A'; ?></td>
                            <td><?php echo $popup['end_date'] ? date('Y-m-d H:i', strtotime($popup['end_date'])) : 'N/A'; ?></td>
                            <td>
                                <a href="edit_popup.php?id=<?php echo $popup['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="delete_popup.php?id=<?php echo $popup['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this popup/announcement?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">No popups/announcements found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- FAQ Tab -->
    <div id="faq" class="tab-content">
        <h4>FAQ Management</h4>
        <p><em>(Feature coming soon)</em></p>
    </div>

</div>

<script>
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) { tabcontent[i].style.display = "none"; }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) { tablinks[i].className = tablinks[i].className.replace(" active", ""); }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}

// Activate the correct tab on page load if a message is present
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('tab')) {
        openTab(event, urlParams.get('tab'));
    } else if (urlParams.has('banner_added') || urlParams.has('banner_error')) {
        openTab(event, 'banners');
    } else if (urlParams.has('popup_added') || urlParams.has('popup_error')) {
        openTab(event, 'popups');
    }
};
</script>

<?php

require_once 'footer.php';
?>