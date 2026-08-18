<?php
/**
 * Satay Ordering System - Menu & Categories API
 */

require_once __DIR__ . '/config.php';

$pdo = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get($pdo);
        break;
    case 'POST':
        handle_post($pdo);
        break;
    case 'PATCH':
        handle_patch($pdo);
        break;
    case 'DELETE':
        handle_delete($pdo);
        break;
    default:
        json_error('Method not allowed', 405);
}

function handle_get(PDO $pdo)
{
    $category_id = isset($_GET['category_id']) ? (int) $_GET['category_id'] : null;
    $include_unavailable = isset($_GET['all']) && $_GET['all'] == '1';

    // Fetch Categories
    $cat_stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order ASC");
    $categories = $cat_stmt->fetchAll();

    // Fetch Menu Items
    $sql = "SELECT m.*, c.name as category_name, c.slug as category_slug 
            FROM menu_items m 
            JOIN categories c ON m.category_id = c.id 
            WHERE 1=1";

    $params = [];
    if (!$include_unavailable) {
        $sql .= " AND m.is_available = 1";
    }
    if ($category_id) {
        $sql .= " AND m.category_id = ?";
        $params[] = $category_id;
    }
    $sql .= " ORDER BY c.display_order ASC, m.is_popular DESC, m.id ASC";

    $item_stmt = $pdo->prepare($sql);
    $item_stmt->execute($params);
    $items = $item_stmt->fetchAll();

    // Group items under categories for easy rendering
    $grouped = [];
    foreach ($categories as $cat) {
        $cat['items'] = [];
        $grouped[$cat['id']] = $cat;
    }

    foreach ($items as $item) {
        $cid = $item['category_id'];
        if (isset($grouped[$cid])) {
            $grouped[$cid]['items'][] = $item;
        }
    }

    json_response([
        'success' => true,
        'categories' => $categories,
        'items' => $items,
        'grouped' => array_values($grouped)
    ]);
}

function handle_post(PDO $pdo)
{
    $data = get_json_input();

    $id = isset($data['id']) ? (int) $data['id'] : null;
    $category_id = isset($data['category_id']) ? (int) $data['category_id'] : null;
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $price_per_unit = isset($data['price_per_unit']) ? (float) $data['price_per_unit'] : 0.0;
    $unit_name = trim($data['unit_name'] ?? 'cucuk');
    $min_quantity = isset($data['min_quantity']) ? max(1, (int) $data['min_quantity']) : 1;
    $image_url = trim($data['image_url'] ?? 'assets/images/sate_ayam.png');
    $is_popular = isset($data['is_popular']) ? (int) $data['is_popular'] : 0;
    $stock_quantity = isset($data['stock_quantity']) ? max(0, (int) $data['stock_quantity']) : 100;
    $is_available = isset($data['is_available']) ? (int) $data['is_available'] : ($stock_quantity > 0 ? 1 : 0);
    if ($stock_quantity === 0) {
        $is_available = 0;
    }
    $stick_meat_type = !empty($data['stick_meat_type']) ? trim($data['stick_meat_type']) : null;

    if (empty($name) || !$category_id || $price_per_unit <= 0) {
        json_error('Please provide a valid name, category, and positive price.');
    }

    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

    if ($id) {
        // Update existing item
        $stmt = $pdo->prepare("
            UPDATE menu_items SET 
                category_id = ?, name = ?, slug = ?, description = ?, price_per_unit = ?, 
                unit_name = ?, min_quantity = ?, image_url = ?, is_popular = ?, is_available = ?, stock_quantity = ?, stick_meat_type = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $category_id,
            $name,
            $slug,
            $description,
            $price_per_unit,
            $unit_name,
            $min_quantity,
            $image_url,
            $is_popular,
            $is_available,
            $stock_quantity,
            $stick_meat_type,
            $id
        ]);
        json_response(['success' => true, 'message' => 'Menu item updated successfully', 'id' => $id]);
    } else {
        // Insert new item
        $stmt = $pdo->prepare("
            INSERT INTO menu_items 
            (category_id, name, slug, description, price_per_unit, unit_name, min_quantity, image_url, is_popular, is_available, stock_quantity, stick_meat_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $category_id,
            $name,
            $slug,
            $description,
            $price_per_unit,
            $unit_name,
            $min_quantity,
            $image_url,
            $is_popular,
            $is_available,
            $stock_quantity,
            $stick_meat_type
        ]);
        $new_id = $pdo->lastInsertId();
        json_response(['success' => true, 'message' => 'New menu item added successfully', 'id' => $new_id], 201);
    }
}

function handle_patch(PDO $pdo)
{
    $data = get_json_input();
    $id = isset($data['id']) ? (int) $data['id'] : null;

    if (!$id) {
        json_error('Item ID is required for updating stock status.');
    }

    if (isset($data['stock_quantity'])) {
        $stock_quantity = max(0, (int) $data['stock_quantity']);
        $is_available = ($stock_quantity > 0) ? (isset($data['is_available']) ? (int) $data['is_available'] : 1) : 0;
        $stmt = $pdo->prepare("UPDATE menu_items SET stock_quantity = ?, is_available = ? WHERE id = ?");
        $stmt->execute([$stock_quantity, $is_available, $id]);
        json_response([
            'success' => true,
            'message' => 'Stock quantity updated',
            'stock_quantity' => $stock_quantity,
            'is_available' => $is_available
        ]);
    }

    if (isset($data['stock_delta'])) {
        $delta = (int) $data['stock_delta'];
        $stmt = $pdo->prepare("UPDATE menu_items SET stock_quantity = MAX(0, stock_quantity + ?), is_available = CASE WHEN (stock_quantity + ?) > 0 THEN 1 ELSE 0 END WHERE id = ?");
        $stmt->execute([$delta, $delta, $id]);
        $updated = $pdo->query("SELECT stock_quantity, is_available FROM menu_items WHERE id = " . (int)$id)->fetch();
        json_response([
            'success' => true,
            'message' => 'Stock adjusted',
            'stock_quantity' => (int) $updated['stock_quantity'],
            'is_available' => (int) $updated['is_available']
        ]);
    }

    if (isset($data['is_available'])) {
        $is_available = (int) $data['is_available'];
        $stmt = $pdo->prepare("UPDATE menu_items SET is_available = ? WHERE id = ?");
        $stmt->execute([$is_available, $id]);
        json_response(['success' => true, 'message' => 'Availability updated', 'is_available' => $is_available]);
    }

    json_error('No valid update fields specified.');
}

function handle_delete(PDO $pdo)
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    if (!$id) {
        json_error('Item ID is required for deletion.');
    }

    $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
    $stmt->execute([$id]);

    json_response(['success' => true, 'message' => 'Menu item deleted successfully']);
}
