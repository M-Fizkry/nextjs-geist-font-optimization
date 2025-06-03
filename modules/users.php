<?php
if (!check_permission($_SESSION['user_id'], 'users')) {
    header('Location: index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && verify_csrf_token($_POST['_csrf'])) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                $username = sanitize_input($_POST['username']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $role = sanitize_input($_POST['role']);
                
                $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $password, $role);
                
                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;
                    
                    // Add permissions
                    if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                        $perm_stmt = $conn->prepare("INSERT INTO user_permissions (user_id, menu_id, can_access) VALUES (?, ?, 1)");
                        foreach ($_POST['permissions'] as $menu) {
                            $perm_stmt->bind_param("is", $user_id, $menu);
                            $perm_stmt->execute();
                        }
                    }
                    
                    $_SESSION['success'] = get_language_string('user_added');
                } else {
                    $_SESSION['error'] = get_language_string('error_adding_user');
                }
                break;
                
            case 'update_user':
                $user_id = intval($_POST['user_id']);
                $role = sanitize_input($_POST['role']);
                
                $updates = ["role = ?"]; 
                $params = [$role];
                $types = "s";
                
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $updates[] = "password = ?";
                    $params[] = $password;
                    $types .= "s";
                }
                
                $params[] = $user_id;
                $types .= "i";
                
                $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    // Update permissions
                    $conn->query("DELETE FROM user_permissions WHERE user_id = $user_id");
                    
                    if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                        $perm_stmt = $conn->prepare("INSERT INTO user_permissions (user_id, menu_id, can_access) VALUES (?, ?, 1)");
                        foreach ($_POST['permissions'] as $menu) {
                            $perm_stmt->bind_param("is", $user_id, $menu);
                            $perm_stmt->execute();
                        }
                    }
                    
                    $_SESSION['success'] = get_language_string('user_updated');
                } else {
                    $_SESSION['error'] = get_language_string('error_updating_user');
                }
                break;
                
            case 'delete_user':
                $user_id = intval($_POST['user_id']);
                
                // Don't allow deleting your own account
                if ($user_id == $_SESSION['user_id']) {
                    $_SESSION['error'] = get_language_string('cannot_delete_self');
                    break;
                }
                
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    // Delete permissions
                    $conn->query("DELETE FROM user_permissions WHERE user_id = $user_id");
                    $_SESSION['success'] = get_language_string('user_deleted');
                } else {
                    $_SESSION['error'] = get_language_string('error_deleting_user');
                }
                break;
        }
        
        header('Location: index.php?page=users');
        exit();
    }
}

// Get all users
$users_result = $conn->query("
    SELECT u.*, GROUP_CONCAT(up.menu_id) as permissions 
    FROM users u 
    LEFT JOIN user_permissions up ON u.id = up.user_id 
    GROUP BY u.id 
    ORDER BY u.username
");

// Available menus for permissions
$available_menus = [
    'dashboard' => get_language_string('dashboard'),
    'bom' => get_language_string('bill_of_materials'),
    'production' => get_language_string('production_plan'),
    'users' => get_language_string('user_management'),
    'settings' => get_language_string('settings')
];

// Available roles
$available_roles = [
    'admin' => get_language_string('administrator'),
    'manager' => get_language_string('manager'),
    'user' => get_language_string('user')
];
?>

<div class="row">
    <div class="col-12">
        <h1 class="mb-4"><?php echo get_language_string('user_management'); ?></h1>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><?php echo get_language_string('users'); ?></h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-plus"></i> <?php echo get_language_string('add_user'); ?>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo get_language_string('username'); ?></th>
                                <th><?php echo get_language_string('role'); ?></th>
                                <th><?php echo get_language_string('permissions'); ?></th>
                                <th><?php echo get_language_string('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($available_roles[$user['role']] ?? $user['role']); ?></td>
                                <td>
                                    <?php
                                    $permissions = explode(',', $user['permissions']);
                                    foreach ($permissions as $menu):
                                        if (isset($available_menus[$menu])):
                                    ?>
                                    <span class="badge bg-primary me-1">
                                        <?php echo $available_menus[$menu]; ?>
                                    </span>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm" 
                                            onclick="editUser(<?php echo $user['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button type="button" class="btn btn-danger btn-sm" 
                                            onclick="deleteUser(<?php echo $user['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="_csrf" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="add_user">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo get_language_string('add_user'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="username" class="form-label"><?php echo get_language_string('username'); ?></label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label"><?php echo get_language_string('password'); ?></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label"><?php echo get_language_string('role'); ?></label>
                        <select class="form-select" id="role" name="role" required>
                            <?php foreach ($available_roles as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo get_language_string('permissions'); ?></label>
                        <?php foreach ($available_menus as $value => $label): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" 
                                   value="<?php echo $value; ?>" id="perm_<?php echo $value; ?>">
                            <label class="form-check-label" for="perm_<?php echo $value; ?>">
                                <?php echo $label; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?php echo get_language_string('cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo get_language_string('add'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="_csrf" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo get_language_string('edit_user'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_username" class="form-label"><?php echo get_language_string('username'); ?></label>
                        <input type="text" class="form-control" id="edit_username" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">
                            <?php echo get_language_string('new_password'); ?>
                            <small class="text-muted">(<?php echo get_language_string('leave_blank_keep'); ?>)</small>
                        </label>
                        <input type="password" class="form-control" id="edit_password" name="password">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_role" class="form-label"><?php echo get_language_string('role'); ?></label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <?php foreach ($available_roles as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo get_language_string('permissions'); ?></label>
                        <?php foreach ($available_menus as $value => $label): ?>
                        <div class="form-check">
                            <input class="form-check-input edit-permission" type="checkbox" name="permissions[]" 
                                   value="<?php echo $value; ?>" id="edit_perm_<?php echo $value; ?>">
                            <label class="form-check-label" for="edit_perm_<?php echo $value; ?>">
                                <?php echo $label; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?php echo get_language_string('cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo get_language_string('save_changes'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit user
async function editUser(id) {
    try {
        const response = await fetch(`ajax/get_user.php?id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('edit_user_id').value = data.user.id;
            document.getElementById('edit_username').value = data.user.username;
            document.getElementById('edit_role').value = data.user.role;
            
            // Reset permissions
            document.querySelectorAll('.edit-permission').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Set permissions
            data.user.permissions.forEach(menu => {
                const checkbox = document.getElementById(`edit_perm_${menu}`);
                if (checkbox) checkbox.checked = true;
            });
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        } else {
            showNotification(data.error, 'danger');
        }
    } catch (error) {
        showNotification('<?php echo get_language_string("error_loading_user"); ?>', 'danger');
    }
}

// Delete user
async function deleteUser(id) {
    if (await confirmAction('<?php echo get_language_string("confirm_delete_user"); ?>')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="_csrf" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Show success/error messages
<?php if (isset($_SESSION['success'])): ?>
showNotification('<?php echo $_SESSION['success']; ?>', 'success');
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
showNotification('<?php echo $_SESSION['error']; ?>', 'danger');
<?php unset($_SESSION['error']); endif; ?>
</script>
