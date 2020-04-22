<?php
/**
 * @package Core
 * @subpackage model.data
 */
class kHttpHeaderCondition extends kRegexCondition
{
	const TYPE_STR = 'type';
	const HTTP_HEADER_PREFIX = 'HTTP_';

	/**
	 * @var string
	 */
	protected $headerName;


	/**
	 * @return string
	 */
	public function getHeaderName()
	{
		return $this->headerName;
	}

	/**
	 * @param string $headerName
	 */
	public function setHeaderName($headerName)
	{
		$this->headerName = $headerName;
	}

	/* (non-PHPdoc)
	 * @see kCondition::__construct()
	 */
	public function __construct($not = false)
	{
		$this->setType(ConditionType::HTTP_HEADER);
		parent::__construct($not);
	}

	/* (non-PHPdoc)
	 * @see kCondition::getFieldValue()
	 */
	public function getFieldValue(kScope $scope)
	{
		$headerName = self::HTTP_HEADER_PREFIX . str_replace('-', '_', strtoupper($this->getHeaderName()));
		kApiCache::addExtraField(array(self::TYPE_STR => kApiCache::ECF_HTTP_HEADER, kApiCache::ECF_HTTP_HEADER => $headerName), kApiCache::COND_REGEX, $this->getStringValues($scope));
		$headerValue = isset($_SERVER[$headerName]) ? $_SERVER[$headerName] : null;
		return $headerValue;
	}

	/* (non-PHPdoc)
	 * @see kMatchCondition::shouldFieldDisableCache()
	 */
	public function shouldFieldDisableCache($scope)
	{
		return false;
	}
}