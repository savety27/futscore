<?php

require_once __DIR__ . '/../admin/player/add_helpers.php';
require_once __DIR__ . '/../admin/player/edit_helpers.php';
require_once __DIR__ . '/../admin/player/edit_service.php';
require_once __DIR__ . '/../api/verify_identity_helpers.php';

if (!isset($db)) {
    $db = new class {
        public function getConnection()
        {
            return null;
        }
    };
}

require_once __DIR__ . '/../includes/functions.php';
