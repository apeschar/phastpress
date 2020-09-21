<?php
namespace Kibo\PhastPlugins\PhastPress\Compat;

class MonsterInsights {
    public function setup() {
        // Don't delay Monsterinsights analytics script.
        add_filter('monsterinsights_tracking_analytics_script_attributes', function ($attrs) {
            if (is_array($attrs)) {
                $attrs['data-phast-no-defer'] = '';
            }
            return $attrs;
        });
    }
}
