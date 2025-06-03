<?php
if (!check_permission($_SESSION['user_id'], 'dashboard')) {
    header('Location: index.php');
    exit();
}

// Get stock data for the chart
$stock_data = get_stock_chart_data();
?>

<div class="row">
    <div class="col-12">
        <h1 class="mb-4"><?php echo get_language_string('dashboard'); ?></h1>
    </div>
</div>

<!-- Stock Overview Chart -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?php echo get_language_string('stock_overview'); ?></h5>
            </div>
            <div class="card-body">
                <canvas id="stockChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Stock Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?php echo get_language_string('stock_details'); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th><?php echo get_language_string('material'); ?></th>
                                <th><?php echo get_language_string('current_stock'); ?></th>
                                <th><?php echo get_language_string('minimum_stock'); ?></th>
                                <th><?php echo get_language_string('maximum_stock'); ?></th>
                                <th><?php echo get_language_string('status'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $result = $conn->query("
                                SELECT * FROM materials 
                                ORDER BY name
                            ");
                            
                            while ($row = $result->fetch_assoc()):
                                $status_class = '';
                                $status_text = '';
                                
                                if ($row['current_stock'] < $row['min_stock']) {
                                    $status_class = 'danger';
                                    $status_text = get_language_string('low_stock');
                                } elseif ($row['current_stock'] > $row['max_stock']) {
                                    $status_class = 'warning';
                                    $status_text = get_language_string('overstock');
                                } else {
                                    $status_class = 'success';
                                    $status_text = get_language_string('normal');
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo number_format($row['current_stock']); ?></td>
                                <td><?php echo number_format($row['min_stock']); ?></td>
                                <td><?php echo number_format($row['max_stock']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
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

<script>
// Initialize stock chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('stockChart').getContext('2d');
    const stockChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($stock_data['labels']); ?>,
            datasets: [
                {
                    label: '<?php echo get_language_string("current_stock"); ?>',
                    data: <?php echo json_encode($stock_data['actual']); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: '<?php echo get_language_string("minimum_stock"); ?>',
                    data: <?php echo json_encode($stock_data['minimum']); ?>,
                    type: 'line',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderDash: [5, 5],
                    fill: false
                },
                {
                    label: '<?php echo get_language_string("maximum_stock"); ?>',
                    data: <?php echo json_encode($stock_data['maximum']); ?>,
                    type: 'line',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderDash: [5, 5],
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });
});
</script>
