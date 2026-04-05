<?php
/* Copyright (C) 2026 Custom */

$res = 0;
if (!$res && file_exists('../main.inc.php')) {
	$res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

$langs->loadLangs(array('companies', 'pmax@pmax'));

if (empty($user->rights->pmax->editlimit) && empty($user->rights->pmax->write)) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$search_name = trim(GETPOST('search_name', 'alphanohtml'));
$search_code = trim(GETPOST('search_code', 'alphanohtml'));
$page = max(0, GETPOSTINT('page'));
$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : 100;
$offset = $page * $limit;

if ($action === 'setpmax') {
	$token = GETPOST('token', 'alpha');
	if (!function_exists('currentToken') || $token !== currentToken()) {
		accessforbidden('Invalid token');
	}

	$socid = GETPOSTINT('socid');
	$pmaxValueRaw = GETPOST('pmax_value', 'alphanohtml');
	$pmaxValue = price2num($pmaxValueRaw, 'MT');
	if ($pmaxValue < 0) {
		$pmaxValue = 0;
	}

	if ($socid > 0) {
		$sql = "UPDATE ".MAIN_DB_PREFIX."societe";
		$sql .= " SET outstanding_limit = ".($pmaxValue > 0 ? $pmaxValue : 'NULL');
		$sql .= " WHERE rowid = ".((int) $socid);
		$sql .= " AND entity IN (".getEntity('societe').")";
		$resql = $db->query($sql);
		if ($resql) {
			setEventMessages($langs->trans('PMaxSavedForCustomer'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('Error').' '.$db->lasterror(), null, 'errors');
		}
	}
}

$sqlCount = "SELECT COUNT(*) as nb";
$sqlCount .= " FROM ".MAIN_DB_PREFIX."societe as s";
$sqlCount .= " WHERE s.entity IN (".getEntity('societe').")";
$sqlCount .= " AND s.client IN (1, 2, 3)";
if ($search_name !== '') {
	$sqlCount .= natural_search('s.nom', $search_name);
}
if ($search_code !== '') {
	$sqlCount .= natural_search('s.code_client', $search_code);
}

$totalNb = 0;
$resCount = $db->query($sqlCount);
if ($resCount) {
	$objCount = $db->fetch_object($resCount);
	$totalNb = (int) $objCount->nb;
}

$sql = "SELECT s.rowid, s.nom, s.code_client, s.outstanding_limit";
$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
$sql .= " WHERE s.entity IN (".getEntity('societe').")";
$sql .= " AND s.client IN (1, 2, 3)";
if ($search_name !== '') {
	$sql .= natural_search('s.nom', $search_name);
}
if ($search_code !== '') {
	$sql .= natural_search('s.code_client', $search_code);
}
$sql .= " ORDER BY s.nom ASC";
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);

$form = new Form($db);
llxHeader('', $langs->trans('PMaxCustomersTitle'));

$linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php?leftmenu=thirdparties">'.$langs->trans('BackToList').'</a>';
print load_fiche_titre($langs->trans('PMaxCustomersTitle'), $linkback, 'generic');

print '<div class="opacitymedium" style="margin-bottom:10px;">'.$langs->trans('PMaxCustomersHelp').'</div>';

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<table class="noborder" style="max-width:900px;">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Search').'</td><td>'.$langs->trans('CustomerCode').'</td><td>'.$langs->trans('RecordsPerPage').'</td><td></td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td><input type="text" class="flat" name="search_name" value="'.dol_escape_htmltag($search_name).'" size="32"></td>';
print '<td><input type="text" class="flat" name="search_code" value="'.dol_escape_htmltag($search_code).'" size="20"></td>';
print '<td><input type="number" class="flat" name="limit" value="'.$limit.'" min="10" max="500"></td>';
print '<td><input type="submit" class="button" value="'.$langs->trans('Filter').'"></td>';
print '</tr>';
print '</table>';
print '</form>';

$param = '&search_name='.urlencode($search_name).'&search_code='.urlencode($search_code).'&limit='.$limit;
print_barre_liste($langs->trans('Customers'), $page, $_SERVER['PHP_SELF'], $param, 's.nom', 'ASC', '', $totalNb, $totalNb, '', 0, '', '', $limit);

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Ref').'</th>';
print '<th>'.$langs->trans('ThirdPartyName').'</th>';
print '<th>'.$langs->trans('CustomerCode').'</th>';
print '<th class="right">'.$langs->trans('PMaxLimit').'</th>';
print '<th class="right">'.$langs->trans('Action').'</th>';
print '</tr>';

if ($resql) {
	$num = $db->num_rows($resql);
	$socstatic = new Societe($db);
	for ($i = 0; $i < min($num, $limit); $i++) {
		$obj = $db->fetch_object($resql);
		print '<tr class="oddeven">';
		print '<td>'.((int) $obj->rowid).'</td>';

		$socstatic->id = $obj->rowid;
		$socstatic->name = $obj->nom;
		print '<td>'.$socstatic->getNomUrl(1).'</td>';

		print '<td>'.dol_escape_htmltag($obj->code_client).'</td>';

		print '<td class="right">';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?page='.$page.$param.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="setpmax">';
		print '<input type="hidden" name="socid" value="'.((int) $obj->rowid).'">';
		print '<input type="text" class="flat right" name="pmax_value" value="'.dol_escape_htmltag((string) price2num($obj->outstanding_limit, 'MT')).'" size="10">';
		print '</td>';
		print '<td class="right">';
		print '<input type="submit" class="button button-edit" value="'.$langs->trans('Save').'">';
		print '</form>';
		print '</td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';

llxFooter();
$db->close();
