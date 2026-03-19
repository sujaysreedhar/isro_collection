<?php
require_once 'config/config.php';
// Trigger activation hooks manually since we just updated the settings table bypassing the UI
HookRegistry::doAction('activate_trade_manager');
echo "Activation hooks triggered.";
