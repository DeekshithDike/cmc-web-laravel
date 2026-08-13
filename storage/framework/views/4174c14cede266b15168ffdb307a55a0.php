<?php $__env->startComponent('mail::message'); ?>
# Your <?php echo new \Illuminate\Support\EncodedHtmlString(config('citymax.name')); ?> account is active

Save these login details. They are also shown once on the credentials page after payment.

**Login ID:** <?php echo new \Illuminate\Support\EncodedHtmlString($loginId); ?>


**Password:** <?php echo new \Illuminate\Support\EncodedHtmlString($password); ?>


<?php $__env->startComponent('mail::button', ['url' => route('customer.login')]); ?>
Customer Login
<?php echo $__env->renderComponent(); ?>

Thanks,<br>
<?php echo new \Illuminate\Support\EncodedHtmlString(config('citymax.name')); ?>

<?php echo $__env->renderComponent(); ?>
<?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/emails/member-credentials.blade.php ENDPATH**/ ?>