<?php
/* Copyright (C) 2026 Custom */

$res = 0;
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

$langs->loadLangs(array('admin', 'pmax@pmax'));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

if ($action === 'setconf') {
	$defaultLimit = price2num(GETPOST('PMAX_DEFAULT_LIMIT', 'alphanohtml'), 'MT');
	if ($defaultLimit < 0) {
		$defaultLimit = 0;
	}

	$includeDelivery = GETPOSTINT('PMAX_INCLUDE_DELIVERYPAYMENT');

	dolibarr_set_const($db, 'PMAX_DEFAULT_LIMIT', (string) $defaultLimit, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'PMAX_INCLUDE_DELIVERYPAYMENT', (string) $includeDelivery, 'chaine', 0, '', $conf->entity);

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

llxHeader('', $langs->trans('PMaxSetup'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('PMaxSetup'), $linkback, 'title_setup');

$head = array();
$head[0][0] = DOL_URL_ROOT.'/custom/pmax/admin/setup.php';
$head[0][1] = $langs->trans('Settings');
$head[0][2] = 'settings';
print dol_get_fiche_head($head, 'settings', $langs->trans('Module').' '.$langs->trans('PMaxModuleName'), -1, 'generic');

$form = new Form($db);
$defaultLimit = getDolGlobalString('PMAX_DEFAULT_LIMIT', '0');
$includeDelivery = getDolGlobalInt('PMAX_INCLUDE_DELIVERYPAYMENT', 1);

print '<div class="opacitymedium">'.$langs->trans('PMaxSetupHelp').'</div><br>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="setconf">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td class="center">'.$langs->trans('Value').'</td></tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('PMaxDefaultLimit').'</td>';
print '<td class="center">';
print '<input type="text" class="flat" name="PMAX_DEFAULT_LIMIT" value="'.dol_escape_htmltag($defaultLimit).'" size="12">';
print '<div class="opacitymedium small">'.$langs->trans('PMaxDefaultLimitHelp').'</div>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('PMaxIncludeDeliveryPayment').'</td>';
print '<td class="center">'.$form->selectyesno('PMAX_INCLUDE_DELIVERYPAYMENT', $includeDelivery, 1).'</td>';
print '</tr>';

print '</table>';
print '<br><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
