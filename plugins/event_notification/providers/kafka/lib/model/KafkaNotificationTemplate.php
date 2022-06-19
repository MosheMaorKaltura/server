<?php
require_once(dirname(__file__) . "/../../../../../../vendor/avro/flix-tech/confluent-schema-registry-api/vendor/autoload.php");

use FlixTech\SchemaRegistryApi\Registry\BlockingRegistry;
use FlixTech\SchemaRegistryApi\Registry\PromisingRegistry;
use GuzzleHttp\Client;

/**
 * @package plugins.kafkaNotification
 * @subpackage model
 */
class KafkaNotificationTemplate extends EventNotificationTemplate
{
	const CUSTOM_DATA_TOPIC_NAME = 'topicName';
	const CUSTOM_DATA_PARTITION_KEY = 'partitionKey';
	const CUSTOM_DATA_MESSAGE_FORMAT = 'messageFormat';
	const CUSTOM_DATA_API_OBJECT_TYPE = 'apiObjectType';
	
	public function __construct()
	{
		$this->setType(KafkaNotificationPlugin::getKafkaNotificationTemplateTypeCoreValue(KafkaNotificationTemplateType::KAFKA));
		parent::__construct();
	}
	
	public function fulfilled(kEventScope $scope)
	{
		if (!kCurrentContext::$serializeCallback)
			return false;
		
		if (!parent::fulfilled($scope))
			return false;
		
		return true;
	}
	
	public function setTopicName($value)
	{
		return $this->putInCustomData(self::CUSTOM_DATA_TOPIC_NAME, $value);
	}
	
	public function getTopicName()
	{
		return $this->getFromCustomData(self::CUSTOM_DATA_TOPIC_NAME);
	}
	
	public function setPartitionKey($value)
	{
		return $this->putInCustomData(self::CUSTOM_DATA_PARTITION_KEY, $value);
	}
	
	public function getPartitionKey()
	{
		return $this->getFromCustomData(self::CUSTOM_DATA_PARTITION_KEY);
	}
	
	public function setMessageFormat($value)
	{
		return $this->putInCustomData(self::CUSTOM_DATA_MESSAGE_FORMAT, $value);
	}
	
	public function getMessageFormat()
	{
		return $this->getFromCustomData(self::CUSTOM_DATA_MESSAGE_FORMAT);
	}
	
	public function setApiObjectType($value)
	{
		return $this->putInCustomData(self::CUSTOM_DATA_API_OBJECT_TYPE, $value);
	}
	
	public function getApiObjectType()
	{
		return $this->getFromCustomData(self::CUSTOM_DATA_API_OBJECT_TYPE);
	}
	
	
	public function dispatch(kScope $scope)
	{
		KalturaLog::debug("Dispatching event notification with name [{$this->getName()}] systemName [{$this->getSystemName()}]");
		if (!$scope || !($scope instanceof kEventScope))
		{
			KalturaLog::err('Failed to dispatch due to incorrect scope [' . $scope . ']');
			return;
		}
		
		$object = $scope->getEvent()->getObject();
		if(!$object)
		{
			KalturaLog::debug("Object not found breaking event handling flow");
			return;
		}
		
		$topicName = $this->getTopicName();
		$partitionKey = $this->getPartitionKey();
		$messageFormat = $this->getMessageFormat();
		
		$getter = "get" . ucfirst($partitionKey);
		if(!is_callable(array($object, $getter)))
		{
			KalturaLog::debug("Partition key getter not found on object");
			return;
		}
		
		$oldValues = array();
		$partitionKeyValue = $object->$getter();
		if($scope->getEvent() instanceof kObjectChangedEvent)
		{
			$oldValues = $scope->getEvent()->getModifiedColumns();
		}
		
		$uniqueId = (string)new UniqueId();
		$eventTime = date('Y-m-d H:i:s');
		$eventType = get_class($scope->getEvent());
		$objectType = get_class($object);
		$oldValues = $oldValues;
		
		$apiObjectType = $this->getApiObjectType();
		$apiObject = call_user_func(kCurrentContext::$serializeCallback, $object, $apiObjectType, 1);
		
		$msg = array(
			"uniqueId" => $uniqueId,
			"eventTime" => $eventTime,
			"eventType" => $eventType,
			"objectType" => $objectType,
			"virtualEventId" => kCurrentContext::$virtual_event_id,
			"object" => $apiObject,
			"oldValues" => $oldValues
		);
		
		try
		{
			$subject = $topicName . '-value';
			$queueProvider = QueueProvider::getInstance(KafkaPlugin::getKafakaQueueProviderTypeCoreValue('Kafka'));
			
			if($messageFormat == KafkaNotificationFormat::AVRO)
			{
				$promisingRegistry = new PromisingRegistry(new Client(['base_uri' => '192.168.56.1:8081']));
				$schemaRegistry = new BlockingRegistry($promisingRegistry);
					
				$schemaId = $schemaRegistry->schemaId("schemaName");
				$schema = $schemaRegistry->schemaForId($schemaId);
				
				$io = new \AvroStringIO();
				$io->write(pack('C', 0));
				$io->write(pack('N', $schemaId));
				$encoder = new \AvroIOBinaryEncoder($io);
				$writer = new \AvroIODatumWriter($schema);
				$writer->write($msg, $encoder);
				$kafkaPayload = $io->string();
				
			}
			elseif($messageFormat == KafkaNotificationFormat::JSON)
			{
				$kafkaPayload = json_encode($msg);
				KalturaLog::debug("Payload is " . print_r($kafkaPayload, true));
			}
			else
			{
				KalturaLog::debug("Unknown notification message format [$messageFormat]");
			}
			
			$queueProvider->send($topicName, $partitionKeyValue, $kafkaPayload);
		}
		catch (Exception $e)
		{
			KalturaLog::debug("Failed to send message with error [" . $e->getMessage() . "]");
			throw $e;
		}
	}
	
	public function create($queueKey)
	{
		// get instance of activated queue proivder and create queue with given name
		$queueProvider = QueueProvider::getInstance();
		$queueProvider->create($queueKey);
	}
	
	public function exists($queueKey)
	{
		// get instance of activated queue proivder and check whether given queue exists
		$queueProvider = QueueProvider::getInstance();
		return $queueProvider->exists($queueKey);
	}
}
