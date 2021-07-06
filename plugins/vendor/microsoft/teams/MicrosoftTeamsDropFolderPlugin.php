<?php

/**
 * @package plugins.MicrosoftTeamsDropFolder
 */
class MicrosoftTeamsDropFolderPlugin extends KalturaPlugin implements IKalturaPending, IKalturaEnumerator, IKalturaAdminConsolePages, IKalturaObjectLoader, IKalturaPermissions
{

	const PLUGIN_NAME = 'MicrosoftTeamsDropFolder';
	/**
	 * @inheritDoc
	 */
	public static function getApplicationPages()
	{
		// TODO: Implement getApplicationPages() method.
	}

	/**
	 * @inheritDoc
	 */
	public static function getEnums($baseEnumName = null)
	{
		if (!$baseEnumName)
		{
			return array('MicrosoftTeamsDropFolderType', 'MicrosoftTeamsVendorType');
		}

		switch ($baseEnumName)
		{
			case 'DropFolderType':
				return array('MicrosoftTeamsDropFolderType');
				break;
			case 'VendorTypeEnum':
				return array('MicrosoftTeamsVendorType');
				break;
		}

		return array();
	}

	/**
	 * @inheritDoc
	 */
	public static function loadObject($baseClass, $enumValue, array $constructorArgs = null)
	{
		switch ($baseClass) {
			case 'KDropFolderEngine':
				if ($enumValue == KalturaDropFolderType::MS_TEAMS) {
					return new KMicrosoftTeamsDropFolderEngine();
				}
				break;
			case ('KalturaDropFolder'):
				if ($enumValue == self::getDropFolderTypeCoreValue(MicrosoftTeamsDropFolderType::MS_TEAMS)) {
					return new KalturaMicrosoftTeamsDropFolder();
				}
				break;
			case ('KalturaDropFolderFile'):
				if ($enumValue == self::getDropFolderTypeCoreValue(MicrosoftTeamsDropFolderType::MS_TEAMS)) {
					return new KalturaMicrosoftTeamsDropFolderFile();
				}
				break;
			case 'kDropFolderContentProcessorJobData':
				if ($enumValue == self::getDropFolderTypeCoreValue(MicrosoftTeamsDropFolderType::MS_TEAMS)) {
					return new kDropFolderContentProcessorJobData();
				}
				break;
			case 'KalturaJobData':
				$jobSubType = $constructorArgs["coreJobSubType"];
				if ($enumValue == DropFolderPlugin::getApiValue(DropFolderBatchType::DROP_FOLDER_CONTENT_PROCESSOR) &&
					$jobSubType == self::getDropFolderTypeCoreValue(MicrosoftTeamsDropFolderType::MS_TEAMS)) {
					return new KalturaDropFolderContentProcessorJobData();
				}
				break;
			case 'KalturaIntegrationSetting':
				if ($enumValue == self::getVendorTypeCoreValue(MicrosoftTeamsVendorType::MS_TEAMS)) {
					return new KalturaMicrosoftTeamsIntegrationSetting();
				}
				break;
		}
	}

	/**
	 * @inheritDoc
	 */
	public static function getObjectClass($baseClass, $enumValue)
	{
		// TODO: Implement getObjectClass() method.
	}

	/**
	 * @inheritDoc
	 */
	public static function dependsOn()
	{
		$dropFolderDependency = new KalturaDependency(DropFolderPlugin::PLUGIN_NAME);
		$vendorDependency = new KalturaDependency(VendorPlugin::PLUGIN_NAME);

		return array($dropFolderDependency, $vendorDependency);
	}

	/**
	 * @inheritDoc
	 */
	public static function getPluginName()
	{
		return self::PLUGIN_NAME;
	}

	/**
	 * @param $valueName
	 * @return string external API value of dynamic enum.
	 */
	public static function getApiValue($valueName)
	{
		return self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
	}

	public static function getDropFolderTypeCoreValue($valueName)
	{
		$value = self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
		return kPluginableEnumsManager::apiToCore('DropFolderType', $value);
	}

	public static function getVendorTypeCoreValue($valueName)
	{
		$value = self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
		return kPluginableEnumsManager::apiToCore('VendorTypeEnum', $value);
	}

	/**
	 * @inheritDoc
	 */
	public static function isAllowedPartner($partnerId)
	{
		if (in_array($partnerId, array(Partner::ADMIN_CONSOLE_PARTNER_ID, Partner::BATCH_PARTNER_ID)))
			return true;

		$partner = PartnerPeer::retrieveByPK($partnerId);
		return $partner->getPluginEnabled(self::PLUGIN_NAME);
	}
}