<?php

/**
 * Enable Reach internal processing queue feature.
 * @package plugins.reachInternal
 */
class ReachInternalPlugin extends KalturaPlugin implements  IKalturaPending, IKalturaEnumerator
{
    const PLUGIN_NAME = 'ReachInternal';

    /**
     * @inheritDoc
     */
    public static function dependsOn()
    {
        $reachPluginDependency = new KalturaDependency(ReachPlugin::getPluginName());
        $metadataPluginDependency = new KalturaDependency(MetadataPlugin::getPluginName());

        return array($reachPluginDependency);
    }

    /**
     * @inheritDoc
     */
    public static function getPluginName()
    {
        return self::PLUGIN_NAME;
    }

    /**
     * @inheritDoc
     */
    public static function getEnums($baseEnumName = null)
    {
        if(is_null($baseEnumName))
            return array('HelloWorldVendorEngineType', 'ReachInternalQueueHandlerBatchJobType');

        if($baseEnumName == 'ReachVendorEngineType')
            return array('HelloWorldVendorEngineType');

        if($baseEnumName == 'BatchJobType')
            return array('ReachInternalQueueHandlerBatchJobType');

        return array();
    }

    public static function getBatchJobObjectTypeCoreValue($valueName)
    {
        return self::getCoreValue('BatchJobObjectType', $valueName);
    }

    /**
     * @return int id of dynamic enum in the DB.
     */
    public static function getCoreValue($type, $valueName)
    {
        $value = self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
        return kPluginableEnumsManager::apiToCore($type, $value);
    }

    /**
     * @return int id of dynamic enum in the DB.
     */
    public static function getVendorEngineTypeCoreValue($valueName)
    {
        $value = self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
        return kPluginableEnumsManager::apiToCore('VendorEngineType', $value);
    }

    /**
     * @return string external API value of dynamic enum.
     */
    public static function getApiValue($valueName)
    {
        return self::getPluginName() . IKalturaEnumerator::PLUGIN_VALUE_DELIMITER . $valueName;
    }

}