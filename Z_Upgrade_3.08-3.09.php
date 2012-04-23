<?php
/* $Id$*/
include('includes/session.inc');
$title = _('Upgrade webERP 3.08 - 3.09');
include('includes/header.inc');


prnMsg(_('This script will run perform any modifications to the database since v 3.08 required to allow the additional functionality in version 3.09 scripts'),'info');

echo '<p><form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '"></p>';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
echo '<button type="submit" name="DoUpgrade">' . _('Perform Upgrade') . '</button>';
echo '</form>';

if ($_POST['DoUpgrade'] == _('Perform Upgrade')){

	$SQLScriptFile = file('./sql/mysql/upgrade3.08-3.09.sql');

	$ScriptFileEntries = sizeof($SQLScriptFile);
	$ErrMsg = _('The script to upgrade the database failed because');
	$SQL ='';
	$InAFunction = false;

	for ($i=0; $i<=$ScriptFileEntries; $i++) {

		$SQLScriptFile[$i] = trim($SQLScriptFile[$i]);

		if (substr($SQLScriptFile[$i], 0, 2) != '--'
			AND substr($SQLScriptFile[$i], 0, 3) != 'USE'
			AND strstr($SQLScriptFile[$i],'/*')==FALSE
			AND strlen($SQLScriptFile[$i])>1){

			$SQL .= ' ' . $SQLScriptFile[$i];

			//check if this line kicks off a function definition - pg chokes otherwise
			if (substr($SQLScriptFile[$i],0,15) == 'CREATE FUNCTION'){
				$InAFunction = true;
			}
			//check if this line completes a function definition - pg chokes otherwise
			if (substr($SQLScriptFile[$i],0,8) == 'LANGUAGE'){
				$InAFunction = false;
			}
			if (strpos($SQLScriptFile[$i],';')>0 AND ! $InAFunction){
				$SQL = substr($SQL,0,strlen($SQL)-1);
				$result = DB_query($SQL, $db, $ErrMsg);
				$SQL='';
			}

		} //end if its a valid sql line not a comment
	} //end of for loop around the lines of the sql script

	/*Now run the data conversions required. */
	$result = DB_query("UPDATE bankaccounts SET currcode='" . $_SESSION['CompanyRecord']['currencydefault'] . "'",$db);

} /*Dont do upgrade */

include('includes/footer.inc');
?>
