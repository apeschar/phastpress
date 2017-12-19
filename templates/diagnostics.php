<div class="phastpress-report-holder">
    <?php foreach ($groups as $type => $statuses):?>

        <table class="phastpress-report">
            <thead>
            <tr>
                <th colspan="3">
                    <?php echo $type;?>
                </th>
            </tr>
            <tr>
                <th><?php _e('Item', 'phastpress');?></th>
                <th><?php _e('Enabled', 'phastpress');?></th>
                <th><?php _e('Error', 'phastpress');?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($statuses as $status):?>
                <tr <?php if (!$status->isAvailable()) echo 'class="phastpress-unavailable"' ?>>
                    <td>
                        <?php echo $status->getPackage()->getNamespace();?>
                    </td>
                    <td style="text-align: center">
                        <?php if ($status->isEnabled()):?>
                            <span class="phastpress-on">
                                    <?php _e('Yes', 'phastpress');?>
                                </span>
                        <?php else: ?>
                            <span class="phastpress-off">
                                    <?php _e('No', 'phastpress');?>
                                </span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php echo $status->getReason();?>
                    </td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>

    <?php endforeach;?>
</div>

