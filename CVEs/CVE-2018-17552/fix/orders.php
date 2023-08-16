<?php
require_once(NAVIGATE_PATH.'/lib/packages/orders/order.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/products/product.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/shipping_methods/shipping_method.class.php');

function run()
{
    global $DB;
    global $website;
    global $layout;

	$out = '';
	$object = new order();
			
	switch($_REQUEST['act'])
	{
        case 'json':
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
					$ids = $_REQUEST['ids'];
					foreach($ids as $id)
					{
						$object->load($id);
						$object->delete();
					}
					echo json_encode(true);
					break;

                case 'find_webuser': // json find webuser by name (for "webuser" autocomplete)
                    $DB->query('SELECT id, username as text
						  FROM nv_webusers
						 WHERE username LIKE :username
						   AND website = :wid
				      ORDER BY username ASC
					     LIMIT 30',
                        'array',
                        array(
                            ':wid' => $website->id,
                            ':username' => '%' . $_REQUEST['username'] . '%'
                        )
                    );

                    $rows = $DB->result();
                    $total = $DB->foundRows();
                    echo json_encode(array('items' => $rows, 'totalCount' => $total));

                    core_terminate();
                    break;


                default: // list or search
					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = ($page - 1) * $max;
					$orderby= $_REQUEST['sidx'].' '.$_REQUEST['sord'];
					$where = " o.website = ".intval($website->id)." ";
										
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
							$where .= $object->quicksearch($_REQUEST['quicksearch']);
						else if(isset($_REQUEST['filters']))
							$where .= navitable::jqgridsearch($_REQUEST['filters']);
						else	// single search
							$where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
					}

                    $sql = ' SELECT SQL_CALC_FOUND_ROWS
					                o.id, o.reference, o.date_created, o.total, o.status, o.payment_done, o.currency,
                                    (   SELECT COUNT(*)
                                        FROM nv_orders_lines ol
                                        WHERE ol.order = o.id
                                    ) AS lines_count,
                                    wu.fullname AS customer_name
							   FROM nv_orders o
						  LEFT JOIN nv_webusers wu
						  			 ON wu.id = o.webuser
							  WHERE '.$where.'	
						   GROUP BY o.id, o.reference, o.date_created, o.total, o.status, o.payment_done, o.currency						   
						   ORDER BY '.$orderby.' 
							  LIMIT '.$max.'
							 OFFSET '.$offset;

                    if(!$DB->query($sql, 'array'))
                        throw new Exception($DB->get_last_error());

                    $dataset = $DB->result();
                    $total = $DB->foundRows();

                    $dataset = grid_notes::summary($dataset, 'order', 'id');
                    $currencies = product::currencies();
                    $states = order::status();

					$out = array();					
											
					for($i=0; $i < count($dataset); $i++)
					{
						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> $dataset[$i]['reference'],
							2	=> core_ts2date($dataset[$i]['date_created'], true),
							3	=> $dataset[$i]['customer_name'],
							4	=> $dataset[$i]['lines_count'],
							5	=> core_decimal2string($dataset[$i]['total'], 2).' '.$currencies[$dataset[$i]['currency']],
							6	=> ($dataset[$i]['payment_done']=='1'? '<i class="fa fa-fw fa-lg fa-check"></i>' : '<i class="fa fa-fw fa-lg fa-exclamation-circle"></i>'),
							7	=> $states[$dataset[$i]['status']],
                            8 	=> $dataset[$i]['_grid_notes_html']
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);					
					break;
			}
			
			session_write_close();
			exit;
			break;

        case 'create':
		case 'edit':
			if(!empty($_REQUEST['id']))
				$object->load(intval($_REQUEST['id']));

			if(isset($_REQUEST['form-sent']))
			{
				$object->load_from_post();
				try
				{
					$object->save();
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}

			$out = orders_form($object);
			break;
					
		case 'delete':
			if(!empty($_REQUEST['id']))
			{
				$object->load(intval($_REQUEST['id']));	
				if($object->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = orders_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = orders_form($object);
				}
			}
			break;

        case 'order_timeline_detail':
            $object->load(intval($_REQUEST['id']));
            foreach($object->history as $event)
            {
                if($event[0] == $_REQUEST['time'])
                {
                    $out = @r($event[2]);
                }
            }

            echo $out;
            echo '
                <style>
                    .ref [data-backtrace] { display: none; }
                </style>';
            core_terminate();
            break;
					
		case 'list':
		default:			
			$out = orders_list();
			break;
	}
	
	return $out;
}

function orders_list()
{
	$navibars = new navibars();
	$navitable = new navitable("orders_list");
	
	$navibars->title(t(26, 'Orders'));

	$navibars->add_actions(
	    array(
			'<a href="?fid=orders&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );
	
	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=orders&act=json&_search=true&quicksearch=".$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid=orders&act=json');
	$navitable->sortBy('id', 'desc');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid=orders&act=edit&id=');
    $navitable->setGridNotesObjectName("order");

    $navitable->addCol("ID", 'id', "40", "true", "left");
    $navitable->addCol(t(707, 'Reference'), 'reference', "64", "true", "left");
    $navitable->addCol(t(86, 'Date'), 'date_created', "64", "true", "center");
    $navitable->addCol(t(704, 'Customer'), 'customer_name', "260", "false", "left");
    $navitable->addCol(t(705, 'Lines'), 'lines', "50", "false", "center");
    $navitable->addCol(t(706, 'Total'), 'total', "80", "true", "right");
    $navitable->addCol(t(708, 'Paid'), 'payment_done', "40", "true", "center");
    $navitable->addCol(t(68, 'Status'), 'status', "100", "true", "center");
    $navitable->addCol(t(168, 'Notes'), 'note', "50", "false", "center");

	$navibars->add_content($navitable->generate());

	return $navibars->generate();
}

function orders_form($object)
{
    global $DB;
    global $website;
	global $layout;
	global $events;
	global $user;
	
	$navibars = new navibars();
	$naviforms = new naviforms();

	$navibars->title(t(26, 'Orders').' / '.t(170, 'Edit').' ['.$object->id.']');

    if(!empty($object->id))
    {
        $navibars->add_actions(
            array(
                ($user->permission('orders.edit')=='true'?
                    '<a href="#" onclick="navigate_tabform_submit(1);" title="Ctrl+S" data-action="save">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'
				</a>' : ""),
                ($user->permission("orders.delete") == 'true'?
                    '<a href="#" onclick="navigate_delete_dialog();">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'
				</a>' : "")
            )
        );

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid=orders&act=delete&id='.$object->id.'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
	}

    if(!empty($object->id))
    {
        $notes = grid_notes::comments('order', $object->id);
        $navibars->add_actions(
            array(
                '<a href="#" onclick="javascript: navigate_display_notes_dialog();">
					<span class="navigate_grid_notes_span" style=" width: 20px; line-height: 16px; ">'.count($notes).'</span>
					<img src="img/skins/badge.png" width="20px" height="18px" style="margin-top: -2px;" class="grid_note_edit" align="absmiddle" /> '.t(168, 'Notes').'
				</a>'
            )
        );
    }


	$extra_actions = array();
    if(!empty($object->id))
    {
        // we attach an event which will be fired by navibars to put an extra button
        $events->add_actions(
            'order',
            array(
                'item' => &$object,
                'navibars' => &$navibars
            ),
            $extra_actions
        );
    }

    if(!empty($object->id))
        $layout->navigate_notes_dialog('order', $object->id);
	
	$navibars->add_actions(
	    array(
			'<a href="?fid=orders&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );

	$navibars->form();

	$navibars->add_tab(t(43, "Main"), "", "fa fa-database");
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $object->id));

	$currencies = product::currencies();
	$currency_symbol = $currencies[$object->currency];
	
	$navibars->add_tab_content_row(
	    array(
	        '<label>ID</label>',
			'<span>'.(!empty($object->id)? $object->id : t(52, '(new)')).'</span>'
        )
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(707, 'Reference').'</label>',
			$naviforms->textfield('reference', $object->reference)
        )
    );

    if($object->date_created > 0)
    {
        $navibars->add_tab_content_row(
            array(
                '<label>'.t(226, 'Date created').'</label>',
                '<span>'.core_ts2date($object->date_created, true).'</span>'
            )
        );
    }

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(708, 'Paid').'</label>',
            $naviforms->checkbox('payment_done', $object->payment_done)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(68, 'Status').'</label>',
            $naviforms->selectfield('status', array_keys(order::status()), array_values(order::status()), $object->status, "navigate_orders_status_change();")
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;</label>',
            $naviforms->checkbox('notify_customer', false),
            '<label for="notify_customer" class="checkbox-text">'.t(718, "Notify customer").'</label>'
        ),
        "notify_customer_wrapper", 'data-previous-value="'.$object->status.'" style="display: none;"'
    );

    $layout->add_script('
        function navigate_orders_status_change()
        {
            $("#notify_customer_wrapper").hide();
            if($("#status").val() != $("#notify_customer_wrapper").data("previous-value"))
            {
                $("#notify_customer_wrapper").show();
            }
        }    
    ');

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(704, 'Customer').'</label>'
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> IP</label>',
            '<span>'.$object->customer_data->ip.'</span>'
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(159, 'Name').'</label>',
            $naviforms->textfield('customer-name', $object->customer_data->name)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(44, 'E-Mail').'</label>',
            $naviforms->textfield('customer-email', $object->customer_data->email)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(320, 'Phone').'</label>',
            $naviforms->textfield('customer-phone', $object->customer_data->phone)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(719, 'Guest').'</label>',
            $naviforms->checkbox('customer-guest', $object->customer_data->guest)
        )
    );

    $layout->add_script('
        $("#customer-guest").on("change", function()
        {
            $("#webuser-wrapper").hide();
            if( !$("#customer-guest").is(":checked") )
                $("#webuser-wrapper").show();
        });
    ');

    $webuser_id = '';
    if(!empty($object->webuser))
    {
        $webuser_username = $DB->query_single('username', 'nv_webusers', ' id = '.$object->webuser);
        if(!empty($webuser_username))
        {
            $webuser_username = array($webuser_username);
            $webuser_id = array($object->webuser);
        }
    }

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(647, 'Webuser').'</label>',
            $naviforms->selectfield('webuser', $webuser_id, $webuser_username, $object->webuser, null, false, null, null, false),
            '<span style="display: none;" id="webuser-helper">'.t(535, "Find user by name").'</span>'
        ),
        "webuser-wrapper",
        (empty($object->webuser)? 'style="display: none;"' : '')
    );

    $layout->add_script('
        $("#webuser").select2(
        {
            placeholder: $("#webuser-helper").text(),
            minimumInputLength: 1,
            ajax: {
                url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=orders&act=json&oper=find_webuser",
                dataType: "json",
                delay: 100,
                data: function (params)
                {
                    return {
                        username: params.term,
                        nd: new Date().getTime(),
                        page_limit: 30, // page size
                        page: params.page // page number
                    };
                },
		        processResults: function (data, params)
		        {
		            params.page = params.page || 1;
		            return {
						results: data.items,
						pagination: { more: (params.page * 30) < data.total_count }
					};
                }
            },
            templateSelection: function(row)
			{
				if(row.id)
					return row.text + " <helper style=\'opacity: .5;\'>#" + row.id + "</helper>";
				else
					return row.text;
			},
			escapeMarkup: function (markup) { return markup; },
            triggerChange: true,
            allowClear: true
        });
    ');

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(789, 'Customer notes').'</label>',
            $naviforms->textarea('customer-notes', $object->customer_notes)
        )
    );

    if($object->date_modified > 0)
    {
        $navibars->add_tab_content_row(
            array(
                '<label>'.t(227, 'Date modified').'</label>',
                core_ts2date($object->date_modified, true)
            )
        );
    }


    $navibars->add_tab(t(705, "Lines"), "", "fa fa-bars");

    $table = new naviorderedtable("order_lines");

    $table->setDblclickCallback("order_lines_open");

    $table->addHeaderColumn('ID', 48);
    $table->addHeaderColumn('SKU', 160);
    $table->addHeaderColumn(t(159, 'Name'), 300);
    $table->addHeaderColumn(t(741, 'Price').'<br />('.t(786, "including taxes").')', 120, false, "right");
    $table->addHeaderColumn(t(701, 'Discount'), 96, false, "right");
    $table->addHeaderColumn(t(676, 'Base price').'<br />('.t(785, "excluding taxes").')', 120, false, "right");
    $table->addHeaderColumn(t(677, 'Tax'), 96, false, "right");
    $table->addHeaderColumn(t(724, 'Quantity'), 64, false, "right");
    $table->addHeaderColumn(t(685, 'Subtotal').'<br />('.t(786, "including taxes").')', 120, false, "right");

    $object->load_lines();
    $taxes_breakout = array();

    foreach($object->lines as $line)
    {
        $line_option_name = "";
        if(!empty($line->option))
            $line_option_name = '<br /><small><em>'.$line->option.'</em></small>';

        $table->addRow(
            $line->id,
            array(
                array('content' => $line->id.'<input type="hidden" name="line['.$line->id.']" value="'.$line->product.'" />', 'align' => 'left'),
                array('content' => $line->sku, 'align' => 'left'),
                array('content' => $line->name.$line_option_name, 'align' => 'left'),
                array('content' => core_decimal2string($line->original_price).' '.$currencies[$line->currency], 'align' => 'right'),
                array('content' => (($line->coupon_unit > 0)? core_decimal2string($line->coupon_unit).' '.$currencies[$line->currency] : "-"), 'align' => 'right'),
                array('content' => core_decimal2string($line->base_price).' '.$currencies[$line->currency], 'align' => 'right'),
                array('content' => core_decimal2string($line->tax_value).' % <br/><small><em>'.core_decimal2string($line->base_price_tax_amount).' '.$currencies[$line->currency].'</em></small>', 'align' => 'right'),
                array('content' => core_decimal2string($line->quantity), 'align' => 'right'),
                array('content' => core_decimal2string($line->total).' '.$currencies[$line->currency], 'align' => 'right')
            )
        );

        // add line tax information to taxes breakout array
        @$taxes_breakout[$line->tax_value]['base_amount'] = $taxes_breakout[$line->tax_value]['base_amount'] + ($line->total - $line->tax_amount);
        @$taxes_breakout[$line->tax_value]['tax_amount'] = $taxes_breakout[$line->tax_value]['tax_amount'] + $line->tax_amount;
    }

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(705, 'Lines').'</label>',
            '<div>'.$table->generate().'</div>'
        )
    );

    $layout->add_script('
        function order_lines_open(element)
        {
            window.open("?fid=products&act=edit&id=" + $(element).find("input[type=hidden]:first").val() );
        }
    ');

    // TODO: allow applying a coupon when editing an order
    if(!empty($object->coupon))
    {
        $navibars->add_tab_content_row(array(
            '<label>' . t(726, 'Coupon') . '</label>',
            '<div><a href="?fid=coupons&act=edit&id=' . $object->coupon . '" style="text-decoration: none;"><i class="fa fa-external-link"></i> ' . $object->coupon_code . '</a></div>'
        ));

        $navibars->add_tab_content_row(array(
            '<label>&nbsp;&nbsp;<i class="fa fa-fw fa-angle-right"></i> ' . t(788, 'Discounts applied') . '</label>',
            '<span>-'.$object->coupon_amount.' '.$currency_symbol.'</span>'
        ));
    }

    $navibars->add_tab_content_row(array(
        '<label>'.t(685, 'Subtotal').'</label>'
    ));

    // lines sum with taxes without coupon
    $navibars->add_tab_content_row(array(
        '<label>&nbsp;&nbsp;<i class="fa fa-fw fa-angle-right"></i> '.t(725, 'Amount').'</label>',
        $naviforms->decimalfield('subtotal_amount', $object->subtotal_amount, 2, $user->decimal_separator, $user->thousands_separator, '', $currency_symbol)
    ));

    $navibars->add_tab_content_row(array(
        '<label>&nbsp;&nbsp;<i class="fa fa-fw fa-angle-right"></i> '.t(784, 'Taxes').'</label>',
        $naviforms->decimalfield('subtotal_taxes_cost', $object->subtotal_taxes_cost, 2, $user->decimal_separator, $user->thousands_separator, '', $currency_symbol)
    ));

    $navibars->add_tab_content_row(array(
        '<label>&nbsp;&nbsp;<i class="fa fa-fw fa-angle-right"></i> '.t(706, 'Total').' <small>('.t(705, 'Lines').')</small></label>',
        $naviforms->decimalfield('subtotal_invoiced', $object->subtotal_invoiced, 2, $user->decimal_separator, $user->thousands_separator, '', $currency_symbol)
    ));


    $shipping_method = new shipping_method();
    list($shipping_method_id, $shipping_method_rate) = explode("/", $object->shipping_method);
    $shipping_method->load($shipping_method_id);

    $navibars->add_tab_content_row(array(
        '<label>'.t(720, 'Shipping method').'</label>',
        '<a href="?fid=shipping_methods&act=edit&id='.$shipping_method_id.'">'.$shipping_method->dictionary[$website->languages_list[0]]["title"].'</a>'
    ));

    $navibars->add_tab_content_row(array(
        '<label>&nbsp;&nbsp;<i class="fa fa-fw fa-angle-right"></i> '.t(725, 'Amount').'</label>',
        $naviforms->decimalfield('shipping_amount', $object->shipping_amount, 2, $user->decimal_separator, $user->thousands_separator, '', $currency_symbol)
    ));

    $navibars->add_tab_content_row(array(
        '<label>&nbsp;&nbsp;<i class="fa fa-fw fa-angle-right"></i> '.t(677, 'Tax').'</label>',
        $naviforms->decimalfield('shipping_tax', $object->shipping_tax, 2, $user->decimal_separator, $user->thousands_separator, '', "%")
    ));

    $navibars->add_tab_content_row(array(
        '<label>&nbsp;&nbsp;<i class="fa fa-fw fa-angle-right"></i> '.t(706, 'Total').' <small>('.t(28, 'Shipping').')</small></label>',
        $naviforms->decimalfield('shipping_invoiced', $object->shipping_invoiced, 2, $user->decimal_separator, $user->thousands_separator, '', $currency_symbol)
    ));

    // add shipping tax information to taxes breakout array
    @$taxes_breakout[$object->shipping_tax]['base_amount'] = $taxes_breakout[$object->shipping_tax]['base_amount'] + $object->shipping_amount;
    @$taxes_breakout[$object->shipping_tax]['tax_amount'] = $taxes_breakout[$object->shipping_tax]['tax_amount'] + $object->shipping_tax_amount;


    $navibars->add_tab_content_row(array(
        '<label><big>'.t(706, 'Total').'</big></label>',
        $naviforms->decimalfield('total', $object->total, 2, $user->decimal_separator, $user->thousands_separator, '', $currency_symbol, NULL, NULL, 'style="font-weight: bold;"')
    ));


    $table = new naviorderedtable("order_taxes_breakout");

    $table->addHeaderColumn(t(677, "Tax"), 64, false, "center");
    $table->addHeaderColumn(t(676, "Base price"), 64, false, "right");
    $table->addHeaderColumn(t(725, "Amount"), 64, false, "right");

    foreach($taxes_breakout as $tax_value => $tax_data)
    {
        $table->addRow(
            $line->tax_value,
            array(
                array('content' => core_decimal2string($tax_value) . "%", 'align' => 'center'),
                array('content' => core_decimal2string($tax_data['base_amount']) . ' ' . $currencies[$object->currency], 'align' => 'right'),
                array('content' => core_decimal2string($tax_data['tax_amount']) . ' ' . $currencies[$object->currency], 'align' => 'right'),
            )
        );
    }

    $table->addRow(
        "999",
        array(
            array('content' => "<strong>".t(706, "Total")."</strong>", 'align' => 'center'),
            array('content' => core_decimal2string($object->shipping_amount + $object->subtotal_amount) . ' ' . $currencies[$object->currency], 'align' => 'right'),
            array('content' => core_decimal2string($object->total - $object->shipping_amount - $object->subtotal_amount) . ' ' . $currencies[$object->currency], 'align' => 'right'),
        )
    );

    $navibars->add_tab_content_row(array(
        '<label>&nbsp;&nbsp;<i class="fa fa-fw fa-angle-right"></i> '.t(790, "Desglose de impuestos").'</label>',
        '<div id="order_taxes_breakout" style="display: block;">'.$table->generate().'</div>'
    ));



    $navibars->add_tab(t(716, "Shipping address"), "", "fa fa-truck");

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(720, 'Shipping method').'</label>',
            '<span>'.$shipping_method->dictionary[$website->languages_list[0]]["title"].'</span>'
        )
    );

    if($object->weight > 0)
    {
        $navibars->add_tab_content_row(
            array(
                '<label>' . t(670, 'Weight') . '</label>',
                '<div>' . $object->weight . ' ' . $object->weight_unit . '</div>'
            )
        );
    }

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(721, 'Shipment reference').'</label>',
            $naviforms->textfield('shipping_data-reference', $object->shipping_data->reference)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(722, 'Tracking URL').'</label>',
            $naviforms->textfield('shipping_data-tracking_url', $object->shipping_data->tracking_url)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(716, 'Shipping address').'</label>',
            '<span>&nbsp;</span>'
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(159, 'Name').'</label>',
            $naviforms->textfield('shipping_address-name', $object->shipping_address->name)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(778, 'National identification number').'</label>',
            $naviforms->textfield('shipping_address-nin', $object->shipping_address->nin)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(592, 'Company').'</label>',
            $naviforms->textfield('shipping_address-company', $object->shipping_address->company)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(233, 'Address').'</label>',
            $naviforms->textfield('shipping_address-address', $object->shipping_address->address)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(319, 'Location').'</label>',
            $naviforms->textfield('shipping_address-location', $object->shipping_address->location)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(318, 'Zip code').'</label>',
            $naviforms->textfield('shipping_address-zipcode', $object->shipping_address->zipcode)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(224, 'Country').'</label>',
            $naviforms->countryfield('shipping_address-country', $object->shipping_address->country)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(473, 'Region').'</label>',
            $naviforms->countryregionfield('shipping_address-region', $object->shipping_address->region, 'shipping_address-country')
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(320, 'Phone').'</label>',
            $naviforms->textfield('shipping_address-phone', $object->shipping_address->phone)
        )
    );

    $navibars->add_tab(t(717, "Billing address"), "", "fa fa-money");

    /* TODO: future use
    $navibars->add_tab_content_row(
        array(
            '<label>'.t(727, 'Payment method').'</label>',
            '<span><a href="?fid=payment_methods&act=edit&id='.$object->payment_method.'>payment method name</a></span>'
        )
    );
    */
    /*
    $navibars->add_tab_content_row(
        array(
            '<label><!--&nbsp;&nbsp;<i class="fa fa-angle-right">--></i> '.t(728, 'Payment information').'</label>',
            '<span>'.print_r($object->payment_data, true).'</span>'
        )
    );
    */

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(717, 'Billing address').'</label>',
            '<span>&nbsp;</span>'
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(159, 'Name').'</label>',
            $naviforms->textfield('billing_address-name', $object->billing_address->name)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(592, 'Company').'</label>',
            $naviforms->textfield('billing_address-company', $object->billing_address->company)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(778, 'National identification number').'</label>',
            $naviforms->textfield('billing_address-nin', $object->billing_address->nin)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(233, 'Address').'</label>',
            $naviforms->textfield('billing_address-address', $object->billing_address->address)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(319, 'Location').'</label>',
            $naviforms->textfield('billing_address-location', $object->billing_address->location)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(318, 'Zip code').'</label>',
            $naviforms->textfield('billing_address-zipcode', $object->billing_address->zipcode)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(224, 'Country').'</label>',
            $naviforms->countryfield('billing_address-country', $object->billing_address->country)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(473, 'Region').'</label>',
            $naviforms->countryregionfield('billing_address-region', $object->billing_address->region, 'billing_address-country')
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(320, 'Phone').'</label>',
            $naviforms->textfield('billing_address-phone', $object->billing_address->phone)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<i class="fa fa-angle-right"></i> '.t(44, 'E-Mail').'</label>',
            $naviforms->textfield('billing_address-email', $object->billing_address->email)
        )
    );

    if(!empty($object->history))
    {
        $navibars->add_tab(t(40, "History"), "", "fa fa-clock-o");

        $timeline_html = [];
        foreach ($object->history as $event)
        {
            $time = $event[0];
            $title = $event[1];

            $timeline_html[] = '
                <div class="timeline-item" date-is="' . core_ts2date($time, true) . '" data-timestamp="'.$time.'">
                    <a href="#" style="text-decoration: none;"><strong>' . $title . '</strong> <i class="fa fa-lg fa-plus-square-o"></i></a>
                </div>
            ';
        }

        $navibars->add_tab_content_row(
            array(
                '<label>' . t(729, "Timeline") . '</label>',
                '<div class="timeline-vertical">' .
                    implode("", $timeline_html) .
                '</div>'
            )
        );

        $layout->add_script('
            $(".timeline-item").on("click", function(e)
            {                       
                e.stopPropagation();
                e.preventDefault();
                
                var that = this;
                
                var timeline_info_dialog = $("<iframe src=\'?fid=orders&act=order_timeline_detail&id='.$object->id.'&time=" + $(that).data("timestamp") + "\'></iframe>").dialog(
                {
                    modal: true,
                    height: 600,
                    width: "90%",
                    title: $(that).attr("date-is") + ", " + $(that).find("strong").text(),
                    resize: function(event, ui)
                    {
                        $(timeline_info_dialog).css("width", "98.5%");                        
                    }
                }).dialogExtend(
                {
                    maximizable: true,
                    "maximize" : function(evt, dlg){ $(timeline_info_dialog).css("width", "98.5%"); },
                    "restore" : function(evt, dlg){ $(timeline_info_dialog).css("width", "98.5%"); }
                }); 
                
                $(timeline_info_dialog).css("width", "98.5%");                
                                
                return false;
            });          
        ');
    }

	return $navibars->generate();
}


?>