<?php
/**
 * Created by IntelliJ IDEA.
 * User: yossi.papiashvili
 * Date: 5/26/19
 * Time: 4:19 PM
 */

require_once(dirname(__FILE__) . '/kSharedFileSystemMgr.php');

class kNfsSharedFileSystemMgr extends kSharedFileSystemMgr
{
	// instances of this class should be created using the 'getInstance' of the 'kFileTransferMgr' class
	public function __construct(array $options = null)
	{
		return;
	}
	
	protected function doCreateDirForPath($filePath)
	{
		$dirname = dirname($filePath);
		if (!is_dir($dirname))
		{
			mkdir($dirname, 0777, true);
		}
	}
	
	protected function doCheckFileExists($filePath)
	{
		return file_exists($filePath);
	}
	
	protected function doGetFileContent($filePath)
	{
		return @file_get_contents($filePath);
	}
	
	protected function doUnlink($filePath)
	{
		return @unlink($filePath);
	}
	
	protected function doPutFileContentAtomic($filePath, $fileContent)
	{
		// write to a temp file and then rename, so that the write will be atomic
		$tempFilePath = tempnam(dirname($filePath), basename($filePath));
		
		if(!$this->doPutFileContent($tempFilePath, $fileContent))
			return false;
		
		if(!$this->doRename($tempFilePath, $filePath))
		{
			$this->doUnlink($tempFilePath);
			return false;
		}
		
		return true;
	}
	
	protected function doPutFileContent($filePath, $fileContent)
	{
		return file_put_contents($filePath, $fileContent);
	}
	
	protected function doRename($filePath, $newFilePath)
	{
		return rename($filePath, $newFilePath);
	}
	
	protected function doCopy($fromFilePath, $toFilePath)
	{
		return copy($fromFilePath, $toFilePath);
	}
	
	protected function doGetFileFromRemoteUrl($url, $destFilePath = null, $allowInternalUrl = false)
	{
		$curlWrapper = new KCurlWrapper();
		$res = $curlWrapper->exec($url, $destFilePath, null, $allowInternalUrl);
		
		$httpCode = $curlWrapper->getHttpCode();
		if (KCurlHeaderResponse::isError($httpCode))
		{
			KalturaLog::info("curl request [$url] return with http-code of [$httpCode]");
			if ($destFilePath && file_exists($destFilePath))
				unlink($destFilePath);
			$res = false;
		}
		
		$curlWrapper->close();
		return $res;
	}

	protected function doFullMkdir($path, $rights = 0755, $recursive = true)
	{
		return $this->doFullMkfileDir(dirname($path), $rights, $recursive);
	}

	protected function doFullMkfileDir($path, $rights = 0777, $recursive = true)
	{
		if(file_exists($path))
			return true;
		$oldUmask = umask(00);
		$result = @mkdir($path, $rights, $recursive);
		umask($oldUmask);
		return $result;
	}

	protected function doMoveFile($from, $to, $override_if_exists = false, $copy = false)
	{
		if(!file_exists($from))
		{
			KalturaLog::err("Source doesn't exist [$from]");
			return false;
		}
		if(strpos($to,'\"') !== false)
		{
			KalturaLog::err("Illegal destination file [$to]");
			return false;
		}
		if($override_if_exists && is_file($to))
		{
			$this->deleteFile($to);
		}
		if(!is_dir(dirname($to)))
		{
			$this->fullMkdir($to);
		}
		return $this->copyRecursively($from,$to, !$copy);
	}

	protected function doDeleteFile($file_name)
	{
		$fh = fopen($file_name, 'w') or die("can't open file");
		fclose($fh);
		unlink($file_name);
	}

	protected function copySingleFile($src, $dest, $deleteSrc)
	{
		if($deleteSrc)
		{
			// In case of move, first try to move the file before copy & unlink.
			$startTime = microtime(true);
			if(rename($src, $dest))
			{
				KalturaLog::log("rename took : ".(microtime(true) - $startTime)." [$src] to [$dest] size: ".filesize($dest));
				return true;
			}
			KalturaLog::err("Failed to rename file : [$src] to [$dest]");
		}
		if (!copy($src,$dest))
		{
			KalturaLog::err("Failed to copy file : [$src] to [$dest]");
			return false;
		}
		if ($deleteSrc && (!unlink($src)))
		{
			KalturaLog::err("Failed to delete source file : [$src]");
			return false;
		}
		return true;
	}

	protected function doIsDir($path)
	{
		return is_dir($path);
	}

	protected function doMkdir($path)
	{
		return mkdir($path);
	}

	protected function doRmdir($path)
	{
		return rmdir($path);
	}

	public function doChmod($path, $mode)
	{
		return chmod($path, $mode);
	}

	public function doFileSize($filename)
	{
		if(PHP_INT_SIZE >= 8)
			return filesize($filename);
		$filename = str_replace('\\', '/', $filename);
		$url = "file://localhost/$filename";
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		$headers = curl_exec($ch);
		if(!$headers)
			KalturaLog::err('Curl error: ' . curl_error($ch));
		curl_close($ch);
		if(!$headers)
			return false;
		if (preg_match('/Content-Length: (\d+)/', $headers, $matches))
			return floatval($matches[1]);
		return false;
	}

}