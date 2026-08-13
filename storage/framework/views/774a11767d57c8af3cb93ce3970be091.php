<?php $__env->startSection('title', 'Withdraw'); ?>
<?php $__env->startSection('heading', 'Withdrawal Now'); ?>
<?php $__env->startSection('content'); ?>
<div class="grid grid-cols-1 lg:grid-cols-5 gap-4 max-w-5xl">
    <section class="cmc-hero-wallet lg:col-span-2 p-6">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs uppercase tracking-wider text-white/70">Wallet balance</p>
            <span class="inline-flex w-10 h-10 items-center justify-center rounded-xl bg-white/15 text-xl"><i class="ph ph-wallet"></i></span>
        </div>
        <p class="text-4xl font-bold mt-2">$<?php echo e(number_format((float) $user->wallet_balance, 2)); ?></p>
        <div class="mt-5 space-y-2 text-sm text-white/80">
            <p class="inline-flex items-center gap-2"><i class="ph ph-shield-check"></i> Secure USDT payout</p>
            <p class="inline-flex items-center gap-2"><i class="ph ph-clock"></i> Processed after review</p>
            <p class="inline-flex items-center gap-2"><i class="ph ph-currency-circle-dollar"></i> ERC-20 / BEP-20 only</p>
        </div>
    </section>

    <section class="cmc-panel lg:col-span-3 p-6">
        <div class="flex items-center gap-3 mb-5">
            <span class="cmc-stat-icon"><i class="ph ph-hand-withdraw"></i></span>
            <div>
                <h2 class="text-base font-semibold text-heading m-0">New withdrawal</h2>
                <p class="text-xs text-muted m-0">Minimum $<?php echo e(number_format((float) $minimum, 2)); ?> · Fee $<?php echo e(number_format((float) $fee, 2)); ?></p>
            </div>
        </div>
        <form method="POST" action="<?php echo e(route('customer.withdrawals.store')); ?>" class="space-y-4">
            <?php echo csrf_field(); ?>
            <div>
                <label class="block text-xs font-medium text-text-secondary mb-1.5">Amount (USD)</label>
                <div class="relative">
                    <i class="ph ph-currency-dollar absolute left-3 top-1/2 -translate-y-1/2 text-muted"></i>
                    <input type="number" step="0.01" name="amount" value="<?php echo e(old('amount')); ?>" required class="w-full h-11 pl-9 pr-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-text-secondary mb-1.5">USDT wallet address</label>
                <div class="relative">
                    <i class="ph ph-wallet absolute left-3 top-1/2 -translate-y-1/2 text-muted"></i>
                    <input type="text" name="wallet_address" value="<?php echo e(old('wallet_address')); ?>" required placeholder="0x…" class="w-full h-11 pl-9 pr-3 rounded-xl bg-subtle border border-border text-sm text-text focus:outline-none focus:border-primary font-mono">
                </div>
                <p class="text-[11px] text-muted mt-1.5"><code>0x</code> + 40 hex characters (ERC-20 / BEP-20)</p>
            </div>
            <button type="submit" class="inline-flex items-center justify-center gap-1.5 h-11 px-5 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">
                Withdraw Now <i class="ph ph-arrow-right"></i>
            </button>
        </form>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/customer/withdrawals/create.blade.php ENDPATH**/ ?>