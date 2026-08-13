<?php $__env->startSection('title', 'Active Users'); ?>
<?php $__env->startSection('content'); ?>
<div class="ibox">
    <div class="ibox-title"><h5>Active Users List</h5></div>
    <div class="ibox-content">
        <form method="GET" class="form-inline m-b-md">
            <input type="text" name="q" value="<?php echo e($q); ?>" class="form-control" placeholder="Search ID / name / email / phone">
            <button class="btn btn-primary m-l-sm">Search</button>
            <a href="<?php echo e(route('admin.users.create')); ?>" class="btn btn-info m-l-sm">Add New User</a>
            <a href="<?php echo e(route('admin.users.export')); ?>" class="btn btn-success m-l-sm">Download Excel (CSV)</a>
        </form>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Package</th><th>Wallet</th><th>Expiry</th></tr></thead>
                <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><?php echo e($user->id); ?></td>
                        <td><?php echo e($user->name); ?></td>
                        <td><?php echo e($user->email); ?></td>
                        <td><?php echo e($user->package->name ?? '—'); ?></td>
                        <td>$<?php echo e(number_format((float)$user->wallet_balance, 2)); ?></td>
                        <td><?php echo e($user->expiry_date?->format('Y-m-d')); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="6">No users found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php echo e($users->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/admin/users/index.blade.php ENDPATH**/ ?>