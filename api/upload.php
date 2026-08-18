<?php
/**
 * Satay Ordering System - Image Upload API
 */

require_once __DIR__ . '/config.php';

// Only staff and admin can upload images
$currentUser = get_current_auth_user();
if (!$currentUser || !in_array($currentUser['role'], ['admin', 'staff'])) {
    json_error('Unauthorized. Only administrators and staff can upload images.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $error_msg = 'No image file uploaded or upload error.';
    if (isset($_FILES['image']['error'])) {
        switch ($_FILES['image']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_msg = 'File size exceeds maximum allowed upload limit.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_msg = 'No file was selected for upload.';
                break;
        }
    }
    json_error($error_msg);
}

$file = $_FILES['image'];
$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_extensions)) {
    json_error('Invalid image format. Allowed formats: PNG, JPG, JPEG, WEBP, GIF.');
}

// Verify actual image MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mime, $allowed_mimes)) {
    json_error('Uploaded file is not a valid image.');
}

$target_dir = __DIR__ . '/../assets/images/';
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// Generate clean, safe filename
$clean_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
$clean_name = strtolower(trim($clean_name, '_'));
if (empty($clean_name)) {
    $clean_name = 'satay_dish';
}

$filename = $clean_name . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
$target_path = $target_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $target_path)) {
    json_error('Failed to save uploaded image on server.');
}

$relative_url = 'assets/images/' . $filename;

json_response([
    'success' => true,
    'message' => 'Image uploaded successfully',
    'image_url' => $relative_url,
    'filename' => $filename
]);
