<?php
// Shared Header Component for ELMS
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'employee';

// Determine panel title and logo based on role
$panelTitle = $role === 'director' ? 'ELMS Director' : 
              ($role === 'manager' ? 'ELMS Department Head' : 
              ($role === 'admin' ? 'ELMS Admin' : 'ELMS Employee'));

$logoIcon = $role === 'director' ? 'fas fa-crown' : 
            ($role === 'manager' ? 'fas fa-user-tie' : 
            ($role === 'admin' ? 'fas fa-user-shield' : 'fas fa-user'));

$logoColor = $role === 'director' ? 'from-purple-500 to-pink-500' : 
             ($role === 'manager' ? 'from-orange-500 to-red-500' : 
             ($role === 'admin' ? 'from-primary to-accent' : 'from-primary to-accent'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/user/') !== false || strpos($_SERVER['PHP_SELF'], '/department/') !== false || strpos($_SERVER['PHP_SELF'], '/director/') !== false || strpos($_SERVER['PHP_SELF'], '/auth/') !== false) ? '../../assets/css/' : 'assets/css/'; ?>tailwind.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/user/') !== false || strpos($_SERVER['PHP_SELF'], '/department/') !== false || strpos($_SERVER['PHP_SELF'], '/director/') !== false || strpos($_SERVER['PHP_SELF'], '/auth/') !== false) ? '../../assets/css/' : 'assets/css/'; ?>font-awesome-local.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'ELMS'; ?></title>
    <link rel="stylesheet" href="../../assets/libs/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/user/') !== false || strpos($_SERVER['PHP_SELF'], '/department/') !== false || strpos($_SERVER['PHP_SELF'], '/director/') !== false || strpos($_SERVER['PHP_SELF'], '/auth/') !== false) ? '../../assets/css/' : 'assets/css/'; ?>style.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/user/') !== false || strpos($_SERVER['PHP_SELF'], '/department/') !== false || strpos($_SERVER['PHP_SELF'], '/director/') !== false || strpos($_SERVER['PHP_SELF'], '/auth/') !== false) ? '../../assets/css/' : 'assets/css/'; ?>dark-theme.css">
    <link rel="icon" type="image/png" href="/ELMS/elmsicon.png">
    <link rel="shortcut icon" href="/ELMS/elmsicon.png">
    <link rel="apple-touch-icon" href="/ELMS/elmsicon.png">
    
</head>
<body class="bg-slate-900 text-white">
    <!-- Top Navigation Bar -->
    <nav class="bg-slate-800 border-b border-slate-700 fixed top-0 left-0 right-0 z-50 h-16">
        <div class="px-6 py-4 h-full">
            <div class="flex items-center justify-between h-full">
                <!-- Logo and Title -->
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-gradient-to-r <?php echo $logoColor; ?> rounded-lg flex items-center justify-center">
                            <i class="<?php echo $logoIcon; ?> text-white text-sm"></i>
                        </div>
                        <span class="text-xl font-bold text-white"><?php echo $panelTitle; ?></span>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    <a href="../auth/logout.php" class="text-slate-300 hover:text-white transition-colors flex items-center space-x-2">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside class="fixed left-0 top-16 h-screen w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40 elms-sidebar">
            <nav class="p-4 space-y-2">
                <?php 
                // Include the sidebar based on role
                if ($role === 'admin') {
                    include 'admin_sidebar.php';
                } elseif ($role === 'manager') {
                    include 'manager_sidebar.php';
                } elseif ($role === 'director') {
                    include 'director_sidebar.php';
                } else {
                    include 'employee_sidebar.php';
                }
                ?>
                
                <!-- Logout Section -->
                <div class="pt-4 border-t border-slate-700">
                    <a href="../auth/logout.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors elms-nav-item">
                        <i class="fas fa-sign-out-alt w-5"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 ml-64 p-6">
            <div class="max-w-7xl mx-auto">
            