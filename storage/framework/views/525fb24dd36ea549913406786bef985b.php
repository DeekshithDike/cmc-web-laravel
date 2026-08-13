<?php $__env->startSection('title', 'Registration successful'); ?>

<?php $__env->startSection('content'); ?>
<h2 class="text-xl font-bold text-heading text-center">Registration successful</h2>
<p class="text-sm text-muted text-center mt-2">
    Your account will be activated within an hour. Login ID and Password will be sent to your registered email address after activation.
</p>

<?php if($transaction && $transaction->status === 'completed' && ! empty($transaction->meta['credentials_token'])): ?>
    <a href="<?php echo e(route('credentials.show', ['token' => $transaction->meta['credentials_token']])); ?>" class="mt-6 w-full inline-flex items-center justify-center gap-1.5 h-11 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">
        View login details <i class="ph ph-arrow-right text-base"></i>
    </a>
<?php elseif($transaction && $transaction->status === 'failed'): ?>
    <div class="mt-6 rounded-xl border border-danger/30 bg-danger/10 text-danger px-4 py-3 text-sm">
        Payment was not completed. Signup again using the invite link.
    </div>
<?php endif; ?>

<p class="text-sm text-muted text-center mt-5">
    <a class="font-medium text-primary hover:text-primary-strong transition-colors" href="<?php echo e(route('customer.login')); ?>">Customer Login</a>
</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer-guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/customer/auth/payment-success.blade.php ENDPATH**/ ?>