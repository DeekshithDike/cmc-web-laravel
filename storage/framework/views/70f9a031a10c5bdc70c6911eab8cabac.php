<?php $__env->startSection('title', 'Change Password'); ?>
<?php $__env->startSection('heading', 'Change Password'); ?>
<?php $__env->startSection('content'); ?>
<div class="card">
    <form method="POST" action="<?php echo e(route('admin.password.update')); ?>">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>
        <div class="form-group">
            <label for="current_password">Current password</label>
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label for="password">New password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn">Update password</button>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/admin/password/change.blade.php ENDPATH**/ ?>