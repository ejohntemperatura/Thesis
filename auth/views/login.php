<?php
session_start();
require_once '../../config/database.php';

// Check for remember me cookie (skip if user just logged out)
if (isset($_COOKIE['remember_token']) && !isset($_SESSION['user_id']) && !isset($_GET['logged_out'])) {
    $rememberToken = $_COOKIE['remember_token'];
    $currentTime = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT e.* FROM employees e WHERE e.remember_token = ? AND e.remember_token_expires > ?");
    $stmt->execute([$rememberToken, $currentTime]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['position'] = $user['position'];
        $_SESSION['department'] = $user['department'];
        $_SESSION['logged_in_this_session'] = true;
        
        // Redirect based on role
        if ($user['role'] === 'employee') {
            header('Location: ../../app/modules/user/views/dtr.php');
            exit();
        } elseif ($user['role'] === 'admin') {
            header('Location: ../../app/modules/admin/views/dashboard.php');
            exit();
        } elseif ($user['role'] === 'manager') {
            header('Location: ../../app/modules/department/views/dashboard.php');
            exit();
        } elseif ($user['role'] === 'director') {
            header('Location: ../../app/modules/director/views/dashboard.php');
            exit();
        } else {
            header('Location: ../../app/modules/user/views/dashboard.php');
            exit();
        }
    } else {
        // Invalid remember token, delete the cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['position'] = $user['position'];
        $_SESSION['department'] = $user['department'];
        $_SESSION['logged_in_this_session'] = true;
        
        // Handle Remember Me functionality
        if ($remember) {
            $rememberToken = bin2hex(random_bytes(32));
            $rememberExpires = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $pdo->prepare("UPDATE employees SET remember_token = ?, remember_token_expires = ? WHERE id = ?");
            $stmt->execute([$rememberToken, $rememberExpires, $user['id']]);
            
            // Set secure cookie
            setcookie('remember_token', $rememberToken, time() + (30 * 24 * 60 * 60), '/', '', true, true);
        }
        
        // For employees, always redirect to DTR page first
        if ($user['role'] === 'employee') {
            header('Location: ../../app/modules/user/views/dtr.php');
            exit();
        }
        
        // For admin, go directly to admin dashboard
        if ($user['role'] === 'admin') {
            header('Location: ../../app/modules/admin/views/dashboard.php');
            exit();
        }

        // For department head/manager
        if ($user['role'] === 'manager') {
            header('Location: ../../app/modules/department/views/dashboard.php');
            exit();
        }

        // For director head
        if ($user['role'] === 'director') {
            header('Location: ../../app/modules/director/views/dashboard.php');
            exit();
        }

        // Default fallback
        header('Location: ../../app/modules/user/views/dashboard.php');
        exit();
    } else {
        $error = "Invalid email or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Login</title>
        <!-- OFFLINE Tailwind CSS - No internet required! -->
        <link rel="stylesheet" href="../../assets/css/tailwind.css">
        <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../assets/libs/fontawesome/css/all.min.css">
        <!-- Font Awesome Local - No internet required! -->
        
        <link rel="stylesheet" href="../../assets/css/elms-dark-theme.css">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/ELMS/elmsicon.png">
    <link rel="shortcut icon" href="/ELMS/elmsicon.png">
    <link rel="apple-touch-icon" href="/ELMS/elmsicon.png">
    
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center relative overflow-x-hidden auth-login">
    <!-- Animated Background -->
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute w-72 h-72 bg-cyan-600/20 rounded-full -top-36 -left-36 animate-pulse"></div>
        <div class="absolute w-48 h-48 bg-cyan-500/20 rounded-full top-20 -right-24 animate-pulse" style="animation-delay: 2s;"></div>
        <div class="absolute w-36 h-36 bg-orange-500/20 rounded-full -bottom-18 left-20 animate-pulse" style="animation-delay: 4s;"></div>
        <div class="absolute w-60 h-60 bg-cyan-600/10 rounded-full bottom-10 right-10 animate-pulse" style="animation-delay: 1s;"></div>
    </div>

    <div class="w-full max-w-6xl mx-auto px-8 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center min-h-screen py-8">
        <!-- Left Side - Branding -->
        <div class="text-white z-10 order-2 lg:order-1 login-brand">
            <div class="w-20 h-20 bg-slate-800/50 backdrop-blur-xl border border-slate-700/50 rounded-2xl flex items-center justify-center mb-8 shadow-2xl animate-pulse">
                <i class="fas fa-calendar-check text-4xl bg-gradient-to-r from-cyan-600 to-cyan-500 bg-clip-text text-transparent"></i>
            </div>
            <h1 class="text-6xl lg:text-7xl font-black leading-tight mb-4 bg-gradient-to-r from-white to-slate-300 bg-clip-text text-transparent">
                ELMS
            </h1>
            <p class="text-xl text-slate-300 mb-8">
                Employee Leave Management System
            </p>
        </div>

        <!-- Right Side - Login Form -->
        <div class="z-10 order-1 lg:order-2">
            <div class="bg-slate-800/50 backdrop-blur-xl border border-slate-700/50 rounded-3xl p-8 w-full max-w-md mx-auto shadow-2xl login-card">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-white mb-2">Welcome Back</h2>
                    <p class="text-slate-400">Sign in to your account to continue</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="login-form" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-300 mb-2 uppercase tracking-wide">Email Address</label>
                        <div class="relative">
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   placeholder="Enter your email" 
                                   required 
                                   autocomplete="username"
                                   class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-4 pl-12 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:border-transparent transition-all duration-300">
                            <i class="fas fa-envelope absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-300 mb-2 uppercase tracking-wide">Password</label>
                        <div class="relative">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Enter your password" 
                                   required 
                                   autocomplete="current-password"
                                   class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-4 pl-12 pr-12 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:border-transparent transition-all duration-300">
                            <i class="fas fa-lock absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                            <button type="button" 
                                    onclick="togglePassword()" 
                                    aria-label="Toggle password visibility"
                                    class="absolute right-4 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-white transition-colors p-2 rounded-lg hover:bg-slate-600/50">
                                <i class="fas fa-eye" id="toggle-icon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   id="remember" 
                                   name="remember" 
                                   class="w-4 h-4 text-cyan-600 bg-slate-700 border-slate-600 rounded focus:ring-cyan-500 focus:ring-2">
                            <label for="remember" class="ml-2 text-sm text-slate-300">Remember me</label>
                        </div>
                        <a href="forgot_password.php" class="text-sm text-cyan-400 hover:text-cyan-300 transition-colors">Forgot password?</a>
                    </div>

                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-cyan-600 to-cyan-500 text-white font-bold py-4 px-6 rounded-xl hover:from-cyan-700 hover:to-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-800 transition-all duration-300 transform hover:scale-[1.02] shadow-lg">
                        <span class="flex items-center justify-center">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Sign In
                        </span>
                    </button>
                </form>

            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form validation
        document.getElementById('login-form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields.');
                return;
            }
        });
    </script>
</body>
</html> 