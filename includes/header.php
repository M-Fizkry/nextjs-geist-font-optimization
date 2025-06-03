<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$current_language = isset($_SESSION['language']) ? $_SESSION['language'] : DEFAULT_LANGUAGE;
?>
<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo get_setting('site_title') ?? 'Inventory Control System'; ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <?php if ($logo = get_setting('site_logo')): ?>
                    <img src="<?php echo $logo; ?>" height="30" alt="Logo">
                <?php endif; ?>
                <?php echo get_setting('site_title') ?? 'Inventory Control System'; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (check_permission($user_id, 'dashboard')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" href="index.php?page=dashboard">
                            <i class="bi bi-speedometer2"></i> <?php echo get_language_string('dashboard'); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (check_permission($user_id, 'bom')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'bom' ? 'active' : ''; ?>" href="index.php?page=bom">
                            <i class="bi bi-diagram-3"></i> <?php echo get_language_string('bill_of_materials'); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (check_permission($user_id, 'production')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo $current_page == 'production' ? 'active' : ''; ?>" 
                           href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear"></i> <?php echo get_language_string('production_plan'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php?page=production&plan=1">Plan 1 (9110)</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="index.php?page=production&plan=2">Plan 2</a></li>
                            <li><a class="dropdown-item ps-4" href="index.php?page=production&plan=2&sub=9210">9210</a></li>
                            <li><a class="dropdown-item ps-4" href="index.php?page=production&plan=2&sub=9220">9220</a></li>
                            <li><a class="dropdown-item ps-4" href="index.php?page=production&plan=2&sub=9230">9230</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="index.php?page=production&plan=3">Plan 3</a></li>
                            <li><a class="dropdown-item ps-4" href="index.php?page=production&plan=3&sub=9310">9310</a></li>
                            <li><a class="dropdown-item ps-4" href="index.php?page=production&plan=3&sub=9320">9320</a></li>
                            <li><a class="dropdown-item ps-4" href="index.php?page=production&plan=3&sub=9330">9330</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (check_permission($user_id, 'users')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'users' ? 'active' : ''; ?>" href="index.php?page=users">
                            <i class="bi bi-people"></i> <?php echo get_language_string('user_management'); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (check_permission($user_id, 'settings')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'settings' ? 'active' : ''; ?>" href="index.php?page=settings">
                            <i class="bi bi-sliders"></i> <?php echo get_language_string('settings'); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Language Selector -->
                <div class="nav-item dropdown me-3">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-globe"></i> <?php echo $available_languages[$current_language]; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($available_languages as $code => $name): ?>
                        <li>
                            <a class="dropdown-item <?php echo $code === $current_language ? 'active' : ''; ?>" 
                               href="index.php?page=<?php echo $current_page; ?>&lang=<?php echo $code; ?>">
                                <?php echo $name; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- User Menu -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($username); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> <?php echo get_language_string('logout'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container-fluid py-4">
        <!-- Page content will be inserted here -->
