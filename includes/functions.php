<?php
// Security functions
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Permission functions
function check_permission($user_id, $menu) {
    global $conn;
    $stmt = $conn->prepare("SELECT can_access FROM user_permissions WHERE user_id = ? AND menu_id = ?");
    $stmt->bind_param("is", $user_id, $menu);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['can_access'] == 1;
    }
    return false;
}

// Language functions
function get_language_string($key, $lang = null) {
    if ($lang === null) {
        $lang = isset($_SESSION['language']) ? $_SESSION['language'] : DEFAULT_LANGUAGE;
    }
    
    $lang_file = "assets/languages/{$lang}.php";
    if (file_exists($lang_file)) {
        include $lang_file;
        return isset($lang_strings[$key]) ? $lang_strings[$key] : $key;
    }
    return $key;
}

// Stock functions
function get_current_stock($material_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT current_stock FROM materials WHERE id = ?");
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['current_stock'];
    }
    return 0;
}

function update_stock($material_id, $quantity, $type = 'in') {
    global $conn;
    $multiplier = ($type == 'in') ? 1 : -1;
    
    $stmt = $conn->prepare("UPDATE materials SET current_stock = current_stock + ? WHERE id = ?");
    $adjusted_quantity = $quantity * $multiplier;
    $stmt->bind_param("di", $adjusted_quantity, $material_id);
    
    if ($stmt->execute()) {
        // Log stock movement
        $movement_stmt = $conn->prepare("INSERT INTO stock_movements (material_id, quantity, type, date) VALUES (?, ?, ?, NOW())");
        $movement_stmt->bind_param("ids", $material_id, $quantity, $type);
        return $movement_stmt->execute();
    }
    return false;
}

// BOM functions
function get_bom_materials($product_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT m.*, b.quantity 
        FROM bom b 
        JOIN materials m ON b.material_id = m.id 
        WHERE b.product_id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    return $stmt->get_result();
}

function calculate_production_requirements($product_id, $quantity) {
    global $conn;
    $materials = [];
    
    $result = get_bom_materials($product_id);
    while ($row = $result->fetch_assoc()) {
        $materials[] = [
            'material_id' => $row['id'],
            'name' => $row['name'],
            'required_quantity' => $row['quantity'] * $quantity,
            'available_quantity' => get_current_stock($row['id'])
        ];
    }
    
    return $materials;
}

// Chart functions
function get_stock_chart_data() {
    global $conn;
    $data = [
        'labels' => [],
        'actual' => [],
        'minimum' => [],
        'maximum' => []
    ];
    
    $result = $conn->query("
        SELECT name, current_stock, min_stock, max_stock 
        FROM materials 
        ORDER BY name
    ");
    
    while ($row = $result->fetch_assoc()) {
        $data['labels'][] = $row['name'];
        $data['actual'][] = $row['current_stock'];
        $data['minimum'][] = $row['min_stock'];
        $data['maximum'][] = $row['max_stock'];
    }
    
    return $data;
}

// Production Plan functions
function get_production_plans($type) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT * FROM production_plans 
        WHERE plan_type = ? 
        ORDER BY plan_code
    ");
    $stmt->bind_param("i", $type);
    $stmt->execute();
    return $stmt->get_result();
}

// User Management functions
function create_user($username, $password, $role) {
    global $conn;
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $hashed_password, $role);
    return $stmt->execute();
}

function update_user_permissions($user_id, $permissions) {
    global $conn;
    
    // First, remove all existing permissions
    $stmt = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Then insert new permissions
    $stmt = $conn->prepare("INSERT INTO user_permissions (user_id, menu_id, can_access) VALUES (?, ?, ?)");
    foreach ($permissions as $menu_id => $access) {
        $can_access = $access ? 1 : 0;
        $stmt->bind_param("isi", $user_id, $menu_id, $can_access);
        $stmt->execute();
    }
    return true;
}

// Settings functions
function get_setting($key) {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return null;
}

function update_setting($key, $value) {
    global $conn;
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    $stmt->bind_param("sss", $key, $value, $value);
    return $stmt->execute();
}

// File handling functions
function upload_file($file, $destination_path) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $destination_path)) {
        return ['success' => true, 'path' => $destination_path];
    }
    
    return ['success' => false, 'message' => 'Upload failed'];
}
?>
