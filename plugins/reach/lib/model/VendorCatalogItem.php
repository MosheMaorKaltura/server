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
class VendorCatalogItem extends BaseVendorCatalogItem implements IRelatedObject 
{

	/**
	 * Initializes internal state of VendorCatalogItem object.
	 * @see        parent::__construct()
	 */
	public function __construct()
	{
		// Make sure that parent constructor is always invoked, since that
		// is where any default values for this object are set.
		parent::__construct();
		$this->applyDefaultValues();
	}
	
	/**
	 * Applies default values to this object.
	 * This method should be called from the object's constructor (or equivalent initialization method).
	 * @see __construct()
	 */
	public function applyDefaultValues()
	{
	}
	
	const CUSTOM_DATA_PRICING = "pricing";
	const CUSTOM_DATA_BULK_UPLOAD_ID = 'bulkUploadId';
	
	public function setPricing($pricing)
	{
		$this->putInCustomData(self::CUSTOM_DATA_PRICING, serialize($pricing));
	}
	
	/**
	 * @return kCatalogItemPricing
	 */
	public function getPricing()
	{
		$pricing = $this->getFromCustomData(self::CUSTOM_DATA_PRICING);
		
		if($pricing)
			$pricing = unserialize($pricing);
		
		return $pricing;
	}

	public function setBulkUploadId($bulkUploadId)
	{
		$this->putInCustomData(self::CUSTOM_DATA_BULK_UPLOAD_ID ,$bulkUploadId);
	}

	public function getBulkUploadId()
	{
		return $this->getFromCustomData(self::CUSTOM_DATA_BULK_UPLOAD_ID);
	}
	
	public function getPartnerId()
	{
		return 0;
	}
	
	public function getKsExpiry()
	{
		$ksExpiry = $this->getTurnAroundTime() * 2;
		
		//Minimum KS expiry should be set 7 days
		return max($ksExpiry, dateUtils::DAY * 7);
	}
	
	public function calculatePriceForEntry(entry $entry)
	{
		return call_user_func($this->getPricing()->getPriceFunction(), $entry, $this->getPricing()->getPricePerUnit());
	}
	
	public function getTaskVersion($entryId, $jobData = null)
	{
		$sourceFlavor = assetPeer::retrieveOriginalByEntryId($entryId);
		return $sourceFlavor != null ? $sourceFlavor->getVersion() : 0;
	}

	public static function translateServiceFeatureEnum($catalogItemId)
	{
		$vendorCatalogItem = VendorCatalogItemPeer::retrieveByPK($catalogItemId);
		if (!$vendorCatalogItem)
		{
			return "";
		}

		switch ($vendorCatalogItem->getServiceFeature())
		{
			case VendorServiceFeature::CAPTIONS:
				$serviceFeatureName = "captions";
				break;

			case VendorServiceFeature::TRANSLATION:
				$serviceFeatureName = "translation";
				break;

			case VendorServiceFeature::ALIGNMENT:
				$serviceFeatureName = "alignment";
				break;

			case VendorServiceFeature::AUDIO_DESCRIPTION:
				$serviceFeatureName = "audio description";
				break;

			case VendorServiceFeature::CHAPTERING:
				$serviceFeatureName = "chaptering";
				break;

			default:
				$serviceFeatureName = "";
		}

		return $serviceFeatureName;
	}

} // VendorCatalogItem
