<?php $__env->startSection('title', 'Your login details'); ?>

<?php $__env->startSection('content'); ?>
<h2 class="text-xl font-bold text-heading text-center">Save these details now</h2>
<p class="text-sm text-muted text-center mt-1">This page is shown once. We do not email or store the password in your session.</p>

<?php if(! $payload): ?>
    <div class="mt-6 rounded-xl border border-danger/30 bg-danger/10 text-danger px-4 py-3 text-sm">
        These credentials have already been viewed or have expired. Use Customer Login if you already saved them, or contact support.
    </div>
    <p class="text-sm text-muted text-center mt-5">
        <a class="font-medium text-primary hover:text-primary-strong transition-colors" href="<?php echo e(route('customer.login')); ?>">Customer Login</a>
    </p>
<?php else: ?>
    <div class="mt-6 space-y-3 rounded-xl bg-subtle border border-border p-4 text-sm">
        <div>
            <p class="text-xs text-muted">Login ID</p>
            <p class="font-semibold text-heading text-lg"><?php echo e($payload['login_id']); ?></p>
        </div>
        <div>
            <p class="text-xs text-muted">Password / code</p>
            <p class="font-semibold text-heading break-all"><?php echo e($payload['password']); ?></p>
        </div>
    </div>
    <?php if(! empty($payload['continue_url'])): ?>
        <a href="<?php echo e($payload['continue_url']); ?>" class="mt-6 w-full inline-flex items-center justify-center gap-1.5 h-11 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">
            Continue <i class="ph ph-arrow-right text-base"></i>
        </a>
    <?php endif; ?>
    <p class="text-sm text-muted text-center mt-5">
        <a class="font-medium text-primary hover:text-primary-strong transition-colors" href="<?php echo e(route('customer.login')); ?>">Go to Customer Login</a>
    </p>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer-guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/auth/credentials.blade.php ENDPATH**/ ?>