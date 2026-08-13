<?php $__env->startSection('title', 'Power ID'); ?>
<?php $__env->startSection('content'); ?>
<div class="ibox">
    <div class="ibox-title"><h5>Create Power ID</h5></div>
    <div class="ibox-content">
        <form method="POST" action="<?php echo e(route('admin.power.store')); ?>" class="form-inline m-b-md">
            <?php echo csrf_field(); ?>
            <input class="form-control m-r-sm" type="number" name="parent_id" placeholder="Placement ID" required>
            <input class="form-control m-r-sm" type="number" name="sponsor_id" placeholder="Sponsor ID" required>
            <select class="form-control m-r-sm" name="position" required>
                <option value="left">Left</option>
                <option value="right">Right</option>
            </select>
            <button class="btn btn-primary">Reserve Power ID</button>
        </form>
    </div>
</div>
<div class="ibox">
    <div class="ibox-title"><h5>Unused Power IDs</h5></div>
    <div class="ibox-content">
        <table class="table table-striped">
            <thead><tr><th>ID</th><th>Parent</th><th>Sponsor</th><th>Position</th></tr></thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $powerIds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($item->id); ?></td>
                    <td><?php echo e($item->parent_id); ?></td>
                    <td><?php echo e($item->sponsor_id); ?></td>
                    <td><?php echo e($item->position?->label() ?? $item->position); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="4">No Power IDs.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php echo e($powerIds->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/admin/power/index.blade.php ENDPATH**/ ?>