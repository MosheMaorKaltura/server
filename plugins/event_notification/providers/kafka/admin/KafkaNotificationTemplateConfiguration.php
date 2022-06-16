<?php

class Form_KafkaNotificationTemplateConfiguration extends Form_EventNotificationTemplateConfiguration
{
	/* (non-PHPdoc)
	 * @see Infra_Form::getObject()
	 */
	public function getObject($objectType, array $properties, $add_underscore = true, $include_empty_fields = false)
	{
		return parent::getObject($objectType, $properties, $add_underscore, $include_empty_fields);
	}
	
	/* (non-PHPdoc)
	 * @see Infra_Form::populateFromObject()
	 */
	public function populateFromObject($object, $add_underscore = true)
	{
		if (!($object instanceof Kaltura_Client_KafkaNotification_Type_KafkaNotificationTemplate))
			return;
		
		$this->addElement('text', 'topic_name', array(
			'label' => 'Topic Name: ',
			'filters' => array('StringTrim'),
			'readonly' => true,
		));
		
		$this->addElement('text', 'partition_key', array(
			'label' => 'partitionKey: ',
			'filters' => array('StringTrim'),
			'readonly' => true,
		));
		
		$this->addElement('text', 'message_format', array(
			'label' => 'messageFormat: ',
			'filters' => array('StringTrim'),
			'readonly' => true,
		));
		
		$this->addDisplayGroup(array('message_format', 'partition_key'),
			'kafka_config',
			array(
				'decorators' => array('FormElements', 'Fieldset', array('HtmlTag', array('tag' => 'div', 'id' => 'frmAutomaticConfig'))),
				'legend' => 'Kafka Config',
			));
		
		parent::populateFromObject($object, $add_underscore);
		
	}
	
	/* (non-PHPdoc)
	 * @see Form_EventNotificationTemplateConfiguration::addTypeElements()
	 */
	protected function addTypeElements(Kaltura_Client_EventNotification_Type_EventNotificationTemplate $eventNotificationTemplate)
	{
		$format = new Kaltura_Form_Element_EnumSelect('message_format', array(
			'enum' => 'Kaltura_Client_KafkaNotification_Enum_KafkaNotificationFormat',
			'label' => 'Format:',
			'filters' => array('StringTrim'),
			'required' => true,
		));
		
		$this->addElements(array($format));
		
	}
}