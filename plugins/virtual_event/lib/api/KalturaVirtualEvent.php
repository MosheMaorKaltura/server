<?php

/**
 * @package plugins.virtualEvent
 * @subpackage api.objects
 */
class KalturaVirtualEvent extends KalturaObject implements IFilterable
{
	const PROPERTY_MIN_LENGTH = 3;
	
	/**
	 * @var int
	 * @readonly
	 * @filter eq,in,notin
	 */
	public $id;
	
	/**
	 * @var int
	 * @filter eq,in
	 * @readonly
	 */
	public $partnerId;
	
	/**
	 * @var string
	 * @filter like,mlikeor,mlikeand,eq,order
	 */
	public $name;
	
	/**
	 * @var string
	 * @filter like,mlikeor,mlikeand,eq,order
	 */
	public $description;
	
	/**
	 * @var KalturaVirtualEventStatus
	 * @readonly
	 * @filter eq,in
	 */
	public $status;
	
	/**
	 * @var string
	 * @filter like,mlikeor,mlikeand,eq,order
	 */
	public $tags;
	
	/**
	 * @var string
	 * @filter eq,in
	 */
	public $attendeesGroupId;
	
	/**
	 * @var string
	 * @filter eq,in
	 */
	public $adminsGroupId;
	
	/**
	 * @var int
	 */
	public $registrationScheduleEventId;
	
	/**
	 * @var int
	 */
	public $agendaScheduleEventId;
	
	/**
	 * @var int
	 */
	public $mainEventScheduleEventId;
	
	/**
	 * @var time
	 * @readonly
	 * @filter gte,lte,order
	 */
	public $createdAt;
	
	/**
	 * @var time
	 * @readonly
	 * @filter gte,lte,order
	 */
	public $updatedAt;
	
	/**
	 * @var time
	 * @filter gte,lte,order
	 */
	public $deletionDueDate;
	
	/*
	 */
	private static $map_between_objects = array(
		'id',
		'partnerId',
		'name',
		'description',
		'status',
		'tags',
		'attendeesGroupId',
		'adminsGroupId',
		'registrationScheduleEventId',
		'agendaScheduleEventId',
		'mainEventScheduleEventId',
		'createdAt',
		'updatedAt',
		'deletionDueDate',
	);
	
	/* (non-PHPdoc)
	 * @see KalturaObject::getMapBetweenObjects()
	 */
	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}
	
	public function toInsertableObject($objectToFill = null, $propertiesToSkip = array())
	{
		return parent::toInsertableObject($objectToFill, $propertiesToSkip);
	}
	
	/* (non-PHPdoc)
	 * @see KalturaObject::validateForInsert()
	 */
	public function validateForInsert($propertiesToSkip = array())
	{
		$this->validatePropertyMinLength('name', self::PROPERTY_MIN_LENGTH, false);
		parent::validateForInsert($propertiesToSkip);
	}
	
	/* (non-PHPdoc)
	 * @see KalturaObject::validateForUpdate()
	 */
	public function validateForUpdate($sourceObject, $propertiesToSkip = array())
	{
		$this->validatePropertyMinLength('name', self::PROPERTY_MIN_LENGTH, true);
		
		return parent::validateForUpdate($sourceObject, $propertiesToSkip);
	}
	
	/* (non-PHPdoc)
	 * @see KalturaObject::toObject()
	 */
	public function toObject($dbObject = null, $propertiesToSkip = array())
	{
		if(is_null($dbObject))
			$dbObject = new VirtualEvent();
		
		$dbObject = parent::toObject($dbObject, $propertiesToSkip);
		if (!is_null($this->objectFilter))
			$dbObject->setObjectFilterApiType(get_class($this->objectFilter));
		return $dbObject;
	}
	
	
	/* (non-PHPdoc)
	 * @see IFilterable::getExtraFilters()
	 */
	public function getExtraFilters()
	{
		return array();
	}
	
	/* (non-PHPdoc)
	 * @see IFilterable::getFilterDocs()
	 */
	public function getFilterDocs()
	{
		return array();
	}
}