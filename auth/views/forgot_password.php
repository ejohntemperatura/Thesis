<?php
session_start();
require_once '../../config/database.php';
require_once '../../app/core/services/RobustEmail.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ? AND email_verified = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate password reset token
            $resetToken = bin2hex(random_bytes(32));
            $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
            
            // Store reset token in database
            $stmt = $pdo->prepare("UPDATE employees SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
            $stmt->execute([$resetToken, $resetExpires, $user['id']]);
            
            // Send password reset email
            $emailService = new RobustEmail($pdo);
            $resetLink = "http://localhost/ELMS/auth/views/reset_password.php?token=" . $resetToken;
            
            if ($emailService->sendPasswordResetEmail($user['email'], $user['name'], $resetLink)) {
                $message = "Password reset instructions have been sent to your email address.";
            } else {
                $error = "Failed to send password reset email. Please try again later.";
            }
        } else {
            // Don't reveal if email exists or not for security
            $message = "If an account with that email exists, password reset instructions have been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ELMS</title>
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
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-slate-700/50 backdrop-blur-xl border border-slate-600/50 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-xl">
                    <i class="fas fa-key text-4xl bg-gradient-to-r from-cyan-600 to-cyan-500 bg-clip-text text-transparent"></i>
                </div>
                <h2 class="text-3xl font-bold text-white mb-2">Forgot Password?</h2>
                <p class="text-slate-400">Enter your email address and we'll send you instructions to reset your password</p>
            </div>

            <?php if ($message): ?>
                <div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center">
                    <i class="fas fa-check-circle mr-3"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!$message): ?>
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-300 mb-2 uppercase tracking-wide">Email Address</label>
                        <div class="relative">
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   placeholder="Enter your email address" 
                                   required 
                                   autocomplete="email"
                                   class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-4 pl-12 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:border-transparent transition-all duration-300">
                            <i class="fas fa-envelope absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-cyan-600 to-cyan-500 text-white font-bold py-4 px-6 rounded-xl hover:from-cyan-700 hover:to-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-800 transition-all duration-300 transform hover:scale-[1.02] shadow-lg">
                        <span class="flex items-center justify-center">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Send Reset Instructions
                        </span>
                    </button>
                </form>
            <?php endif; ?>

            <div class="text-center mt-6">
                <a href="login.php" class="text-cyan-400 hover:text-cyan-300 transition-colors flex items-center justify-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>

