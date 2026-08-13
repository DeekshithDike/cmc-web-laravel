<?php $__env->startSection('title', 'My Tree'); ?>
<?php $__env->startSection('heading', 'My Tree'); ?>
<?php $__env->startSection('content'); ?>
<div class="bg-surface border border-border rounded-2xl p-6 text-center shadow-sm">
    <div class="inline-flex flex-col items-center gap-1 rounded-2xl bg-primary text-white px-6 py-4 mb-6">
        <span class="text-xs opacity-80">You</span>
        <strong class="text-lg">#<?php echo e($user->id); ?> <?php echo e($user->name); ?></strong>
        <span class="text-sm opacity-90"><?php echo e($user->package->name ?? 'No package'); ?></span>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-3xl mx-auto text-left">
        <div class="bg-subtle border border-border rounded-2xl p-4">
            <h3 class="font-semibold text-heading mb-2">Left</h3>
            <?php if($left): ?>
                <p class="text-sm text-text mb-2"><strong>#<?php echo e($left->id); ?></strong> <?php echo e($left->name); ?></p>
                <a class="text-sm font-medium text-primary" href="<?php echo e(route('customer.tree.show', $left->id)); ?>">Open tree →</a>
            <?php else: ?>
                <p class="text-sm text-muted mb-2">Empty seat</p>
                <input type="text" readonly value="<?php echo e($leftLink); ?>" class="w-full text-xs h-10 px-3 rounded-xl bg-surface border border-border" onclick="this.select()">
            <?php endif; ?>
        </div>
        <div class="bg-subtle border border-border rounded-2xl p-4">
            <h3 class="font-semibold text-heading mb-2">Right</h3>
            <?php if($right): ?>
                <p class="text-sm text-text mb-2"><strong>#<?php echo e($right->id); ?></strong> <?php echo e($right->name); ?></p>
                <a class="text-sm font-medium text-primary" href="<?php echo e(route('customer.tree.show', $right->id)); ?>">Open tree →</a>
            <?php else: ?>
                <p class="text-sm text-muted mb-2">Empty seat</p>
                <input type="text" readonly value="<?php echo e($rightLink); ?>" class="w-full text-xs h-10 px-3 rounded-xl bg-surface border border-border" onclick="this.select()">
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/customer/tree/index.blade.php ENDPATH**/ ?>