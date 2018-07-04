<?php

namespace Kibo\PhastPlugins\PhastPress;

use Kibo\PhastPlugins\SDK\Security\PhastUser;

class PhastUserImplementation implements PhastUser {

    public function mayModifySettings() {
        return current_user_can('manage_options');
    }

    public function seesPreviewMode() {
        return $this->mayModifySettings();
    }

}
