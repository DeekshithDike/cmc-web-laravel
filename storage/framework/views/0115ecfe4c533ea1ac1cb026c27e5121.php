<?php $__env->startSection('title', 'Daily Paid Income'); ?>
<?php $__env->startSection('content'); ?>
<div class="ibox">
    <div class="ibox-title"><h5>Run Daily Income</h5></div>
    <div class="ibox-content">
        <p class="m-b-sm">Calculates <strong><?php echo e($asOf); ?></strong> (yesterday, 00:00–23:59). Same job as the 00:05 cron. Already calculated days are skipped.</p>
        <?php if($existing && $existing->status === 'completed'): ?>
            <p class="text-success">Already calculated: <?php echo e($existing->processed); ?> members, $<?php echo e(number_format((float) $existing->total_paid, 2)); ?> (<?php echo e($existing->triggered_by); ?>).</p>
        <?php endif; ?>
        <form method="POST" action="<?php echo e(route('admin.income.daily.run')); ?>">
            <?php echo csrf_field(); ?>
            <button class="btn btn-primary" <?php if($existing && $existing->status === 'completed'): ?> disabled <?php endif; ?>>Run previous day income</button>
        </form>
        <p class="text-muted m-t-sm">Pays ROI (skipped Sunday and Monday), 5% binary matching (capped at package and $500), and 10% of that day's stored referral package volume.</p>
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