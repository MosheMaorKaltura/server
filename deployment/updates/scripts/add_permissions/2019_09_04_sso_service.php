<?php
/**
 * @package deployment
 * @subpackage orion.roles_and_permissions
 */
$script = realpath(dirname(__FILE__) . '/../../../../') . '/alpha/scripts/utils/permissions/addPermissionsAndItems.php';

$config = realpath(dirname(__FILE__)) . '/../../../permissions/service.sso.sso.ini';
echo "Running php $script $config";
passthru("php $script $config");
