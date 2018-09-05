<?php

include 'AmoEnums.php';
include 'AmoAPI.php';

header("Access-Control-Allow-Origin: *");

/*
$_POST = array(
    'method' => 'make_csv',
    'lead_ids' => array(7238951, 7209515),
    'subdomain' => 'leprosy93',
    'user_login' => 'leprosy93@mail.ru',
    'user_hash' => '4e0a9489dfe355d6ecf0687e00b625452afba1e9'
);
 */
if (!isset($_POST['method'])){
    die('no method');
}

$csv_dir = __DIR__ . '/csvs';

switch ($_POST['method']){
case 'make_csv':
    if(!isset($_POST['lead_ids'])){
	die('bad params');
    }		
    $lead_ids = $_POST['lead_ids'];
    $subdomain = $_POST['subdomain'];
    $user_login = $_POST['user_login'];
    $user_hash = $_POST['user_hash'];
    
    $amo = new AmoAPI($user_login, $user_hash, $subdomain, 'cookie', FALSE);
    if (!$amo->auth()){
	    die("Can't auth!!\n");
    }
    $leads = $amo->get_leads_by_id($lead_ids);
    if (!$leads){
	die("got no leads");
    }
    do {
	$file_name = "{$csv_dir}/" . md5(rand(0, 1000000)) . ".csv";
    } while (file_exists($file_name));

    $file = fopen($file_name, 'w');
    fclose($file);
    $file = fopen($file_name, 'a');
    fputcsv($file, array("Название сделки",
	"Дата создания сделки",
	"Теги",
	"Значения кастомных полей черезе запятую",
	"Названия связанных  контактов",
	"Названия связанных компаний"
    ));
    foreach($leads as $lead){
	$string = array();
	$string[] = $lead['name'];
	$string[] = date("Y-m-d H:i:s", $lead['created_at']);
	$string[] = implode(',', $lead['tags']);
	$field_vals = array();
	foreach ($lead['custom_fields'] as $field){
	    foreach ($field['values'] as $val){
		if (isset($val['value'])){
		    $field_vals[] = $val['value'];
		}
	    }
	}
	$field_vals = implode(',', $field_vals);
	$string[] = $field_vals;
	$linked_contacts = array();
	if (isset($lead['contacts']['id'])){
	    foreach($lead['contacts']['id'] as $id){
		$contact = $amo->get_contact_by_id($id)[0];
		$linked_contacts[] = $contact['name'];
	    }
	}
	$string[] = implode(',', $linked_contacts);
	$linked_comps = array();
	if (isset($lead['company']['id'])){
	    $comp = $amo->get_company_by_id($lead['company']['id'])[0];
	    $linked_comps[] = $comp['name'];
	}
	$string[] = implode(',', $linked_comps);
	
	fputcsv($file, $string);
    }
    fclose($file);

    die(json_encode(array("code" => 200, "file" => str_replace(__DIR__, '',$file_name))));
    break;

}