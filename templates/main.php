<div class="wrap">
    <h1><?php _e('PhastPress', 'phastpress');?></h1>
    <hr class="wp-header-end">

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
