<?php
/* $Id$*/

include('includes/session.inc');

$title = _('Tax Categories');

include('includes/header.inc');

echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/maintenance.png" title="' . _('Supplier Types') . '" alt="" />' . $title. '</p>';

if ( isset($_GET['SelectedTaxCategory']) )
	$SelectedTaxCategory = $_GET['SelectedTaxCategory'];
elseif (isset($_POST['SelectedTaxCategory']))
	$SelectedTaxCategory = $_POST['SelectedTaxCategory'];

if (isset($_POST['submit'])) {

	//initialise no input errors assumed initially before we test

	$InputError = 0;

	/* actions to take once the user has clicked the submit button
	ie the page has called itself with some user input */

	//first off validate inputs sensible

	if (ContainsIllegalCharacters($_POST['TaxCategoryName'])) {
		$InputError = 1;
		prnMsg( _('The tax category name contains illegal characters'),'error');
	}
	if (trim($_POST['TaxCategoryName']) == '') {
		$InputError = 1;
		prnMsg( _('The tax category name may not be empty'), 'error');
	}

	if ($_POST['SelectedTaxCategory']!='' AND $InputError !=1) {

		/*SelectedTaxCategory could also exist if submit had not been clicked this code would not run in this case cos submit is false of course  see the delete code below*/
		// Check the name does not clash
		$sql = "SELECT * FROM taxcategories
				WHERE taxcatid <> '" . $SelectedTaxCategory ."'
				AND taxcatname ".LIKE." '" . $_POST['TaxCategoryName'] . "'";
		$result = DB_query($sql,$db);

		if (DB_num_rows($result)>0) {
			$InputError = 1;
			prnMsg( _('The tax category cannot be renamed because another with the same name already exists.'),'error');
		} else {
			// Get the old name and check that the record still exists

			$sql = "SELECT taxcatname FROM taxcategories
				WHERE taxcatid = '" . $SelectedTaxCategory . "'";
			$result = DB_query($sql,$db);
			if ( DB_num_rows($result) != 0 ) {
				// This is probably the safest way there is
				$myrow = DB_fetch_array($result);
				$OldTaxCategoryName = $myrow['taxcatname'];
				$sql = "UPDATE taxcategories
					SET taxcatname='" . $_POST['TaxCategoryName'] . "'
					WHERE taxcatname ".LIKE." '".$OldTaxCategoryName."'";
				$ErrMsg = _('The tax category could not be updated');
				$result = DB_query($sql,$db,$ErrMsg);
			} else {
				$InputError = 1;
				prnMsg( _('The tax category no longer exists'),'error');
			}
		}
		$msg = _('Tax category name changed');
	} elseif ($InputError !=1) {
		/*SelectedTaxCategory is null cos no item selected on first time round so must be adding a record*/
		$sql = "SELECT * FROM taxcategories
				WHERE taxcatname " .LIKE. " '".$_POST['TaxCategoryName'] ."'";
		$result = DB_query($sql,$db);

		if (DB_num_rows($result)>0) {
			$InputError = 1;
			prnMsg( _('The tax category cannot be created because another with the same name already exists'),'error');
		} else {
			$result = DB_Txn_Begin($db);
			$sql = "INSERT INTO taxcategories (
						taxcatname )
				VALUES (
					'" . $_POST['TaxCategoryName'] ."'
					)";
			$ErrMsg = _('The new tax category could not be added');
			$result = DB_query($sql,$db,$ErrMsg,true);

			$LastTaxCatID = DB_Last_Insert_ID($db, 'taxcategories','taxcatid');

			$sql = "INSERT INTO taxauthrates (taxauthority,
					dispatchtaxprovince,
					taxcatid)
				SELECT taxauthorities.taxid,
 					taxprovinces.taxprovinceid,
					'" . $LastTaxCatID . "'
				FROM taxauthorities CROSS JOIN taxprovinces";
			$result = DB_query($sql,$db,$ErrMsg,true);

			$result = DB_Txn_Commit($db);
		}
		$msg = _('New tax category added');
	}

	if ($InputError!=1){
		prnMsg($msg,'success');
	}
	unset ($SelectedTaxCategory);
	unset ($_POST['SelectedTaxCategory']);
	unset ($_POST['TaxCategoryName']);

} elseif (isset($_GET['delete'])) {
//the link to delete a selected record was clicked instead of the submit button
// PREVENT DELETES IF DEPENDENT RECORDS IN 'stockmaster'
	// Get the original name of the tax category the ID is just a secure way to find the tax category
	$sql = "SELECT taxcatname FROM taxcategories
		WHERE taxcatid = '" . $SelectedTaxCategory . "'";
	$result = DB_query($sql,$db);
	if ( DB_num_rows($result) == 0 ) {
		// This is probably the safest way there is
		prnMsg( _('Cannot delete this tax category because it no longer exists'),'warn');
	} else {
		$myrow = DB_fetch_array($result);
		$OldTaxCategoryName = $myrow['taxcatname'];
		$sql= "SELECT stockid FROM stockmaster WHERE taxcatid ".LIKE." '" . $OldTaxCategoryName . "'";
		$result = DB_query($sql,$db);

		if ( DB_num_rows($result) == 0 ) {
			prnMsg( _('Cannot delete this tax category because inventory items have been created using this tax category'),'warn');
			echo '<br />' . _('There are') . ' ' . $myrow[0] . ' ' . _('inventory items that refer to this tax category') . '</font>';
		} else {
			$sql = "DELETE FROM taxauthrates WHERE taxcatid  = '" . $SelectedTaxCategory . "'";
			$result = DB_query($sql,$db);
			$sql = "DELETE FROM taxcategories WHERE taxcatid = '" . $SelectedTaxCategory . "'";
			$result = DB_query($sql,$db);
			prnMsg( $OldTaxCategoryName . ' ' . _('tax category and any tax rates set for it have been deleted'),'success');
		}
	} //end if
	unset ($SelectedTaxCategory);
	unset ($_GET['SelectedTaxCategory']);
	unset($_GET['delete']);
	unset ($_POST['SelectedTaxCategory']);
	unset ($_POST['TaxCategoryName']);
}

 if (!isset($SelectedTaxCategory)) {

/* An tax category could be posted when one has been edited and is being updated
  or GOT when selected for modification
  SelectedTaxCategory will exist because it was sent with the page in a GET .
  If its the first time the page has been displayed with no parameters
  then none of the above are true and the list of account groups will be displayed with
  links to delete or edit each. These will call the same page again and allow update/input
  or deletion of the records*/

	$sql = "SELECT taxcatid,
			taxcatname
			FROM taxcategories
			ORDER BY taxcatid";

	$ErrMsg = _('Could not get tax categories because');
	$result = DB_query($sql,$db,$ErrMsg);

	echo '<table class="selection">
		<tr>
		<th>' . _('Tax Categories') . '</th>
		</tr>';

	$k=0; //row colour counter
	while ($myrow = DB_fetch_array($result)) {

		if ($k==1){
			echo '<tr class="EvenTableRows">';
			$k=0;
		} else {
			echo '<tr class="OddTableRows">';
			$k++;
		}

		echo '<td>' . $myrow['taxcatname'] . '</td>';
		echo '<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?SelectedTaxCategory=' . $myrow['taxcatid'] . '">' . _('Edit') . '</a></td>';
		echo '<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?SelectedTaxCategory=' . $myrow['taxcatid'] . '&delete=1">' . _('Delete') .'</a></td>';
		echo '</tr>';

	} //END WHILE LIST LOOP
	echo '</table><br />';
} //end of ifs and buts!


if (isset($SelectedTaxCategory)) {
	echo '<div class="centre"><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">' . _('Review Tax Categories') . '</a></div>';
}

echo '<br />';

if (! isset($_GET['delete'])) {

	echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

	if (isset($SelectedTaxCategory)) {
		//editing an existing section

		$sql = "SELECT taxcatid,
				taxcatname
				FROM taxcategories
				WHERE taxcatid='" . $SelectedTaxCategory . "'";

		$result = DB_query($sql, $db);
		if ( DB_num_rows($result) == 0 ) {
			prnMsg( _('Could not retrieve the requested tax category, please try again.'),'warn');
			unset($SelectedTaxCategory);
		} else {
			$myrow = DB_fetch_array($result);

			$_POST['TaxCategoryName']  = $myrow['taxcatname'];

			echo '<input type="hidden" name="SelectedTaxCategory" value="' . $myrow['taxcatid'] . '" />';
			echo '<table class="selection">';
		}

	}  else {
		$_POST['TaxCategoryName']='';
		echo '<table class="selection">';
	}
	echo '<tr>
		<td>' . _('Tax Category Name') . ':' . '</td>
		<td><input type="text" name="TaxCategoryName" size="30" maxlength="30" value="' . $_POST['TaxCategoryName'] . '" /></td>
		</tr>';
	echo '</table>';

	echo '<br /><div class="centre"><button type="submit" name="submit">' . _('Enter Information') . '</button></div>';

	echo '</form>';

} //end if record deleted no point displaying form to add record

include('includes/footer.inc');
?>