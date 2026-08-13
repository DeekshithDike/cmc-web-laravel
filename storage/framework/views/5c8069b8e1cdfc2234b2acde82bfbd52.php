<?php $__env->startSection('title', 'Payment cancelled'); ?>

<?php $__env->startSection('content'); ?>
<h2 class="text-xl font-bold text-heading text-center">Payment cancelled</h2>
<p class="text-sm text-muted text-center mt-2">Signup again using the link</p>
<p class="text-sm text-muted text-center mt-5">
    <a class="font-medium text-primary hover:text-primary-strong transition-colors" href="<?php echo e(route('landing')); ?>">Back to home</a>
</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer-guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/customer/auth/payment-cancel.blade.php ENDPATH**/ ?>