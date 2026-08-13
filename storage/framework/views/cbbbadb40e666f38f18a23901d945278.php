<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Admin'); ?> — <?php echo e(config('citymax.name')); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e(asset('branding/favicon-32.png')); ?>">
    <link href="<?php echo e(asset('admin-assets/css/bootstrap.min.css')); ?>" rel="stylesheet">
    <link href="<?php echo e(asset('admin-assets/font-awesome/css/font-awesome.min.css')); ?>" rel="stylesheet">
    <link href="<?php echo e(asset('admin-assets/css/animate.css')); ?>" rel="stylesheet">
    <link href="<?php echo e(asset('admin-assets/css/style.css')); ?>" rel="stylesheet">
    <link href="<?php echo e(asset('admin-assets/css/citymax-admin.css')); ?>" rel="stylesheet">
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body>
<div id="wrapper">
    <nav class="navbar-default navbar-static-side" role="navigation">
        <div class="sidebar-collapse">
            <ul class="nav metismenu" id="side-menu">
                <li class="nav-header">
                    <div class="dropdown profile-element">
                        <img alt="logo" class="m-b-md" src="<?php echo e(asset('branding/logo-light-header.png')); ?>" style="max-height:40px">
                        <span class="clear">
                            <span class="block m-t-xs"><strong class="font-bold text-white"><?php echo e(auth()->user()->name); ?></strong></span>
                            <span class="text-muted text-xs block">Administrator</span>
                        </span>
                    </div>
                </li>
                <li class="<?php echo e(request()->routeIs('admin.dashboard') ? 'active' : ''); ?>">
                    <a href="<?php echo e(route('admin.dashboard')); ?>"><i class="fa fa-th-large"></i> <span class="nav-label">Dashboard</span></a>
                </li>
                <li class="<?php echo e(request()->routeIs('admin.users.index') ? 'active' : ''); ?>">
                    <a href="<?php echo e(route('admin.users.index')); ?>"><i class="fa fa-users"></i> <span class="nav-label">Active Users List</span></a>
                </li>
                <li class="<?php echo e(request()->routeIs('admin.users.create') ? 'active' : ''); ?>">
                    <a href="<?php echo e(route('admin.users.create')); ?>"><i class="fa fa-user-plus"></i> <span class="nav-label">Add New User</span></a>
                </li>
                <li class="<?php echo e(request()->routeIs('admin.payments.*') ? 'active' : ''); ?>">
                    <a href="<?php echo e(route('admin.payments.index')); ?>"><i class="fa fa-credit-card"></i> <span class="nav-label">Payments</span></a>
                </li>
                <li class="<?php echo e(request()->routeIs('admin.income.daily') ? 'active' : ''); ?>">
                    <a href="<?php echo e(route('admin.income.daily')); ?>"><i class="fa fa-money"></i> <span class="nav-label">Daily Paid Income</span></a>
                </li>
                <li class="<?php echo e(request()->routeIs('admin.power.index') ? 'active' : ''); ?>">
                    <a href="<?php echo e(route('admin.power.index')); ?>"><i class="fa fa-lock"></i> <span class="nav-label">Power ID</span></a>
                </li>
                <li class="<?php echo e(request()->routeIs('admin.power.activate') ? 'active' : ''); ?>">
                    <a href="<?php echo e(route('admin.power.activate')); ?>"><i class="fa fa-user"></i> <span class="nav-label">Activate Power ID</span></a>
                </li>
                <li class="<?php echo e(request()->routeIs('admin.withdrawals.*') ? 'active' : ''); ?>">
                    <a href="#"><i class="fa fa-history"></i> <span class="nav-label">Withdrawal History</span> <span class="fa arrow"></span></a>
                    <ul class="nav nav-second-level collapse">
                        <li><a href="<?php echo e(route('admin.withdrawals.index', 'pending')); ?>">Pending</a></li>
                        <li><a href="<?php echo e(route('admin.withdrawals.index', 'processing')); ?>">Processing</a></li>
                        <li><a href="<?php echo e(route('admin.withdrawals.index', 'completed')); ?>">Completed</a></li>
                        <li><a href="<?php echo e(route('admin.withdrawals.index', 'declined')); ?>">Declined</a></li>
                    </ul>
                </li>
                <li class="<?php echo e(request()->routeIs('admin.business.*') ? 'active' : ''); ?>">
                    <a href="#"><i class="fa fa-bar-chart"></i> <span class="nav-label">Business Details</span> <span class="fa arrow"></span></a>
                    <ul class="nav nav-second-level collapse">
                        <li><a href="<?php echo e(route('admin.business.all')); ?>">All Users Business</a></li>
                        <li><a href="<?php echo e(route('admin.business.offer')); ?>">Offer Business</a></li>
                    </ul>
                </li>
                <li class="<?php echo e(request()->routeIs('admin.renewals.*') ? 'active' : ''); ?>">
                    <a href="#"><i class="fa fa-refresh"></i> <span class="nav-label">Manage Renewals</span> <span class="fa arrow"></span></a>
                    <ul class="nav nav-second-level collapse">
                        <li><a href="<?php echo e(route('admin.renewals.active')); ?>">Active Users</a></li>
                        <li><a href="<?php echo e(route('admin.renewals.renewed')); ?>">Renewed Users</a></li>
                        <li><a href="<?php echo e(route('admin.renewals.expired')); ?>">Expired Users</a></li>
                    </ul>
                </li>
                <li class="<?php echo e(request()->routeIs('admin.password.*') ? 'active' : ''); ?>">
                    <a href="<?php echo e(route('admin.password.edit')); ?>"><i class="fa fa-key"></i> <span class="nav-label">Change Password</span></a>
                </li>
            </ul>
        </div>
    </nav>

    <div id="page-wrapper" class="gray-bg">
        <div class="row border-bottom">
            <nav class="navbar navbar-static-top white-bg" role="navigation" style="margin-bottom:0">
                <div class="navbar-header">
                    <a class="navbar-minimalize minimalize-styl-2 btn btn-primary" href="#"><i class="fa fa-bars"></i></a>
                </div>
                <ul class="nav navbar-top-links navbar-right">
                    <li>
                        <form method="POST" action="<?php echo e(route('admin.logout')); ?>" class="m-t-sm m-r-md">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-link"><i class="fa fa-sign-out"></i> Log out</button>
                        </form>
                    </li>
                </ul>
            </nav>
        </div>

        <div class="wrapper wrapper-content animated fadeInRight">
            <?php echo $__env->make('partials.alerts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->yieldContent('content'); ?>
        </div>
    </div>
</div>

<script src="<?php echo e(asset('admin-assets/js/jquery-3.1.1.min.js')); ?>"></script>
<script src="<?php echo e(asset('admin-assets/js/popper.min.js')); ?>"></script>
<script src="<?php echo e(asset('admin-assets/js/bootstrap.js')); ?>"></script>
<script src="<?php echo e(asset('admin-assets/js/plugins/metisMenu/jquery.metisMenu.js')); ?>"></script>
<script src="<?php echo e(asset('admin-assets/js/plugins/slimscroll/jquery.slimscroll.min.js')); ?>"></script>
<script src="<?php echo e(asset('admin-assets/js/custom.js')); ?>"></script>
<script src="<?php echo e(asset('admin-assets/js/plugins/pace/pace.min.js')); ?>"></script>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/layouts/admin.blade.php ENDPATH**/ ?>