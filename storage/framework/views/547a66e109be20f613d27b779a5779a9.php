<?php $__env->startSection('title', 'Expired Users'); ?>
<?php $__env->startSection('heading', 'Manage Renewals — Expired Users'); ?>
<?php $__env->startSection('content'); ?>
<div class="card">
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Expiry</th></tr></thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($user->id); ?></td>
                    <td><?php echo e($user->name); ?></td>
                    <td><?php echo e($user->expiry_date?->format('Y-m-d')); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="3">No expired members.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div style="margin-top:1rem;"><?php echo e($users->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/admin/renewals/expired.blade.php ENDPATH**/ ?>