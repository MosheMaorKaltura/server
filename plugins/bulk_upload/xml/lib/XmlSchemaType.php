<?php
/**
 * @package plugins.bulkUploadXml
 * @subpackage lib
 */
class XmlSchemaType implements IKalturaPluginEnum, SchemaType
{
	const BULK_UPLOAD_XML = 'bulkUploadXML';
	const BULK_UPLOAD_RESULT_XML = 'bulkUploadResultXML';
	
	/**
	 * 
	 * Returns the dynamic enum additional values
	 */
	public static function getAdditionalValues()
	{
		return array(
			'BULK_UPLOAD_XML' => self::BULK_UPLOAD_XML,
			'BULK_UPLOAD_RESULT_XML' => self::BULK_UPLOAD_RESULT_XML,
		);
	}
}
