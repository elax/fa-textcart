<?php

$page_security = 10;
$path_to_root="..";
include_once($path_to_root . "/includes/session.inc");

include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/db/inventory_db.inc");
include_once($path_to_root . "/includes/manufacturing.inc");

include_once($path_to_root . "/manufacturing/includes/manufacturing_db.inc");
include_once($path_to_root . "/manufacturing/includes/manufacturing_ui.inc");

$js = "";
if ($use_date_picker)
	$js .= get_js_date_picker();
page(_("Produce or Unassemble Finished Items From Work Order"), false, false, "", $js);

if (isset($_GET['trans_no']) && $_GET['trans_no'] != "")
{
	$_POST['selected_id'] = $_GET['trans_no'];
}

//--------------------------------------------------------------------------------------------------

if (isset($_GET['AddedID'])) 
{

	display_note(_("The manufacturing process has been entered."));

	hyperlink_no_params("search_work_orders.php", _("Select another Work Order to Process"));

	end_page();
	exit;
}

//--------------------------------------------------------------------------------------------------

$wo_details = get_work_order($_POST['selected_id']);

if (strlen($wo_details[0]) == 0) 
{
	display_error(_("The order number sent is not valid."));
	exit;
}

//--------------------------------------------------------------------------------------------------

function can_process()
{
	global $wo_details;

	if (!references::is_valid($_POST['ref'])) 
	{
		display_error(_("You must enter a reference."));
		return false;
	}

	if (!is_new_reference($_POST['ref'], 29)) 
	{
		display_error(_("The entered reference is already in use."));
		return false;
	}

	if (!is_numeric($_POST['quantity']))
	{
		display_error(_("The quantity entered is not a valid number."));
		return false;
	}

	if ($_POST['quantity'] <= 0)
	{
		display_error(_("The quantity entered must be greater than zero."));
		return false;
	}

	if (!is_date($_POST['date_']))
	{
		display_error(_("The entered date is invalid."));
		return false;
	} 
	elseif (!is_date_in_fiscalyear($_POST['date_'])) 
	{
		display_error(_("The entered date is not in fiscal year."));
		return false;
	}
	if (date_diff(sql2date($wo_details["released_date"]), $_POST['date_'], "d") > 0) 
	{
		display_error(_("The production date cannot be before the release date of the work order."));
		return false;
	}

	// if unassembling we need to check the qoh
	if (($_POST['ProductionType'] == 0) && !sys_prefs::allow_negative_stock())
	{
		$wo_details = get_work_order($_POST['selected_id']);

		$qoh = get_qoh_on_date($wo_details["stock_id"], $wo_details["loc_code"], $date_);
		if (-$_POST['quantity'] + $qoh < 0) 
		{
			display_error(_("The unassembling cannot be processed because there is insufficient stock."));
			return false;
		}
	}

	return true;
}

//--------------------------------------------------------------------------------------------------

if (isset($_POST['Process']) || (isset($_POST['ProcessAndClose']) && can_process() == true)) 
{

	$close_wo = 0;
	if (isset($_POST['ProcessAndClose']) && ($_POST['ProcessAndClose']!=""))
		$close_wo = 1;

	// if unassembling, negate quantity
	if ($_POST['ProductionType'] == 0)
		$_POST['quantity'] = -$_POST['quantity'];

	 $id = work_order_produce($_POST['selected_id'], $_POST['ref'], $_POST['quantity'],
			$_POST['date_'], $_POST['memo_'], $close_wo);

	meta_forward($_SERVER['PHP_SELF'], "AddedID=$id");
}

//-------------------------------------------------------------------------------------

display_wo_details($_POST['selected_id']);

//-------------------------------------------------------------------------------------

start_form();

hidden('selected_id', $_POST['selected_id']);
//hidden('WOReqQuantity', $_POST['WOReqQuantity']);

if (!isset($_POST['quantity']) || $_POST['quantity'] == '') 
{
	$_POST['quantity'] = max($wo_details["units_reqd"] - $wo_details["units_issued"], 0);
}

start_table();

ref_row(_("Reference:"), 'ref', references::get_next(29));

if (!isset($_POST['ProductionType']))
	$_POST['ProductionType'] = 1;

yesno_list_row(_("Type:"), 'ProductionType', $_POST['ProductionType'],
	_("Produce Finished Items"), _("Return Items to Work Order"));

text_row(_("Quantity:"), 'quantity', $_POST['quantity'], 13, 15);

date_row(_("Date:"), 'date_');

textarea_row(_("Memo:"), 'memo_', null, 40, 3);

end_table(1);

submit_center_first('Process', _("Process"));
submit_center_last('ProcessAndClose', _("Process And Close Order"));

end_form();

end_page();

?>