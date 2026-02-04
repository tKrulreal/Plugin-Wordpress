<?php
/*
Plugin Name: Simple Post Manager
Description: Plugin quản lý bài viết (CRUD) cho wp_posts
Version: 1.0
Author: Khương
*/

// Ngăn truy cập trực tiếp file
if (!defined('ABSPATH')) {
    exit;
}

// Thêm menu trong admin
add_action('admin_menu', 'spm_add_admin_menu');

function spm_add_admin_menu() {
    add_menu_page(
        'Simple Post Manager',       // Tên hiển thị ở thanh tiêu đề trang
        'Post Manager',              // Tên hiển thị ở menu sidebar
        'manage_options',            // Quyền truy cập (admin)
        'simple-post-manager',       // Slug (đường dẫn)
        'spm_admin_page_content',    // Hàm hiển thị nội dung
        'dashicons-edit',            // Icon (WordPress icon)
        26                           // Vị trí trong menu
    );
}

// Nội dung trang admin
function spm_admin_page_content() {
    global $wpdb;

    echo '<div class="wrap">';
    // --- CSS giao diện plugin ---
    echo '<style>
    input[name="search"] { border-radius:5px; border:1px solid #ccc; }
    select[name="status"] { border-radius:5px; border:1px solid #ccc; }
    form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }

    h1, h2 { color: #1d2327; }
    table.widefat th, table.widefat td { padding: 8px; text-align:left; }
    table.widefat tbody tr:nth-child(even) { background: #f9f9f9; }
    table.widefat tbody tr:hover { background: #eaf2ff; }
    .button-danger { background:#dc3545; color:#fff; border:none; }
    .button-danger:hover { background:#b52a36; }
    .notice { transition: opacity 0.8s ease-in-out; }
    form { background: #fff; padding: 15px 20px; border-radius: 10px; box-shadow: 0 0 6px rgba(0,0,0,0.1); }
    input[type="text"], textarea, select { width: 100%; border:1px solid #ccc; border-radius:5px; padding:6px; }
    input[type="file"] { margin-top:5px; }
    img { border-radius: 8px; }
    a.button, a.button:visited { text-decoration:none; }
    a[style*="font-weight:bold"] { color:#0073aa; }

    </style>';

    echo '<h1>Simple Post Manager</h1>';

    // Xử lý khi form được gửi
    if (isset($_POST['spm_submit'])) {
        $title   = sanitize_text_field($_POST['spm_title']);//làm sạch văn bản loại bỏ kí tự ko hợp lệ
        $author  = sanitize_text_field($_POST['spm_author']);
        $content = wp_kses_post($_POST['spm_content']);//lao sạch nội dung bài viết, cho phép một số thẻ HTML an toàn
        $status  = sanitize_text_field($_POST['spm_status']);

        // Xử lý upload ảnh
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        $uploadedfile = $_FILES['spm_image'];
        $upload_overrides = array('test_form' => false);

        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            $image_url = $movefile['url'];
        } else {
            $image_url = '';
        }

        if (!empty($title)) {
            // Tạo bài viết mới
            $new_post = array(
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => $status,
                'post_author'  => get_current_user_id(), 
                'post_type'    => 'post',
            );

            $post_id = wp_insert_post($new_post);

            if ($post_id) {
                // Lưu metadata tên tác giả thủ công
                update_post_meta($post_id, '_manual_author', $author);

                // Nếu có ảnh, tạo attachment và đặt làm featured image
                if (!empty($image_url)) {
                    $image_name = basename($image_url);
                    $upload_dir = wp_upload_dir();
                    $image_data = file_get_contents($image_url);
                    $image_path = $upload_dir['path'] . '/' . $image_name;
                    file_put_contents($image_path, $image_data);

                    $attachment = array(
                        'post_mime_type' => 'image/jpeg',
                        'post_title'     => sanitize_file_name($image_name),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    );

                    $attach_id = wp_insert_attachment($attachment, $image_path, $post_id);

                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $attach_data = wp_generate_attachment_metadata($attach_id, $image_path);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    set_post_thumbnail($post_id, $attach_id);
                }

                echo '<div class="updated notice"><p>✅ Bài viết đã được thêm thành công!</p></div>';
            } else {
                echo '<div class="error notice"><p>❌ Có lỗi xảy ra khi thêm bài viết.</p></div>';
            }
        } else {
            echo '<div class="error notice"><p>⚠️ Tiêu đề không được để trống!</p></div>';
        }
    }
    
    // --- Form thêm bài viết ---
        if (!isset($_GET['edit'])) {  // Chỉ hiển thị nếu KHÔNG đang sửa bài
            echo '<h2>Thêm bài viết mới</h2>';
            echo '<form method="post" enctype="multipart/form-data" style="margin-bottom:30px;">';
            echo '<table class="form-table">';
            echo '<tr><th><label>Tiêu đề:</label></th><td><input type="text" name="spm_title" class="regular-text" required></td></tr>';
            echo '<tr><th><label>Tên tác giả:</label></th><td><input type="text" name="spm_author" class="regular-text" required></td></tr>';
            echo '<tr><th><label>Nội dung:</label></th><td><textarea name="spm_content" rows="5" class="large-text"></textarea></td></tr>';
            echo '<tr><th><label>Ảnh đại diện:</label></th><td><input type="file" name="spm_image" accept="image/*"></td></tr>';
            echo '<tr><th><label>Trạng thái:</label></th><td>
                <select name="spm_status">
                    <option value="publish">Công khai</option>
                    <option value="draft">Nháp</option>
                </select></td></tr>';
            echo '</table>';
            echo '<p><input type="submit" name="spm_submit" class="button button-primary" value="Thêm bài viết"></p>';
            echo '</form>';
        }


    // Cập nhật bài viết
        if (isset($_POST['spm_update'])) {
            $edit_id = intval($_POST['spm_edit_id']);
            $title   = sanitize_text_field($_POST['spm_edit_title']);
            $author  = sanitize_text_field($_POST['spm_edit_author']);
            $content = wp_kses_post($_POST['spm_edit_content']);
            $status  = sanitize_text_field($_POST['spm_edit_status']);

            $update_post = array(
                'ID'           => $edit_id,
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => $status,
            );

            wp_update_post($update_post);
            update_post_meta($edit_id, '_manual_author', $author);

            // Nếu có ảnh mới
            if (!empty($_FILES['spm_edit_image']['name'])) {
                if (!function_exists('wp_handle_upload')) {
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                }
                $uploadedfile = $_FILES['spm_edit_image'];
                $upload_overrides = array('test_form' => false);
                $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

                if ($movefile && !isset($movefile['error'])) {
                    $image_url = $movefile['url'];
                    $image_name = basename($image_url);
                    $upload_dir = wp_upload_dir();
                    $image_data = file_get_contents($image_url);
                    $image_path = $upload_dir['path'] . '/' . $image_name;
                    file_put_contents($image_path, $image_data);

                    $attachment = array(
                        'post_mime_type' => 'image/jpeg',
                        'post_title'     => sanitize_file_name($image_name),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    );
                    $attach_id = wp_insert_attachment($attachment, $image_path, $edit_id);

                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $attach_data = wp_generate_attachment_metadata($attach_id, $image_path);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    set_post_thumbnail($edit_id, $attach_id);
                }
            }

            echo '<div class="updated notice"><p>✏️ Bài viết #' . esc_html($edit_id) . ' đã được cập nhật!</p></div>';
            // Sau khi cập nhật xong, tự reload lại trang để ẩn form sửa
            echo '<script>
                setTimeout(function() {
                    window.location.href = "admin.php?page=simple-post-manager";
                }, 500);
            </script>';
        }

        // Xử lý xóa bài viết
        if (isset($_GET['delete'])) {
            $del_id = intval($_GET['delete']);
            wp_delete_post($del_id, true); // true = xóa vĩnh viễn, false = chuyển vào thùng rác
            echo '<div class="updated notice"><p>🗑️ Bài viết #' . esc_html($del_id) . ' đã bị xóa.</p></div>';
        }



    // Nếu có yêu cầu sửa
        if (isset($_GET['edit'])) {
            $edit_id = intval($_GET['edit']);
            $post = get_post($edit_id);
            if ($post) {
                $manual_author = get_post_meta($edit_id, '_manual_author', true);
                $thumb_url = get_the_post_thumbnail_url($edit_id);

                echo '<h2>Sửa bài viết #' . esc_html($edit_id) . '</h2>';
                echo '<form method="post" enctype="multipart/form-data" style="margin-bottom:30px;">';
                echo '<input type="hidden" name="spm_edit_id" value="' . esc_attr($edit_id) . '">';
                echo '<table class="form-table">';
                echo '<tr><th>Tiêu đề:</th><td><input type="text" name="spm_edit_title" class="regular-text" value="' . esc_attr($post->post_title) . '"></td></tr>';
                echo '<tr><th>Tác giả:</th><td><input type="text" name="spm_edit_author" class="regular-text" value="' . esc_attr($manual_author) . '"></td></tr>';
                echo '<tr><th>Nội dung:</th><td><textarea name="spm_edit_content" rows="5" class="large-text">' . esc_textarea($post->post_content) . '</textarea></td></tr>';
                echo '<tr><th>Ảnh đại diện:</th><td>';
                if ($thumb_url) echo '<img src="' . esc_url($thumb_url) . '" width="100" height="100"><br>';
                echo '<input type="file" name="spm_edit_image" accept="image/*"></td></tr>';
                echo '<tr><th>Trạng thái:</th><td>
                        <select name="spm_edit_status">
                            <option value="publish" ' . selected($post->post_status, 'publish', false) . '>Công khai</option>
                            <option value="draft" ' . selected($post->post_status, 'draft', false) . '>Nháp</option>
                        </select></td></tr>';
                echo '</table>';
                echo '<p>
                <input type="submit" name="spm_update" class="button button-primary" value="Cập nhật bài viết">
                <a href="admin.php?page=simple-post-manager" class="button">Hủy sửa</a>
                </p>';
                echo '</form>';
            }
        }

        // --- Thanh tìm kiếm + lọc trạng thái ---
        $search_keyword = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $filter_status  = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

        // --- Phân trang ---
        $limit  = 5; // số bài mỗi trang
        $page   = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $limit;

        // --- Form lọc và tìm kiếm ---
        echo '<h2>Danh sách bài viết</h2>';
        echo '<form method="get" style="margin-bottom:15px;">';
        echo '<input type="hidden" name="page" value="simple-post-manager">';
        echo '<input type="text" name="search" placeholder="Tìm theo tiêu đề..." value="' . esc_attr($search_keyword) . '" style="padding:5px 10px; width:220px;"> ';
        echo '<select name="status" style="padding:5px 8px;">';
        echo '<option value="all"' . selected($filter_status, 'all', false) . '>Tất cả</option>';
        echo '<option value="publish"' . selected($filter_status, 'publish', false) . '>Công khai</option>';
        echo '<option value="draft"' . selected($filter_status, 'draft', false) . '>Nháp</option>';
        echo '</select> ';
        echo '<input type="submit" class="button" value="Lọc">';
        echo '</form>';

        // --- Điều kiện lọc ---
        $where = "WHERE post_type = 'post'";
        if ($search_keyword !== '') {
            $where .= $wpdb->prepare(" AND post_title LIKE %s", '%' . $wpdb->esc_like($search_keyword) . '%');
        }
        if ($filter_status !== 'all') {
            $where .= $wpdb->prepare(" AND post_status = %s", $filter_status);
        }

        // --- Tổng số bài để tính số trang ---
        $total_posts = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->posts $where");

        // --- Lấy bài viết có phân trang ---
        $posts = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_title, post_author, post_date, post_status
            FROM $wpdb->posts
            $where
            ORDER BY post_date DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset));

        // --- Hiển thị danh sách ---
        echo '<table class="widefat fixed" style="margin-top:10px;">';
        echo '<thead><tr>
                <th>ID</th>
                <th>Tiêu đề</th>
                <th>Tác giả</th>
                <th>Ảnh</th>
                <th>Ngày đăng</th>
                <th>Trạng thái</th>
                <th>Hành động</th>
            </tr></thead><tbody>';

        if ($posts) {
            foreach ($posts as $p) {
                $author_name = get_post_meta($p->ID, '_manual_author', true);
                if (empty($author_name)) {
                    $author_name = get_the_author_meta('display_name', $p->post_author);
                }

                $thumb = get_the_post_thumbnail_url($p->ID, 'thumbnail');
                $thumb_html = $thumb ? '<img src="' . esc_url($thumb) . '" width="60" height="60">' : '<span style="color:#999;">(Không có ảnh)</span>';

                $edit_url = admin_url('admin.php?page=simple-post-manager&edit=' . $p->ID);
                $del_url  = admin_url('admin.php?page=simple-post-manager&delete=' . $p->ID . '&paged=' . $page . '&search=' . urlencode($search_keyword) . '&status=' . urlencode($filter_status));

                echo '<tr>';
                echo '<td>' . esc_html($p->ID) . '</td>';
                echo '<td>' . esc_html($p->post_title) . '</td>';
                echo '<td>' . esc_html($author_name) . '</td>';
                echo '<td>' . $thumb_html . '</td>';
                echo '<td>' . esc_html($p->post_date) . '</td>';
                echo '<td>' . esc_html($p->post_status) . '</td>';
                echo '<td>
                        <a href="' . esc_url($edit_url) . '" class="button">Sửa</a>
                        <a href="' . esc_url($del_url) . '" class="button button-danger" onclick="return confirm(\'Xóa bài này?\')">Xóa</a>
                    </td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7">Không có bài viết nào.</td></tr>';
        }

        echo '</tbody></table>';

        // --- Thanh phân trang ---
        $total_pages = ceil($total_posts / $limit);
        if ($total_pages > 1) {
            echo '<div style="margin-top:15px;">';
            $base_url = admin_url('admin.php?page=simple-post-manager&search=' . urlencode($search_keyword) . '&status=' . urlencode($filter_status));

            echo '<div style="display:flex; gap:8px; align-items:center;">';
            if ($page > 1) {
                echo '<a class="button" href="' . esc_url($base_url . '&paged=' . ($page - 1)) . '">« Trang trước</a>';
            }
            for ($i = 1; $i <= $total_pages; $i++) {
                $active = ($i == $page) ? 'style="font-weight:bold; text-decoration:underline;"' : '';
                echo '<a ' . $active . ' href="' . esc_url($base_url . '&paged=' . $i) . '">' . $i . '</a>';
            }
            if ($page < $total_pages) {
                echo '<a class="button" href="' . esc_url($base_url . '&paged=' . ($page + 1)) . '">Trang sau »</a>';
            }
            echo '</div></div>';
        }


        echo '</div>';
    }

?>
