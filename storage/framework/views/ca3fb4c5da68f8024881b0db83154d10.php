<?php $__env->startSection('title', 'Withdrawals'); ?>
<?php $__env->startSection('content'); ?>
<div class="ibox">
    <div class="ibox-title"><h5>Withdrawals — <?php echo e($status->label()); ?></h5>
        <?php if($status->value === 'completed'): ?>
            <a href="<?php echo e(route('admin.withdrawals.export.completed')); ?>" class="btn btn-success btn-sm pull-right">Download Excel (CSV)</a>
        <?php endif; ?>
    </div>
    <div class="ibox-content">
        <table class="table table-striped">
            <thead>
            <tr>
                <th>ID</th><th>User</th><th>Amount</th><th>Fee</th><th>Payable</th><th>Wallet</th><th>Payout</th><th>Date</th>
                <?php if($status->value === 'pending'): ?><th>Action</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $withdrawals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($item->id); ?></td>
                    <td>#<?php echo e($item->user_id); ?> <?php echo e($item->user->name ?? ''); ?></td>
                    <td>$<?php echo e(number_format((float)$item->amount, 2)); ?></td>
                    <td>$<?php echo e(number_format((float)$item->fee, 2)); ?></td>
                    <td>$<?php echo e(number_format((float)$item->payable_amount, 2)); ?></td>
                    <td class="text-break"><?php echo e($item->wallet_address); ?></td>
                    <td><?php echo e($item->payout_provider ?? '—'); ?><?php if($item->payout_ref): ?><br><small class="text-muted"><?php echo e($item->payout_ref); ?></small><?php endif; ?></td>
                    <td><?php echo e($item->created_at?->format('Y-m-d H:i')); ?></td>
                    <?php if($status->value === 'pending'): ?>
                    <td>
                        <form method="POST" action="<?php echo e(route('admin.withdrawals.complete', $item)); ?>" style="display:inline"><?php echo csrf_field(); ?>
                            <button class="btn btn-primary btn-sm">Pay Now</button>
                        </form>
                        <form method="POST" action="<?php echo e(route('admin.withdrawals.decline', $item)); ?>" style="display:inline"><?php echo csrf_field(); ?>
                            <button class="btn btn-danger btn-sm">Decline</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="8">No records.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php echo e($withdrawals->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/admin/withdrawals/index.blade.php ENDPATH**/ ?>