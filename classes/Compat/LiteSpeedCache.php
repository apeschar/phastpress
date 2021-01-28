<?php
namespace Kibo\PhastPlugins\PhastPress\Compat;

class LiteSpeedCache {
    /** @return ?int */
    public function getHookPriority() {
        try {
            $cls = new \ReflectionClass(\LiteSpeed\Core::class);
            $prop = $cls->getProperty('_instance');
            $prop->setAccessible(true);
            $instance = $prop->getValue();
        } catch (\ReflectionException $e) {
            return null;
        }

        if (!$instance) {
            return null;
        }

        $priority = has_filter('after_setup_theme', [$instance, 'init']);

        if ($priority === false) {
            return null;
        }

        return (int) $priority;
    }
}
