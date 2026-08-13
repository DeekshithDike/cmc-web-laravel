<?php $__env->startSection('title', 'Dashboard'); ?>
<?php $__env->startSection('heading', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<?php if($showExpiryWarning): ?>
    <div class="mb-5 rounded-2xl border border-danger/30 bg-danger/10 text-danger px-4 py-3 text-sm flex items-start gap-3">
        <i class="ph ph-warning-circle text-xl mt-0.5"></i>
        <div>
            <p class="font-semibold">Membership renewing soon</p>
            <p class="opacity-90">Expires in <?php echo e($daysLeft); ?> day(s) on <?php echo e($user->expiry_date?->format('Y-m-d')); ?>.</p>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
    <section class="cmc-hero-wallet xl:col-span-2 p-5 sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wider text-white/70 mb-1">Available balance</p>
                <p class="text-4xl sm:text-5xl font-bold tracking-tight">$<?php echo e(number_format((float) $user->wallet_balance, 2)); ?></p>
                <p class="mt-2 text-sm text-white/75">Member ID <?php echo e($user->id); ?> · <?php echo e($user->name); ?></p>
            </div>
            <div class="text-right">
                <span class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-white/15 text-2xl">
                    <i class="ph ph-wallet"></i>
                </span>
                <p class="mt-2">
                    <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide">
                        <i class="ph ph-currency-circle-dollar"></i> USDT
                    </span>
                </p>
                <p class="text-xs text-white/70 mt-1.5">ERC-20 / BEP-20</p>
            </div>
        </div>
        <div class="mt-6 flex flex-wrap gap-2">
            <a href="<?php echo e(route('customer.withdrawals.create')); ?>" class="inline-flex items-center gap-1.5 h-10 px-4 rounded-xl bg-white text-primary font-semibold text-sm hover:bg-white/90 transition-colors">
                <i class="ph ph-hand-withdraw"></i> Withdraw
            </a>
            <a href="<?php echo e(route('customer.income.history')); ?>" class="inline-flex items-center gap-1.5 h-10 px-4 rounded-xl bg-white/10 text-white border border-white/25 text-sm font-medium hover:bg-white/15 transition-colors">
                <i class="ph ph-chart-line-up"></i> Income
            </a>
            <a href="<?php echo e(route('customer.tree')); ?>" class="inline-flex items-center gap-1.5 h-10 px-4 rounded-xl bg-white/10 text-white border border-white/25 text-sm font-medium hover:bg-white/15 transition-colors">
                <i class="ph ph-tree-structure"></i> My Tree
            </a>
        </div>
    </section>

    <section class="cmc-panel p-5 flex flex-col justify-between">
        <div>
            <span class="cmc-chip mb-3"><i class="ph ph-package"></i> Active package</span>
            <p class="text-3xl font-bold text-heading">$<?php echo e(number_format((float) ($user->package->amount ?? 0), 2)); ?></p>
            <p class="text-sm text-muted mt-1"><?php echo e($user->package->name ?? 'No package'); ?></p>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-primary/5 border border-primary/15 p-3">
                <p class="text-[11px] text-muted uppercase tracking-wide">Expiry</p>
                <p class="text-sm font-semibold text-heading mt-1"><?php echo e($user->expiry_date?->format('Y-m-d') ?? '—'); ?></p>
            </div>
            <div class="rounded-xl bg-primary/5 border border-primary/15 p-3">
                <p class="text-[11px] text-muted uppercase tracking-wide">Status</p>
                <p class="text-sm font-semibold text-success mt-1 inline-flex items-center gap-1"><i class="ph ph-check-circle"></i> Active</p>
            </div>
        </div>
    </section>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <?php $__currentLoopData = [
        ['Today Left', '$'.$leftBusinessToday, 'ph-arrow-fat-left', ''],
        ['Today Right', '$'.$rightBusinessToday, 'ph-arrow-fat-right', ''],
        ['Overall Left', '$'.$leftBusinessTotal, 'ph-chart-bar', 'is-accent'],
        ['Overall Right', '$'.$rightBusinessTotal, 'ph-chart-bar', 'is-accent'],
        ['Today Referral', '$'.$referralToday, 'ph-users', 'is-warn'],
        ['Overall Referral', '$'.$referralTotal, 'ph-users-three', 'is-warn'],
        ['Your ID', (string) $user->id, 'ph-identification-badge', ''],
        ['Package', '$'.number_format((float) ($user->package->amount ?? 0), 2), 'ph-package', ''],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $icon, $tone]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="cmc-stat-card <?php echo e($tone); ?> p-4">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-medium text-muted"><?php echo e($label); ?></p>
            <span class="cmc-stat-icon"><i class="ph <?php echo e($icon); ?>"></i></span>
        </div>
        <p class="text-2xl font-bold text-heading tracking-tight"><?php echo e($value); ?></p>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<section class="cmc-panel">
    <div class="cmc-panel-head">
        <span class="cmc-stat-icon"><i class="ph ph-lightning"></i></span>
        <div>
            <h2 class="text-base font-semibold text-heading m-0">Quick actions</h2>
            <p class="text-xs text-muted m-0">Move through your crypto workspace faster</p>
        </div>
    </div>
    <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <a href="<?php echo e(route('customer.withdrawals.create')); ?>" class="cmc-quick-link">
            <span class="cmc-ql-icon"><i class="ph ph-hand-withdraw"></i></span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-heading">Request withdrawal</p>
                <p class="text-xs text-muted">Send USDT to your wallet</p>
            </div>
            <i class="ph ph-caret-right text-muted ml-auto"></i>
        </a>
        <a href="<?php echo e(route('customer.tree')); ?>" class="cmc-quick-link">
            <span class="cmc-ql-icon"><i class="ph ph-tree-structure"></i></span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-heading">Grow your tree</p>
                <p class="text-xs text-muted">Invite & place members</p>
            </div>
            <i class="ph ph-caret-right text-muted ml-auto"></i>
        </a>
        <a href="<?php echo e(route('customer.income.history')); ?>" class="cmc-quick-link">
            <span class="cmc-ql-icon"><i class="ph ph-chart-line-up"></i></span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-heading">Income ledger</p>
                <p class="text-xs text-muted">ROI, binary & referral</p>
            </div>
            <i class="ph ph-caret-right text-muted ml-auto"></i>
        </a>
    </div>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/customer/dashboard.blade.php ENDPATH**/ ?>