<?php $__env->startSection('title', 'Withdrawal History'); ?>
<?php $__env->startSection('heading', 'Withdrawal History'); ?>
<?php $__env->startSection('content'); ?>
<div class="bg-surface border border-border rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-subtle text-muted text-xs uppercase">
            <tr>
                <th class="text-left px-4 py-3">Amount</th>
                <th class="text-left px-4 py-3">Fee</th>
                <th class="text-left px-4 py-3">Payable</th>
                <th class="text-left px-4 py-3">Status</th>
                <th class="text-left px-4 py-3">Date</th>
            </tr>
            </thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $withdrawals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="border-t border-border">
                    <td class="px-4 py-3 text-heading font-medium">$<?php echo e(number_format((float)$item->amount, 2)); ?></td>
                    <td class="px-4 py-3">$<?php echo e(number_format((float)$item->fee, 2)); ?></td>
                    <td class="px-4 py-3">$<?php echo e(number_format((float)$item->payable_amount, 2)); ?></td>
                    <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-lg bg-subtle text-xs font-medium"><?php echo e($item->status->label()); ?></span></td>
                    <td class="px-4 py-3 text-muted"><?php echo e($item->created_at?->format('Y-m-d H:i')); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-muted">No withdrawals yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-4"><?php echo e($withdrawals->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/customer/withdrawals/history.blade.php ENDPATH**/ ?>