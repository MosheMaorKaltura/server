<?php
/**
 * @package plugins.schedule
 * @subpackage model
 */
class LiveRedirectScheduleEvent extends BaseLiveStreamScheduleEvent
{
	const REDIRECT_ENTRY_ID = 'redirect_entry_id';
	
	public function getRedirectEntryId ()
	{
		return $this->getFromCustomData(self::REDIRECT_ENTRY_ID);
	}
	public function setRedirectEntryId ($v)
	{
		$this->putInCustomData(self::REDIRECT_ENTRY_ID,$v);
	}
	
	public function dynamicGetter($context, &$output)
	{
			switch ($context)
			{
				case 'getRedirectEntryId':
				case 'getRecordedEntryId':
					$output = $this->getRedirectEntryId();
					break;
				case 'getLiveStatus':
					$output = EntryServerNodeStatus::STOPPED;
					break;
				default:
					return false;
			}
			return true;
	}
	
	/* (non-PHPdoc)
	 * @see ScheduleEvent::applyDefaultValues()
	 */
	public function applyDefaultValues()
	{
		parent::applyDefaultValues();
		$this->setType(ScheduleEventType::LIVE_REDIRECT);
	}
	
	protected function addCapabilityToTemplateEntry($con)
	{
		$liveEntry = entryPeer::retrieveByPK($this->getTemplateEntryId());
		if ($liveEntry)
		{
			if (!$liveEntry->hasCapability(LiveEntry::LIVE_SCHEDULE_CAPABILITY))
			{
				$liveEntry->addCapability(LiveEntry::LIVE_SCHEDULE_CAPABILITY);
				$liveEntry->save($con);
			}
		}
	}
}