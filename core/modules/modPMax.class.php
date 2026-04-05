<?php
/* Copyright (C) 2026 Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Module descriptor for P-MAX.
 */
class modPMax extends DolibarrModules
{
	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->numero = 104202;
		$this->rights_class = 'pmax';
		$this->family = 'crm';
		$this->module_position = 610;
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Customer P-MAX credit ceiling with automatic blocking and alerts';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'generic';
		$this->editor_name = 'Custom';
		$this->editor_url = '';
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(17, 0);

		$this->langfiles = array('pmax@pmax');
		$this->config_page_url = array('setup.php@pmax');

		$this->module_parts = array(
			'triggers' => 1,
			'hooks' => array('thirdpartylist', 'thirdpartycomm', 'globalcard'),
			'css' => array('/pmax/css/pmax.css'),
			'js' => array('/pmax/js/pmax.js')
		);

		$this->depends = array('modSociete', 'modCommande', 'modFacture');
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->dirs = array('/pmax/temp');

		$this->const = array(
			array('PMAX_DEFAULT_LIMIT', 'chaine', '0', 'Default P-MAX limit when customer outstanding limit is empty', 0, 'current', 1),
			array('PMAX_INCLUDE_DELIVERYPAYMENT', 'chaine', '1', 'Include unpaid delivery notes (DeliveryPayment module)', 0, 'current', 1),
		);

		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero.'01';
		$this->rights[$r][1] = 'Read P-MAX indicators';
		$this->rights[$r][4] = 'read';
		$r++;

		$this->rights[$r][0] = $this->numero.'02';
		$this->rights[$r][1] = 'Configure P-MAX';
		$this->rights[$r][4] = 'write';
		$r++;

		$this->rights[$r][0] = $this->numero.'03';
		$this->rights[$r][1] = 'Edit customer P-MAX limit on third-party card';
		$this->rights[$r][4] = 'editlimit';
		$r++;

	}

	/**
	 * Init module.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function init($options = '')
	{
		return $this->_init(array(), $options);
	}

	/**
	 * Remove module.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function remove($options = '')
	{
		return $this->_remove(array());
	}
}
