<?php
/*
 $Id: adm_defquotas.php,v 1.4 2006/01/24 05:03:30 joe Exp $
 ----------------------------------------------------------------------
 AlternC - Web Hosting System
 Copyright (C) 2006 Le r�seau Koumbit Inc.
 http://koumbit.org/
 Copyright (C) 2002 by the AlternC Development Team.
 http://alternc.org/
 ----------------------------------------------------------------------
 Based on:
 Valentin Lacambre's web hosting softwares: http://altern.org/
 ----------------------------------------------------------------------
 LICENSE

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License (GPL)
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 To read the license please visit http://www.gnu.org/copyleft/gpl.html
 ----------------------------------------------------------------------
 Original Author of file: Benjamin Sonntag
 Purpose of file: Manage the default quotas
 ----------------------------------------------------------------------
*/
require_once("../class/config.php");

if (!$admin->enabled) {
	__("This page is restricted to authorized staff");
	exit();
}

include_once ("head.php");

?>
<h3><?php __("Change the default quotas"); ?></h3>
<hr id="topbar"/>
<br />
<?php
	if (isset($error) && $error) {
	  echo "<p class=\"error\">$error</p>";
	}

?>
<form method="post" action="adm_dodefquotas.php">
<p>
<input type="hidden" name="action" value="add" />
<input type="text" name="type" class="int" />
<input type="submit" class="inb" value="<?php __("Add account type"); ?>" />
</p>
</form>

<?php
?>
<form method="post" action="adm_dodefquotas.php">
<table border="0" cellpadding="4" cellspacing="0">
<p>
<input type="hidden" name="action" value="delete" />
<tr class="lst1">
<td>
<select name="type" id="type" class="inl">
<?php
$db->query("SELECT distinct(type) FROM defquotas WHERE TYPE != 'default' ORDER by type");
while($db->next_record()) {
  $type = $db->f("type");
  echo "<option value=\"$type\">$type</option>\n";
}
?></select>
</td><td><input type="submit" class="inb" value="<?php __("Delete account type"); ?>" /></td>
</tr>
</table>
</form>
<p>
<?php __("Here is the list of the quotas on the server for the new accounts. If you want to change them, enter new values"); ?>
</p>

<form method="post" action="adm_dodefquotas.php">
<div>
<input type="hidden" name="action" value="modify" />
<?php
$col=1;
$qarray=$quota->qlist();
$qlist=$quota->getdefaults();
reset($qlist);
foreach($qlist as $type => $q) {
?>
<div>
<h4><?php echo _("Accounts of type"). " \"$type\"" ?></h4>
<table border="0" cellpadding="4" cellspacing="0">
<tr><th><?php __("Quotas") ?></th><th><?php __("Default Value"); ?></th></tr>
<?php
foreach($q as $name => $value) {
	$key = $type . ":" . $name;
	$col=3-$col;
?>

<tr class="lst<?php echo $col; ?>">
<td><label for="<?php echo $key; ?>"><?php $name= ($name=="stats")? 'sta2': $name ;echo $qarray[$name]= ($qarray[$name]=="quota_sta2") ? 'quota_stats':  $qarray[$name]  ; ?></label></td>
<td><input type="text" class="int" size="16" maxlength="16" name="<?php echo $key; ?>" id="<?php echo $name; ?>" value="<?php ehe($value); ?>" /></td></tr>

<?php
  }
?>
</table>
</div>
<?php
}
?>
<input type="submit" class="inb" value="<?php __("Edit the default quotas"); ?>" />
</div>
</form>
<?php include_once("foot.php"); ?>
