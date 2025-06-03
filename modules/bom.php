<?php
if (!check_permission($_SESSION['user_id'], 'bom')) {
    header('Location: index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && verify_csrf_token($_POST['_csrf'])) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_material':
                $name = sanitize_input($_POST['name']);
                $code = sanitize_input($_POST['code']);
                $type = sanitize_input($_POST['type']);
                $min_stock = floatval($_POST['min_stock']);
                $max_stock = floatval($_POST['max_stock']);
                $current_stock = floatval($_POST['current_stock']);
                
                $stmt = $conn->prepare("
                    INSERT INTO materials (name, code, type, min_stock, max_stock, current_stock) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssddd", $name, $code, $type, $min_stock, $max_stock, $current_stock);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = get_language_string('material_added');
                } else {
                    $_SESSION['error'] = get_language_string('error_adding_material');
                }
                break;
                
            case 'add_bom':
                $product_id = intval($_POST['product_id']);
                $material_id = intval($_POST['material_id']);
                $quantity = floatval($_POST['quantity']);
                
                $stmt = $conn->prepare("
                    INSERT INTO bom (product_id, material_id, quantity) 
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iid", $product_id, $material_id, $quantity);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = get_language_string('bom_added');
                } else {
                    $_SESSION['error'] = get_language_string('error_adding_bom');
                }
                break;
        }
        
        header('Location: index.php?page=bom');
        exit();
    }
}

// Get all materials
$materials_result = $conn->query("
    SELECT * FROM materials 
    ORDER BY name
");

// Get all BOMs with their details
$boms_result = $conn->query("
    SELECT b.*, m.name as material_name, p.name as product_name 
    FROM bom b 
    JOIN materials m ON b.material_id = m.id 
    JOIN materials p ON b.product_id = p.id 
    ORDER BY p.name, m.name
");
?>

<div class="row">
    <div class="col-12">
        <h1 class="mb-4"><?php echo get_language_string('bill_of_materials'); ?></h1>
    </div>
</div>

<div class="row mb-4">
    <!-- Materials Management -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><?php echo get_language_string('materials'); ?></h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                    <i class="bi bi-plus"></i> <?php echo get_language_string('add_material'); ?>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo get_language_string('code'); ?></th>
                                <th><?php echo get_language_string('name'); ?></th>
                                <th><?php echo get_language_string('type'); ?></th>
                                <th><?php echo get_language_string('current_stock'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($material = $materials_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($material['code']); ?></td>
                                <td><?php echo htmlspecialchars($material['name']); ?></td>
                                <td><?php echo htmlspecialchars($material['type']); ?></td>
                                <td><?php echo number_format($material['current_stock']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- BOM Management -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><?php echo get_language_string('bom_list'); ?></h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBomModal">
                    <i class="bi bi-plus"></i> <?php echo get_language_string('add_bom'); ?>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo get_language_string('product'); ?></th>
                                <th><?php echo get_language_string('material'); ?></th>
                                <th><?php echo get_language_string('quantity'); ?></th>
                                <th><?php echo get_language_string('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($bom = $boms_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($bom['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($bom['material_name']); ?></td>
                                <td><?php echo number_format($bom['quantity'], 2); ?></td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm" 
                                            onclick="deleteBom(<?php echo $bom['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
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

<!-- Add Material Modal -->
<div class="modal fade" id="addMaterialModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="_csrf" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="add_material">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo get_language_string('add_material'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="code" class="form-label"><?php echo get_language_string('code'); ?></label>
                        <input type="text" class="form-control" id="code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label"><?php echo get_language_string('name'); ?></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type" class="form-label"><?php echo get_language_string('type'); ?></label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="raw"><?php echo get_language_string('raw_material'); ?></option>
                            <option value="semi"><?php echo get_language_string('semi_finished'); ?></option>
                            <option value="finished"><?php echo get_language_string('finished_product'); ?></option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="min_stock" class="form-label"><?php echo get_language_string('minimum_stock'); ?></label>
                        <input type="number" class="form-control" id="min_stock" name="min_stock" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="max_stock" class="form-label"><?php echo get_language_string('maximum_stock'); ?></label>
                        <input type="number" class="form-control" id="max_stock" name="max_stock" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="current_stock" class="form-label"><?php echo get_language_string('current_stock'); ?></label>
                        <input type="number" class="form-control" id="current_stock" name="current_stock" step="0.01" required>
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

<!-- Add BOM Modal -->
<div class="modal fade" id="addBomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="_csrf" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="add_bom">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo get_language_string('add_bom'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="product_id" class="form-label"><?php echo get_language_string('product'); ?></label>
                        <select class="form-select" id="product_id" name="product_id" required>
                            <?php
                            $products_result = $conn->query("
                                SELECT * FROM materials 
                                WHERE type = 'finished' 
                                ORDER BY name
                            ");
                            while ($product = $products_result->fetch_assoc()):
                            ?>
                            <option value="<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="material_id" class="form-label"><?php echo get_language_string('material'); ?></label>
                        <select class="form-select" id="material_id" name="material_id" required>
                            <?php
                            $materials_result->data_seek(0);
                            while ($material = $materials_result->fetch_assoc()):
                            ?>
                            <option value="<?php echo $material['id']; ?>">
                                <?php echo htmlspecialchars($material['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quantity" class="form-label"><?php echo get_language_string('quantity'); ?></label>
                        <input type="number" class="form-control" id="quantity" name="quantity" step="0.01" required>
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

<script>
async function deleteBom(id) {
    if (await confirmAction('<?php echo get_language_string("confirm_delete_bom"); ?>')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="_csrf" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="delete_bom">
            <input type="hidden" name="id" value="${id}">
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
