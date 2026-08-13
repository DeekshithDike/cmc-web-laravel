<?php $__env->startSection('title', 'Register'); ?>

<?php $__env->startSection('content'); ?>
<div class="text-center mb-1">
    <span class="cmc-chip"><i class="ph ph-rocket-launch"></i> <?php echo e($powerId ? 'Power ID' : 'Invite join'); ?></span>
</div>
<h2 class="text-xl font-bold text-heading text-center mt-3"><?php echo e($heading ?? 'Join '.config('citymax.name')); ?></h2>
<p class="text-sm text-muted text-center mt-1"><?php echo e($powerId ? 'Pay to activate this reserved Power ID' : 'Complete your invite registration'); ?></p>

<?php echo $__env->make('partials.alerts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="mt-4 rounded-xl bg-primary/5 border border-primary/20 p-3 text-xs text-text-secondary flex flex-wrap gap-x-3 gap-y-1 justify-center">
    <span><i class="ph ph-map-pin text-primary"></i> Placement <strong>#<?php echo e($placementId); ?></strong></span>
    <span><i class="ph ph-arrows-left-right text-primary"></i> <?php echo e(ucfirst($position)); ?></span>
    <span><i class="ph ph-user text-primary"></i> Sponsor <strong>#<?php echo e($sponsorId); ?></strong></span>
</div>

<form class="space-y-3 mt-4" method="POST" action="<?php echo e($formAction ?? route('customer.register.save')); ?>">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="parent_id" value="<?php echo e($placementId); ?>">
    <input type="hidden" name="position" value="<?php echo e($position); ?>">
    <input type="hidden" name="sponsor_id" value="<?php echo e($sponsorId); ?>">
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Full Name</label>
        <input name="name" value="<?php echo e(old('name')); ?>" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
    </div>
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Email</label>
        <input type="email" name="email" value="<?php echo e(old('email')); ?>" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
    </div>
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Phone</label>
        <input name="phone" value="<?php echo e(old('phone')); ?>" class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
    </div>
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Country</label>
        <input name="country" value="<?php echo e(old('country')); ?>" class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
    </div>
    <div>
        <label class="block text-xs font-medium text-text-secondary mb-1.5">Package</label>
        <select name="package_id" required class="w-full h-11 px-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
            <?php $__currentLoopData = $packages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($package->id); ?>"><?php echo e($package->name); ?> ($<?php echo e($package->amount); ?>)</option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 h-11 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors mt-2 shadow-lg shadow-primary/25">
        Continue to payment <i class="ph ph-arrow-right text-base"></i>
    </button>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer-guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/customer/auth/register.blade.php ENDPATH**/ ?>