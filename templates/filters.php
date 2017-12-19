<div id="col-left">
    <div class="col-wrap phastpress-col">

        <ol class="phastpress-filters-list form-wrap">

            <li class="phastpress-filter-item phastpress-selected">
                <div class="phastpress-filter-name">
                    \Kibo\Phast\Filters\HTML\DeferScripts
                </div>
                <div class="phastpress-filter-enabled form-field">
                    <label>
                        <input type="checkbox" value="1" checked>
                        <?php _e('Enabled', 'phastpress');?>
                    </label>
                </div>
            </li>

            <li class="phastpress-filter-item phastpress-error">
                <div class="phastpress-filter-name">
                    \Kibo\Phast\Filters\HTML\OptimizeCss
                </div>
                <div class="phastpress-filter-enabled form-field">
                    <label>
                        <input type="checkbox" value="1">
                        <?php _e('Enabled', 'phastpress');?>
                    </label>
                </div>
                <div class="phastpress-filter-error">
                    <?php _e('There is a problem with this filter', 'phastpress');?>
                </div>
            </li>

            <li class="phastpress-filter-item">
                <div class="phastpress-filter-name">
                    \Kibo\Phast\Filters\HTML\DefferIFrame
                </div>
                <div class="phastpress-filter-enabled form-field">
                    <label>
                        <input type="checkbox" value="1" checked disabled>
                        <?php _e('Enabled', 'phastpress');?>
                    </label>
                    <p>
                        <?php _e('(the filter\'s enabled state is tied to a switch)');?>
                    </p>
                </div>
            </li>

            <li class="phastpress-filter-item phastpress-selected phastpress-error">
                <div class="phastpress-filter-name">
                    \Kibo\Phast\Filters\HTML\CSSInline
                </div>
                <div class="phastpress-filter-enabled form-field">
                    <label>
                        <input type="checkbox" value="1">
                        <?php _e('Enabled', 'phastpress');?>
                    </label>
                </div>
                <div class="phastpress-filter-error">
                    <?php _e('There is a problem with this filter', 'phastpress');?>
                </div>
            </li>

        </ol>

    </div>
</div>
<div id="col-right">
    <div class="col-wrap phastpress-col">

        <div class="phastpress-filter-error-message">
            Executable not found: /usr/bin/pngquant
        </div>

        <div class="phastpress-settings">

            <div class="phastpress-settings-header">
                <div class="phastpress-settings-form-title">
                    <? _e('Settings for', 'phastpress');?>
                    \Kibo\Phast\Images\Resize
                </div>
            </div>

            <div class="phastpress-settings-description">
                <p>
                    This is like the coolest filter ever.<br>
                    No, seriously, you've gotta try it!
                </p>
            </div>

            <div class="phastpress-settings-form form-wrap">
                <div class="form-field">
                    <label for="max-width">
                        Default max. width
                    </label>
                    <input type="number" value="80" id="max-width">
                    <p>The max. width to which images will be resized unless an attribute is set</p>
                </div>
                <div class="form-field">
                    <label for="max-height">
                        Default max. height
                    </label>
                    <input type="number" value="80" id="max-height">
                    <p>The max. height to which images will be resized unless an attribute is set</p>
                </div>
            </div>

        </div>

    </div>
</div>
