<?php

/**
 * @package plugins.OneDrive
 * @subpackage batch
 */
class OneDriveDropFolderEngine extends KDropFolderEngine
{
	const ADMIN_TAG_TEAMS = 'msTeams';


	/**
	 * @var VendorPlugin
	 */
	protected $vendorPlugin;

	/**
	 * @var KMicrosoftGraphClient
	 */
	protected $graphClient;

	/**
	 * @var array
	 */
	protected $singleRunFoundItems;
	
	/**
	 * @var array
	 */
	protected $existingDropFolderFiles;
	
	/**
	 * @var KalturaOneDriveIntegrationSetting
	 */
	protected $vendorIntegrationSetting;
	
	
	public function watchFolder(KalturaDropFolder $dropFolder)
	{
		$this->initializeEngine($dropFolder);
		
		$filter = new KalturaTeamsVendorIntegrationUserFilter();
		$filter->partnerIdEqual = $dropFolder->partnerId;
		$filter->statusEqual = KalturaUserStatus::ACTIVE;
		if ($this->vendorIntegrationSetting->userFilterTag)
		{
			$filter->tagsMultiLikeOr = $this->vendorIntegrationSetting->userFilterTag;
		}
		
		$pager = new KalturaFilterPager();
		$pager->pageSize = 500;
		do
		{
			$usersList = $this->vendorPlugin->vendorIntegrationUser->listAction($filter, $pager);
			
			foreach ($usersList->objects as $kalturaTeamsVendorIntegrationUser)
			{
				try
				{
					$this->watchMicrosoftUserFiles($kalturaTeamsVendorIntegrationUser);
				}
				catch (Exception $e)
				{
					KalturaLog::info("Error running OneDrive drop folder for user {$kalturaTeamsVendorIntegrationUser->id}: {$e->getMessage()};");
				}
			}
			
			$returnedSize = $usersList->objects ? count($usersList->objects) : 0;
			$pager->pageIndex++;
		}
		while ($pager->pageSize == $returnedSize);
		
		if (!$this->vendorIntegrationSetting->isInitialized)
		{
			$updatedVendorIntegrationSetting = new KalturaOneDriveIntegrationSetting();
			$updatedVendorIntegrationSetting->clientSecret = $this->vendorIntegrationSetting->clientSecret;
			$updatedVendorIntegrationSetting->clientId = $this->vendorIntegrationSetting->clientId;
			$updatedVendorIntegrationSetting->isInitialized = true;
			$this->vendorPlugin->vendorIntegration->update($this->vendorIntegrationSetting->id, $updatedVendorIntegrationSetting);
		}
	}
	
	protected function initializeEngine($dropFolder)
	{
		/* @var $dropFolder KalturaMicrosoftTeamsDropFolder */
		$this->dropFolder = $dropFolder;
		$this->vendorPlugin = KalturaVendorClientPlugin::get(KBatchBase::$kClient);
		$this->graphClient = new KMicrosoftGraphClient($dropFolder->tenantId, $dropFolder->path, $dropFolder->clientId, $dropFolder->clientSecret);
		$this->existingDropFolderFiles = $this->loadDropFolderFiles();
		$this->vendorIntegrationSetting = $this->vendorPlugin->vendorIntegration->get($dropFolder->integrationId);
	}
	
	protected function watchMicrosoftUserFiles($user)
	{
		/* @var $user KalturaTeamsVendorIntegrationUser */
		if (!$user->email)
		{
			return;
		}
		
		$updateUser = new KalturaTeamsVendorIntegrationUser();
		$updateCurrentUser = false;
		
		if (!$user->microsoftUserId)
		{
			$updateUser->microsoftUserId = $this->retrieveMicrosoftUserByMail($user->email);
			if (!$updateUser->microsoftUserId)
			{
				return;
			}
			
			$user->microsoftUserId = $updateUser->microsoftUserId;
			$updateCurrentUser = true;
		}
		
		if ($user->recordingsFolderDeltaLink)
		{
			if ($this->vendorIntegrationSetting->isInitialized)
			{
				$filesFromDrive = $this->graphClient->sendGraphRequest($user->recordingsFolderDeltaLink);
				if ($filesFromDrive)
				{
					$updateUser->recordingsFolderDeltaLink = $this->downloadFilesFromDrive($filesFromDrive);
					if ($updateUser->recordingsFolderDeltaLink)
					{
						$updateCurrentUser = true;
					}
				}
			}
		}
		else
		{
			$recordingsFolderId = $this->getRecordingsFolderId($user->microsoftUserId);
			if ($recordingsFolderId)
			{
				$filesFromDrive = $this->graphClient->getRecordingFolderDeltaPage($user->microsoftUserId, $recordingsFolderId);
				if ($filesFromDrive)
				{
					if ($this->vendorIntegrationSetting->isInitialized)
					{
						$updateUser->recordingsFolderDeltaLink = $this->downloadFilesFromDrive($filesFromDrive);
						if ($updateUser->recordingsFolderDeltaLink)
						{
							$updateCurrentUser = true;
						}
					}
					else
					{
						do
						{
							if (isset($filesFromDrive['@odata.nextLink']))
							{
								$filesFromDrive = $this->graphClient->sendGraphRequest($filesFromDrive['@odata.nextLink']);
							}
							elseif (isset($filesFromDrive['@odata.deltaLink']))
							{
								$updateUser->recordingsFolderDeltaLink = $filesFromDrive['@odata.deltaLink'];
								$updateCurrentUser = true;
							}
						}
						while (!$updateUser->recordingsFolderDeltaLink);
					}
				}
			}
		}
		
		if ($updateCurrentUser)
		{
			$this->vendorPlugin->vendorIntegrationUser->update($user->id, $updateUser);
		}
	}
	
	protected function retrieveMicrosoftUserByMail($userEmail)
	{
		$microsoftUsers = $this->graphClient->getUserByMail($userEmail);
		if (isset($microsoftUsers[MicrosoftGraphFieldNames::VALUE]))
		{
			foreach ($microsoftUsers[MicrosoftGraphFieldNames::VALUE] as $microsoftUser)
			{
				if ($microsoftUser['id'])
				{
					return $microsoftUser['id'];
				}
			}
		}
		
		return null;
	}
	
	protected function getRecordingsFolderId($microsoftUserId)
	{
		$filesList = $this->graphClient->getDriveDeltaPage($microsoftUserId);
		if (isset($filesList[MicrosoftGraphFieldNames::VALUE]))
		{
			foreach ($filesList[MicrosoftGraphFieldNames::VALUE] as $item)
			{
				if (isset($item['specialFolder']['name']) && $item['specialFolder']['name'] == 'recordings')
				{
					return $item['id'];
				}
			}
		}
		
		return null;
	}
	
	protected function downloadFilesFromDrive($filesFromDrive)
	{
		if (isset($filesFromDrive[MicrosoftGraphFieldNames::VALUE]))
		{
			foreach ($filesFromDrive[MicrosoftGraphFieldNames::VALUE] as $fileInRecordings)
			{
				if (!isset($item[MicrosoftGraphFieldNames::FOLDER_FACET]) && !isset($item[MicrosoftGraphFieldNames::DELETED_FACET]))
				{
					$this->getDriveItem($fileInRecordings);
				}
			}
		}
		
		if (isset($filesFromDrive['@odata.nextLink']))
		{
			return $filesFromDrive['@odata.nextLink'];
		}
		elseif (isset($filesFromDrive['@odata.deltaLink']))
		{
			return $filesFromDrive['@odata.deltaLink'];
		}
		
		return null;
	}
	
	protected function getDriveItem($item)
	{
		$extendedItem = $this->graphClient->getDriveItem($item[MicrosoftGraphFieldNames::PARENT_REFERENCE][MicrosoftGraphFieldNames::DRIVE_ID], $item[MicrosoftGraphFieldNames::ID_FIELD]);
		$result = null;
		if ($extendedItem)
		{
			$this->singleRunFoundItems[$extendedItem[MicrosoftGraphFieldNames::ID_FIELD]] = $extendedItem;
			if (in_array($extendedItem[MicrosoftGraphFieldNames::ID_FIELD], array_keys($this->existingDropFolderFiles))) {
				$currentDropFolderFile = $this->existingDropFolderFiles[$extendedItem[MicrosoftGraphFieldNames::ID_FIELD]];
				unset ($this->existingDropFolderFiles[$extendedItem[MicrosoftGraphFieldNames::ID_FIELD]]);
				if ($currentDropFolderFile->fileSize == $extendedItem[MicrosoftGraphFieldNames::SIZE]) {
					KalturaLog::info('Drive item with ID ' . $extendedItem[MicrosoftGraphFieldNames::ID_FIELD] . ' already exists in the system, and the content size remains the same. Skipping.');
				}
				else
				{
					$currentDropFolderFile = $this->updateDropFolderFile($currentDropFolderFile, $extendedItem);
					$result = $this->handleExistingDropFolderFile($currentDropFolderFile);
				}
			}
			else
			{
				$result = $this->handleFileAdded($extendedItem, $this->dropFolder->id, $this->vendorIntegrationSetting);
			}
		}
		
		return $result;
	}

	protected function updateDropFolderFile(KalturaDropFolderFile $currentDropFolderFile, array $driveItem)
	{
		KalturaLog::info("Updating drop folder file $currentDropFolderFile->id");

		$updateDropFolderFile = new KalturaMicrosoftTeamsDropFolderFile();
		$updateDropFolderFile->name = $driveItem[MicrosoftGraphFieldNames::NAME];
		$updateDropFolderFile->description = $driveItem[MicrosoftGraphFieldNames::DESCRIPTION];
		$updateDropFolderFile->fileSize = $driveItem[MicrosoftGraphFieldNames::SIZE];
		$updateDropFolderFile->contentUrl = $driveItem[MicrosoftGraphFieldNames::DOWNLOAD_URL];

		try
		{
			$dropFolderFile = $this->dropFolderFileService->update($currentDropFolderFile->id, $updateDropFolderFile);
			return $dropFolderFile;
		}
		catch(Exception $e)
		{
			KalturaLog::err('Cannot update drop folder file with name ['.$driveItem[MicrosoftGraphFieldNames::ID_FIELD].'] - '.$e->getMessage());
			return null;
		}
	}

	protected function handleFileAdded($extendedItem, $dropFolderId, KalturaIntegrationSetting $integrationData)
	{
		KalturaLog::info('Handling drive item with ID ' . $extendedItem[MicrosoftGraphFieldNames::ID_FIELD]);
		$dropFolderFile = new KalturaMicrosoftTeamsDropFolderFile();
		$dropFolderFile->dropFolderId = $dropFolderId;
		$dropFolderFile->fileSize = $extendedItem[MicrosoftGraphFieldNames::SIZE];
		$dropFolderFile->fileName = $extendedItem[MicrosoftGraphFieldNames::ID_FIELD];
		$dropFolderFile->remoteId = $extendedItem[MicrosoftGraphFieldNames::ID_FIELD];
		$dropFolderFile->name = $extendedItem[MicrosoftGraphFieldNames::NAME];
		$dropFolderFile->contentUrl = $extendedItem[MicrosoftGraphFieldNames::DOWNLOAD_URL];
		if (isset($extendedItem[MicrosoftGraphFieldNames::DESCRIPTION]))
		{
			$dropFolderFile->description = $extendedItem[MicrosoftGraphFieldNames::DESCRIPTION];
		}

		$dropFolderFile->ownerId = $this->retrieveUserId($extendedItem[MicrosoftGraphFieldNames::CREATED_BY]);
		$dropFolderFile->additionalUserIds = $this->retrieveParticipants($extendedItem);

		try
		{
			$dropFolderFile = $this->dropFolderFileService->add($dropFolderFile);

			$this->dropFolderFileService->updateStatus($dropFolderFile->id, KalturaDropFolderFileStatus::PENDING);
			return $dropFolderFile;
		}
		catch(Exception $e)
		{
			KalturaLog::err('Cannot add new drop folder file with name ['.$extendedItem[MicrosoftGraphFieldNames::ID_FIELD].'] - '.$e->getMessage());
			return null;
		}

	}
	
	protected function retrieveUserId($creatorInfo)
	{
		$userInfo = $creatorInfo[MicrosoftGraphFieldNames::USER];
		if(isset($userInfo[MicrosoftGraphFieldNames::EMAIL]))
		{
			return $userInfo[MicrosoftGraphFieldNames::EMAIL];
		}
		
		return $userInfo[MicrosoftGraphFieldNames::ID_FIELD];
	}

	protected function retrieveParticipants($item)
	{
		$participants = array();

		$callRecordId = $item[MicrosoftGraphFieldNames::SOURCE][MicrosoftGraphFieldNames::EXTERNAL_ID];
		$callRecord = $this->graphClient->getCallRecord($callRecordId);

		if ($callRecord)
		{
			foreach ($callRecord[MicrosoftGraphFieldNames::PARTICIPANTS] as $participant)
			{
				$userId = $participant[MicrosoftGraphFieldNames::USER][MicrosoftGraphFieldNames::ID_FIELD];
				$user = $this->graphClient->getUser($userId);
				if ($user)
				{
					$participants[] = $user[MicrosoftGraphFieldNames::MAIL];
				}
			}
		}

		return implode(',', $participants);
	}

	public function processFolder(KalturaBatchJob $job, KalturaDropFolderContentProcessorJobData $data)
	{
		KBatchBase::impersonate($job->partnerId);

		/* @var $data KalturaDropFolderContentProcessorJobData */
		$dropFolder = $this->dropFolderPlugin->dropFolder->get($data->dropFolderId);
		//In the case of the microsoft teams drop folder engine, the only possible contentMatch policy is ADD_AS_NEW.
		//Any other policy should cause an error.
		switch ($data->contentMatchPolicy)
		{
			case KalturaDropFolderContentFileHandlerMatchPolicy::ADD_AS_NEW:
				$this->addAsNewContent($job, $data, $dropFolder);
				break;
			default:
				throw new kApplicativeException(KalturaDropFolderErrorCode::DROP_FOLDER_APP_ERROR, 'Content match policy not allowed for Microsoft Teams drop folders');
				break;
		}

		KBatchBase::unimpersonate();
	}

	protected function addAsNewContent(KalturaBatchJob $job, KalturaDropFolderContentProcessorJobData $data, $dropFolder)
	{
		/* @var $data KalturaDropFolderContentProcessorJobData */
		$resource = $this->getIngestionResource($job, $data);
		$dropFolderFile = $this->dropFolderFileService->get($job->jobObjectId);
		$newEntry = new KalturaMediaEntry();
		$newEntry->mediaType = KalturaMediaType::VIDEO;
		$newEntry->conversionProfileId = $data->conversionProfileId;
		$newEntry->name = $dropFolderFile->name;
		$newEntry->description = $dropFolderFile->description;
		$newEntry->userId = $dropFolderFile->ownerId;
		$newEntry->creatorId = $newEntry->userId;
		$newEntry->referenceId = $dropFolderFile->remoteId;
		$newEntry->adminTags = self::ADMIN_TAG_TEAMS;

		KBatchBase::$kClient->startMultiRequest();
		$addedEntry = KBatchBase::$kClient->media->add($newEntry, null);
		KBatchBase::$kClient->baseEntry->addContent($addedEntry->id, $resource);
		$result = KBatchBase::$kClient->doMultiRequest();

		if ($result [1] && $result[1] instanceof KalturaBaseEntry)
		{
			$entry = $result [1];
			$this->createCategoryAssociations ($dropFolder, $entry->userId, $entry->id);
		}
	}
}