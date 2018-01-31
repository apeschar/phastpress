<div class="wrap">
    <h1><?php _e('PhastPress', 'phastpress');?></h1>
    <hr class="wp-header-end">

    <?php if (phastpress_get_config()['enabled'] === 'admin'):?>
        <div class="phastpress-settings-problem phastpress-settings-warning">
            <?php _e(
                'PhastPress optimizations will be applied only for logged-in users with the "Administrator" privilege.<br>' .
                'This is for previewing purposes. ' .
                'Select the <i>On</i> setting for <i>PhastPress optimizations</i> below to activate for all users!',
                'phastpress'
            ); ?>
        </div>
    <?php elseif (!phastpress_get_config()['enabled']):?>
        <div class="phastpress-settings-problem phastpress-settings-error">
            <?php _e('PhastPress optimizations are off!', 'phastpress');?>
        </div>
    <?php endif;?>

    <form action="" method="post">

        <?php foreach ($sections as $sectionName => $section):?>
            <section class="phastpress-settings">

                <h2 class="phastpress-settings-title">
                    <?php echo $section['title'];?>
                </h2>

                <?php foreach ($section['settings'] as $setting):?>
                    <div class="phastpress-settings-setting">
                        <div class="phastpress-settings-setting-name">
                            <?php echo $setting['name'];?>
                        </div>
                        <div class="phastpress-settings-setting-value">
                            <?php echo $setting['options'];?>
                        </div>
                        <div class="phastpress-settings-setting-description">
                            <?php echo $setting['description'];?>
                        </div>
                    </div>
                <?php endforeach;?>

                <?php if ($sectionName == 'images'):?>
                    <table class="phastpress-features-report">
                        <thead>
                            <tr>
                                <th colspan="3" class="phastpress-features-report-header">
                                    <?php _e('Image optimization status', 'phastpress');?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th class="phastpress-feature phastpress-header">
                                    <?php _e('Feature', 'phastpress');?>
                                </th>
                                <th class="phastpress-availability">
                                    <?php _e('Available', 'phastpress');?>
                                </th>
                                <th class="phastpress-reason">
                                    <?php _e('Reason', 'phastpress');?>
                                </th>
                            </tr>
                            <?php foreach ($imageFeatures as $feature):?>
                                <tr>
                                    <td class="phastpress-feature"><?php echo $feature['name'];?></td>
                                    <td class="phastpress-availability">
                                        <?php if (isset ($feature['error'])): $hasImageError = true;?>
                                            <span
                                                    class="phastpress-feature-unavailable"
                                                    title="<?php _e('No', 'phastpress')?>"
                                            ></span>
                                        <?php else:?>
                                            <span
                                                    class="phastpress-feature-available"
                                                    title="<?php _e('Yes', 'phastpress');?>"
                                            ></span>
                                        <?php endif;?>
                                    </td>
                                    <?php if (isset ($feature['error'])):?>
                                        <td class="phastpress-reason">
                                            <?php echo htmlspecialchars($feature['error']);?>
                                        </td>
                                    <?php endif;?>
                                </tr>
                            <?php endforeach;?>
                        </tbody>
                        <?php if (isset ($hasImageError)):?>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="phastpress-api-hint">
                                        <?php _e(
                                            'It seems your setup does not allow for full image optimization.<br>' .
                                            'Consider using the Phast Image Optimization API for best results!',
                                            'phastpress'
                                        );?>
                                    </td>
                                </tr>
                            </tfoot>
                        <?php endif;?>
                    </table>
                <?php endif;?>

                <?php if ($sectionName == 'phastpress' && isset ($cacheError)):?>
                    <div class="phastpress-settings-problem phastpress-settings-error">
                        <?php echo $cacheError;?>
                    </div>
                <?php endif;?>

            </section>

        <?php endforeach;?>


        <div class="phastpress-settings-controls tablenav">
            <div class="alignright actions">
                <input
                    type="submit"
                    class="button"
                    name="phastpress-use-defaults"
                    onclick="return window.confirm('<?php _e('Are you sure that you want to restore the defaults?', 'phastpress');?>')"
                    value="<?php _e('Use Defaults', 'phastpress');?>"
                >
                <input
                    type="submit"
                    name="phastpress-settings"
                    class="button-primary"
                    value="<?php _e('Save', 'phastpress');?>"
                >
            </div>
        </div>

        <?php wp_nonce_field(PHASTPRESS_NONCE_NAME);?>
    </form>
</div>
