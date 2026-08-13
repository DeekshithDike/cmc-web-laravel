<?php $__env->startSection('title', 'Daily Paid Income'); ?>
<?php $__env->startSection('content'); ?>
<div class="ibox">
    <div class="ibox-title"><h5>Run Daily Income</h5></div>
    <div class="ibox-content">
        <form method="POST" action="<?php echo e(route('admin.income.daily.run')); ?>" class="form-inline">
            <?php echo csrf_field(); ?>
            <input class="form-control m-r-sm" type="date" name="as_of" value="<?php echo e(now()->toDateString()); ?>">
            <button class="btn btn-primary">Run ROI payout</button>
        </form>
        <p class="text-muted m-t-sm">Credits package ROI % to each active member once per day. Binary/referral remain for Node calc later.</p>
    </div>
</div>
<div class="ibox">
    <div class="ibox-title"><h5>Daily Paid Income</h5></div>
    <div class="ibox-content">
        <table class="table table-striped">
            <thead><tr><th>Date</th><th>User</th><th>ROI</th><th>Binary</th><th>Referral</th><th>Total</th></tr></thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($row->paid_on?->format('Y-m-d')); ?></td>
                    <td>#<?php echo e($row->user_id); ?> <?php echo e($row->user->name ?? ''); ?></td>
                    <td>$<?php echo e(number_format((float)$row->roi_amount, 2)); ?></td>
                    <td>$<?php echo e(number_format((float)$row->binary_amount, 2)); ?></td>
                    <td>$<?php echo e(number_format((float)$row->referral_amount, 2)); ?></td>
                    <td>$<?php echo e(number_format((float)$row->total_amount, 2)); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="6">No income rows yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php echo e($rows->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/admin/income/daily.blade.php ENDPATH**/ ?>