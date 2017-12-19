<div id="col-left">
    <div class="col-wrap">

        <ol class="phastpress-filters-list form-wrap">

            <li class="phastpress-filter-item phastpress-selected">
                <div class="phastpress-filter-name">
                    \Kibo\Phast\Filters\HTML\ImagesOptimization
                </div>
                <div class="phast-filter-enabled">
                    <label>
                        <input type="checkbox" value="1" checked>
                        <?php _e('Enabled', 'phastpress');?>
                    </label>
                </div>
            </li>

            <li class="phastpress-filter-item phastpress-error">
                <div class="phastpress-filter-name">
                    \Kibo\Phast\Filters\HTML\ScriptsRearrangement
                </div>
                <div class="phast-filter-enabled form-field">
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
                    \Kibo\Phast\Filters\HTML\DeferIFrame
                </div>
                <div class="phast-filter-enabled">
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
                <div class="phast-filter-enabled">
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
    <div class="col-wrap">

        <div class="phastpress-filter-error-message">
            Executable not found: /usr/bin/pngquant
        </div>

        <div class="phastpress-filter-settings form-wrap">

            <div class="phastpress-settings-form-title">
                <? _e('Settings for: ', 'phastpress');?>
                <div class="phastpress-settings-form-title-filtername">
                    \Kibo\Phast\Images\Resize
                </div>
            </div>

            <div class="phastpress-settings-form">
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
