<?php

class kBatchUtils
{
	public static function getKconfParam($param)
	{
		$chunkConfig = self::tryLoadKconfConfig();
		if(!$chunkConfig || !isset($chunkConfig[$param])) {
			return null;
		}
		
		return $chunkConfig[$param];
	}
	
	public static function tryLoadKconfConfig()
	{
		$configCacheFileName = kEnvironment::get('cache_root_path') . DIRECTORY_SEPARATOR . 'batch' . DIRECTORY_SEPARATOR . 'sharedRemoteChunkConfig_serialized.txt';
		if(!kFile::checkFileExists($configCacheFileName))
		{
			$sharedStorageClientConfig = self::loadAndSaveKcofnConfig($configCacheFileName);
			self::setStorageRunParams($sharedStorageClientConfig);
			return $sharedStorageClientConfig;
		}
		
		$sharedStorageClientConfig = unserialize(kFile::getFileContent($configCacheFileName));
		if(time() > $sharedStorageClientConfig['expirationTime'])
		{
			KalturaLog::debug("Config cache file no longer valid, Will reload config");
			$sharedStorageClientConfig = self::loadAndSaveKcofnConfig($configCacheFileName);
			self::setStorageRunParams($sharedStorageClientConfig);
			return $sharedStorageClientConfig;
		}
		else
		{
			KalturaLog::debug("Config cache file valid, returning cached config");
		}
		
		self::setStorageRunParams($sharedStorageClientConfig);
		return $sharedStorageClientConfig;
	}
	
	public static function loadAndSaveKcofnConfig($configCacheFileName)
	{
		$s3Arn = kConf::get('s3Arn', 'cloud_storage', null);
		$storageOptions = kConf::get('storage_options', 'cloud_storage', array());
		$storageTypeMap = kConf::get('storage_type_map', 'cloud_storage', array());
		$remoteChunkConfigStaticFileCacheTime = kConf::get("remote_chunk_config_static_file_cache_time", "runtime_config", 120);
		$ffmpegReconnectParams = kConf::get("ffmpeg_reconnect_params", "runtime_config", null);
		
		$chunkConvertSharedStorageConfig = array(
			'arnRole' => $s3Arn,
			'storageTypeMap' => $storageTypeMap,
			'ffmpegReconnectParams' => $ffmpegReconnectParams,
			's3Region' => isset($storageOptions['s3Region']) ? $storageOptions['s3Region'] : null,
			'expirationTime' => time() + $remoteChunkConfigStaticFileCacheTime
		);
		
		if($storageOptions && isset($storageOptions['accessKeyId']) && isset($storageOptions['accessKeySecret']))
		{
			$chunkConvertSharedStorageConfig['endPoint'] = isset($storageOptions['endPoint']) ? $storageOptions['endPoint'] : null;
			$chunkConvertSharedStorageConfig['accessKeyId'] = isset($storageOptions['accessKeyId']) ? $storageOptions['accessKeyId'] : null;
			$chunkConvertSharedStorageConfig['accessKeySecret'] = isset($storageOptions['accessKeySecret']) ? $storageOptions['accessKeySecret'] : null;
		}
		
		
		KalturaLog::debug("Config loaded: " . print_r($chunkConvertSharedStorageConfig, true));
		kFile::safeFilePutContents($configCacheFileName, serialize($chunkConvertSharedStorageConfig));
		kCacheConfFactory::close();
		return $chunkConvertSharedStorageConfig;
	}
	
	private static function setStorageRunParams($storageRunParams)
	{
		kSharedFileSystemMgr::setFileSystemOptions('arnRole', $storageRunParams['arnRole']);
		kSharedFileSystemMgr::setFileSystemOptions('s3Region', $storageRunParams['s3Region']);
		
		if(isset($storageRunParams['endPoint'])) {
			kSharedFileSystemMgr::setFileSystemOptions('endPoint', $storageRunParams['endPoint']);
		}
		
		if(isset($storageRunParams['accessKeyId'])) {
			kSharedFileSystemMgr::setFileSystemOptions('accessKeyId', $storageRunParams['accessKeyId']);
		}
		
		if(isset($storageRunParams['accessKeySecret'])) {
			kSharedFileSystemMgr::setFileSystemOptions('accessKeySecret', $storageRunParams['accessKeySecret']);
		}
		
		$storageTypeMap = $storageRunParams['storageTypeMap'];
		foreach ($storageTypeMap as $key => $value) {
			kFile::setStorageTypeMap($key, $value);
		}
	}
	
	public static function addReconnectParams($pattern, $fileCmd, &$cmdLine)
	{
		if (strpos($fileCmd, $pattern) !== 0) {
			return;
		}
		
		$ffmpegReconnectParams = self::getKconfParam('ffmpegReconnectParams');
		if ($ffmpegReconnectParams) {
			$cmdLine .= " $ffmpegReconnectParams";
		}
	}
}