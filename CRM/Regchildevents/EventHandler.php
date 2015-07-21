<?php

/**
 * Class CRM_Regchildevents_EventHandler
 */
class CRM_Regchildevents_EventHandler {

    /**
     * Called by civicrm_hook_post handler on creation of a recurring entity.
     * @param CRM_Core_BAO_RecurringEntity $entity Recurring entity
     * @param string $operation Operation
     * @return bool Success
     */
    public function updateEvents($entity, $operation) {

        if ($operation != 'create' || !$entity || $entity->entity_table != 'civicrm_event') {
            return false;
        }

        try {
            // Get (new) recurring child event and parent event
            $new_event = civicrm_api3('Event', 'getsingle', array('id' => $entity->entity_id));
            $parent_event = civicrm_api3('Event', 'getsingle', array('id' => $entity->parent_id));
        } catch (CiviCRM_API3_Exception $e) {
            CRM_Core_Session::setStatus('An error occurred updating child events: ' . $e->getMessage(), 'Error', 'error');
            return false;
        }

        if($new_event['id'] == $parent_event['id']) {
            return false;
        }

        // Update child event options if our Recurring Events option is enabled
        if (CRM_Regchildevents_RegHandler::checkIfActiveForEvent($parent_event)) {

            // Repeating events: no online registration, no payments
            $params = array(
                'id'                     => $new_event['id'],
                'is_monetary'            => 0,
                'is_online_registration' => 0,
                'is_email_confirm'       => 0,
                // 'title' => $new_event['title'] . ' (vervolg)',
            );

            // Copy all custom fields (that should cover it, eh?)
            foreach ($parent_event as $pe_key => $pe_value) {
                if (strpos($pe_key, 'custom_') !== false && $pe_key != CRM_Regchildevents_RegHandler::getActiveForEventFieldId())
                    $params[$pe_key] = $pe_value;
            }

            // Update event
            $ret = civicrm_api3('Event', 'create', $params);
            if (!$ret || $ret['is_error'])
                return false;
        }

        return true;
    }

    /* Not in use anymore: get key for custom field by name
    private function getCustomFieldKey($name) {

        try {
            $customField = civicrm_api3('CustomField', 'getsingle', array('name' => $name));
            return 'custom_' . $customField['id'];
        } catch(CiviCRM_API3_Exception $e) {
            throw new CRM_Regchildevents_Exception('Custom field \'' . $name . '\' is not defined.');
        }
    } */

}