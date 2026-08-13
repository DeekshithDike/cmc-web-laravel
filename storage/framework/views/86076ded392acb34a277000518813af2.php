<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title'); ?> — <?php echo e(config('citymax.name')); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e(asset('branding/favicon-32.png')); ?>">
    <script>
      (function () {
        const saved = localStorage.getItem("cmc-theme");
        const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
        if ((saved ? saved === "dark" : prefersDark)) document.documentElement.classList.add("dark");
      })();
    </script>
    <link href="<?php echo e(asset('customer-assets/css/index.css')); ?>" rel="stylesheet">
</head>
<body class="bg-bg text-text dark:text-text font-sans antialiased">
<main class="min-h-dvh flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="bg-surface border border-border rounded-3xl shadow-xl shadow-black/5 p-6 sm:p-8">
            <a class="flex justify-center mb-6" href="<?php echo e(route('landing')); ?>">
                <img src="<?php echo e(asset('customer-assets/images/logo.png')); ?>" alt="<?php echo e(config('citymax.name')); ?>" class="h-10 object-contain">
            </a>
            <?php echo $__env->yieldContent('content'); ?>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 text-[11px] text-muted mt-5">
            <span class="inline-flex items-center gap-1"><i class="ph ph-shield-check text-success"></i>Secure sign-in</span>
            <span class="inline-flex items-center gap-1"><i class="ph ph-lock-key text-success"></i><?php echo e(config('citymax.tagline')); ?></span>
        </div>
        <p class="text-center text-[11px] text-faint mt-4">© <?php echo e(date('Y')); ?> <?php echo e(config('citymax.name')); ?></p>
    </div>
</main>
<script src="<?php echo e(asset('customer-assets/js/app.js')); ?>"></script>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/layouts/customer-guest.blade.php ENDPATH**/ ?>