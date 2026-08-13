<?php if(session('success')): ?>
    <div class="alert alert-success mb-4 rounded-2xl border border-success/30 bg-success/10 text-success px-4 py-3 text-sm" role="alert"><?php echo e(session('success')); ?></div>
<?php endif; ?>
<?php if(session('error')): ?>
    <div class="alert alert-danger mb-4 rounded-2xl border border-danger/30 bg-danger/10 text-danger px-4 py-3 text-sm" role="alert"><?php echo e(session('error')); ?></div>
<?php endif; ?>
<?php if(isset($errors) && $errors->any()): ?>
    <div class="alert alert-danger mb-4 rounded-2xl border border-danger/30 bg-danger/10 text-danger px-4 py-3 text-sm" role="alert">
        <ul class="m-0 pl-4">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </div>
<?php endif; ?>
<?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/partials/alerts.blade.php ENDPATH**/ ?>