<?php $__env->startSection('title', 'Income History'); ?>
<?php $__env->startSection('heading', 'Income History'); ?>
<?php $__env->startSection('content'); ?>
<div class="cmc-panel">
    <div class="cmc-panel-head">
        <span class="cmc-stat-icon"><i class="ph ph-chart-line-up"></i></span>
        <div>
            <h2 class="text-base font-semibold text-heading m-0">Income ledger</h2>
            <p class="text-xs text-muted m-0">Daily ROI, binary matching & referral bonuses</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-primary/5 text-muted text-xs uppercase">
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
                <tr class="border-t border-border hover:bg-primary/5 transition-colors">
                    <td class="px-4 py-3 text-heading font-medium"><?php echo e($row->paid_on?->format('Y-m-d')); ?></td>
                    <td class="px-4 py-3">$<?php echo e(number_format((float) $row->roi_amount, 2)); ?></td>
                    <td class="px-4 py-3">$<?php echo e(number_format((float) $row->binary_amount, 2)); ?></td>
                    <td class="px-4 py-3">$<?php echo e(number_format((float) $row->referral_amount, 2)); ?></td>
                    <td class="px-4 py-3 font-semibold text-primary">$<?php echo e(number_format((float) $row->total_amount, 2)); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="5" class="px-4 py-12 text-center">
                        <span class="cmc-stat-icon mx-auto mb-3"><i class="ph ph-chart-line-up"></i></span>
                        <p class="text-muted">No income yet.</p>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-border"><?php echo e($rows->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/customer/income/history.blade.php ENDPATH**/ ?>