<?php
/**
 * @package plugins.dropFolder
 * @subpackage Admin
 */
class Form_WebexAPIDropFolderConfigureExtend_SubForm extends Form_DropFolderConfigureExtend_SubForm
{
	public function getTitle()
	{
		return 'WebexAPI settings';
	}
	
	public function init()
	{
		$this->addElement('text', 'webexAPIVendorIntegrationId', array(
			'label'			=> 'Vendor Integration id:',
			'filters'		=> array('StringTrim'),
		));
	}
	
}
