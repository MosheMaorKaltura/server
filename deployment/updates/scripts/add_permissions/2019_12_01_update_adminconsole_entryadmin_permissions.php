<?php
/**
 * @package deployment
 * @subpackage Orion.roles_and_permissions
 *
 * Update permission to adminconsole entryadmin
 */

$addPermissionsAndItemsScript = realpath(dirname(__FILE__) . '/../../../../') . '/alpha/scripts/utils/permissions/addPermissionsAndItems.php';
$insertDefaultsScript = realpath(dirname(__FILE__) . '/../../../') . '/base/scripts/insertDefaults.php';

$config = realpath(dirname(__FILE__)) . '/../../../permissions/partner.-2.ini';
passthru("php $addPermissionsAndItemsScript $config");

$config = realpath(dirname(__FILE__)) .'/../../../base/scripts/init_data/03.UserRole.ini';
passthru("php $insertDefaultsScript $config");

$config = realpath(dirname(__FILE__)) . '/../../../permissions/service.adminconsole.entryadmin.ini';
passthru("php $addPermissionsAndItemsScript $config");

