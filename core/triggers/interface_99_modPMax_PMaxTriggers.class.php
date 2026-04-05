<?php
/* Copyright (C) 2026 Custom */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/pmax/class/pmax.class.php');

/**
 * P-MAX triggers.
 */
class InterfacePMaxTriggers extends DolibarrTriggers
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = 'pmax';
		$this->description = 'P-MAX credit ceiling triggers';
		$this->version = self::VERSIONS['dev'];
		$this->picto = 'generic';
	}

	/**
	 * Run trigger.
	 *
	 * @param string $action
	 * @param CommonObject $object
	 * @param User $user
	 * @param Translate $langs
	 * @param Conf $conf
	 * @return int
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		$guardedActions = array(
			'PROPAL_CREATE',
			'ORDER_CREATE',
			'SHIPPING_CREATE',
			'BILL_CREATE'
		);

		if (!isModEnabled('pmax')) {
			return 0;
		}

		if (!in_array($action, $guardedActions)) {
			return 0;
		}

		$socid = $this->extractSocid($object);
		if ($socid <= 0) {
			return 0;
		}

		$pmax = new PMax($this->db);
		$exposure = $pmax->getExposure($socid);
		if ((float) $exposure['limit'] <= 0) {
			return 0;
		}

		// At/over limit: strict block for all guarded flows.
		if ((float) $exposure['total'] >= (float) $exposure['limit']) {
			$exposure['total'] = (float) price2num(max((float) $exposure['total'], (float) $exposure['limit']), 'MT');
			$exposure['remaining'] = 0.0;
			$exposure['is_over_limit'] = true;
			$message = $pmax->buildBlockMessage($exposure);

			$this->error = $message;
			$this->errors[] = $message;
			$object->error = $message;
			if (!is_array($object->errors)) {
				$object->errors = array();
			}
			$object->errors[] = $message;

			return -1;
		}

		$projectedIncrease = $this->getProjectedAmountFromCurrentObject($action, $object);
		$projectedTotal = price2num($exposure['total'] + $projectedIncrease, 'MT');

		if ($projectedTotal > (float) $exposure['limit']) {
			$exposure['total'] = (float) $projectedTotal;
			$exposure['remaining'] = (float) price2num(max(0, (float) $exposure['limit'] - $projectedTotal), 'MT');
			$exposure['is_over_limit'] = true;
			$message = $pmax->buildBlockMessage($exposure);

			$this->error = $message;
			$this->errors[] = $message;
			$object->error = $message;
			if (!is_array($object->errors)) {
				$object->errors = array();
			}
			$object->errors[] = $message;

			return -1;
		}

		return 0;
	}

	/**
	 * @param CommonObject $object
	 * @return int
	 */
	private function extractSocid($object)
	{
		if (!empty($object->socid)) {
			return (int) $object->socid;
		}
		if (!empty($object->fk_soc)) {
			return (int) $object->fk_soc;
		}
		return 0;
	}

	/**
	 * @param string $action
	 * @param CommonObject $object
	 * @return float
	 */
	private function getProjectedAmountFromCurrentObject($action, $object)
	{
		$amount = 0.0;

		if (in_array($action, array('PROPAL_CREATE', 'ORDER_CREATE'))) {
			$amount = (float) price2num(!empty($object->total_ttc) ? $object->total_ttc : 0, 'MT');
		}

		if ($amount < 0) {
			$amount = 0;
		}

		return (float) $amount;
	}


	/**
	 * @param CommonObject $object
	 * @return float
	 */
	private function getShipmentProjectedAmount($object)
	{
		if (!empty($object->id)) {
			$sql = "SELECT COALESCE(SUM(CASE";
			$sql .= " WHEN cd.qty IS NULL OR cd.qty = 0 THEN 0";
			$sql .= " ELSE (COALESCE(ed.qty, 0) / cd.qty) * cd.total_ttc";
			$sql .= " END), 0) as total_ttc";
			$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet as ed";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet as cd ON cd.rowid = ed.fk_elementdet";
			$sql .= " WHERE ed.fk_expedition = ".((int) $object->id);
			$sql .= " AND (ed.element_type = 'commande' OR ed.element_type IS NULL OR ed.element_type = '')";

			$resql = $this->db->query($sql);
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				$amount = (float) price2num(empty($obj->total_ttc) ? 0 : $obj->total_ttc, 'MT');
				if ($amount > 0) {
					return $amount;
				}
			}
		}

		if (!empty($object->origin) && $object->origin === 'commande' && !empty($object->origin_id)) {
			$sql = "SELECT total_ttc FROM ".MAIN_DB_PREFIX."commande";
			$sql .= " WHERE rowid = ".((int) $object->origin_id);
			$sql .= " AND entity IN (".getEntity('commande').")";

			$resql = $this->db->query($sql);
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				return (float) price2num(empty($obj->total_ttc) ? 0 : $obj->total_ttc, 'MT');
			}
		}

		return (float) price2num(!empty($object->total_ttc) ? $object->total_ttc : 0, 'MT');
	}
}
