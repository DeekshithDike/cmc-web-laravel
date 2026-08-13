<?php $__env->startSection('title', 'Income History'); ?>
<?php $__env->startSection('heading', 'Income History'); ?>
<?php $__env->startSection('content'); ?>
<div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-subtle text-muted text-xs uppercase">
            <tr>
                <th class="text-left px-4 py-3">Date</th>
                <th class="text-left px-4 py-3">ROI</th>
                <th class="text-left px-4 py-3">Binary</th>
                <th class="text-left px-4 py-3">Referral</th>
                <th class="text-left px-4 py-3">Total</th>
            </tr>
            </thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="border-t border-border">
                    <td class="px-4 py-3"><?php echo e($row->paid_on?->format('Y-m-d')); ?></td>
                    <td class="px-4 py-3">$<?php echo e(number_format((float)$row->roi_amount, 2)); ?></td>
                    <td class="px-4 py-3">$<?php echo e(number_format((float)$row->binary_amount, 2)); ?></td>
                    <td class="px-4 py-3">$<?php echo e(number_format((float)$row->referral_amount, 2)); ?></td>
                    <td class="px-4 py-3 font-semibold text-heading">$<?php echo e(number_format((float)$row->total_amount, 2)); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-muted">No income yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-4"><?php echo e($rows->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/customer/income/history.blade.php ENDPATH**/ ?>