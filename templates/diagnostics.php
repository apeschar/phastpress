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

<style>


    .phastpress-report {
        width: 90%;
        margin-top: 20px;
    }

    .phastpress-report tr:first-child th {
        font-size: 1.5em;
        padding-bottom: 20px;
    }

    .phastpress-report td, .phastpress-report th {
        padding: 5px;
    }

    .phastpress-report thead tr:last-child,
    .phastpress-report tbody tr:nth-child(even) {
        background: lightgray;
    }

    .phastpress-report tbody td:last-child {
        text-align: left;
    }

    .phastpress-on {
        color: darkgreen;
    }

    .phastpress-off {
        color: maroon;
    }

    .phastpress-report tbody tr.phastpress-unavailable {
        background: lightpink;
    }

    .phastpress-report tbody tr:hover {
        background: #e5e5e5;
    }



</style>
