<div class="wrap">
    <h1><?php _e('PhastPress', 'phastpress');?></h1>
    <hr class="wp-header-end">

    <section class="phastpress-settings">

        <h2 class="phastpress-settings-title">
            <? _e('PhastPress General', 'phastpress');?>
        </h2>

        <div class="phastpress-settings-setting">
            <div class="phastpress-settings-setting-name">
                <?php _e('Enabling PhastPress', 'phastpress');?>
            </div>
            <div class="phastpress-settings-setting-value">
                <label>
                    <input type="radio" name="phastpress-enabled" value="on">
                    <?php _e('On', 'phastpress');?>
                </label>
                <label>
                    <input type="radio" name="phastpress-enabled" value="admin">
                    <?php _e('On for admins', 'phastpress');?>phastpress
                </label>
                <label>
                    <input type="radio" name="phastpress-enabled" value="off">
                    <?php _e('Off', 'phastpress');?>
                </label>
            </div>
            <div class="phastpress-settings-setting-description">
                <?php _e('Description of PhastPress and info about the three options', 'phastpress');?>
            </div>
        </div>

        <div class="phastpress-settings-setting">
            <div class="phastpress-settings-setting-name">
                <?php _e('Let the world know about PhastPress', 'phastpress');?>
            </div>
            <div class="phastpress-settings-setting-value">
                <label>
                    <input type="radio" name="phastpress-footer-link" value="on">
                    <?php _e('On', 'phastpress');?>
                </label>
                <label>
                    <input type="radio" name="phastpress-footer-link" value="off">
                    <?php _e('Off', 'phastpress');?>
                </label>
            </div>
            <div class="phastpress-settings-setting-description">
                <?php _e('Description of the option', 'phastpress');?>
            </div>
        </div>

        <div class="phastpress-settings-problem phastpress-settings-error">
            <?php _e('Can\'t write to cache!' , 'phastpress');?>
        </div>

    </section>

    <section class="phastpress-settings">

        <h2 class="phastpress-settings-title">
            <?php _e('Images', 'phastpress');?>
        </h2>

        <div class="phastpress-settings-setting">
            <div class="phastpress-settings-setting-name">
                <?php _e('Optimize images in tags', 'phastpress');?>
            </div>
            <div class="phastpress-settings-setting-value">
                <label>
                    <input type="radio" name="phastpress-img-optimization-tags" value="on">
                    <?php _e('On', 'phastpress');?>
                </label>
                <label>
                    <input type="radio" name="phastpress-img-optimization-tags" value="off">
                    <?php _e('Off', 'phastpress');?>
                </label>
            </div>
            <div class="phastpress-settings-setting-description">
                Descriptions of the optimization of the images in the tags
            </div>
        </div>

        <div class="phastpress-settings-setting">
            <div class="phastpress-settings-setting-name">
                <?php _e('Optimize images in CSS', 'phastpress');?>
            </div>
            <div class="phastpress-settings-setting-value">
                <label>
                    <input type="radio" name="phastpress-img-optimization-css" value="on">
                    <?php _e('On', 'phastpress');?>
                </label>
                <label>
                    <input type="radio" name="phastpress-img-optimization-css" value="off">
                    <?php _e('Off', 'phastpress');?>
                </label>
            </div>
            <div class="phastpress-settings-setting-description">
                Descriptions of the optimization of the images in the tags
            </div>
        </div>
        <div class="phastpress-settings-problem phastpress-settings-error">
            <?php _e('GD library is missing! Image resizing, compression and recoding won\'t work!' , 'phastpress');?>
        </div>
        <div class="phastpress-settings-problem phastpress-settings-warning">
            <?php _e('Can not find \'pngquant\'! Install \'pngquant\' into /usr/bin directory!' , 'phastpress');?>
        </div>
    </section>

    <section class="phastpress-settings">

        <h2 class="phastpress-settings-title">
            <? _e('CSS &amp; JS', 'phastpress');?>
        </h2>

        <div class="phastpress-settings-setting">
            <div class="phastpress-settings-setting-name">
                <?php _e('Optimize CSS', 'phastpress');?>
            </div>
            <div class="phastpress-settings-setting-value">
                <label>
                    <input type="radio" name="phastpress-css-optimization" value="on">
                    <?php _e('On', 'phastpress');?>
                </label>
                <label>
                    <input type="radio" name="phastpress-css-optimization" value="off">
                    <?php _e('Off', 'phastpress');?>
                </label>
            </div>
            <div class="phastpress-settings-setting-description">
                <?php _e('Description of CSS optimization', 'phastpress');?>
            </div>
        </div>

        <div class="phastpress-settings-setting">
            <div class="phastpress-settings-setting-name">
                <?php _e('Move scripts to end of body', 'phastpress');?>
            </div>
            <div class="phastpress-settings-setting-value">
                <label>
                    <input type="radio" name="phastpress-scripts-rearrangement" value="on">
                    <?php _e('On', 'phastpress');?>
                </label>
                <label>
                    <input type="radio" name="phastpress-scripts-rearrangement" value="off">
                    <?php _e('Off', 'phastpress');?>
                </label>
            </div>
            <div class="phastpress-settings-setting-description">
                <?php _e('Description of JS rearrangement', 'phastpress');?>
            </div>
        </div>

        <div class="phastpress-settings-setting">
            <div class="phastpress-settings-setting-name">
                <?php _e('Load scripts asynchronously', 'phastpress');?>
            </div>
            <div class="phastpress-settings-setting-value">
                <label>
                    <input type="radio" name="phastpress-scripts-defer" value="on">
                    <?php _e('On', 'phastpress');?>
                </label>
                <label>
                    <input type="radio" name="phastpress-scripts-defer" value="off">
                    <?php _e('Off', 'phastpress');?>
                </label>
            </div>
            <div class="phastpress-settings-setting-description">
                <?php _e('Description of scripts deferring', 'phastpress');?>
            </div>
        </div>

        <div class="phastpress-settings-setting">
            <div class="phastpress-settings-setting-name">
                <?php _e('Cache external scripts', 'phastpress');?>
            </div>
            <div class="phastpress-settings-setting-value">
                <label>
                    <input type="radio" name="phastpress-scripts-proxy" value="on">
                    <?php _e('On', 'phastpress');?>
                </label>
                <label>
                    <input type="radio" name="phastpress-scripts-proxy" value="off">
                    <?php _e('Off', 'phastpress');?>
                </label>
            </div>
            <div class="phastpress-settings-setting-description">
                <?php _e('Description of scripts proxy', 'phastpress');?>
            </div>
        </div>

    </section>

    <div class="phastpress-settings-controls tablenav">
        <div class="alignright actions">
            <button class="button"><?php _e('Use Defaults', 'phastpress');?></button>
            <button class="button" disabled><?php _e('Revert changes', 'phastpress');?></button>
            <button class="button-primary" disabled><?php _e('Save', 'phastpress');?></button>
        </div>
    </div>

</div>
