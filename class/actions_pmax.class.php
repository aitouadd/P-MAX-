<?php
/* Copyright (C) 2026 Custom */

dol_include_once('/pmax/class/pmax.class.php');

/**
 * Hooks for P-MAX module.
 */
class Actionspmax
{
	/** @var DoliDB */
	private $db;

	/** @var string */
	public $resprints = '';

	/** @var bool */
	private $stylePrinted = false;

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Restrict update of outstanding limit to dedicated P-MAX right.
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if ($action !== 'setoutstanding_limit') {
			return 0;
		}

		if (!empty($user->rights->pmax->editlimit)) {
			return 0;
		}

		$langs->loadLangs(array('pmax@pmax'));
		setEventMessages($langs->trans('PMaxNoPermissionEditLimit'), null, 'errors');
		return -1;
	}

	/**
	 * Add designed warning block on customer card.
	 */
	public function addMoreBoxStatsCustomer($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf;

		$langs->loadLangs(array('pmax@pmax'));
		if (empty($object->id)) {
			return 0;
		}

		$pmax = new PMax($this->db);
		$exposure = $pmax->getExposure((int) $object->id);
		if ((float) $exposure['limit'] <= 0) {
			return 0;
		}

		$currency = !empty($conf->currency) ? $conf->currency : '';
		$currencyCode = !empty($currency) ? strtoupper($currency) : 'MAD';
		$displayTotalRaw = min((float) $exposure['total'], (float) $exposure['limit']);
		$total = price((float) $displayTotalRaw, 0, $langs, 1, -1, -1, $currency);
		$limit = price((float) $exposure['limit'], 0, $langs, 1, -1, -1, $currency);
		$remaining = price((float) (isset($exposure['remaining']) ? $exposure['remaining'] : 0), 0, $langs, 1, -1, -1, $currency);
		$orders = price((float) $exposure['orders_undelivered'], 0, $langs, 1, -1, -1, $currency);
		$invoices = price((float) $exposure['invoices_unpaid'], 0, $langs, 1, -1, -1, $currency);
		$shipments = price((float) $exposure['deliveries_unpaid'], 0, $langs, 1, -1, -1, $currency);

		$icoLimit = '<span class="pmax-line-icon">'.img_picto('', 'object_generic').'</span>';
		$icoOrders = '<span class="pmax-line-icon">'.img_picto('', 'object_order').'</span>';
		$icoInvoices = '<span class="pmax-line-icon">'.img_picto('', 'object_bill').'</span>';
		$icoShipments = '<span class="pmax-line-icon">'.img_picto('', 'object_delivery').'</span>';
		$icoTotal = '<span class="pmax-line-icon">'.img_picto('', 'object_statut').'</span>';

		$isBlocked = ((float) $exposure['total'] >= (float) $exposure['limit']);
		$usageRatio = ((float) $exposure['limit'] > 0 ? ((float) $exposure['total'] / (float) $exposure['limit']) : 0);
		$usagePercent = (float) round((float) price2num($usageRatio * 100, 'MT'), 1);
		$usagePercentDisplay = max(0, min(100, $usagePercent));
		$usageBarPercent = $usagePercentDisplay;
		$showCurrencyCode = (stripos($remaining, $currencyCode) === false);

		$stateClass = 'pmax-state-safe';
		$statusText = $langs->trans('PMaxBudgetStatusAvailable');
		if ($usageRatio >= 1) {
			$stateClass = 'pmax-state-danger';
			$statusText = $langs->trans('PMaxBudgetStatusBlocked');
		} elseif ($usageRatio >= 0.8) {
			$stateClass = 'pmax-state-warning';
			$statusText = $langs->trans('PMaxBudgetStatusWarning');
		}

		$box = '';
		$box .= '<div class="pmax-budget-panel '.$stateClass.'">';
		$box .= '<div class="pmax-budget-header"><span class="pmax-header-icon">'.img_picto('', 'object_bill').'</span><span>'.$langs->trans('PMaxCardTitle').'</span></div>';
		$box .= '<div class="pmax-budget-remaining-wrap">';
		$box .= '<div class="pmax-budget-remaining">'.$remaining.'</div>';
		if ($showCurrencyCode) {
			$box .= '<span class="pmax-currency">'.$currencyCode.'</span>';
		}
		$box .= '</div>';
		$box .= '<div class="pmax-budget-status">'.$statusText.'</div>';
		$box .= '<div class="pmax-usage">';
		$box .= '<div class="pmax-usage-row"><span class="pmax-usage-label">'.$langs->trans('PMaxCreditUsage').'</span><span class="pmax-usage-value">'.price2num($usagePercentDisplay, 'MT').' %</span></div>';
		$box .= '<div class="pmax-progress"><span class="pmax-progress-fill" style="width: '.$usageBarPercent.'%;"></span></div>';
		$box .= '</div>';
		$box .= '<div class="pmax-section-title">'.$langs->trans('PMaxFinancialDetails').'</div>';
		$box .= '<div class="pmax-budget-formula">';
		$box .= '<div class="pmax-line">'.$icoLimit.'<span class="pmax-label">'.$langs->trans('PMaxFormulaLimit').' :</span><span class="pmax-value">'.$limit.'</span></div>';
		$box .= '<div class="pmax-line">'.$icoOrders.'<span class="pmax-label">'.$langs->trans('PMaxFormulaOrders').' :</span><span class="pmax-value">'.$orders.'</span></div>';
		$box .= '<div class="pmax-line">'.$icoInvoices.'<span class="pmax-label">'.$langs->trans('PMaxFormulaInvoices').' :</span><span class="pmax-value">'.$invoices.'</span></div>';
		$box .= '<div class="pmax-line">'.$icoShipments.'<span class="pmax-label">'.$langs->trans('PMaxFormulaDeliveries').' :</span><span class="pmax-value">'.$shipments.'</span></div>';
		$box .= '<div class="pmax-line pmax-total">'.$icoTotal.'<span class="pmax-label">'.$langs->trans('PMaxFormulaTotal').' :</span><span class="pmax-value">'.$total.'</span></div>';
		$box .= '</div>';
		$box .= '</div>';

		$this->resprints = $box;

		if (!$isBlocked) {
			return 0;
		}

		$html = '<div class="info-box warning" style="margin-top:12px; border:1px solid #f5b2b2; background:#fff2f2; border-radius:8px; padding:10px 12px;">';
		$html .= '<div style="display:flex; align-items:center; gap:8px; font-weight:600; color:#b10000;">';
		$html .= '<span class="fa fa-exclamation-triangle" aria-hidden="true"></span>';
		$html .= '<span>'.$langs->trans('PMaxNotificationTitle').'</span>';
		$html .= '</div>';
		$html .= '<div style="margin-top:6px; color:#8b0000;">';
		$html .= $langs->trans('PMaxNotificationBody', dol_escape_htmltag($object->name), $total, $limit, $remaining);
		$html .= '</div>';
		$html .= '</div>';

		$this->resprints .= $html;
		return 0;
	}

	/**
	 * Color customer name in red when above P-MAX limit.
	 */
	public function printFieldListValue($parameters, &$object, &$action, $hookmanager)
	{
		$this->resprints = '';
		return 0;
	}
}
