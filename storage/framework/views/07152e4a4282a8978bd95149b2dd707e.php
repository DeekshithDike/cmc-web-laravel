<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Member'); ?> — <?php echo e(config('citymax.name')); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e(asset('branding/favicon-32.png')); ?>">
    <script>
      (function () {
        const saved = localStorage.getItem("cmc-theme");
        const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
        if ((saved ? saved === "dark" : prefersDark)) document.documentElement.classList.add("dark");
        if (localStorage.getItem("cmc-sidebar") !== "expanded") document.documentElement.classList.add("sidebar-collapsed");
      })();
    </script>
    <link href="<?php echo e(asset('customer-assets/css/index.css')); ?>" rel="stylesheet">
    <link href="<?php echo e(asset('customer-assets/css/crypto-ui.css')); ?>?v=<?php echo e(@filemtime(public_path('customer-assets/css/crypto-ui.css')) ?: '1'); ?>" rel="stylesheet">
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body class="cmc-shell bg-bg text-text dark:text-text font-sans antialiased relative">
<div class="cmc-ambient" aria-hidden="true"></div>
<div id="mobile-sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden opacity-0 transition-opacity duration-300 lg:hidden" onclick="closeMobileSidebar()"></div>

<aside id="mobile-sidebar" class="cmc-sidebar fixed top-0 left-0 z-70 h-full w-[280px] shadow-2xl transform -translate-x-full transition-transform duration-300 lg:hidden flex flex-col">
    <div class="h-16 flex items-center justify-between px-4 border-b border-border">
        <a class="flex items-center gap-3" href="<?php echo e(route('customer.dashboard')); ?>">
            <img src="<?php echo e(asset('customer-assets/images/logo.png')); ?>" alt="<?php echo e(config('citymax.name')); ?>" class="h-8">
        </a>
        <button type="button" aria-label="Close menu" class="w-10 h-10 rounded-xl flex items-center justify-center text-muted hover:bg-white/5" onclick="closeMobileSidebar()">
            <i class="ph ph-x text-xl"></i>
        </button>
    </div>
    <nav class="flex-1 flex flex-col overflow-y-auto p-3">
        <div class="cmc-nav-group">
            <p class="nav-section">Main</p>
            <a class="mobile-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl <?php echo e(request()->routeIs('customer.dashboard') ? 'is-active active' : ''); ?>" href="<?php echo e(route('customer.dashboard')); ?>"><i class="ph ph-squares-four text-xl"></i><span class="font-medium">Dashboard</span></a>
            <a class="mobile-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl <?php echo e(request()->routeIs('customer.tree*') ? 'is-active active' : ''); ?>" href="<?php echo e(route('customer.tree')); ?>"><i class="ph ph-tree-structure text-xl"></i><span class="font-medium">My Tree</span></a>
        </div>
        <div class="cmc-nav-group">
            <p class="nav-section">Wallet</p>
            <a class="mobile-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl <?php echo e(request()->routeIs('customer.withdrawals.create') ? 'is-active active' : ''); ?>" href="<?php echo e(route('customer.withdrawals.create')); ?>"><i class="ph ph-hand-withdraw text-xl"></i><span class="font-medium">Withdrawal Now</span></a>
            <a class="mobile-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl <?php echo e(request()->routeIs('customer.withdrawals.history') ? 'is-active active' : ''); ?>" href="<?php echo e(route('customer.withdrawals.history')); ?>"><i class="ph ph-clock-counter-clockwise text-xl"></i><span class="font-medium">Withdrawal History</span></a>
            <a class="mobile-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl <?php echo e(request()->routeIs('customer.income.history') ? 'is-active active' : ''); ?>" href="<?php echo e(route('customer.income.history')); ?>"><i class="ph ph-chart-line-up text-xl"></i><span class="font-medium">Income History</span></a>
        </div>
        <div class="cmc-nav-group">
            <p class="nav-section">Account</p>
            <a class="mobile-nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl <?php echo e(request()->routeIs('customer.password.*') ? 'is-active active' : ''); ?>" href="<?php echo e(route('customer.password.edit')); ?>"><i class="ph ph-key text-xl"></i><span class="font-medium">Change Password</span></a>
        </div>
    </nav>
</aside>

<aside id="sidebar" class="cmc-sidebar fixed top-0 left-0 z-40 h-full w-64 border-r hidden lg:flex flex-col transition-all duration-300">
    <div class="h-16 flex items-center justify-between px-4 border-b border-border-subtle">
        <a class="flex items-center gap-3 overflow-hidden" href="<?php echo e(route('customer.dashboard')); ?>">
            <img src="<?php echo e(asset('customer-assets/images/logo.png')); ?>" alt="logo" class="h-8">
        </a>
        <button id="sidebar-toggle" class="sidebar-toggle-btn w-8 h-8 rounded-lg flex items-center justify-center text-muted hover:text-white hover:bg-white/5 transition-colors flex-shrink-0" aria-label="Toggle sidebar">
            <i class="ph ph-sidebar-simple text-lg"></i>
        </button>
    </div>
    <nav class="flex-1 flex flex-col overflow-y-auto py-3 px-2.5">
        <div class="cmc-nav-group">
            <p class="nav-section nav-text">Main</p>
            <a class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?php echo e(request()->routeIs('customer.dashboard') ? 'is-active active' : ''); ?>" href="<?php echo e(route('customer.dashboard')); ?>"><i class="ph ph-squares-four text-2xl flex-shrink-0"></i><span class="nav-text font-medium whitespace-nowrap">Dashboard</span></a>
            <a class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?php echo e(request()->routeIs('customer.tree*') ? 'is-active active' : ''); ?>" href="<?php echo e(route('customer.tree')); ?>"><i class="ph ph-tree-structure text-2xl flex-shrink-0"></i><span class="nav-text font-medium whitespace-nowrap">My Tree</span></a>
        </div>
        <div class="cmc-nav-group">
            <p class="nav-section nav-text">Wallet</p>
            <a class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?php echo e(request()->routeIs('customer.withdrawals.create') ? 'is-active active' : ''); ?>" href="<?php echo e(route('customer.withdrawals.create')); ?>"><i class="ph ph-hand-withdraw text-2xl flex-shrink-0"></i><span class="nav-text font-medium whitespace-nowrap">Withdrawal Now</span></a>
            <a class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?php echo e(request()->routeIs('customer.withdrawals.history') ? 'is-active active' : ''); ?>" href="<?php echo e(route('customer.withdrawals.history')); ?>"><i class="ph ph-clock-counter-clockwise text-2xl flex-shrink-0"></i><span class="nav-text font-medium whitespace-nowrap">Withdrawal History</span></a>
            <a class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?php echo e(request()->routeIs('customer.income.history') ? 'is-active active' : ''); ?>" href="<?php echo e(route('customer.income.history')); ?>"><i class="ph ph-chart-line-up text-2xl flex-shrink-0"></i><span class="nav-text font-medium whitespace-nowrap">Income History</span></a>
        </div>
        <div class="cmc-nav-group">
            <p class="nav-section nav-text">Account</p>
            <a class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?php echo e(request()->routeIs('customer.password.*') ? 'is-active active' : ''); ?>" href="<?php echo e(route('customer.password.edit')); ?>"><i class="ph ph-key text-2xl flex-shrink-0"></i><span class="nav-text font-medium whitespace-nowrap">Change Password</span></a>
        </div>
    </nav>
    <div class="border-t border-border-subtle">
        <div class="cmc-side-user flex items-center gap-3">
            <div class="cmc-avatar w-9 h-9 rounded-full flex items-center justify-center font-bold flex-shrink-0"><?php echo e(strtoupper(substr(auth()->user()->name, 0, 1))); ?></div>
            <div class="nav-text min-w-0 flex-1 leading-tight">
                <p class="text-sm font-semibold text-white truncate"><?php echo e(auth()->user()->name); ?></p>
                <p class="text-[11px] truncate" style="color: color-mix(in srgb, #9ec9ef 80%, transparent)">ID <?php echo e(auth()->id()); ?></p>
            </div>
        </div>
    </div>
</aside>

<header id="topbar" class="fixed top-0 right-0 z-45 h-16 bg-white/70 dark:bg-w1/60 backdrop-blur-xl border-b border-border-subtle left-0 lg:left-64 transition-all duration-300">
    <div class="flex items-center justify-between h-full gap-2 px-3 sm:px-4 lg:px-6">
        <div class="flex items-center gap-2 min-w-0">
            <button type="button" class="sidebar-toggle-btn w-9 h-9 rounded-lg flex lg:hidden items-center justify-center text-muted transition-colors flex-shrink-0" aria-label="Toggle sidebar">
                <i class="ph ph-list text-xl"></i>
            </button>
            <div class="min-w-0">
                <h1 class="hidden sm:block text-lg sm:text-xl font-bold text-heading truncate"><?php echo $__env->yieldContent('heading', 'Dashboard'); ?></h1>
                <p class="hidden md:block text-[11px] text-muted truncate"><?php echo e(config('citymax.name')); ?> · crypto member desk</p>
            </div>
        </div>
        <div class="flex items-center gap-1.5 sm:gap-2">
            <span class="hidden sm:inline-flex cmc-chip"><i class="ph ph-currency-circle-dollar"></i> USDT</span>
            <button type="button" onclick="location.reload()" class="cmc-refresh-btn topbar-icon-btn w-9 h-9 sm:w-10 sm:h-10" aria-label="Refresh page" title="Refresh">
                <i class="ph ph-arrows-clockwise text-lg"></i>
            </button>
            <button type="button" onclick="cmcToggleTheme()" class="topbar-icon-btn w-9 h-9 sm:w-10 sm:h-10 border border-border" aria-label="Toggle theme">
                <i class="ph ph-moon text-lg"></i>
            </button>
            <form method="POST" action="<?php echo e(route('customer.logout')); ?>">
                <?php echo csrf_field(); ?>
                <button type="submit" class="inline-flex items-center gap-1.5 h-10 px-3 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">
                    Log out <i class="ph ph-sign-out text-base"></i>
                </button>
            </form>
        </div>
    </div>
</header>

<main id="main-content" class="pt-16 lg:ml-64 min-h-dvh transition-all duration-300 relative z-1">
    <div class="p-4 sm:p-6 lg:p-8 cmc-page-enter">
        <?php echo $__env->make('partials.alerts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->yieldContent('content'); ?>
    </div>
</main>

<script src="<?php echo e(asset('customer-assets/js/app.js')); ?>"></script>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/layouts/customer.blade.php ENDPATH**/ ?>