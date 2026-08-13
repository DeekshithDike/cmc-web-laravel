<?php $__env->startSection('title', 'Renewed Users'); ?>
<?php $__env->startSection('heading', 'Manage Renewals — Renewed Users'); ?>
<?php $__env->startSection('content'); ?>
<div class="card">
    <table>
        <thead><tr><th>User</th><th>Previous</th><th>New Expiry</th><th>Amount</th><th>Date</th></tr></thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($row->user?->name ?? $row->user_id); ?></td>
                    <td><?php echo e($row->previous_expiry?->format('Y-m-d')); ?></td>
                    <td><?php echo e($row->new_expiry?->format('Y-m-d')); ?></td>
                    <td>$<?php echo e(number_format((float) $row->amount, 2)); ?></td>
                    <td><?php echo e($row->created_at?->format('Y-m-d')); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5">No renewals yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div style="margin-top:1rem;"><?php echo e($rows->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/admin/renewals/renewed.blade.php ENDPATH**/ ?>