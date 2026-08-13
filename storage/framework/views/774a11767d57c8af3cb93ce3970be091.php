<?php $__env->startSection('title', 'Withdraw'); ?>
<?php $__env->startSection('heading', 'Withdrawal Now'); ?>
<?php $__env->startSection('content'); ?>
<div class="bg-surface border border-border rounded-2xl p-6 shadow-sm max-w-xl">
    <p class="text-sm text-muted mb-1">Wallet balance</p>
    <p class="text-3xl font-bold text-heading mb-4">$<?php echo e(number_format((float)$user->wallet_balance, 2)); ?></p>
    <p class="text-xs text-muted mb-4">Minimum $<?php echo e(number_format((float)$minimum, 2)); ?> · Fee $<?php echo e(number_format((float)$fee, 2)); ?> · Address must start with <code>0x</code></p>
    <form method="POST" action="<?php echo e(route('customer.withdrawals.store')); ?>" class="space-y-4">
        <?php echo csrf_field(); ?>
        <div>
            <label class="block text-xs font-medium text-text-secondary mb-1.5">Amount (USD)</label>
            <input type="number" step="0.01" name="amount" value="<?php echo e(old('amount')); ?>" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
        </div>
        <div>
            <label class="block text-xs font-medium text-text-secondary mb-1.5">Wallet Address</label>
            <input type="text" name="wallet_address" value="<?php echo e(old('wallet_address')); ?>" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
        </div>
        <button type="submit" class="inline-flex items-center justify-center gap-1.5 h-11 px-5 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">
            Withdraw Now <i class="ph ph-arrow-right"></i>
        </button>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/customer/withdrawals/create.blade.php ENDPATH**/ ?>