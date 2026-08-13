<?php $__env->startSection('title', 'Active Renewals'); ?>
<?php $__env->startSection('content'); ?>
<div class="ibox">
    <div class="ibox-title"><h5>Manage Renewals — Active Users</h5></div>
    <div class="ibox-content">
        <p class="text-muted">Renew Now is available within <?php echo e($warningDays); ?> days of expiry.</p>
        <table class="table table-striped">
            <thead><tr><th>ID</th><th>Name</th><th>Package</th><th>Expiry</th><th></th></tr></thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php $days = $user->expiry_date ? now()->startOfDay()->diffInDays($user->expiry_date, false) : null; ?>
                <tr>
                    <td><?php echo e($user->id); ?></td>
                    <td><?php echo e($user->name); ?></td>
                    <td><?php echo e($user->package->name ?? '—'); ?></td>
                    <td><?php echo e($user->expiry_date?->format('Y-m-d')); ?> <?php if(!is_null($days) && $days>=0): ?> (<?php echo e($days); ?>d) <?php endif; ?></td>
                    <td>
                        <?php if(!is_null($days) && $days <= $warningDays): ?>
                        <form method="POST" action="<?php echo e(route('admin.renewals.renew', $user->id)); ?>"><?php echo csrf_field(); ?>
                            <button class="btn btn-primary btn-sm">Renew Now</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5">No active members.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php echo e($users->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/admin/renewals/active.blade.php ENDPATH**/ ?>