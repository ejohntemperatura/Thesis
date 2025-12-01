<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - ELMS</title>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../../assets/css/tailwind.css">
    <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../assets/libs/fontawesome/css/all.min.css">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/ELMS/elmsicon.png">
    <link rel="shortcut icon" href="/ELMS/elmsicon.png">
    <link rel="apple-touch-icon" href="/ELMS/elmsicon.png">
    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const eyeIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
    </script>
    
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dark-theme.css">
    
</head>
<body class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="bg-slate-800 rounded-2xl p-8 border border-slate-700/50 shadow-2xl">
            <?php if (isset($success_message)): ?>
                <!-- Success State -->
                <div class="text-center">
                    <div class="w-20 h-20 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-check-circle text-green-400 text-3xl"></i>
                    </div>
                    
                    <h1 class="text-2xl font-bold text-white mb-4">Email Verified Successfully!</h1>
                    <p class="text-slate-300 mb-6"><?php echo htmlspecialchars($success_message); ?></p>
                    
                    <div class="space-y-3">
                        <a href="login.php" class="w-full bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] inline-block">
                            <i class="fas fa-sign-in-alt mr-2"></i>Login to ELMS
                        </a>
                        <p class="text-slate-400 text-sm">
                            <i class="fas fa-shield-alt mr-1"></i>
                            Your account is now active and ready to use
                        </p>
                    </div>
                </div>
            <?php elseif (isset($verification_token)): ?>
                <!-- Password Creation Form -->
                <div class="text-center">
                    <div class="w-20 h-20 bg-blue-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-key text-blue-400 text-3xl"></i>
                    </div>
                    
                    <h1 class="text-2xl font-bold text-white mb-4">Create Your Password</h1>
                    <p class="text-slate-300 mb-6">Hello <?php echo htmlspecialchars($user_name); ?>! Please create a secure password for your account.</p>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="verification_token" value="<?php echo htmlspecialchars($verification_token); ?>">
                        
                        <div class="text-left">
                            <label for="password" class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-lock mr-2"></i>New Password
                            </label>
                            <div class="relative">
                                <input type="password" id="password" name="password" required 
                                       minlength="6"
                                       class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 pr-12 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="Enter your password (minimum 6 characters)">
                                <button type="button" onclick="togglePassword('password', 'eyeIcon1')" 
                                        class="absolute top-0 right-0 h-full flex items-center px-4 text-slate-400 hover:text-slate-200 transition-colors focus:outline-none">
                                    <i id="eyeIcon1" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="text-left">
                            <label for="confirm_password" class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-lock mr-2"></i>Confirm Password
                            </label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" required 
                                       minlength="6"
                                       class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 pr-12 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="Confirm your password">
                                <button type="button" onclick="togglePassword('confirm_password', 'eyeIcon2')" 
                                        class="absolute top-0 right-0 h-full flex items-center px-4 text-slate-400 hover:text-slate-200 transition-colors focus:outline-none">
                                    <i id="eyeIcon2" class="fas fa-eye"></i>
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
                        
                        <button type="submit" class="w-full bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02]">
                            <i class="fas fa-check mr-2"></i>Create Password & Activate Account
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Error State -->
                <div class="text-center">
                    <div class="w-20 h-20 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-exclamation-triangle text-red-400 text-3xl"></i>
                    </div>
                    
                    <h1 class="text-2xl font-bold text-white mb-4">Verification Failed</h1>
                    <p class="text-slate-300 mb-6"><?php echo htmlspecialchars($error_message); ?></p>
                    
                    <div class="space-y-3">
                        <a href="verify_email.php?token=<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>" class="w-full bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors inline-block">
                            <i class="fas fa-redo mr-2"></i>Create Again
                        </a>
                        <p class="text-slate-400 text-sm">
                            <i class="fas fa-info-circle mr-1"></i>
                            Contact your administrator if the problem persists
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-6">
            <p class="text-slate-400 text-sm">
                <i class="fas fa-envelope mr-1"></i>
                Check your email for detailed instructions
            </p>
        </div>
    </div>
</body>
</html>
