<?php
/* $Id$*/

//$PageSecurity = 9;

include('includes/DefineTenderClass.php');
include('includes/SQL_CommonFunctions.inc');
include('includes/session.inc');

$Maximum_Number_Of_Parts_To_Show=50;

if (isset($_GET['New']) and isset($_SESSION['tender'])) {
	unset($_SESSION['tender']);
}

$ShowTender = 0;

if (isset($_GET['ID'])) {
	$sql="SELECT tenderid,
				location,
				address1,
				address2,
				address3,
				address4,
				address5,
				address6,
				telephone
			FROM tenders
			WHERE tenderid='" . $_GET['ID'] . "'";
	$result=DB_query($sql, $db);
	$myrow=DB_fetch_array($result);
	if (isset($_SESSION['tender'])) {
		unset($_SESSION['tender']);
	}
	$_SESSION['tender'] = new Tender();
	$_SESSION['tender']->TenderId = $myrow['tenderid'];
	$_SESSION['tender']->Location = $myrow['location'];
	$_SESSION['tender']->DelAdd1 = $myrow['address1'];
	$_SESSION['tender']->DelAdd2 = $myrow['address2'];
	$_SESSION['tender']->DelAdd3 = $myrow['address3'];
	$_SESSION['tender']->DelAdd4 = $myrow['address4'];
	$_SESSION['tender']->DelAdd5 = $myrow['address5'];
	$_SESSION['tender']->DelAdd6 = $myrow['address6'];

	$sql="SELECT tenderid,
				tendersuppliers.supplierid,
				suppliers.suppname,
				tendersuppliers.email
			FROM tendersuppliers
			LEFT JOIN suppliers
			ON tendersuppliers.supplierid=suppliers.supplierid
			WHERE tenderid='" . $_GET['ID'] . "'";
	$result=DB_query($sql, $db);
	while ($myrow=DB_fetch_array($result)) {
		$_SESSION['tender']->add_supplier_to_tender(
				$myrow['supplierid'],
				$myrow['suppname'],
				$myrow['email']);
	}

	$sql="SELECT tenderid,
				tenderitems.stockid,
				tenderitems.quantity,
				stockmaster.description,
				tenderitems.units,
				stockmaster.decimalplaces
			FROM tenderitems
			LEFT JOIN stockmaster
			ON tenderitems.stockid=stockmaster.stockid
			WHERE tenderid='" . $_GET['ID'] . "'";
	$result=DB_query($sql, $db);
	while ($myrow=DB_fetch_array($result)) {
		$_SESSION['tender']->add_item_to_tender(
				$_SESSION['tender']->LinesOnTender,
				$myrow['stockid'],
				$myrow['quantity'],
				$myrow['description'],
				$myrow['units'],
				$myrow['decimalplaces'],
				DateAdd(date($_SESSION['DefaultDateFormat']),'m',3));
	}
	$ShowTender = 1;
}

if (isset($_GET['Edit'])) {
	$title = _('Edit an Existing Supplier Tender Request');
	include('includes/header.inc');
	echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/supplier.png" title="' .
		_('Purchase Order Tendering') . '" alt="">  '.$title . '</p>';
	$sql="SELECT tenderid,
				location,
				address1,
				address2,
				address3,
				address4,
				address5,
				address6,
				telephone
			FROM tenders
			WHERE closed=0";
	$result=DB_query($sql, $db);
	echo '<table class="selection">';
	echo '<tr><th>' . _('Tender ID') . '</th>';
	echo '<th>' . _('Location') . '</th>';
	echo '<th>' . _('Address 1') . '</th>';
	echo '<th>' . _('Address 2') . '</th>';
	echo '<th>' . _('Address 3') . '</th>';
	echo '<th>' . _('Address 4') . '</th>';
	echo '<th>' . _('Address 5') . '</th>';
	echo '<th>' . _('Address 6') . '</th>';
	echo '<th>' . _('Telephone') . '</th></tr>';
	while ($myrow=DB_fetch_array($result)) {
		echo '<tr><td>' . $myrow['tenderid'] . '</td>';
		echo '<td>' . $myrow['location'] . '</td>';
		echo '<td>' . $myrow['address1'] . '</td>';
		echo '<td>' . $myrow['address2'] . '</td>';
		echo '<td>' . $myrow['address3'] . '</td>';
		echo '<td>' . $myrow['address4'] . '</td>';
		echo '<td>' . $myrow['address5'] . '</td>';
		echo '<td>' . $myrow['address6'] . '</td>';
		echo '<td>' . $myrow['telephone'] . '</td>';
		echo '<td><a href="'.$_SERVER['PHP_SELF'] . '?' . SID . '&ID='.$myrow['tenderid'].'">'. _('Edit') .'</a></td>';
	}
	echo '</table>';
	include('includes/footer.inc');
	exit;
} else if (isset($_GET['ID']) or (isset($_SESSION['tender']->TenderId))) {
	$title = _('Edit an Existing Supplier Tender Request');
	include('includes/header.inc');
	echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/supplier.png" title="' .
		_('Purchase Order Tendering') . '" alt="">  '.$title . '</p>';
} else {
	$title = _('Create a New Supplier Tender Request');
	include('includes/header.inc');
	echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/supplier.png" title="' .
		_('Purchase Order Tendering') . '" alt="">  '.$title . '</p>';
}

if (isset($_POST['Save'])) {
	$_SESSION['tender']->RequiredByDate=$_POST['RequiredByDate'];
	$_SESSION['tender']->save($db);
	prnMsg( _('The tender has been successfully saved'), 'success');
	include('includes/footer.inc');
	exit;
}

if (isset($_GET['DeleteSupplier'])) {
	$_SESSION['tender']->remove_supplier_from_tender($_GET['DeleteSupplier']);
	$ShowTender = 1;
}

if (isset($_GET['DeleteItem'])) {
	$_SESSION['tender']->remove_item_from_tender($_GET['DeleteItem']);
	$ShowTender = 1;
}

if (isset($_POST['SelectedSupplier'])) {
	$sql = "SELECT suppname,
					email
				FROM suppliers
				WHERE supplierid='" . $_POST['SelectedSupplier'] . "'";
	$result = DB_query($sql, $db);
	$myrow = DB_fetch_array($result);
	if (strlen($myrow['email'])>0) {
		$_SESSION['tender']->add_supplier_to_tender(
				$_POST['SelectedSupplier'],
				$myrow['suppname'],
				$myrow['email']);
	} else {
		prnMsg( _('The supplier must have an email set up or they cannot be part of a tender'), 'warn');
	}
	$ShowTender = 1;
}

if (isset($_POST['NewItem']) and !isset($_POST['Refresh'])) {
	foreach ($_POST as $key => $value) {
		if (substr($key,0,3)=='qty') {
			$StockID=substr($key,3);
			$Quantity=$value;
		}
		if (substr($key,0,5)=='price') {
			$Price=$value;
		}
		if (substr($key,0,3)=='uom') {
			$UOM=$value;
		}
		if (isset($UOM)) {
			$sql="SELECT description, decimalplaces FROM stockmaster WHERE stockid='".$StockID."'";
			$result=DB_query($sql, $db);
			$myrow=DB_fetch_array($result);
			$_SESSION['tender']->add_item_to_tender(
				$_SESSION['tender']->LinesOnTender,
				$StockID,
				$Quantity,
				$myrow['description'],
				$UOM,
				$myrow['decimalplaces'],
				DateAdd(date($_SESSION['DefaultDateFormat']),'m',3));
			unset($UOM);
		}
	}
	$ShowTender = 1;
}

if (!isset($_SESSION['tender']) or isset($_POST['LookupDeliveryAddress']) or $ShowTender==1) {
	/* Show Tender header screen */
	if (!isset($_SESSION['tender'])) {
		$_SESSION['tender']=new Tender();
	}
	echo '<form name="form1" action="' . $_SERVER['PHP_SELF'] . '?' . SID . '" method=post>';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<table class=selection>';
	echo '<tr><th colspan="4"><font size="3" color="navy">' . _('Tender header details') . '</font></th></tr>';
	echo '<tr><td>' . _('Delivery Must Be Made Before') . '</td>';
	echo '<td><input type="text" class="date" alt="' .$_SESSION['DefaultDateFormat'] . '" name="RequiredByDate" size="11" value="' .
		date($_SESSION['DefaultDateFormat']) . '" /></td></tr>';

	if (!isset($_POST['StkLocation']) OR $_POST['StkLocation']==''){
	/* If this is the first time
	* the form loaded set up defaults */

		$_POST['StkLocation'] = $_SESSION['UserStockLocation'];

		$sql = "SELECT deladd1,
						deladd2,
						deladd3,
						deladd4,
						deladd5,
						deladd6,
						tel,
						contact
					FROM locations
					WHERE loccode='" . $_POST['StkLocation'] . "'";

		$LocnAddrResult = DB_query($sql,$db);
		if (DB_num_rows($LocnAddrResult)==1){
			$LocnRow = DB_fetch_array($LocnAddrResult);
			$_POST['DelAdd1'] = $LocnRow['deladd1'];
			$_POST['DelAdd2'] = $LocnRow['deladd2'];
			$_POST['DelAdd3'] = $LocnRow['deladd3'];
			$_POST['DelAdd4'] = $LocnRow['deladd4'];
			$_POST['DelAdd5'] = $LocnRow['deladd5'];
			$_POST['DelAdd6'] = $LocnRow['deladd6'];
			$_POST['Tel'] = $LocnRow['tel'];
			$_POST['Contact'] = $LocnRow['contact'];

			$_SESSION['tender']->Location= $_POST['StkLocation'];
			$_SESSION['tender']->DelAdd1 = $_POST['DelAdd1'];
			$_SESSION['tender']->DelAdd2 = $_POST['DelAdd2'];
			$_SESSION['tender']->DelAdd3 = $_POST['DelAdd3'];
			$_SESSION['tender']->DelAdd4 = $_POST['DelAdd4'];
			$_SESSION['tender']->DelAdd5 = $_POST['DelAdd5'];
			$_SESSION['tender']->DelAdd6 = $_POST['DelAdd6'];
			$_SESSION['tender']->Telephone = $_POST['Tel'];
			$_SESSION['tender']->Contact = $_POST['Contact'];

		} else {
			 /*The default location of the user is crook */
			prnMsg(_('The default stock location set up for this user is not a currently defined stock location') .
				'. ' . _('Your system administrator needs to amend your user record'),'error');
		}


	} elseif (isset($_POST['LookupDeliveryAddress'])){

		$sql = "SELECT deladd1,
						deladd2,
						deladd3,
						deladd4,
						deladd5,
						deladd6,
						tel,
						contact
					FROM locations
					WHERE loccode='" . $_POST['StkLocation'] . "'";

		$LocnAddrResult = DB_query($sql,$db);
		if (DB_num_rows($LocnAddrResult)==1){
			$LocnRow = DB_fetch_array($LocnAddrResult);
			$_POST['DelAdd1'] = $LocnRow['deladd1'];
			$_POST['DelAdd2'] = $LocnRow['deladd2'];
			$_POST['DelAdd3'] = $LocnRow['deladd3'];
			$_POST['DelAdd4'] = $LocnRow['deladd4'];
			$_POST['DelAdd5'] = $LocnRow['deladd5'];
			$_POST['DelAdd6'] = $LocnRow['deladd6'];
			$_POST['Tel'] = $LocnRow['tel'];
			$_POST['Contact'] = $LocnRow['contact'];

			$_SESSION['tender']->Location= $_POST['StkLocation'];
			$_SESSION['tender']->DelAdd1 = $_POST['DelAdd1'];
			$_SESSION['tender']->DelAdd2 = $_POST['DelAdd2'];
			$_SESSION['tender']->DelAdd3 = $_POST['DelAdd3'];
			$_SESSION['tender']->DelAdd4 = $_POST['DelAdd4'];
			$_SESSION['tender']->DelAdd5 = $_POST['DelAdd5'];
			$_SESSION['tender']->DelAdd6 = $_POST['DelAdd6'];
			$_SESSION['tender']->Telephone = $_POST['Tel'];
			$_SESSION['tender']->Contact = $_POST['Contact'];
		}
	}
	echo '<tr><td>' . _('Warehouse') . ':</td>
			<td><select name=StkLocation onChange="ReloadForm(form1.LookupDeliveryAddress)">';

	$sql = "SELECT loccode,
					locationname
					FROM locations";
	$LocnResult = DB_query($sql,$db);

	while ($LocnRow=DB_fetch_array($LocnResult)){
		if ((isset($_SESSION['tender']->Location) and $_SESSION['tender']->Location == $LocnRow['loccode'])){
			echo '<option selected value="' . $LocnRow['loccode'] . '">' . $LocnRow['locationname'] . '</option>';
		} else {
			echo '<option value="' . $LocnRow['loccode'] . '">' . $LocnRow['locationname'] . '</option>';
		}
	}

	echo '</select>
		<input type="submit" name="LookupDeliveryAddress" value="' ._('Select') . '"></td>
		</tr>';

	/* Display the details of the delivery location
	 */
	echo '<tr><td>' . _('Delivery Contact') . ':</td>
		<td><input type="text" name="Contact" size="41"  value="' . $_SESSION['tender']->Contact . '"></td>
		</tr>';
	echo '<tr><td>' . _('Address') . ' 1 :</td>
		<td><input type="text" name="DelAdd1" size="41" maxlength="40" value="' . $_POST['DelAdd1'] . '"></td>
		</tr>';
	echo '<tr><td>' . _('Address') . ' 2 :</td>
		<td><input type="text" name="DelAdd2" size="41" maxlength="40" value="' . $_POST['DelAdd2'] . '"></td>
		</tr>';
	echo '<tr><td>' . _('Address') . ' 3 :</td>
		<td><input type="text" name="DelAdd3" size="41" maxlength="40" value="' . $_POST['DelAdd3'] . '"></td>
		</tr>';
	echo '<tr><td>' . _('Address') . ' 4 :</td>
		<td><input type="text" name="DelAdd4" size="21" maxlength="20" value="' . $_POST['DelAdd4'] . '"></td>
		</tr>';
	echo '<tr><td>' . _('Address') . ' 5 :</td>
		<td><input type="text" name="DelAdd5" size="16" maxlength="15" value="' . $_POST['DelAdd5'] . '"></td>
		</tr>';
	echo '<tr><td>' . _('Address') . ' 6 :</td>
		<td><input type="text" name="DelAdd6" size="16" maxlength=15 value="' . $_POST['DelAdd6'] . '"></td>
		</tr>';
	echo '<tr><td>' . _('Phone') . ':</td>
		<td><input type="text" name="Tel" size="31" maxlength="30" value="' . $_SESSION['tender']->Telephone . '"></td>
		</tr>';
	echo '</table><br />';

	/* Display the supplier/item details
	 */
	echo '<table>';

	/* Supplier Details
	 */
	echo '<tr><td valign="top"><table class="selection">';
	echo '<tr><th colspan="4"><font size="3" color="navy">' . _('Suppliers To Send Tender') . '</font></th></tr>';
	echo '<tr><th>'. _('Supplier Code') . '</th><th>' ._('Supplier Name') . '</th><th>' ._('Email Address') . '</th></tr>';
	foreach ($_SESSION['tender']->Suppliers as $Supplier) {
		echo '<tr><td>' . $Supplier->SupplierCode . '</td>';
		echo '<td>' . $Supplier->SupplierName . '</td>';
		echo '<td>' . $Supplier->EmailAddress . '</td>';
		echo "<td><a href='" . $_SERVER['PHP_SELF'] . "?" . SID . "DeleteSupplier=" . $Supplier->SupplierCode . "'>" . _('Delete') . "</a></td></tr>";
	}
	echo '</table></td>';
	/* Item Details
	 */
	echo '<td valign="top"><table class="selection">';
	echo '<tr><th colspan="6"><font size="3" color="navy">' . _('Items in Tender') . '</font></th></tr>';
	echo '<tr>';
	echo '<th>'._('Stock ID').'</th>';
	echo '<th>'._('Description').'</th>';
	echo '<th>'._('Quantity').'</th>';
	echo '<th>'._('UOM').'</th>';
	echo '</tr>';
	$k=0;
	foreach ($_SESSION['tender']->LineItems as $LineItems) {
		if ($LineItems->Deleted==False) {
			if ($k==1){
				echo '<tr class="EvenTableRows">';
				$k=0;
			} else {
				echo '<tr class="OddTableRows">';
				$k=1;
			}
			echo '<td>'.$LineItems->StockID.'</td>';
			echo '<td>'.$LineItems->ItemDescription.'</td>';
			echo '<td class="number">' . number_format($LineItems->Quantity,$LineItems->DecimalPlaces).'</td>';
			echo '<td>'.$LineItems->Units.'</td>';
			echo "<td><a href='" . $_SERVER['PHP_SELF'] . "?" . SID . "DeleteItem=" . $LineItems->LineNo . "'>" . _('Delete') . "</a></td></tr>";
			echo '</tr>';
		}
	}
	echo '</table></td></tr></table><br />';

	echo '<div class="centre"><input type="submit" name="Suppliers" value="' . _('Select Suppliers') . '" />';
	echo '<input type="submit" name="Items" value="' . _('Select Item Details') . '" /></div><br />';
	if ($_SESSION['tender']->LinesOnTender > 0 and $_SESSION['tender']->SuppliersOnTender > 0) {
		echo '<div class="centre"><input type="submit" name="Save" value="' . _('Save Tender') . '" /></div>';
	}
	echo '</form>';
	include('includes/footer.inc');
	exit;
}

if (isset($_POST['SearchSupplier']) OR isset($_POST['Go']) OR isset($_POST['Next']) OR isset($_POST['Previous'])) {
	if (strlen($_POST['Keywords']) > 0 AND strlen($_POST['SupplierCode']) > 0) {
		prnMsg( '<br>' . _('Supplier name keywords have been used in preference to the Supplier code extract entered'), 'info' );
	}
	if ($_POST['Keywords'] == '' AND $_POST['SupplierCode'] == '') {
		$SQL = "SELECT supplierid,
					suppname,
					currcode,
					address1,
					address2,
					address3,
					address4
				FROM suppliers
				ORDER BY suppname";
	} else {
		if (strlen($_POST['Keywords']) > 0) {
			$_POST['Keywords'] = strtoupper($_POST['Keywords']);
			//insert wildcard characters in spaces
			$SearchString = '%' . str_replace(' ', '%', $_POST['Keywords']) . '%';
			$SQL = "SELECT supplierid,
					suppname,
					currcode,
					address1,
					address2,
					address3,
					address4
				FROM suppliers
				WHERE suppname " . LIKE . " '$SearchString'
				ORDER BY suppname";
		} elseif (strlen($_POST['SupplierCode']) > 0) {
			$_POST['SupplierCode'] = strtoupper($_POST['SupplierCode']);
			$SQL = "SELECT supplierid,
					suppname,
					currcode,
					address1,
					address2,
					address3,
					address4
				FROM suppliers
				WHERE supplierid " . LIKE . " '%" . $_POST['SupplierCode'] . "%'
				ORDER BY supplierid";
		}
	} //one of keywords or SupplierCode was more than a zero length string
	$result = DB_query($SQL, $db);
	if (DB_num_rows($result) == 1) {
		$myrow = DB_fetch_row($result);
		$SingleSupplierReturned = $myrow[0];
	}
} //end of if search
if (isset($SingleSupplierReturned)) { /*there was only one supplier returned */
	$_SESSION['SupplierID'] = $SingleSupplierReturned;
	unset($_POST['Keywords']);
	unset($_POST['SupplierCode']);
}

if (!isset($_POST['PageOffset'])) {
	$_POST['PageOffset'] = 1;
} else {
	if ($_POST['PageOffset'] == 0) {
		$_POST['PageOffset'] = 1;
	}
}

if (isset($_POST['Suppliers'])) {
	echo "<form action='" . $_SERVER['PHP_SELF'] . '?' . SID . "' method=post>";
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<p class="page_title_text"><img src="' . $rootpath . '/css/' . $theme . '/images/magnifier.png" title="' . _('Search') .
		'" alt="" />' . ' ' . _('Search for Suppliers') . '</p>
		<table cellpadding=3 colspan=4 class=selection><tr><td>' . _('Enter a partial Name') . ':</font></td><td>';
	if (isset($_POST['Keywords'])) {
		echo "<input type='Text' name='Keywords' value='" . $_POST['Keywords'] . "' size=20 maxlength=25>";
	} else {
		echo "<input type='Text' name='Keywords' size=20 maxlength=25>";
	}
	echo '</td><td><b>' . _('OR') . '</b></font></td><td>' . _('Enter a partial Code') . ':</font></td><td>';
	if (isset($_POST['SupplierCode'])) {
		echo "<input type='Text' name='SupplierCode' value='" . $_POST['SupplierCode'] . "' size=15 maxlength=18>";
	} else {
		echo "<input type='Text' name='SupplierCode' size=15 maxlength=18>";
	}
	echo "</td></tr></table><br><div class='centre'><input type=submit name='SearchSupplier' value='" . _('Search Now') . "'></div>";
	echo '</form>';
}

if (isset($_POST['SearchSupplier'])) {
	echo "<form action='" . $_SERVER['PHP_SELF'] . '?' . SID . "' method=post>";
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	$ListCount = DB_num_rows($result);
	$ListPageMax = ceil($ListCount / $_SESSION['DisplayRecordsMax']);
	if (isset($_POST['Next'])) {
		if ($_POST['PageOffset'] < $ListPageMax) {
			$_POST['PageOffset'] = $_POST['PageOffset'] + 1;
		}
	}
	if (isset($_POST['Previous'])) {
		if ($_POST['PageOffset'] > 1) {
			$_POST['PageOffset'] = $_POST['PageOffset'] - 1;
		}
	}
	if ($ListPageMax > 1) {
		echo "<p>&nbsp;&nbsp;" . $_POST['PageOffset'] . ' ' . _('of') . ' ' . $ListPageMax . ' ' . _('pages') . '. ' . _('Go to Page') . ': ';
		echo '<select name="PageOffset">';
		$ListPage = 1;
		while ($ListPage <= $ListPageMax) {
			if ($ListPage == $_POST['PageOffset']) {
				echo '<option value=' . $ListPage . ' selected>' . $ListPage . '</option>';
			} else {
				echo '<option value=' . $ListPage . '>' . $ListPage . '</option>';
			}
			$ListPage++;
		}
		echo '</select>
			<input type=submit name="Go" value="' . _('Go') . '">
			<input type=submit name="Previous" value="' . _('Previous') . '">
			<input type=submit name="Next" value="' . _('Next') . '">';
		echo '<p>';
	}
	echo "<input type=hidden name='Search' value='" . _('Search Now') . "'>";
	echo '<br><br>';
	echo '<br><table cellpadding=2 colspan=7';
	$tableheader = "<tr>
  		<th>" . _('Code') . "</th>
		<th>" . _('Supplier Name') . "</th>
		<th>" . _('Currency') . "</th>
		<th>" . _('Address 1') . "</th>
		<th>" . _('Address 2') . "</th>
		<th>" . _('Address 3') . "</th>
		<th>" . _('Address 4') . "</th>
		</tr>";
	echo $tableheader;
	$j = 1;
	$k = 0; //row counter to determine background colour
	$RowIndex = 0;
	if (DB_num_rows($result) <> 0) {
		DB_data_seek($result, ($_POST['PageOffset'] - 1) * $_SESSION['DisplayRecordsMax']);
	}
	while (($myrow = DB_fetch_array($result)) AND ($RowIndex <> $_SESSION['DisplayRecordsMax'])) {
		if ($k == 1) {
			echo '<tr class="EvenTableRows">';
			$k = 0;
		} else {
			echo '<tr class="OddTableRows">';
			$k = 1;
		}
		echo "<td><input type=submit name='SelectedSupplier' value='".$myrow['supplierid']."'</td>
			<td>".$myrow['suppname']."</td>
			<td>".$myrow['currcode']."</td>
			<td>".$myrow['address1']."</td>
			<td>".$myrow['address2']."</td>
			<td>".$myrow['address3']."</td>
			<td>".$myrow['address4']."</td>
			</tr>";
		$RowIndex = $RowIndex + 1;
		//end of page full new headings if
	}
	//end of while loop
	echo '</table>';
}

/*The supplier has chosen option 2
 */
if (isset($_POST['Items'])) {
	echo '<form action="' . $_SERVER['PHP_SELF'] . '?' . SID . '" method=post>';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<p class="page_title_text"><img src="' . $rootpath . '/css/' . $theme . '/images/magnifier.png" title="' .
		_('Search') . '" alt="" />' . ' ' . _('Search for Inventory Items') . '</p>';
	$sql = "SELECT categoryid,
			categorydescription
		FROM stockcategory
		ORDER BY categorydescription";
	$result = DB_query($sql, $db);
	if (DB_num_rows($result) == 0) {
		echo '<p><font size=4 color=red>' . _('Problem Report') . ':</font><br>' .
			_('There are no stock categories currently defined please use the link below to set them up');
		echo '<br><a href="' . $rootpath . '/StockCategories.php?' . SID . '">' . _('Define Stock Categories') . '</a>';
		exit;
	}
	echo '<table class=selection><tr>';
	echo '<td>' . _('In Stock Category') . ':';
	echo '<select name="StockCat">';
	if (!isset($_POST['StockCat'])) {
		$_POST['StockCat'] = "";
	}
	if ($_POST['StockCat'] == "All") {
		echo '<option selected value="All">' . _('All');
	} else {
		echo '<option value="All">' . _('All');
	}
	while ($myrow1 = DB_fetch_array($result)) {
		if ($myrow1['categoryid'] == $_POST['StockCat']) {
			echo '<option selected VALUE="' . $myrow1['categoryid'] . '">' . $myrow1['categorydescription'];
		} else {
			echo '<option value="' . $myrow1['categoryid'] . '">' . $myrow1['categorydescription'];
		}
	}
	echo '</select>';
	echo '<td>' . _('Enter partial') . '<b> ' . _('Description') . '</b>:</td><td>';
	if (isset($_POST['Keywords'])) {
		echo '<input type="text" name="Keywords" value="' . $_POST['Keywords'] . '" size=20 maxlength=25>';
	} else {
		echo '<input type="text" name="Keywords" size=20 maxlength=25>';
	}
	echo '</td></tr><tr><td></td>';
	echo '<td><font size 3><b>' . _('OR') . ' ' . '</b></font>' . _('Enter partial') . ' <b>' . _('Stock Code') . '</b>:</td>';
	echo '<td>';
	if (isset($_POST['StockCode'])) {
		echo '<input type="text" name="StockCode" value="' . $_POST['StockCode'] . '" size=15 maxlength=18>';
	} else {
		echo '<input type="text" name="StockCode" size=15 maxlength=18>';
	}
	echo '</td></tr></table><br>';
	echo '<div class="centre"><input type=submit name="Search" value="' . _('Search Now') . '"></div><br></form>';
	echo '<script  type="text/javascript">defaultControl(document.forms[0].StockCode);</script>';
	echo '</form>';
}

if (isset($_POST['Search'])){  /*ie seach for stock items */
	echo "<form method='post' action=" . $_SERVER['PHP_SELF'] . "?" . SID . ">";
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<p class="page_title_text"><img src="' . $rootpath . '/css/' . $theme . '/images/supplier.png" title="' .
		_('Tenders') . '" alt="" />' . ' ' . _('Select items required on this tender').'</p>';

	if ($_POST['Keywords'] AND $_POST['StockCode']) {
		prnMsg( _('Stock description keywords have been used in preference to the Stock code extract entered'), 'info' );
	}
	if ($_POST['Keywords']) {
		//insert wildcard characters in spaces
		$SearchString = '%' . str_replace(' ', '%', $_POST['Keywords']) . '%';

		if ($_POST['StockCat']=='All'){
			$sql = "SELECT stockmaster.stockid,
					stockmaster.description,
					stockmaster.units
				FROM stockmaster INNER JOIN stockcategory
				ON stockmaster.categoryid=stockcategory.categoryid
				WHERE stockmaster.mbflag!='D'
				AND stockmaster.mbflag!='A'
				AND stockmaster.mbflag!='K'
				and stockmaster.discontinued!=1
				AND stockmaster.description " . LIKE . " '$SearchString'
				ORDER BY stockmaster.stockid";
		} else {
			$sql = "SELECT stockmaster.stockid,
					stockmaster.description,
					stockmaster.units
				FROM stockmaster INNER JOIN stockcategory
				ON stockmaster.categoryid=stockcategory.categoryid
				WHERE stockmaster.mbflag!='D'
				AND stockmaster.mbflag!='A'
				AND stockmaster.mbflag!='K'
				and stockmaster.discontinued!=1
				AND stockmaster.description " . LIKE . " '$SearchString'
				AND stockmaster.categoryid='" . $_POST['StockCat'] . "'
				ORDER BY stockmaster.stockid";
		}

	} elseif ($_POST['StockCode']){

		$_POST['StockCode'] = '%' . $_POST['StockCode'] . '%';

		if ($_POST['StockCat']=='All'){
			$sql = "SELECT stockmaster.stockid,
					stockmaster.description,
					stockmaster.units
				FROM stockmaster INNER JOIN stockcategory
				ON stockmaster.categoryid=stockcategory.categoryid
				WHERE stockmaster.mbflag!='D'
				AND stockmaster.mbflag!='A'
				AND stockmaster.mbflag!='K'
				and stockmaster.discontinued!=1
				AND stockmaster.stockid " . LIKE . " '" . $_POST['StockCode'] . "'
				ORDER BY stockmaster.stockid";
		} else {
			$sql = "SELECT stockmaster.stockid,
					stockmaster.description,
					stockmaster.units
				FROM stockmaster INNER JOIN stockcategory
				ON stockmaster.categoryid=stockcategory.categoryid
				WHERE stockmaster.mbflag!='D'
				AND stockmaster.mbflag!='A'
				AND stockmaster.mbflag!='K'
				and stockmaster.discontinued!=1
				AND stockmaster.stockid " . LIKE . " '" . $_POST['StockCode'] . "'
				AND stockmaster.categoryid='" . $_POST['StockCat'] . "'
				ORDER BY stockmaster.stockid";
		}

	} else {
		if ($_POST['StockCat']=='All'){
			$sql = "SELECT stockmaster.stockid,
					stockmaster.description,
					stockmaster.units
				FROM stockmaster INNER JOIN stockcategory
				ON stockmaster.categoryid=stockcategory.categoryid
				WHERE stockmaster.mbflag!='D'
				AND stockmaster.mbflag!='A'
				AND stockmaster.mbflag!='K'
				and stockmaster.discontinued!=1
				ORDER BY stockmaster.stockid";
		} else {
			$sql = "SELECT stockmaster.stockid,
					stockmaster.description,
					stockmaster.units
				FROM stockmaster INNER JOIN stockcategory
				ON stockmaster.categoryid=stockcategory.categoryid
				WHERE stockmaster.mbflag!='D'
				AND stockmaster.mbflag!='A'
				AND stockmaster.mbflag!='K'
				and stockmaster.discontinued!=1
				AND stockmaster.categoryid='" . $_POST['StockCat'] . "'
				ORDER BY stockmaster.stockid";
		}
	}

	$ErrMsg = _('There is a problem selecting the part records to display because');
	$DbgMsg = _('The SQL statement that failed was');
	$SearchResult = DB_query($sql,$db,$ErrMsg,$DbgMsg);

	if (DB_num_rows($SearchResult)==0 && $debug==1){
		prnMsg( _('There are no products to display matching the criteria provided'),'warn');
	}
	if (DB_num_rows($SearchResult)==1){

		$myrow=DB_fetch_array($SearchResult);
		$_GET['NewItem'] = $myrow['stockid'];
		DB_data_seek($SearchResult,0);
	}

	if (isset($SearchResult)) {

		echo "<table cellpadding=1 colspan=7>";

		$tableheader = "<tr>
			<th>" . _('Code')  . "</th>
			<th>" . _('Description') . "</th>
			<th>" . _('Units') . "</th>
			<th>" . _('Image') . "</th>
			<th>" . _('Quantity') . "</th>
			</tr>";
		echo $tableheader;

		$j = 1;
		$k=0; //row colour counter
		$PartsDisplayed=0;
		while ($myrow=DB_fetch_array($SearchResult)) {

			if ($k==1){
				echo '<tr class="EvenTableRows">';
				$k=0;
			} else {
				echo '<tr class="OddTableRows">';
				$k=1;
			}

			$filename = $myrow['stockid'] . '.jpg';
			if (file_exists( $_SESSION['part_pics_dir'] . '/' . $filename) ) {

				$ImageSource = '<img src="'.$rootpath . '/' . $_SESSION['part_pics_dir'] . '/' . $myrow['stockid'] .
					'.jpg" width="50" height="50">';

			} else {
				$ImageSource = '<i>'._('No Image').'</i>';
			}

			$uom=$myrow['units'];

			echo "<td>".$myrow['stockid']."</td>
					<td>".$myrow['description']."</td>
					<td>".$uom."</td>
					<td>".$ImageSource."</td>
					<td><input class='number' type='text' size=6 value=0 name='qty".$myrow['stockid']."'></td>
					<input type='hidden' size=6 value=".$uom." name='uom".$myrow['stockid']."'>
					</tr>";

			$PartsDisplayed++;
			if ($PartsDisplayed == $Maximum_Number_Of_Parts_To_Show){
				break;
			}
#end of page full new headings if
		}
#end of while loop
		echo '</table>';
		if ($PartsDisplayed == $Maximum_Number_Of_Parts_To_Show){

	/*$Maximum_Number_Of_Parts_To_Show defined in config.php */

			prnMsg( _('Only the first') . ' ' . $Maximum_Number_Of_Parts_To_Show . ' ' . _('can be displayed') . '. ' .
				_('Please restrict your search to only the parts required'),'info');
		}
		echo '<a name="end"></a><br><div class="centre"><input type="submit" name="NewItem" value="Add to Tender"></div>';
	}#end if SearchResults to show

	echo '</form>';

} //end of if search

include('includes/footer.inc');

?>