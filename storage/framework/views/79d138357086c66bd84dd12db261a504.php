<?php $__env->startSection('title', 'Change Password'); ?>
<?php $__env->startSection('heading', 'Change Password'); ?>
<?php $__env->startSection('content'); ?>
<div class="bg-surface border border-border rounded-2xl p-6 shadow-sm max-w-lg">
    <form method="POST" action="<?php echo e(route('customer.password.update')); ?>" class="space-y-4">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>
        <div>
            <label class="block text-xs font-medium text-text-secondary mb-1.5">Current password</label>
            <input id="current_password" type="password" name="current_password" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
        </div>
        <div>
            <label class="block text-xs font-medium text-text-secondary mb-1.5">New password</label>
            <input id="password" type="password" name="password" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
        </div>
        <div>
            <label class="block text-xs font-medium text-text-secondary mb-1.5">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
        </div>
        <button type="submit" class="inline-flex items-center justify-center gap-1.5 h-11 px-5 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">
            Update password
        </button>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/customer/password/change.blade.php ENDPATH**/ ?>