<?php

function get_partner_data($partner_id, $partner_type, $partner_detail_id) {
    $sql = "
	SELECT * FROM ".TB_PREF."bi_partners_data
	    WHERE partner_id=".db_escape($partner_id)." AND partner_type=".db_escape($partner_type);
    if ($partner_type == PT_CUSTOMER)
	$sql .= " AND partner_detail_id=".db_escape($partner_detail_id);

//    display_notification($sql);
	
    
    $result = db_query($sql, "could not get partner data");	
    return db_fetch($result);
}

function set_partner_data($partner_id, $partner_type, $partner_detail_id, $data) {
    $sql = "
	INSERT INTO ".TB_PREF."bi_partners_data(partner_id, partner_type, partner_detail_id, data) VALUES(".
	    db_escape($partner_id).",".db_escape($partner_type).",".db_escape($partner_detail_id).",".db_escape($data).")
	ON DUPLICATE KEY UPDATE
	    data=".db_escape($data);
//    display_notification($sql);
    db_query($sql, 'Could not update partner');

}


function search_partner_by_bank_account($partner_type, $needle) {
    if (empty($needle))
	return array();

    $sql = "
	SELECT * FROM ".TB_PREF."bi_partners_data
	    WHERE partner_type=".db_escape($partner_type)." AND data LIKE '%".$needle."%' LIMIT 1";

//    display_notification($sql);
	
    
    $result = db_query($sql, "could not get search partner");	
    return db_fetch($result);
}

//in development
function update_partner_data($partner_id, $partner_type, $partner_detail_id, $account) {

	$partner_data = get_partner_data($partner_id, $partner_type, $partner_detail_id);
	if (!$partner_data || (strpos($partner_data['data'], $account) === false)) {
		// add/append account to data
		set_partner_data($partner_id, $partner_type, $partner_detail_id, $account);
		return;
	}
}
