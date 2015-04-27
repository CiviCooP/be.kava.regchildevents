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
	public function updateEvents($entity, $operation)
    {

        if($operation != 'create' || !$entity || $entity->entity_table != 'civicrm_event' ) {
            return false;
        }

        try {
            // Get new recurring child event and parent event
            $new_event = civicrm_api3('Event', 'getsingle', array('id' => $entity->entity_id));
            $parent_event = civicrm_api3('Event', 'getsingle', array('id' => $entity->parent_id));
        } catch (CiviCRM_API3_Exception $e) {
            // print_r($e->getMessage()); exit;
            return false;
        }

        // Update child event options if our Recurring Events option is enabled
        if(CRM_Regchildevents_RegHandler::checkIfActiveForEvent($parent_event)) {

            return civicrm_api3('Event', 'create', array(
                'id' => $new_event['id'],
                'is_monetary' => 0,
                'is_online_registration' => 0,
                // 'title' => $new_event['title'] . ' (vervolg)',
            ));
        }

        return true;
    }

}