<?php $__env->startSection('title', 'My Tree'); ?>
<?php $__env->startSection('heading', 'My Tree'); ?>
<?php $__env->startPush('styles'); ?>
<link href="<?php echo e(asset('admin-assets/font-awesome/css/font-awesome.min.css')); ?>" rel="stylesheet">
<style>
    .cmc-tree-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .cmc-tree {
        width: 720px;
        max-width: 100%;
        margin: 0 auto;
        table-layout: fixed;
        border-collapse: collapse;
        text-align: center;
    }
    .cmc-tree td {
        width: 25%;
        vertical-align: top;
        text-align: center;
        border: none !important;
        padding: 1.25rem 0.35rem;
    }
    .cmc-tree-node {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 0.35rem;
        text-decoration: none;
        color: inherit;
        max-width: 100%;
    }
    .cmc-tree-node:hover { opacity: 0.9; }
    .cmc-tree-node .tree-user-icon {
        font-size: 4.75rem;
        line-height: 1;
        display: block;
    }
    .cmc-tree-node p {
        margin: 0;
        font-size: 0.875rem;
        line-height: 1.35;
        word-break: break-word;
    }
    @media (max-width: 640px) {
        .cmc-tree { width: 560px; }
        .cmc-tree-node .tree-user-icon { font-size: 3.75rem; }
    }
</style>
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<?php
    $whatsappInvite = function (int $placementId, string $position) use ($inviteBase, $brand): string {
        $link = $inviteBase.'?placementID='.$placementId.'&position='.$position;
        $text = 'Use my referral link to join '.$brand.'. '.$link;

        return 'https://api.whatsapp.com/send?phone&text='.rawurlencode($text);
    };

    $nodeIconClass = function (?object $node = null, $amount = null): string {
        if ($node === null && $amount === null) {
            return 'text-muted';
        }
        $hasPackage = $node ? filled($node->amount ?? null) : filled($amount);
        $isPower = $node && ! empty($node->is_power_id) && empty($node->is_active);

        return ($hasPackage && ! $isPower) ? 'text-success' : 'text-[#e6a700]';
    };

    $renderMember = function (?object $node) use ($nodeIconClass): string {
        if (! $node) {
            return '';
        }
        $url = route('customer.tree.show', $node->users_id);
        $icon = $nodeIconClass($node);
        $amount = $node->amount
            ? '<br>$ '.number_format((float) $node->amount)
            : '';

        return '<a href="'.e($url).'" class="cmc-tree-node">'
            .'<i class="fa fa-user tree-user-icon '.e($icon).'"></i>'
            .'<p class="text-heading">ID '.e((string) $node->users_id).$amount.'</p>'
            .'</a>';
    };

    $renderAdd = function (int $placementId, string $position) use ($whatsappInvite): string {
        $url = $whatsappInvite($placementId, $position);

        return '<a target="_blank" rel="noopener" href="'.e($url).'" class="cmc-tree-node">'
            .'<i class="fa fa-user tree-user-icon text-muted"></i>'
            .'<p class="text-muted">Add user</p>'
            .'</a>';
    };
?>

<div class="bg-surface border border-border rounded-2xl p-4 sm:p-6 shadow-sm">
    <div class="text-center mb-4">
        <?php if($isOwnTree): ?>
            <h3 class="text-lg font-semibold text-success mt-0 mb-1">Your ID: <?php echo e($parentId); ?></h3>
            <p class="text-sm text-muted"><?php echo e($parentName); ?></p>
        <?php else: ?>
            <h3 class="text-lg font-semibold text-heading mt-0 mb-1">ID: <?php echo e($parentId); ?></h3>
            <p class="text-sm text-muted"><?php echo e($parentName); ?></p>
        <?php endif; ?>
    </div>

    <div class="text-center pb-4">
        <button type="button" onclick="window.history.go(-1); return false;" class="inline-flex items-center h-10 px-4 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-strong transition-colors">Go Back</button>
    </div>

    <div class="cmc-tree-wrap">
        <table class="cmc-tree" align="center">
            
            <tr>
                <td></td>
                <td colspan="2">
                    <div class="cmc-tree-node">
                        <i class="fa fa-user tree-user-icon <?php echo e($nodeIconClass(null, $parentAmount)); ?>"></i>
                        <p class="text-heading">
                            ID <?php echo e($parentId); ?>

                            <?php if($parentAmount): ?>
                                <br>$ <?php echo e(number_format((float) $parentAmount)); ?>

                            <?php endif; ?>
                        </p>
                    </div>
                </td>
                <td></td>
            </tr>

            
            <tr>
                <td colspan="2">
                    <?php if($leftChild1): ?>
                        <?php echo $renderMember($leftChild1); ?>

                    <?php else: ?>
                        <?php echo $renderAdd($parentId, 'left'); ?>

                    <?php endif; ?>
                </td>
                <td colspan="2">
                    <?php if($rightChild1): ?>
                        <?php echo $renderMember($rightChild1); ?>

                    <?php else: ?>
                        <?php echo $renderAdd($parentId, 'right'); ?>

                    <?php endif; ?>
                </td>
            </tr>

            
            <tr>
                <td>
                    <?php if($leftChild2): ?>
                        <?php echo $renderMember($leftChild2); ?>

                    <?php elseif($leftChild1): ?>
                        <?php echo $renderAdd((int) $leftChild1->users_id, 'left'); ?>

                    <?php endif; ?>
                </td>
                <td>
                    <?php if($rightChild2): ?>
                        <?php echo $renderMember($rightChild2); ?>

                    <?php elseif($leftChild1): ?>
                        <?php echo $renderAdd((int) $leftChild1->users_id, 'right'); ?>

                    <?php endif; ?>
                </td>
                <td>
                    <?php if($leftChild3): ?>
                        <?php echo $renderMember($leftChild3); ?>

                    <?php elseif($rightChild1): ?>
                        <?php echo $renderAdd((int) $rightChild1->users_id, 'left'); ?>

                    <?php endif; ?>
                </td>
                <td>
                    <?php if($rightChild3): ?>
                        <?php echo $renderMember($rightChild3); ?>

                    <?php elseif($rightChild1): ?>
                        <?php echo $renderAdd((int) $rightChild1->users_id, 'right'); ?>

                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.customer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Freelancing\Projects\MLM\26\App\cmc-web-laravel\resources\views/customer/tree/index.blade.php ENDPATH**/ ?>