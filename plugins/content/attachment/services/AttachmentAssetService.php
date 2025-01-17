<?php

/**
 * Retrieve information and invoke actions on attachment Asset
 *
 * @service attachmentAsset
 * @package plugins.attachment
 * @subpackage api.services
 */
class AttachmentAssetService extends KalturaAssetService
{
	const MAX_FILE_NAME_LENGTH = 255;

	public function initService($serviceId, $serviceName, $actionName)
	{
		parent::initService($serviceId, $serviceName, $actionName);
		
		$this->applyPartnerFilterForClass('conversionProfile2');
		$this->applyPartnerFilterForClass('assetParamsOutput');
		$this->applyPartnerFilterForClass('asset');
		$this->applyPartnerFilterForClass('assetParams');
	}
	
	protected function getEnabledMediaTypes()
	{
		$liveStreamTypes = KalturaPluginManager::getExtendedTypes(entryPeer::OM_CLASS, KalturaEntryType::LIVE_STREAM);
		
		$mediaTypes = array_merge($liveStreamTypes, parent::getEnabledMediaTypes());
		$mediaTypes[] = entryType::AUTOMATIC;
		$mediaTypes = array_unique($mediaTypes);
		return $mediaTypes;
	}
	
	/* (non-PHPdoc)
	 * @see KalturaBaseService::partnerRequired()
	 */
	protected function partnerRequired($actionName)
	{
		if ($actionName === 'serve') 
			return false;

		return parent::partnerRequired($actionName);
	}

	/* (non-PHPdoc)
	 * @see KalturaBaseService::kalturaNetworkAllowed()
	 */
	protected function kalturaNetworkAllowed($actionName)
	{
		if(
			$actionName == 'list' ||
			$actionName == 'getUrl'
			)
		{
			$this->partnerGroup .= ',0';
			return true;
		}

		if($actionName==='get')
		{
			$this->partnerGroup .= ',0';
		}
			
		return parent::kalturaNetworkAllowed($actionName);
	}
	
    /**
     * Add attachment asset
     *
     * @action add
     * @param string $entryId
     * @param KalturaAttachmentAsset $attachmentAsset
     * @return KalturaAttachmentAsset
     * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 * @throws KalturaErrors::UPLOAD_TOKEN_INVALID_STATUS_FOR_ADD_ENTRY
	 * @throws KalturaErrors::UPLOADED_FILE_NOT_FOUND_BY_TOKEN
	 * @throws KalturaErrors::RECORDED_WEBCAM_FILE_NOT_FOUND
	 * @throws KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND
	 * @throws KalturaErrors::STORAGE_PROFILE_ID_NOT_FOUND
	 * @throws KalturaErrors::RESOURCE_TYPE_NOT_SUPPORTED
	 * @validateUser entry entryId edit
     */
    function addAction($entryId, KalturaAttachmentAsset $attachmentAsset)
    {
		$dbEntry = entryPeer::retrieveByPK($entryId);
		if(!$dbEntry || !in_array($dbEntry->getType(), $this->getEnabledMediaTypes())  || !$dbEntry->allowEdit())
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);

		$dbAsset = $attachmentAsset->toInsertableObject();
		$dbAsset->setEntryId($dbEntry->getEntryId());
		$dbAsset->setPartnerId($dbEntry->getPartnerId());
		$dbAsset->setStatus(AttachmentAsset::ASSET_STATUS_QUEUED);
		$dbAsset->save();

		$asset = KalturaAsset::getInstance($dbAsset);
		$asset->fromObject($dbAsset, $this->getResponseProfile());
		return $asset;
    }

    /**
     * Update content of attachment asset
     *
     * @action setContent
     * @param string $id
     * @param KalturaContentResource $contentResource
     * @return KalturaAttachmentAsset
     * @throws KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND
	 * @throws KalturaErrors::UPLOAD_TOKEN_INVALID_STATUS_FOR_ADD_ENTRY
	 * @throws KalturaErrors::UPLOADED_FILE_NOT_FOUND_BY_TOKEN
	 * @throws KalturaErrors::RECORDED_WEBCAM_FILE_NOT_FOUND
	 * @throws KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND
	 * @throws KalturaErrors::STORAGE_PROFILE_ID_NOT_FOUND
	 * @throws KalturaErrors::RESOURCE_TYPE_NOT_SUPPORTED 
	 * @validateUser asset::entry id edit
     */
    function setContentAction($id, KalturaContentResource $contentResource)
    {
   		$dbAttachmentAsset = assetPeer::retrieveById($id);
   		if (!$dbAttachmentAsset || !($dbAttachmentAsset instanceof AttachmentAsset))
   			throw new KalturaAPIException(KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND, $id);
    	
		$dbEntry = $dbAttachmentAsset->getentry();
    	if(!$dbEntry || !in_array($dbEntry->getType(), $this->getEnabledMediaTypes())  || !$dbEntry->allowEdit())
    		throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $dbAttachmentAsset->getEntryId());
		
		
   		$previousStatus = $dbAttachmentAsset->getStatus();
		$contentResource->validateEntry($dbAttachmentAsset->getentry());
		$contentResource->validateAsset($dbAttachmentAsset);
		$kContentResource = $contentResource->toObject();
    	$this->attachContentResource($dbAttachmentAsset, $kContentResource);
		$contentResource->entryHandled($dbAttachmentAsset->getentry());
		kEventsManager::raiseEvent(new kObjectDataChangedEvent($dbAttachmentAsset));
		
    	$newStatuses = array(
    		AttachmentAsset::ASSET_STATUS_READY,
    		AttachmentAsset::ASSET_STATUS_VALIDATING,
    		AttachmentAsset::ASSET_STATUS_TEMP,
    	);
    	
    	if($previousStatus == AttachmentAsset::ASSET_STATUS_QUEUED && in_array($dbAttachmentAsset->getStatus(), $newStatuses))
   			kEventsManager::raiseEvent(new kObjectAddedEvent($dbAttachmentAsset));
   		
		$attachmentAsset = KalturaAsset::getInstance($dbAttachmentAsset);
		$attachmentAsset->fromObject($dbAttachmentAsset, $this->getResponseProfile());
		return $attachmentAsset;
    }
	
    /**
     * Update attachment asset
     *
     * @action update
     * @param string $id
     * @param KalturaAttachmentAsset $attachmentAsset
     * @return KalturaAttachmentAsset
     * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
     * @validateUser asset::entry id edit
     */
    function updateAction($id, KalturaAttachmentAsset $attachmentAsset)
    {
		$dbAttachmentAsset = assetPeer::retrieveById($id);
		if (!$dbAttachmentAsset || !($dbAttachmentAsset instanceof AttachmentAsset))
			throw new KalturaAPIException(KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND, $id);
    	
		$dbEntry = $dbAttachmentAsset->getentry();
    	if(!$dbEntry || !in_array($dbEntry->getType(), $this->getEnabledMediaTypes())  || !$dbEntry->allowEdit())
    		throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $dbAttachmentAsset->getEntryId());
		
		
    	$dbAttachmentAsset = $attachmentAsset->toUpdatableObject($dbAttachmentAsset);
    	$dbAttachmentAsset->save();
		
		$attachmentAsset = KalturaAsset::getInstance($dbAttachmentAsset);
		$attachmentAsset->fromObject($dbAttachmentAsset, $this->getResponseProfile());
		return $attachmentAsset;
    }
    
	/**
	 * @param AttachmentAsset $attachmentAsset
	 * @param string $fullPath
	 * @param bool $copyOnly
	 */
	protected function attachFile(AttachmentAsset $attachmentAsset, $fullPath, $copyOnly = false)
	{
		if (myUploadUtils::isFileTypeRestricted($fullPath))
		{
			throw new KalturaAPIException(KalturaErrors::FILE_CONTENT_NOT_SECURE);
		}
		$ext = pathinfo($fullPath, PATHINFO_EXTENSION);
		
		$attachmentAsset->incrementVersion();

		if ($ext)
        {
            $attachmentAsset->setFileExt($ext);
        }
		$attachmentAsset->setSize(kFile::fileSize($fullPath));
		$attachmentAsset->save();
		
		$syncKey = $attachmentAsset->getSyncKey(AttachmentAsset::FILE_SYNC_ASSET_SUB_TYPE_ASSET);
		
		try {
			kFileSyncUtils::moveFromFile($fullPath, $syncKey, true, $copyOnly);
		}
		catch (Exception $e) {
			
			if($attachmentAsset->getStatus() == AttachmentAsset::ASSET_STATUS_QUEUED || $attachmentAsset->getStatus() == AttachmentAsset::ASSET_STATUS_NOT_APPLICABLE)
			{
				$attachmentAsset->setDescription($e->getMessage());
				$attachmentAsset->setStatus(AttachmentAsset::ASSET_STATUS_ERROR);
				$attachmentAsset->save();
			}												
			throw $e;
		}
		
		$fileSync = kFileSyncUtils::getLocalFileSyncForKey($syncKey, false);
		list($width, $height, $type, $attr) = kImageUtils::getImageSize($fileSync);
		
		$attachmentAsset->setWidth($width);
		$attachmentAsset->setHeight($height);
		$attachmentAsset->setSize($fileSync->getFileSize());
		
		$attachmentAsset->setStatus(AttachmentAsset::ASSET_STATUS_READY);
		$attachmentAsset->save();
	}

	/**
	 * @param AttachmentAsset $attachmentAsset
	 * @param kUrlResource $contentResource
	 * @throws KalturaAPIException
	 */
	protected function attachUrl(AttachmentAsset $attachmentAsset, kUrlResource $contentResource)
	{
		$url = $contentResource->getUrl();
		$fileName = basename($url);
		if(strlen($fileName) > self::MAX_FILE_NAME_LENGTH)
		{
			$fileName = md5($url);
		}

		$fullPath = myContentStorage::getFSUploadsPath() . '/' . $fileName;

        //curl does not supports sftp protocol, therefore we will use 'addImportJob'
        if (!kString::beginsWith( $url , infraRequestUtils::PROTOCOL_SFTP))
        {
            if (KCurlWrapper::getDataFromFile($url, $fullPath) && !myUploadUtils::isFileTypeRestricted($fullPath))
            {
                return $this->attachFile($attachmentAsset, $fullPath);
            }

            if($attachmentAsset->getStatus() == AttachmentAsset::ASSET_STATUS_QUEUED || $attachmentAsset->getStatus() == AttachmentAsset::ASSET_STATUS_NOT_APPLICABLE)
            {
                $attachmentAsset->setDescription("Failed downloading file[$url]");
                $attachmentAsset->setStatus(AttachmentAsset::ASSET_STATUS_ERROR);
                $attachmentAsset->save();
            }

            throw new KalturaAPIException(KalturaAttachmentErrors::ATTACHMENT_ASSET_DOWNLOAD_FAILED, $url);
        }

        kJobsManager::addImportJob(null, $attachmentAsset->getEntryId(), $attachmentAsset->getPartnerId(), $url, $attachmentAsset, null, $contentResource->getImportJobData());

    }
    
	/**
	 * @param AttachmentAsset $attachmentAsset
	 * @param kUrlResource $contentResource
	 */
	protected function attachUrlResource(AttachmentAsset $attachmentAsset, kUrlResource $contentResource)
	{
    	$this->attachUrl($attachmentAsset, $contentResource);
    }
    
	/**
	 * @param AttachmentAsset $attachmentAsset
	 * @param kLocalFileResource $contentResource
	 */
	protected function attachLocalFileResource(AttachmentAsset $attachmentAsset, kLocalFileResource $contentResource)
	{
		if($contentResource->getIsReady())
			return $this->attachFile($attachmentAsset, $contentResource->getLocalFilePath(), $contentResource->getKeepOriginalFile());
			
		$attachmentAsset->setStatus(asset::ASSET_STATUS_IMPORTING);
		$attachmentAsset->save();
		
		$contentResource->attachCreatedObject($attachmentAsset);
    }
    
	/**
	 * @param AttachmentAsset $attachmentAsset
	 * @param FileSyncKey $srcSyncKey
	 */
	protected function attachFileSync(AttachmentAsset $attachmentAsset, FileSyncKey $srcSyncKey)
	{
		$attachmentAsset->incrementVersion();
		$attachmentAsset->save();
		
        $newSyncKey = $attachmentAsset->getSyncKey(AttachmentAsset::FILE_SYNC_ASSET_SUB_TYPE_ASSET);
        kFileSyncUtils::createSyncFileLinkForKey($newSyncKey, $srcSyncKey);
		
		$fileSync = kFileSyncUtils::getLocalFileSyncForKey($newSyncKey, false);
		list($width, $height, $type, $attr) = kImageUtils::getImageSize($fileSync);
		
		$attachmentAsset->setWidth($width);
		$attachmentAsset->setHeight($height);
		$attachmentAsset->setSize($fileSync->getFileSize());
		
		$attachmentAsset->setStatus(AttachmentAsset::ASSET_STATUS_READY);
		$attachmentAsset->save();
    }
    
	/**
	 * @param AttachmentAsset $attachmentAsset
	 * @param kFileSyncResource $contentResource
	 */
	protected function attachFileSyncResource(AttachmentAsset $attachmentAsset, kFileSyncResource $contentResource)
	{
    	$syncable = kFileSyncObjectManager::retrieveObject($contentResource->getFileSyncObjectType(), $contentResource->getObjectId());
    	$srcSyncKey = $syncable->getSyncKey($contentResource->getObjectSubType(), $contentResource->getVersion());
    	
        return $this->attachFileSync($attachmentAsset, $srcSyncKey);
    }
    
	/**
	 * @param AttachmentAsset $attachmentAsset
	 * @param IRemoteStorageResource $contentResource
	 * @throws KalturaErrors::STORAGE_PROFILE_ID_NOT_FOUND
	 */
	protected function attachRemoteStorageResource(AttachmentAsset $attachmentAsset, IRemoteStorageResource $contentResource)
	{
		$resources = $contentResource->getResources();
		
		$attachmentAsset->setFileExt($contentResource->getFileExt());
        $attachmentAsset->incrementVersion();
		$attachmentAsset->setStatus(AttachmentAsset::ASSET_STATUS_READY);
        $attachmentAsset->save();
        	
        $syncKey = $attachmentAsset->getSyncKey(AttachmentAsset::FILE_SYNC_ASSET_SUB_TYPE_ASSET);
		foreach($resources as $currentResource)
		{
			$storageProfile = StorageProfilePeer::retrieveByPK($currentResource->getStorageProfileId());
			$fileSync = kFileSyncUtils::createReadyExternalSyncFileForKey($syncKey, $currentResource->getUrl(), $storageProfile);
		}
    }
    
    
	/**
	 * @param AttachmentAsset $attachmentAsset
	 * @param kContentResource $contentResource
	 * @throws KalturaErrors::UPLOAD_TOKEN_INVALID_STATUS_FOR_ADD_ENTRY
	 * @throws KalturaErrors::UPLOADED_FILE_NOT_FOUND_BY_TOKEN
	 * @throws KalturaErrors::RECORDED_WEBCAM_FILE_NOT_FOUND
	 * @throws KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND
	 * @throws KalturaErrors::STORAGE_PROFILE_ID_NOT_FOUND
	 * @throws KalturaErrors::RESOURCE_TYPE_NOT_SUPPORTED
	 */
	protected function attachContentResource(AttachmentAsset $attachmentAsset, kContentResource $contentResource)
	{
    	switch($contentResource->getType())
    	{
			case 'kUrlResource':
				return $this->attachUrlResource($attachmentAsset, $contentResource);
				
			case 'kLocalFileResource':
				return $this->attachLocalFileResource($attachmentAsset, $contentResource);
				
			case 'kFileSyncResource':
				return $this->attachFileSyncResource($attachmentAsset, $contentResource);
				
			case 'kRemoteStorageResource':
			case 'kRemoteStorageResources':
				return $this->attachRemoteStorageResource($attachmentAsset, $contentResource);
				
			default:
				$msg = "Resource of type [" . get_class($contentResource) . "] is not supported";
				KalturaLog::err($msg);
				
				if($attachmentAsset->getStatus() == AttachmentAsset::ASSET_STATUS_QUEUED || $attachmentAsset->getStatus() == AttachmentAsset::ASSET_STATUS_NOT_APPLICABLE)
				{
					$attachmentAsset->setDescription($msg);
					$attachmentAsset->setStatus(asset::ASSET_STATUS_ERROR);
					$attachmentAsset->save();
				}
				
				throw new KalturaAPIException(KalturaErrors::RESOURCE_TYPE_NOT_SUPPORTED, get_class($contentResource));
    	}
    }
	
	/**
	 * Get download URL for the asset
	 * 
	 * @action getUrl
	 * @param string $id
	 * @param int $storageId
	 * @return string
	 * @throws KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND
	 * @throws KalturaAttachmentErrors::ATTACHMENT_ASSET_IS_NOT_READY
	 */
	public function getUrlAction($id, $storageId = null)
	{
		$assetDb = assetPeer::retrieveById($id);
		if (!$assetDb || !($assetDb instanceof AttachmentAsset))
			throw new KalturaAPIException(KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND, $id);

		$this->validateEntryEntitlement($assetDb->getEntryId(), $id);
		
		if ($assetDb->getStatus() != asset::ASSET_STATUS_READY)
			throw new KalturaAPIException(KalturaAttachmentErrors::ATTACHMENT_ASSET_IS_NOT_READY);
		
		$entryDb = $assetDb->getentry();
		if(is_null($entryDb))
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $assetDb->getEntryId());
		
		if($storageId)
			return $assetDb->getExternalUrl($storageId);
			
		return $assetDb->getDownloadUrl(true);
	}
	
	/**
	 * Get remote storage existing paths for the asset
	 * 
	 * @action getRemotePaths
	 * @param string $id
	 * @return KalturaRemotePathListResponse
	 * @throws KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND
	 * @throws KalturaAttachmentErrors::ATTACHMENT_ASSET_IS_NOT_READY
	 */
	public function getRemotePathsAction($id)
	{
		$assetDb = assetPeer::retrieveById($id);
		if (!$assetDb || !($assetDb instanceof AttachmentAsset))
			throw new KalturaAPIException(KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND, $id);

		if ($assetDb->getStatus() != asset::ASSET_STATUS_READY)
			throw new KalturaAPIException(KalturaAttachmentErrors::ATTACHMENT_ASSET_IS_NOT_READY);

		$fileSyncs = kFileSyncUtils::getReadyRemoteFileSyncsForAsset($id, $assetDb, FileSyncObjectType::ASSET, asset::FILE_SYNC_ASSET_SUB_TYPE_ASSET);

		$listResponse = new KalturaRemotePathListResponse();
		$listResponse->objects = KalturaRemotePathArray::fromDbArray($fileSyncs, $this->getResponseProfile());
		$listResponse->totalCount = count($listResponse->objects);
		return $listResponse;
	}
	
	/**
	 * Serves attachment by its id
	 *  
	 * @action serve
	 * @param string $attachmentAssetId
	 * @param KalturaAttachmentServeOptions $serveOptions
	 * @return file
	 * @ksOptional
	 *  
	 * @throws KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND
	 */
	public function serveAction($attachmentAssetId, KalturaAttachmentServeOptions $serveOptions = null)
	{
		$attachmentAsset = null;
		if (!kCurrentContext::$ks)
		{	
			$attachmentAsset = kCurrentContext::initPartnerByAssetId($attachmentAssetId);
			
			if (!$attachmentAsset || $attachmentAsset->getStatus() == asset::ASSET_STATUS_DELETED)
				throw new KalturaAPIException(KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND, $attachmentAssetId);
				
			// enforce entitlement
			$this->setPartnerFilters(kCurrentContext::getCurrentPartnerId());
			kEntitlementUtils::initEntitlementEnforcement();
		}
		else 
		{	
			$attachmentAsset = assetPeer::retrieveById($attachmentAssetId);
		}
		
		if (!$attachmentAsset || !($attachmentAsset instanceof AttachmentAsset))
			throw new KalturaAPIException(KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND, $attachmentAssetId);
		
		$entry = entryPeer::retrieveByPK($attachmentAsset->getEntryId());
		if(!$entry)
		{
			//we will throw attachment asset not found, as the user is not entitled, and should not know that the entry exists.
			throw new KalturaAPIException(KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND, $attachmentAssetId);
		}
		
		$securyEntryHelper = new KSecureEntryHelper($entry, kCurrentContext::$ks, null, ContextType::DOWNLOAD);
		$securyEntryHelper->validateForDownload();
		
		$ext = $attachmentAsset->getFileExt();
		if(is_null($ext))
			$ext = 'txt';
			
		$fileName = $attachmentAsset->getFilename();
		if (!$fileName)	
			$fileName = $attachmentAsset->getEntryId()."_" . $attachmentAsset->getId() . ".$ext";
		
		if(!$serveOptions || ($serveOptions && $serveOptions->download == true))
			header("Content-Disposition: attachment; filename=\"$fileName\"");
		
		return $this->serveAsset($attachmentAsset, $fileName);
	}

	/**
	 * @action get
	 * @param string $attachmentAssetId
	 * @return KalturaAttachmentAsset
	 * 
	 * @throws KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND
	 */
	public function getAction($attachmentAssetId)
	{
		$attachmentAssetsDb = assetPeer::retrieveById($attachmentAssetId);
		if (!$attachmentAssetsDb || !($attachmentAssetsDb instanceof AttachmentAsset))
			throw new KalturaAPIException(KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND, $attachmentAssetId);
		
		$attachmentAsset = KalturaAsset::getInstance($attachmentAssetsDb);
		$attachmentAsset->fromObject($attachmentAssetsDb, $this->getResponseProfile());
		return $attachmentAsset;
	}
	
	/**
	 * List attachment Assets by filter and pager
	 * 
	 * @action list
	 * @param KalturaAssetFilter $filter
	 * @param KalturaFilterPager $pager
	 * @return KalturaAttachmentAssetListResponse
	 */
	function listAction(KalturaAssetFilter $filter = null, KalturaFilterPager $pager = null)
	{
		if(!$filter)
		{
			$filter = new KalturaAttachmentAssetFilter();
		}
		elseif(! $filter instanceof KalturaAttachmentAssetFilter)
		{
			$filter = $filter->cast('KalturaAttachmentAssetFilter');
		}
			
		if(!$pager)
		{
			$pager = new KalturaFilterPager();
		}

		$types = KalturaPluginManager::getExtendedTypes(assetPeer::OM_CLASS, AttachmentPlugin::getAssetTypeCoreValue(AttachmentAssetType::ATTACHMENT));
		return $filter->getTypeListResponse($pager, $this->getResponseProfile(), $types);
	}
	
	/**
	 * @action delete
	 * @param string $attachmentAssetId
	 * 
	 * @throws KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND
	 * @validateUser asset::entry attachmentAssetId edit
	 */
	public function deleteAction($attachmentAssetId)
	{
		$attachmentAssetDb = assetPeer::retrieveById($attachmentAssetId);
		if (!$attachmentAssetDb || !($attachmentAssetDb instanceof AttachmentAsset))
			throw new KalturaAPIException(KalturaAttachmentErrors::ATTACHMENT_ASSET_ID_NOT_FOUND, $attachmentAssetId);
	
		$dbEntry = $attachmentAssetDb->getentry();
    	if(!$dbEntry || !in_array($dbEntry->getType(), $this->getEnabledMediaTypes()) || !$dbEntry->allowEdit())
    		throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $attachmentAssetDb->getEntryId());
		
		
		$attachmentAssetDb->setStatus(AttachmentAsset::ASSET_STATUS_DELETED);
		$attachmentAssetDb->setDeletedAt(time());
		$attachmentAssetDb->save();
	}
}
