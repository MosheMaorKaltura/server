<?php
/**
 * @package plugins.vendor
 * @subpackage model.filters
 */
class VendorFilter extends baseObjectFilter
{
	public function init()
	{
		$this->fields = kArray::makeAssociativeDefaultValue(array(
			'_eq_id',
			'_in_id',
			'_eq_vendor_Type',
			'_in_vendor_Type',
			'_eq_account_id',
			'_eq_status',
			'_in_status',
			'_gte_created_at',
			'_lte_created_at',
			'_gte_updated_at',
			'_lte_updated_at',
			'_eq_partner_id'
		), null);

		$this->allowed_order_fields = array('created_at','updated_at');
	}

	public function getFieldNameFromPeer($field_name)
	{
		return VendorIntegrationPeer::translateFieldName($field_name, $this->field_name_translation_type, BasePeer::TYPE_COLNAME);
	}
	public function getIdFromPeer()
	{
		return VendorIntegrationPeer::ID;
	}
}