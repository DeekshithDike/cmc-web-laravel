<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="row">
    <?php $__currentLoopData = [
        'Active Users' => $stats['active_users'],
        "Today's Users" => $stats['today_users'],
        'Power ID' => $stats['power_ids'],
        'Withdrawal Requests' => $stats['pending_withdrawals'],
        'Total Business' => '$'.$stats['total_business'],
        "Today's Business" => '$'.$stats['today_business'],
        'Total Withdrawal' => '$'.$stats['total_withdrawal'],
        "Today's Withdrawal" => '$'.$stats['today_withdrawal'],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="col-lg-3">
        <div class="ibox">
            <div class="ibox-title"><h5><?php echo e($label); ?></h5></div>
            <div class="ibox-content"><h1 class="no-margins"><?php echo e($value); ?></h1></div>
        </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>