<?php $__env->startSection('title', 'Dashboard'); ?>
<?php $__env->startSection('heading', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<?php if($showExpiryWarning): ?>
    <div class="mb-4 rounded-2xl border border-danger/30 bg-danger/10 text-danger px-4 py-3 text-sm">
        Your membership expires in <?php echo e($daysLeft); ?> day(s) on <?php echo e($user->expiry_date?->format('Y-m-d')); ?>.
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    <?php $__currentLoopData = [
        ['Your ID', $user->id, 'ph-identification-badge'],
        ['Wallet', '$'.number_format((float)$user->wallet_balance, 2), 'ph-wallet'],
        ['Package', '$'.number_format((float)($user->package->amount ?? 0), 2), 'ph-package'],
        ['Expiry', $user->expiry_date?->format('Y-m-d') ?? '—', 'ph-calendar'],
        ['Today Left', '$'.$leftBusinessToday, 'ph-arrow-fat-left'],
        ['Today Right', '$'.$rightBusinessToday, 'ph-arrow-fat-right'],
        ['Overall Left', '$'.$leftBusinessTotal, 'ph-chart-bar'],
        ['Overall Right', '$'.$rightBusinessTotal, 'ph-chart-bar'],
        ['Today Referral', '$'.$referralToday, 'ph-users'],
        ['Overall Referral', '$'.$referralTotal, 'ph-users-three'],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $icon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="bg-surface border border-border rounded-2xl p-4 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs font-medium text-muted"><?php echo e($label); ?></p>
            <i class="ph <?php echo e($icon); ?> text-xl text-primary"></i>
        </div>
        <p class="text-2xl font-bold text-heading"><?php echo e($value); ?></p>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/customer/dashboard.blade.php ENDPATH**/ ?>