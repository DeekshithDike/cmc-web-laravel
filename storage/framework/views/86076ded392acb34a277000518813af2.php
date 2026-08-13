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
    <link href="<?php echo e(asset('customer-assets/css/crypto-ui.css')); ?>?v=<?php echo e(@filemtime(public_path('customer-assets/css/crypto-ui.css')) ?: '1'); ?>" rel="stylesheet">
</head>
<body class="cmc-guest-body bg-bg text-text dark:text-text font-sans antialiased">
<main class="cmc-guest-stage">
    <span class="cmc-float-ico hidden sm:inline-flex" style="top:14%;left:9%"><i class="ph ph-currency-btc"></i></span>
    <span class="cmc-float-ico hidden md:inline-flex" style="top:20%;right:11%"><i class="ph ph-currency-eth"></i></span>
    <span class="cmc-float-ico hidden lg:inline-flex" style="bottom:16%;right:18%"><i class="ph ph-currency-circle-dollar"></i></span>

    <div class="w-full max-w-md relative z-1 cmc-page-enter">
        <div class="cmc-guest-card p-6 sm:p-8">
            <a class="flex justify-center mb-5" href="<?php echo e(route('landing')); ?>">
                <img src="<?php echo e(asset('customer-assets/images/logo.png')); ?>" alt="<?php echo e(config('citymax.name')); ?>" class="h-10 object-contain">
            </a>
            <div class="flex justify-center mb-4">
                <span class="cmc-chip"><i class="ph ph-currency-circle-dollar"></i> USDT workspace</span>
            </div>
            <?php echo $__env->yieldContent('content'); ?>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 text-[11px] text-muted mt-5">
            <span class="inline-flex items-center gap-1"><i class="ph ph-shield-check text-success"></i>Secure sign-in</span>
            <span class="inline-flex items-center gap-1"><i class="ph ph-lock-key text-success"></i><?php echo e(config('citymax.tagline')); ?></span>
            <span class="inline-flex items-center gap-1"><i class="ph ph-lightning text-primary"></i>Fast USDT payouts</span>
        </div>
        <p class="text-center text-[11px] text-faint mt-4">© <?php echo e(date('Y')); ?> <?php echo e(config('citymax.name')); ?></p>
    </div>
</main>
<script src="<?php echo e(asset('customer-assets/js/app.js')); ?>"></script>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/layouts/customer-guest.blade.php ENDPATH**/ ?>