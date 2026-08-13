<?php $__env->startSection('title', 'Activate Power ID'); ?>
<?php $__env->startSection('content'); ?>
<div class="ibox">
    <div class="ibox-title"><h5>Activate Power ID</h5></div>
    <div class="ibox-content">
        <form method="POST" action="<?php echo e(route('admin.power.activate.save')); ?>">
            <?php echo csrf_field(); ?>
            <div class="form-group"><label>Power ID</label><input class="form-control" type="number" name="power_id" value="<?php echo e(old('power_id')); ?>" required></div>
            <div class="form-group"><label>Full Name</label><input class="form-control" name="name" value="<?php echo e(old('name')); ?>" required></div>
            <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" value="<?php echo e(old('email')); ?>" required></div>
            <div class="form-group"><label>Phone</label><input class="form-control" name="phone" value="<?php echo e(old('phone')); ?>"></div>
            <div class="form-group"><label>Country</label><input class="form-control" name="country" value="<?php echo e(old('country')); ?>"></div>
            <div class="form-group"><label>Package</label>
                <select class="form-control" name="package_id" required>
                    <?php $__currentLoopData = $packages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($package->id); ?>"><?php echo e($package->name); ?> ($<?php echo e($package->amount); ?>)</option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <button class="btn btn-primary">Activate</button>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/admin/power/activate.blade.php ENDPATH**/ ?>