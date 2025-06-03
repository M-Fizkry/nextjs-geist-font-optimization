<?php
if (!check_permission($_SESSION['user_id'], 'production')) {
    header('Location: index.php');
    exit();
}

$plan_type = isset($_GET['plan']) ? intval($_GET['plan']) : 1;
$sub_plan = isset($_GET['sub']) ? $_GET['sub'] : null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && verify_csrf_token($_POST['_csrf'])) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_plan':
                $product_id = intval($_POST['product_id']);
                $quantity = floatval($_POST['quantity']);
                $plan_date = $_POST['plan_date'];
                $plan_code = sanitize_input($_POST['plan_code']);
                
                // First check if we have enough materials
                $materials = calculate_production_requirements($product_id, $quantity);
                $can_produce = true;
                $missing_materials = [];
                
                foreach ($materials as $material) {
                    if ($material['available_quantity'] < $material['required_quantity']) {
                        $can_produce = false;
                        $missing_materials[] = [
                            'name' => $material['name'],
                            'required' => $material['required_quantity'],
                            'available' => $material['available_quantity']
                        ];
                    }
                }
                
                if ($can_produce) {
                    $stmt = $conn->prepare("
                        INSERT INTO production_plans (product_id, quantity, plan_date, plan_type, plan_code) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("idsss", $product_id, $quantity, $plan_date, $plan_type, $plan_code);
                    
                    if ($stmt->execute()) {
                        // Deduct materials from stock
                        foreach ($materials as $material) {
                            update_stock($material['material_id'], $material['required_quantity'], 'out');
                        }
                        
                        // Add finished product to stock
                        update_stock($product_id, $quantity, 'in');
                        
                        $_SESSION['success'] = get_language_string('plan_added');
                    } else {
                        $_SESSION['error'] = get_language_string('error_adding_plan');
                    }
                } else {
                    $_SESSION['error'] = get_language_string('insufficient_materials') . ":\n";
                    foreach ($missing_materials as $material) {
                        $_SESSION['error'] .= sprintf(
                            "- %s (Required: %s, Available: %s)\n",
                            $material['name'],
                            number_format($material['required']),
                            number_format($material['available'])
                        );
                    }
                }
                break;
        }
        
        header('Location: index.php?page=production&plan=' . $plan_type . ($sub_plan ? '&sub=' . $sub_plan : ''));
        exit();
    }
}

// Get plan details
$plan_title = "Plan $plan_type";
if ($sub_plan) {
    $plan_title .= " - $sub_plan";
}

// Get production plans
$plans_query = "
    SELECT pp.*, m.name as product_name, m.code as product_code
    FROM production_plans pp
    JOIN materials m ON pp.product_id = m.id
    WHERE pp.plan_type = ?
";

if ($sub_plan) {
    $plans_query .= " AND pp.plan_code = ?";
    $stmt = $conn->prepare($plans_query);
    $stmt->bind_param("is", $plan_type, $sub_plan);
} else {
    $stmt = $conn->prepare($plans_query);
    $stmt->bind_param("i", $plan_type);
}

$stmt->execute();
$plans_result = $stmt->get_result();

// Get finished products for planning
$products_result = $conn->query("
    SELECT * FROM materials 
    WHERE type = 'finished' 
    ORDER BY name
");
?>

<div class="row">
    <div class="col-12">
        <h1 class="mb-4">
            <?php echo get_language_string('production_plan'); ?> - 
            <?php echo htmlspecialchars($plan_title); ?>
        </h1>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><?php echo get_language_string('production_plans'); ?></h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                    <i class="bi bi-plus"></i> <?php echo get_language_string('add_plan'); ?>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo get_language_string('date'); ?></th>
                                <th><?php echo get_language_string('product_code'); ?></th>
                                <th><?php echo get_language_string('product_name'); ?></th>
                                <th><?php echo get_language_string('quantity'); ?></th>
                                <th><?php echo get_language_string('status'); ?></th>
                                <th><?php echo get_language_string('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($plan = $plans_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($plan['plan_date'])); ?></td>
                                <td><?php echo htmlspecialchars($plan['product_code']); ?></td>
                                <td><?php echo htmlspecialchars($plan['product_name']); ?></td>
                                <td><?php echo number_format($plan['quantity']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $plan['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                        <?php echo get_language_string($plan['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($plan['status'] != 'completed'): ?>
                                    <button type="button" class="btn btn-success btn-sm" 
                                            onclick="completePlan(<?php echo $plan['id']; ?>)">
                                        <i class="bi bi-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-info btn-sm" 
                                            onclick="viewDetails(<?php echo $plan['id']; ?>)">
                                        <i class="bi bi-eye"></i>
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

<!-- Add Plan Modal -->
<div class="modal fade" id="addPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="addPlanForm">
                <input type="hidden" name="_csrf" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="add_plan">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo get_language_string('add_plan'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="plan_code" class="form-label"><?php echo get_language_string('plan_code'); ?></label>
                        <input type="text" class="form-control" id="plan_code" name="plan_code" 
                               value="<?php echo $sub_plan ?? ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="product_id" class="form-label"><?php echo get_language_string('product'); ?></label>
                        <select class="form-select" id="product_id" name="product_id" required>
                            <?php while ($product = $products_result->fetch_assoc()): ?>
                            <option value="<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['code'] . ' - ' . $product['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quantity" class="form-label"><?php echo get_language_string('quantity'); ?></label>
                        <input type="number" class="form-control" id="quantity" name="quantity" step="1" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="plan_date" class="form-label"><?php echo get_language_string('date'); ?></label>
                        <input type="date" class="form-control" id="plan_date" name="plan_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
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

<!-- Plan Details Modal -->
<div class="modal fade" id="planDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo get_language_string('plan_details'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="planDetailsContent"></div>
            </div>
        </div>
    </div>
</div>

<script>
async function completePlan(id) {
    if (await confirmAction('<?php echo get_language_string("confirm_complete_plan"); ?>')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="_csrf" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="complete_plan">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

async function viewDetails(id) {
    try {
        const response = await fetch(`ajax/plan_details.php?id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('planDetailsContent').innerHTML = data.html;
            new bootstrap.Modal(document.getElementById('planDetailsModal')).show();
        } else {
            showNotification(data.error, 'danger');
        }
    } catch (error) {
        showNotification('<?php echo get_language_string("error_loading_details"); ?>', 'danger');
    }
}

// Form validation
document.getElementById('addPlanForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const productId = this.product_id.value;
    const quantity = this.quantity.value;
    
    try {
        const response = await fetch(`ajax/check_materials.php?product_id=${productId}&quantity=${quantity}`);
        const data = await response.json();
        
        if (data.success) {
            this.submit();
        } else {
            showNotification(data.error, 'danger');
        }
    } catch (error) {
        showNotification('<?php echo get_language_string("error_checking_materials"); ?>', 'danger');
    }
});

// Show success/error messages
<?php if (isset($_SESSION['success'])): ?>
showNotification('<?php echo $_SESSION['success']; ?>', 'success');
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
showNotification('<?php echo $_SESSION['error']; ?>', 'danger');
<?php unset($_SESSION['error']); endif; ?>
</script>
