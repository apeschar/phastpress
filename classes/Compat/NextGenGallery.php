<?php
namespace Kibo\PhastPlugins\PhastPress\Compat;

use ReflectionClass;
use ReflectionException;

class NextGenGallery {
    public function setup($deploymentPriority) {
        try {
            $class = new ReflectionClass('C_Photocrati_Resource_Manager');
            $resourceManager = $class->getStaticPropertyValue('instance');
        } catch (ReflectionException $e) {
            return;
        }

        if ($resourceManager
            && ($resourceManagerPriority = has_filter('init', [$resourceManager, 'start_buffer'])) !== false
            && $resourceManagerPriority < $deploymentPriority
        ) {
            Log::add(
                'nextgen-gallery',
                'moving NextGEN Gallery resource manager hook after PhastPress deployment ' .
                'to support WP Super Cache late init'
            );
            remove_action('init', [$resourceManager, 'start_buffer'], $resourceManagerPriority);
            add_action('init', [$resourceManager, 'start_buffer'], $deploymentPriority);
        }
    }
}
