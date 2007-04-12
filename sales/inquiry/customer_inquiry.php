<?php

$page_security = 1;
$path_to_root="../..";
include_once($path_to_root . "/includes/session.inc");

include_once($path_to_root . "/sales/includes/sales_ui.inc");
include_once($path_to_root . "/sales/includes/sales_db.inc");

$js = "";
if ($use_popup_windows)
	$js .= get_js_open_window(900, 500);
page(_("Customer Inquiry"), false, false, "", $js);


if (isset($_GET['customer_id']))
{
	$_POST['customer_id'] = $_GET['customer_id'];
}

//------------------------------------------------------------------------------------------------

start_form();

if (!isset($_POST['customer_id']))
	$_POST['customer_id'] = get_global_customer();

start_table("class='tablestyle_noborder'");

customer_list_cells(_("Select a customer: "), 'customer_id', $_POST['customer_id'], true);

date_cells(_("From:"), 'TransAfterDate', null, -30);
date_cells(_("To:"), 'TransToDate', null, 1);

if (!isset($_POST['filterType']))
	$_POST['filterType'] = 0;

cust_allocations_list_cells(null, 'filterType', $_POST['filterType']);

submit_cells('Refresh Inquiry', _("Search"));

end_table();

end_form();

set_global_customer($_POST['customer_id']);

//------------------------------------------------------------------------------------------------

function display_customer_summary($customer_record)
{
	global $table_style;

	$past1 = get_company_pref('past_due_days');
	$past2 = 2 * $past1;
    if ($customer_record["dissallow_invoices"] != 0)
    {
    	echo "<center><font color=red size=4><b>" . _("CUSTOMER ACCOUNT IS ON HOLD") . "</font></b></center>";
    }

	$nowdue = "1-" . $past1 . " " . _('Days');
	$pastdue1 = $past1 + 1 . "-" . $past2 . " " . _('Days');
	$pastdue2 = _('Over') . " " . $past2 . " " . _('Days');
	
    start_table("width=80% $table_style");
    $th = array(_("Currency"), _("Terms"), _("Current"), $nowdue,
    	$pastdue1, $pastdue2, _("Total Balance"));
    table_header($th);	

	start_row();
    label_cell($customer_record["curr_code"]);
    label_cell($customer_record["terms"]);
	amount_cell($customer_record["Balance"] - $customer_record["Due"]);
	amount_cell($customer_record["Due"] - $customer_record["Overdue1"]);
	amount_cell($customer_record["Overdue1"] - $customer_record["Overdue2"]);
	amount_cell($customer_record["Overdue2"]);
	amount_cell($customer_record["Balance"]);
	end_row();

	end_table();;
}

//------------------------------------------------------------------------------------------------

function get_transactions()
{
    $date_after = date2sql($_POST['TransAfterDate']);
    $date_to = date2sql($_POST['TransToDate']);

    $sql = "SELECT ".TB_PREF."debtor_trans.*,
		".TB_PREF."debtors_master.name AS CustName, ".TB_PREF."debtors_master.curr_code AS CustCurrCode,
		(".TB_PREF."debtor_trans.ov_amount + ".TB_PREF."debtor_trans.ov_gst + ".TB_PREF."debtor_trans.ov_freight + ".TB_PREF."debtor_trans.ov_discount)
		AS TotalAmount, ".TB_PREF."debtor_trans.alloc AS Allocated,
		((".TB_PREF."debtor_trans.type = 10)
			AND ".TB_PREF."debtor_trans.due_date < '" . date2sql(Today()) . "') AS OverDue
		FROM ".TB_PREF."debtor_trans, ".TB_PREF."debtors_master
		WHERE ".TB_PREF."debtors_master.debtor_no = ".TB_PREF."debtor_trans.debtor_no
			AND ".TB_PREF."debtor_trans.tran_date >= '$date_after'
			AND ".TB_PREF."debtor_trans.tran_date <= '$date_to'";

   	if ($_POST['customer_id'] != reserved_words::get_all())
   		$sql .= " AND ".TB_PREF."debtor_trans.debtor_no = '" . $_POST['customer_id'] . "'";

   	if ($_POST['filterType'] != reserved_words::get_all())
   	{
   		if ($_POST['filterType'] == '1') 
   		{
   			$sql .= " AND (".TB_PREF."debtor_trans.type = 10 OR ".TB_PREF."debtor_trans.type = 1) ";
   		} 
   		elseif ($_POST['filterType'] == '2') 
   		{
   			$sql .= " AND (".TB_PREF."debtor_trans.type = 10) ";
   		} 
   		elseif ($_POST['filterType'] == '3') 
   		{
			$sql .= " AND (".TB_PREF."debtor_trans.type = " . systypes::cust_payment() . " OR ".TB_PREF."debtor_trans.type = 2) ";
   		} 
   		elseif ($_POST['filterType'] == '4') 
   		{
			$sql .= " AND ".TB_PREF."debtor_trans.type = 11 ";
   		}

    	if ($_POST['filterType'] == '2') 
    	{
    		$today =  date2sql(Today());
    		$sql .= " AND ".TB_PREF."debtor_trans.due_date < '$today'
				AND (".TB_PREF."debtor_trans.ov_amount + ".TB_PREF."debtor_trans.ov_gst + ".TB_PREF."debtor_trans.ov_freight + ".TB_PREF."debtor_trans.ov_discount - ".TB_PREF."debtor_trans.alloc > 0) ";
    	}
   	}

    $sql .= " ORDER BY ".TB_PREF."debtor_trans.tran_date";

    return db_query($sql,"No transactions were returned");
}

//------------------------------------------------------------------------------------------------

if (($_POST['customer_id'] != "") && ($_POST['customer_id'] != reserved_words::get_all()))
{
	$customer_record = get_customer_details($_POST['customer_id']);
    display_customer_summary($customer_record);
    echo "<br>";
}

//------------------------------------------------------------------------------------------------

$result = get_transactions();

if (db_num_rows($result) == 0)
{
	display_note(_("The selected customer has no transactions for the given dates."), 0, 2);
	end_page();
	exit;
}

//------------------------------------------------------------------------------------------------

start_table("$table_style width='80%'");

if ($_POST['customer_id'] == reserved_words::get_all())
	$th = array(_("Type"), _("#"), _("Order"), _("Reference"), _("Date"), _("Due Date"),
		_("Customer"), _("Branch"), _("Currency"), _("Debit"), _("Credit"), "", "");
else		
	$th = array(_("Type"), _("#"), _("Order"), _("Reference"), _("Date"), _("Due Date"),
		_("Branch"), _("Debit"), _("Credit"), "", "");
table_header($th);


$j = 1;
$k = 0; //row colour counter
$over_due = false;
while ($myrow = db_fetch($result)) 
{

	if ($myrow['OverDue'] == 1)
	{
		start_row("class='overduebg'");
		$over_due = true;
	} 
	else
		alt_table_row_color($k);

	$date = sql2date($myrow["tran_date"]);

	if ($myrow["order_"] > 0)
		$preview_order_str = get_customer_trans_view_str(systypes::sales_order(), $myrow["order_"]);
	else
		$preview_order_str = "";

	$gl_trans_str = get_gl_view_str_cell($myrow["type"], $myrow["trans_no"]);

	$credit_me_str = "";

	$credit_invoice_str = "<a href='$path_to_root/sales/customer_credit_invoice.php?InvoiceNumber=" . $myrow["trans_no"] . "'>" . _("Credit This") . "</a>";

	$due_date_str = "";

	if ($myrow["type"] == 10)
		$due_date_str = sql2date($myrow["due_date"]);

	if ($myrow["type"] == 10)
	{
		/*Show a link to allow an invoice to be credited */
		// only allow crediting if it's not been totally allocated
		if ($myrow["TotalAmount"] - $myrow["Allocated"] > 0)
			$credit_me_str = $credit_invoice_str;
	}

	$branch_name = "";
	if ($myrow["branch_code"] > 0) 
	{
		$branch_name = get_branch_name($myrow["branch_code"]);
	}

	$preview_trans_str = get_trans_view_str($myrow["type"], $myrow["trans_no"]);

	label_cell(systypes::name($myrow["type"]));

	label_cell($preview_trans_str);
	label_cell($preview_order_str);
	label_cell($myrow["reference"]);
	label_cell($date, "nowrap");
	label_cell($due_date_str, "nowrap");
	if ($_POST['customer_id'] == reserved_words::get_all())
		label_cell($myrow["CustName"]);
	label_cell($branch_name);
	if ($_POST['customer_id'] == reserved_words::get_all())
		label_cell($myrow["CustCurrCode"]);
	display_debit_or_credit_cells($myrow["TotalAmount"]);
	echo $gl_trans_str;
	if ($credit_me_str != "")
		label_cell($credit_me_str, "nowrap");


	end_row();

	$j++;
	If ($j == 12)
	{
		$j = 1;
		table_header($th);
	}
//end of page full new headings if
}
//end of while loop

end_table(1);

if ($over_due)
	display_note(_("Marked items are overdue."), 0, 1, "class='overduefg'");
	
end_page();

?>
