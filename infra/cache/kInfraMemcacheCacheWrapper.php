<?php

require_once(dirname(__FILE__) . '/kInfraBaseCacheWrapper.php');

/**
 * @package infra
 * @subpackage cache
 */
class kInfraMemcacheCacheWrapper extends kInfraBaseCacheWrapper
{
	const MAX_CONNECT_ATTEMPTS = 4;

	const COMPRESSED = 1;

	const STAT_CONN = 'conn';
	const STAT_OP = 'op';
	const STAT_COUNT = 'count';
	const STAT_TIME = 'time';

	protected $hostName;
	protected $port;
	protected $flags = 0;
	protected $persistent = false;
	protected $statsKey;

	protected static $_stats = array();

	protected $memcache = null;
	protected $gotError = false;
	protected $connectAttempts = 0;
	
	/* (non-PHPdoc)
	 * @see kBaseCacheWrapper::doInit()
	 */
	protected function doInit($config)
	{
		if (!class_exists('Memcache'))
		{
			return false;
		}
		
		if (!isset($config['host']) || !isset($config['port']))
		{
			self::safeLog("Missing host or port in config, can't connect without it");
			return false;
		}

		$this->hostName = $config['host'];
		$this->port = $config['port'];
		if (isset($config['flags']) && $config['flags'] == self::COMPRESSED)
			$this->flags = MEMCACHE_COMPRESSED;
		if (isset($config['persistent']) && $config['persistent'])
			$this->persistent = true;

		$this->statsKey = $this->hostName . ':' . $this->port;

		return $this->reconnect();
	}
	
	public function __destruct()
	{
		$this->close();
	}

	protected function close()
	{
		if ($this->memcache)
		{
			$this->memcache->close();
		}
		$this->memcache = null;
	}

	/**
	 * @return bool false on error
	 */
	protected function reconnect()
	{
		$this->close();
		
		if ($this->connectAttempts >= self::MAX_CONNECT_ATTEMPTS)
		{
			return false;
		}

		$connectResult = false;
		$connStart = microtime(true);
		while ($this->connectAttempts < self::MAX_CONNECT_ATTEMPTS)
		{
			$this->connectAttempts++;
			
			$memcache = new Memcache;	
			
			//$memcache->setOption(Memcached::OPT_BINARY_PROTOCOL, true);			// TODO: enable when moving to memcached v1.3

			$curConnStart = microtime(true);
			if ($this->persistent)
				$connectResult = @$memcache->pconnect($this->hostName, $this->port);
			else 
				$connectResult = @$memcache->connect($this->hostName, $this->port);
			if ($connectResult || microtime(true) - $curConnStart < .5)		// retry only if there's an error and it's a timeout error
				break;

			self::safeLog("got timeout error while connecting to memcache...");
		}

		$connTook = microtime(true) - $connStart;
		self::safeLog("connect took - {$connTook} seconds to {$this->hostName}:{$this->port} attempts {$this->connectAttempts}");

		$this->updateStats(self::STAT_CONN, array(
			self::STAT_COUNT => 1,
			self::STAT_TIME => $connTook));

		if (!$connectResult)
		{
			self::safeLog("failed to connect to memcache");
			return false;
		}
		
		$this->memcache = $memcache;
		return true;
	}
	
	/**
	 * @param int $errno
	 * @param string $errstr
	 * @return bool
	 */
	public function errorHandler($errno, $errstr)
	{
		self::safeLog("got error from memcache [$errno] [$errstr]");
		$this->gotError = true;
		return false;
	}
	
	/**
	 * @param string $methodName
	 * @param array $params
	 * @return mixed false on error
	 */
	protected function callAndDetectErrors($methodName, $params)
	{
		while ($this->memcache)
		{
			$this->gotError = false;
			
			set_error_handler(array($this, 'errorHandler'));
			$start = microtime(true);
			$result = call_user_func_array(array($this->memcache, $methodName), $params);
			$end = microtime(true);
			restore_error_handler();

			$this->updateStats(self::STAT_OP, array(
				self::STAT_COUNT => 1,
				self::STAT_TIME => $end - $start));
			
			if (!$this->gotError)
			{
				return $result;
			}
			
			$this->reconnect();
		}

		self::safeLog("There isnt an active memcache connection");
		return false;
	}
	
	/* (non-PHPdoc)
	 * @see kBaseCacheWrapper::get()
	 */
	protected function doGet($key)
	{
		return $this->callAndDetectErrors('get', array($key));
	}
	
	/* (non-PHPdoc)
	 * @see kBaseCacheWrapper::set()
	 */
	protected function doSet($key, $var, $expiry = 0)
	{
		return $this->callAndDetectErrors('set', array($key, $var, $this->flags, $expiry));
	}

	/* (non-PHPdoc)
	 * @see kBaseCacheWrapper::add()
	 */
	public function doAdd($key, $var, $expiry = 0)
	{
		return $this->callAndDetectErrors('add', array($key, $var, $this->flags, $expiry));
	}
	
	/* (non-PHPdoc)
	 * @see kBaseCacheWrapper::multiGet()
	 */
	public function doMultiGet($keys)
	{
		return $this->callAndDetectErrors('get', array($keys));
	}

	/* (non-PHPdoc)
	 * @see kBaseCacheWrapper::delete()
	 */
	public function doDelete($key)
	{
		return $this->callAndDetectErrors('delete', array($key));
	}
	
	/* (non-PHPdoc)
	 * @see kBaseCacheWrapper::increment()
	 */
	public function doIncrement($key, $delta = 1)
	{
		return $this->callAndDetectErrors('increment', array($key, $delta));
	}
	
	/* (non-PHPdoc)
	 * @see kBaseCacheWrapper::decrement()
	 */
	public function doDecrement($key, $delta = 1)
	{
		return $this->callAndDetectErrors('decrement', array($key, $delta));
	}

	protected function updateStats($type, $stats)
	{
		if (!isset(self::$_stats[$this->statsKey]))
		{
			self::$_stats[$this->statsKey] = array();
		}
		$typeStats = &self::$_stats[$this->statsKey];

		if (!isset($typeStats[$type]))
		{
			$typeStats[$type] = array();
		}

		foreach ($stats as $key => $value)
		{
			if (isset($typeStats[$type][$key]))
			{
				$typeStats[$type][$key] += $value;
			}
			else
			{
				$typeStats[$type][$key] = $value;
			}
		}
	}

	public static function outputStats()
	{
		foreach (self::$_stats as $statsKey => $typeStats)
		{
			$cur = 'instance:' . $statsKey;
			foreach ($typeStats as $type => $stats)
			{
				foreach ($stats as $key => $value)
				{
					$cur .= ', ' . $type . '_' . $key . ':' . $value;
				}
			}

			KalturaLog::log($cur);
		}
	}

	public static function sendMonitorEvents()
	{
		foreach (self::$_stats as $statsKey => $typeStats)
		{
			foreach ($typeStats as $type => $stats)
			{
				switch ($type)
				{
				case self::STAT_CONN:
					KalturaMonitorClient::monitorConnTook($statsKey, $stats[self::STAT_TIME], $stats[self::STAT_COUNT]);
					break;

				case self::STAT_OP:
					KalturaMonitorClient::monitorMemcacheAccess($statsKey, $stats[self::STAT_TIME], $stats[self::STAT_COUNT]);
					break;
				}
			}
		}

		self::$_stats = array();
	}
}
