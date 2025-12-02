<?php
/**
 * Tailwind CSS Includes for ELMS
 * This file provides consistent CSS includes across the entire system
 */

// Determine the correct asset path based on current directory
$current_dir = dirname($_SERVER['PHP_SELF']);
$asset_path = '';

if (strpos($current_dir, '/admin') !== false || 
    strpos($current_dir, '/user') !== false || 
    strpos($current_dir, '/department') !== false || 
    strpos($current_dir, '/director') !== false || 
    strpos($current_dir, '/auth') !== false) {
    $asset_path = '../../assets/css/';
} else {
    $asset_path = 'assets/css/';
}
?>

<!-- ===== ELMS Tailwind CSS System Includes ===== -->
<!-- OFFLINE Tailwind CSS - No internet required! -->
<link rel="stylesheet" href="<?php echo $asset_path; ?>tailwind.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>font-awesome-local.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>style.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>dark-theme.css">

<!-- Additional CSS for specific roles -->
<?php if (isset($_SESSION['role'])): ?>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <link rel="stylesheet" href="<?php echo $asset_path; ?>admin_style.css">
    <?php endif; ?>
<?php endif; ?>

<!-- Chart.js for dashboard charts -->
<script src="../../assets/libs/chartjs/chart.umd.min.js"></script>

<!-- Custom ELMS JavaScript -->
<script>
// ELMS Global JavaScript Functions
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('-translate-x-full');
    }
}

// Removed conflicting dropdown functions - using unified navbar instead

function toggleSearch() {
    const searchBar = document.getElementById('searchBar');
    if (searchBar) {
        searchBar.classList.toggle('hidden');
    }
}

// Removed conflicting dropdown event listeners - using unified navbar instead

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 5000);
    });
});

// Form validation enhancement
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    field.classList.add('border-red-500');
                    isValid = false;
                } else {
                    field.classList.remove('border-red-500');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                if (typeof showStyledAlert === 'function') {
                    showStyledAlert('Please fill in all required fields.', 'warning');
                } else {
                    alert('Please fill in all required fields.');
                }
            }
        });
    });
});
</script>

