<?php $__env->startSection('title', 'All Users Business'); ?>
<?php $__env->startSection('content'); ?>
<div class="ibox">
    <div class="ibox-title"><h5>All Users Business</h5></div>
    <div class="ibox-content">
        <form method="GET" class="form-inline m-b-md">
            <input type="date" name="date" value="<?php echo e($date); ?>" class="form-control">
            <button class="btn btn-primary m-l-sm">Filter</button>
        </form>
        <table class="table table-striped">
            <thead><tr><th>User ID</th><th>Name</th><th>Left</th><th>Right</th><th>Total</th></tr></thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($row['user_id']); ?></td>
                    <td><?php echo e($row['name']); ?></td>
                    <td>$<?php echo e($row['left']); ?></td>
                    <td>$<?php echo e($row['right']); ?></td>
                    <td>$<?php echo e(number_format((float)$row['left'] + (float)$row['right'], 2)); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5">No business volume for this date.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/admin/business/all.blade.php ENDPATH**/ ?>