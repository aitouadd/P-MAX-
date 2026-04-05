<?php
/* Copyright (C) 2026 Custom */

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
if (isModEnabled('order')) {
	require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
}

/**
 * P-MAX business calculator.
 */
class PMax
{
	/** @var DoliDB */
	private $db;

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Get effective customer limit.
	 * Priority: customer outstanding_limit > PMAX_DEFAULT_LIMIT.
	 *
	 * @param int $socid
	 * @return float
	 */
	public function getCustomerLimit($socid)
	{
		$socid = (int) $socid;
		if ($socid <= 0) {
			return 0;
		}

		$sql = "SELECT outstanding_limit";
		$sql .= " FROM ".MAIN_DB_PREFIX."societe";
		$sql .= " WHERE rowid = ".$socid;
		$sql .= " AND entity IN (".getEntity('societe').")";

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj && price2num($obj->outstanding_limit, 'MT') > 0) {
				return (float) price2num($obj->outstanding_limit, 'MT');
			}
		}

		$defaultLimit = (float) price2num(getDolGlobalString('PMAX_DEFAULT_LIMIT', '0'), 'MT');
		return ($defaultLimit > 0 ? $defaultLimit : 0);
	}

	/**
	 * Calculate P-MAX exposure.
	 *
	 * @param int $socid
	 * @return array{limit:float,invoices_unpaid:float,deliveries_unpaid:float,orders_undelivered:float,total:float,is_over_limit:bool}
	 */
	public function getExposure($socid)
	{
		$socid = (int) $socid;
		$limit = $this->getCustomerLimit($socid);

		$invoicesUnpaid = $this->getUnpaidInvoicesAmount($socid);
		$ordersUndelivered = $this->getUndeliveredOrdersAmount($socid);
		$deliveriesUnpaid = $this->getUnpaidDeliveriesAmount($socid);

		$total = price2num($invoicesUnpaid + $ordersUndelivered + $deliveriesUnpaid, 'MT');
		$remaining = ($limit > 0 ? price2num($limit - $total, 'MT') : 0);
		if ($remaining < 0) {
			$remaining = 0;
		}

		return array(
			'limit' => (float) $limit,
			'invoices_unpaid' => (float) price2num($invoicesUnpaid, 'MT'),
			'deliveries_unpaid' => (float) price2num($deliveriesUnpaid, 'MT'),
			'orders_undelivered' => (float) price2num($ordersUndelivered, 'MT'),
			'total' => (float) $total,
			'remaining' => (float) $remaining,
			'is_over_limit' => ($limit > 0 && $total >= $limit),
		);
	}

	/**
	 * Format a human-readable block reason.
	 *
	 * @param array<string,mixed> $exposure
	 * @return string
	 */
	public function buildBlockMessage(array $exposure)
	{
		global $conf, $langs;

		$langs->loadLangs(array('pmax@pmax'));

		$currency = !empty($conf->currency) ? $conf->currency : '';
		$limitRaw = (float) $exposure['limit'];
		$totalRaw = (float) $exposure['total'];
		$displayTotalRaw = ($limitRaw > 0 ? min($totalRaw, $limitRaw) : $totalRaw);
		$total = price($displayTotalRaw, 0, $langs, 1, -1, -1, $currency);
		$limit = price($limitRaw, 0, $langs, 1, -1, -1, $currency);
		$remaining = price((float) (isset($exposure['remaining']) ? $exposure['remaining'] : 0), 0, $langs, 1, -1, -1, $currency);

		return $langs->trans('PMaxBlockMessage', $total, $limit, $remaining);
	}

	/**
	 * @param int $socid
	 * @return float
	 */
	private function getUnpaidInvoicesAmount($socid)
	{
		if ($socid <= 0 || !isModEnabled('invoice')) {
			return 0;
		}

		$thirdparty = new Societe($this->db);
		if ($thirdparty->fetch((int) $socid) <= 0) {
			return 0;
		}

		$tmp = $thirdparty->getOutstandingBills('customer', 0);
		return (float) price2num(empty($tmp['opened']) ? 0 : $tmp['opened'], 'MT');
	}

	/**
	 * @param int $socid
	 * @return float
	 */
	private function getUndeliveredOrdersAmount($socid)
	{
		if ($socid <= 0 || !isModEnabled('order')) {
			return 0;
		}

		$sql = "SELECT COALESCE(SUM(CASE";
		$sql .= " WHEN cd.qty IS NULL OR cd.qty <= 0 THEN 0";
		$sql .= " WHEN (cd.qty - COALESCE(s.qty_shipped, 0)) <= 0 THEN 0";
		$sql .= " ELSE ((cd.qty - COALESCE(s.qty_shipped, 0)) / cd.qty) * cd.total_ttc";
		$sql .= " END), 0) as total";
		$sql .= " FROM ".MAIN_DB_PREFIX."commande as c";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."commandedet as cd ON cd.fk_commande = c.rowid";
		$sql .= " LEFT JOIN (";
		$sql .= "   SELECT ed.fk_elementdet as fk_commandedet, SUM(ed.qty) as qty_shipped";
		$sql .= "   FROM ".MAIN_DB_PREFIX."expeditiondet as ed";
		$sql .= "   INNER JOIN ".MAIN_DB_PREFIX."expedition as e ON e.rowid = ed.fk_expedition";
		$sql .= "   WHERE (ed.element_type = 'commande' OR ed.element_type IS NULL OR ed.element_type = '')";
		$sql .= "   AND e.entity IN (".getEntity('expedition').")";
		$sql .= "   AND e.fk_statut > 0";
		$sql .= "   GROUP BY ed.fk_elementdet";
		$sql .= " ) as s ON s.fk_commandedet = cd.rowid";
		$sql .= " WHERE c.fk_soc = ".((int) $socid);
		$sql .= " AND c.entity IN (".getEntity('commande').")";
		$sql .= " AND COALESCE(c.facture, 0) = 0";
		$sql .= " AND c.fk_statut IN (".Commande::STATUS_VALIDATED.", ".Commande::STATUS_SHIPMENTONPROCESS.")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}

		$obj = $this->db->fetch_object($resql);
		return (float) price2num(empty($obj->total) ? 0 : $obj->total, 'MT');
	}

	/**
	 * @param int $socid
	 * @return float
	 */
	private function getUnpaidDeliveriesAmount($socid)
	{
		if ($socid <= 0 || !isModEnabled('shipping')) {
			return 0;
		}

		if (!getDolGlobalInt('PMAX_INCLUDE_DELIVERYPAYMENT', 1)) {
			return 0;
		}

		$sql = "SELECT COALESCE(SUM(CASE";
		$sql .= " WHEN cd.qty IS NULL OR cd.qty = 0 THEN 0";
		$sql .= " ELSE (COALESCE(ed.qty, 0) / cd.qty) * cd.total_ttc";
		$sql .= " END), 0) as total";
		$sql .= " FROM ".MAIN_DB_PREFIX."expedition as e";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expeditiondet as ed ON ed.fk_expedition = e.rowid";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet as cd ON cd.rowid = ed.fk_elementdet";
		$sql .= " WHERE e.fk_soc = ".((int) $socid);
		$sql .= " AND e.entity IN (".getEntity('expedition').")";
		$sql .= " AND e.fk_statut > 0";
		$sql .= " AND COALESCE(e.billed, 0) = 0";
		$sql .= " AND (ed.element_type = 'commande' OR ed.element_type IS NULL OR ed.element_type = '')";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}

		$obj = $this->db->fetch_object($resql);
		return (float) price2num(empty($obj->total) ? 0 : $obj->total, 'MT');
	}

	/**
	 * @param string $tableName
	 * @return bool
	 */
	private function tableExists($tableName)
	{
		$sql = "SHOW TABLES LIKE '".$this->db->escape($tableName)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}

		return ((int) $this->db->num_rows($resql) > 0);
	}
}
