<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Admin Login — <?php echo e(config('citymax.name')); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e(asset('branding/favicon-32.png')); ?>">
    <style>
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(160deg, #06101c, #0a1628 50%, #0d2138);
            color: #e8eef7;
        }
        .box {
            width: min(400px, 92vw); background: rgba(255,255,255,0.04);
            border: 1px solid rgba(0,163,255,0.25); border-radius: 12px; padding: 2rem 1.5rem;
            backdrop-filter: blur(8px);
        }
        .logo { display: block; margin: 0 auto 1.25rem; height: 48px; }
        h1 { text-align: center; font-size: 1.25rem; margin: 0 0 0.35rem; }
        .tag { text-align: center; color: #00a3ff; font-size: 0.75rem; letter-spacing: 0.2em; margin-bottom: 1.5rem; }
        label { display: block; font-size: 0.85rem; margin-bottom: 0.35rem; }
        input {
            width: 100%; box-sizing: border-box; padding: 0.65rem 0.75rem; margin-bottom: 1rem;
            border-radius: 6px; border: 1px solid #334155; background: #0b1524; color: #fff;
        }
        button {
            width: 100%; padding: 0.75rem; border: none; border-radius: 6px;
            background: #00a3ff; color: #041018; font-weight: 700; cursor: pointer;
        }
        .back { display: block; text-align: center; margin-top: 1rem; color: #8fa3bf; font-size: 0.85rem; text-decoration: none; }
        .alert-danger { background: rgba(180,35,24,0.2); color: #fecaca; padding: 0.65rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="box">
    <img class="logo" src="<?php echo e(asset('branding/logo-light-auth.png')); ?>" alt="<?php echo e(config('citymax.name')); ?>">
    <h1>Admin Login</h1>
    <p class="tag"><?php echo e(config('citymax.tagline')); ?></p>
    <?php echo $__env->make('partials.alerts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <form method="POST" action="<?php echo e(route('admin.login.submit')); ?>">
        <?php echo csrf_field(); ?>
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus autocomplete="username">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        <label style="display:flex;align-items:center;gap:0.4rem;font-weight:400;margin-bottom:1rem;">
            <input type="checkbox" name="remember" value="1" style="width:auto;margin:0;"> Remember me
        </label>
        <button type="submit">Sign in</button>
    </form>
    <a class="back" href="<?php echo e(route('landing')); ?>">← Back to home</a>
</div>
</body>
</html>
<?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/admin/auth/login.blade.php ENDPATH**/ ?>