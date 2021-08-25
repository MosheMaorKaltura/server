<?php


/**
 * Skeleton subclass for representing a row from the 'vendor_catalog_item' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package plugins.reach
 * @subpackage model
 */
class VendorTranslationCatalogItem extends VendorCaptionsCatalogItem 
{
	public function applyDefaultValues()
	{
		$this->setServiceFeature(VendorServiceFeature::TRANSLATION);
	}

    /**
     * @param $object
     * @return kTranslationVendorTaskData|null
     */
    public function getTaskJobData($object)
    {
        if($object instanceof CaptionAsset)
        {
            $taskJobData = new kTranslationVendorTaskData();
            $taskJobData->captionAssetId = $object->getId();
            return $taskJobData;
        }

        return null;
    }

} // VendorTranslationCatalogItem
