<?php
session_start();
require_once '../../config/database.php';

$error = '';
$success = '';
$validToken = false;
$user = null;

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $error = "Invalid reset link. Please request a new password reset.";
} else {
    $token = $_GET['token'];
    
    // Find user with valid reset token (using PHP time to avoid timezone issues)
    $currentTime = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE password_reset_token = ? AND password_reset_expires > ?");
    $stmt->execute([$token, $currentTime]);
    $user = $stmt->fetch();
    
    if ($user) {
        $validToken = true;
        
        // Handle password reset form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Validate password
            if (strlen($password) < 6) {
                $error = "Password must be at least 6 characters long.";
            } elseif ($password !== $confirmPassword) {
                $error = "Passwords do not match.";
            } else {
                // Update password and clear reset token
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE employees SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
                $stmt->execute([$hashedPassword, $user['id']]);
                
                $success = "Password reset successfully! You can now log in with your new password.";
                $validToken = false; // Hide form after successful reset
            }
        }
    } else {
        $error = "Invalid or expired reset token. Please request a new password reset.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ELMS</title>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../../assets/css/tailwind.css">
    <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../assets/libs/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dark-theme.css">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/ELMS/elmsicon.png">
    <link rel="shortcut icon" href="/ELMS/elmsicon.png">
    <link rel="apple-touch-icon" href="/ELMS/elmsicon.png">
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center relative overflow-x-hidden">
    <!-- Animated Background -->
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute w-72 h-72 bg-cyan-600/20 rounded-full -top-36 -left-36 animate-pulse"></div>
        <div class="absolute w-48 h-48 bg-cyan-500/20 rounded-full top-20 -right-24 animate-pulse" style="animation-delay: 2s;"></div>
        <div class="absolute w-36 h-36 bg-orange-500/20 rounded-full -bottom-18 left-20 animate-pulse" style="animation-delay: 4s;"></div>
        <div class="absolute w-60 h-60 bg-cyan-600/10 rounded-full bottom-10 right-10 animate-pulse" style="animation-delay: 1s;"></div>
    </div>

    <div class="w-full max-w-md mx-auto px-8">
        <div class="bg-slate-800/50 backdrop-blur-xl border border-slate-700/50 rounded-3xl p-8 shadow-2xl">
            <?php if ($success): ?>
                <!-- Success State -->
                <div class="text-center">
                    <div class="w-20 h-20 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-check-circle text-green-400 text-3xl"></i>
                    </div>
                    
                    <h2 class="text-3xl font-bold text-white mb-4">Password Reset Successful!</h2>
                    <p class="text-slate-300 mb-6"><?php echo $success; ?></p>
                    
                    <a href="login.php" class="w-full bg-gradient-to-r from-cyan-600 to-cyan-500 text-white font-bold py-4 px-6 rounded-xl hover:from-cyan-700 hover:to-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-800 transition-all duration-300 transform hover:scale-[1.02] shadow-lg inline-block">
                        <span class="flex items-center justify-center">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Go to Login
                        </span>
                    </a>
                </div>
            <?php elseif ($validToken): ?>
                <!-- Reset Form -->
                <div class="text-center mb-8">
                    <div class="w-20 h-20 bg-slate-700/50 backdrop-blur-xl border border-slate-600/50 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-xl">
                        <i class="fas fa-key text-4xl bg-gradient-to-r from-cyan-600 to-cyan-500 bg-clip-text text-transparent"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-white mb-2">Reset Your Password</h2>
                    <p class="text-slate-400">Hello <?php echo htmlspecialchars($user['name']); ?>! Please enter your new password below.</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-300 mb-2 uppercase tracking-wide">New Password</label>
                        <div class="relative">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Enter your new password" 
                                   required 
                                   minlength="6"
                                   autocomplete="new-password"
                                   class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-4 pl-12 pr-12 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:border-transparent transition-all duration-300">
                            <i class="fas fa-lock absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                            <button type="button" 
                                    onclick="togglePassword('password')" 
                                    aria-label="Toggle password visibility"
                                    class="absolute right-4 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-white transition-colors p-2 rounded-lg hover:bg-slate-600/50">
                                <i class="fas fa-eye" id="toggle-icon-password"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-semibold text-slate-300 mb-2 uppercase tracking-wide">Confirm Password</label>
                        <div class="relative">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Confirm your new password" 
                                   required 
                                   minlength="6"
                                   autocomplete="new-password"
                                   class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-4 pl-12 pr-12 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:border-transparent transition-all duration-300">
                            <i class="fas fa-lock absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                            <button type="button" 
                                    onclick="togglePassword('confirm_password')" 
                                    aria-label="Toggle password visibility"
                                    class="absolute right-4 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-white transition-colors p-2 rounded-lg hover:bg-slate-600/50">
                                <i class="fas fa-eye" id="toggle-icon-confirm_password"></i>
                            </button>
                        </div>
                    </div>

                    <div class="bg-yellow-500/20 border border-yellow-500/30 rounded-xl p-4 text-left">
                        <h3 class="text-yellow-400 font-semibold mb-2">Password Requirements:</h3>
                        <ul class="text-slate-300 text-sm space-y-1">
                            <li>• At least 6 characters long</li>
                            <li>• Use a combination of letters and numbers</li>
                            <li>• Avoid common passwords</li>
                        </ul>
                    </div>

                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-cyan-600 to-cyan-500 text-white font-bold py-4 px-6 rounded-xl hover:from-cyan-700 hover:to-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-800 transition-all duration-300 transform hover:scale-[1.02] shadow-lg">
                        <span class="flex items-center justify-center">
                            <i class="fas fa-check mr-2"></i>
                            Reset Password
                        </span>
                    </button>
                </form>
            <?php else: ?>
                <!-- Error State -->
                <div class="text-center">
                    <div class="w-20 h-20 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-exclamation-triangle text-red-400 text-3xl"></i>
                    </div>
                    
                    <h2 class="text-3xl font-bold text-white mb-4">Reset Failed</h2>
                    <p class="text-slate-300 mb-6"><?php echo $error; ?></p>
                    
                    <a href="forgot_password.php" class="w-full bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors inline-block">
                        <i class="fas fa-redo mr-2"></i>Request New Reset
                    </a>
                </div>
            <?php endif; ?>

            <div class="text-center mt-6">
                <a href="login.php" class="text-cyan-400 hover:text-cyan-300 transition-colors flex items-center justify-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Login
                </a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = document.getElementById('toggle-icon-' + fieldId);
            
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
    </script>
</body>
</html>
