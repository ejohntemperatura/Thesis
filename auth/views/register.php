<?php
session_start();
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO employees (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $role]);
        
        // If admin registration, log them in and redirect to admin dashboard
        if ($role === 'admin') {
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            
            header('Location: admin_dashboard.php');
            exit();
        }
        
        $_SESSION['success'] = "Registration successful! Please login.";
        header('Location: index.php');
        exit();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry error
            $error = "Email already exists";
        } else {
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Register</title>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../../assets/css/tailwind.css">
    <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../assets/libs/fontawesome/css/all.min.css">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/ELMS/elmsicon.png">
    <link rel="shortcut icon" href="/ELMS/elmsicon.png">
    <link rel="apple-touch-icon" href="/ELMS/elmsicon.png">
    <script>
    </script>
    
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dark-theme.css">
    
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center relative overflow-x-hidden">
    <!-- Animated Background -->
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute w-72 h-72 bg-primary/20 rounded-full -top-36 -left-36 animate-float"></div>
        <div class="absolute w-48 h-48 bg-accent/20 rounded-full top-20 -right-24 animate-float" style="animation-delay: 2s;"></div>
        <div class="absolute w-36 h-36 bg-secondary/20 rounded-full -bottom-18 left-20 animate-float" style="animation-delay: 4s;"></div>
        <div class="absolute w-60 h-60 bg-primary/10 rounded-full bottom-10 right-10 animate-float" style="animation-delay: 1s;"></div>
    </div>

    <div class="w-full max-w-6xl mx-auto px-8 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center min-h-screen py-8">
        <!-- Left Side - Branding -->
        <div class="text-white z-10 order-2 lg:order-1">
            <div class="w-20 h-20 bg-slate-800/50 backdrop-blur-xl border border-slate-700/50 rounded-2xl flex items-center justify-center mb-8 shadow-2xl animate-pulse-slow">
                <i class="fas fa-user-plus text-4xl bg-gradient-to-r from-primary to-accent bg-clip-text text-transparent"></i>
            </div>
            <h1 class="text-6xl lg:text-7xl font-black leading-tight mb-4 bg-gradient-to-r from-white to-slate-300 bg-clip-text text-transparent">
                ELMS
            </h1>
            <h2 class="text-2xl lg:text-3xl font-bold text-slate-300 mb-6">
                Employee Leave Management System
            </h2>
            <p class="text-lg text-slate-400 leading-relaxed">
                Join our team and manage your leave requests efficiently with our modern, user-friendly platform.
            </p>
        </div>

        <!-- Right Side - Registration Form -->
        <div class="z-10 order-1 lg:order-2">
            <div class="bg-slate-800/50 backdrop-blur-xl border border-slate-700/50 rounded-3xl p-8 w-full max-w-md mx-auto shadow-2xl animate-slide-up">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-white mb-2">Create Account</h2>
                    <p class="text-slate-400">Sign up to get started with ELMS</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center animate-shake">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-check-circle mr-3"></i>
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="register-form" class="space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-semibold text-slate-300 mb-2 uppercase tracking-wide">Full Name</label>
                        <div class="relative">
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   placeholder="Enter your full name" 
                                   required 
                                   class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-4 pl-12 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300">
                            <i class="fas fa-user absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-300 mb-2 uppercase tracking-wide">Email Address</label>
                        <div class="relative">
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   placeholder="Enter your email" 
                                   required 
                                   autocomplete="username"
                                   class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-4 pl-12 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300">
                            <i class="fas fa-envelope absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-300 mb-2 uppercase tracking-wide">Password</label>
                        <div class="relative">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Create a password" 
                                   required 
                                   autocomplete="new-password"
                                   class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-4 pl-12 pr-12 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300">
                            <i class="fas fa-lock absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                            <button type="button" 
                                    onclick="togglePassword()" 
                                    aria-label="Toggle password visibility"
                                    class="absolute right-4 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-white transition-colors p-2 rounded-lg hover:bg-slate-600/50">
                                <i class="fas fa-eye" id="toggle-icon"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-semibold text-slate-300 mb-2 uppercase tracking-wide">Role</label>
                        <div class="relative">
                            <select id="role" 
                                    name="role" 
                                    required 
                                    class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-4 pl-12 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300">
                                <option value="">Select your role</option>
                                <option value="employee">Employee</option>
                                <option value="manager">Department Head</option>
                                <option value="director">Director</option>
                                <option value="admin">Administrator</option>
                            </select>
                            <i class="fas fa-user-tag absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        </div>
                    </div>

                    <button type="submit" 
                            id="register-btn"
                            class="w-full bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl relative overflow-hidden group">
                        <span class="relative z-10 flex items-center justify-center">
                            <i class="fas fa-user-plus mr-2"></i>
                            <span id="register-btn-text">Create Account</span>
                        </span>
                        <div class="absolute inset-0 bg-gradient-to-r from-primary/20 to-accent/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    </button>
                </form>

                <div class="text-center mt-6">
                    <p class="text-slate-400">
                        Already have an account? 
                        <a href="index.php" class="text-primary hover:text-accent transition-colors font-semibold">
                            Sign in here
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Enhanced form submission with loading states
        document.getElementById('register-form').addEventListener('submit', function(e) {
            const btn = document.getElementById('register-btn');
            const btnText = document.getElementById('register-btn-text');
            
            // Disable button and show loading state
            btn.disabled = true;
            btnText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating Account...';
            btn.classList.add('opacity-75', 'cursor-not-allowed');
        });

        // Add focus effects to form inputs
        document.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('ring-2', 'ring-primary/50');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('ring-2', 'ring-primary/50');
            });
        });

        // Add ripple effect to button
        document.getElementById('register-btn').addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('absolute', 'bg-white', 'bg-opacity-30', 'rounded-full', 'pointer-events-none', 'animate-ping');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });

        // Add shake animation for error messages
        const errorDiv = document.querySelector('.bg-red-500/20');
        if (errorDiv) {
            errorDiv.classList.add('animate-bounce');
        }
    </script>
</body>
</html>