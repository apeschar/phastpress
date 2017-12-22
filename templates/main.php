<div class="wrap">
    <h1><?php _e('PhastPress', 'phastpress');?></h1>
    <hr class="wp-header-end">

    <?php if (phastpress_get_config()['enabled'] === 'admin'):?>
        <div class="phastpress-settings-problem phastpress-settings-warning">
            <?php _e(
<<<EOT
                PhastPress optimizations will be applied only for logged users with administrator privilege.<br>
                This is for previewing purposes. 
                Select the 'On' setting for 'PhastPress optimizations' bellow to activate for all users!
EOT
, 'phastpress');?>
        </div>
    <?php elseif (!phastpress_get_config()['enabled']):?>
        <div class="phastpress-settings-problem phastpress-settings-error">
            <?php _e('PhastPress optimizations are off!', 'phastpress');?>
        </div>
    <?php endif;?>

    <form action="" method="post">

        <?php foreach ($sections as $section):?>
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

                <?php foreach ($section['errors'] as $error):?>
                    <div class="phastpress-settings-problem phastpress-settings-error">
                        <?php echo $error;?>
                    </div>
                <?php endforeach;?>

                <?php foreach ($section['warnings'] as $warning):?>
                    <div class="phastpress-settings-problem phastpress-settings-warning">
                        <?php echo $warning;?>
                    </div>
                <?php endforeach;?>

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
