<?php $__env->startSection('title', 'Payments'); ?>
<?php $__env->startSection('content'); ?>
<div class="ibox">
    <div class="ibox-title"><h5>Start Manual Payment</h5></div>
    <div class="ibox-content">
        <form method="POST" action="<?php echo e(route('admin.payments.start')); ?>" class="form-inline m-b-md">
            <?php echo csrf_field(); ?>
            <input class="form-control m-r-sm" type="number" name="user_id" placeholder="User ID" required>
            <input class="form-control m-r-sm" type="number" step="0.01" name="amount" placeholder="Amount" required>
            <button class="btn btn-primary">Create pending payment</button>
        </form>
    </div>
</div>
<div class="ibox">
    <div class="ibox-title"><h5>Payment Transactions</h5></div>
    <div class="ibox-content">
        <table class="table table-striped">
            <thead><tr><th>ID</th><th>User</th><th>Provider</th><th>Ref</th><th>Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($tx->id); ?></td>
                    <td>#<?php echo e($tx->user_id); ?></td>
                    <td><?php echo e($tx->provider?->value ?? $tx->provider); ?></td>
                    <td><?php echo e($tx->provider_ref); ?></td>
                    <td>$<?php echo e(number_format((float)$tx->amount, 2)); ?></td>
                    <td><?php echo e($tx->status); ?></td>
                    <td>
                        <?php if($tx->status === 'pending'): ?>
                        <form method="POST" action="<?php echo e(route('admin.payments.confirm', $tx)); ?>"><?php echo csrf_field(); ?>
                            <button class="btn btn-primary btn-sm">Confirm</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="7">No payments yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php echo e($transactions->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/admin/payments/index.blade.php ENDPATH**/ ?>