<?php

class AmoAPI {

	const PROTOCOL = 'https';
	const API_URL = 'amocrm.ru/api/';
	const API_VERSION = 'v2/';

	const API_AUTH_URL = 'amocrm.ru/private/api/';
	const AUTH_URL = 'auth.php?type=json';
	const ACCOUNT_URL = self::API_VERSION . 'account';
	const FIELDS_URL = self::API_VERSION . 'fields';
	const LEADS_URL = self::API_VERSION . 'leads';
	const CONTACTS_URL = self::API_VERSION . 'contacts';
	const COMPANIES_URL = self::API_VERSION . 'companies';
	const NOTES_URL = self::API_VERSION . 'notes';
	const TASKS_URL = self::API_VERSION . 'tasks';

	private $user_login;
	private $user_hash;
	private $subdomain;
	private $cookie_file;
	private $debug;

	/* locally stored account data */
	private $users;
	private $free_users;
	private $pipelines;
	private $groups;
	private $note_types;
	private $task_types;
	private $custom_fields;

	public function __construct(string $user_login, string $user_hash,
		string $subdomain, string $cookie_file, bool $debug = FALSE){

		$this->user_login = $user_login;
		$this->user_hash = $user_hash;
		$this->subdomain = $subdomain;
		$this->base_link = self::PROTOCOL . "://{$subdomain}." . self::API_URL;
		$this->auth_link = self::PROTOCOL . "://{$subdomain}." . self::API_AUTH_URL;
		$this->cookie_file = $cookie_file;

		$this->debug = $debug;
	}

	private function log($message){
		$string = $message;
		if (is_array($message)){
			$string = print_r($message, TRUE);
		}
		echo "\n $message \n";
	}

	private function query(string $link_tail, bool $post = FALSE, array $data = array()){

		$curl = curl_init();
		
		if ($link_tail == self::AUTH_URL){
			$link = $this->auth_link . $link_tail;
		} else {	
			$link = $this->base_link . $link_tail;
		}

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
		if ($post){
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
			curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		} elseif (!empty($data)) {
			$link .= '?' . http_build_query($data);
		}
		curl_setopt($curl, CURLOPT_URL, $link);

		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookie_file); 
		curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookie_file);
		//curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 0);
		//curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 0);
		//curl_setopt($curl, CURLOPT_VERBOSE, true);

		$out = curl_exec($curl); 
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl); 

		if ($this->debug){
			$this->log(time() . " {$link} {$code}");
			$this->log("data: " . print_r($data, true));
			$this->log("response: " . print_r(json_decode($out, true), true));
		}

		if ($code == 429){
			usleep(500);
			return $this->query($data, $link_tail);
		}

		
		if($code != 200 && $code != 204){
			return FALSE;
		}

		try {
			$result = json_decode($out, TRUE);
		} catch (Exception $e){
			return FALSE;
		}

		if (isset($result['response'])){
			$result = $result['response'];
		}

		if (isset($result['_embedded']['items'])){
			$result = $result['_embedded']['items'];
		}

		return $result;
	}

	public function auth(){
		$data = array(
			'USER_LOGIN' => $this->user_login,
			'USER_HASH' => $this->user_hash
		);

		return $this->query(self::AUTH_URL, TRUE, $data); 
	}

	public function get_account(){

		return $this->query(self::ACCOUNT_URL); 
	}

	public function get_account_full(){

		$result = $this->query(self::ACCOUNT_URL, FALSE, 
			array('with' => 'pipelines,groups,note_types,task_types,users,free_users,custom_fields')
		); 
		if (isset($result['_embedded']['users'])){
			$this->users = $result['_embedded']['users'];
			if (isset($result['_embedded']['free_users'])){
				$this->free_users = $result['_embedded']['free_users'];
			}
			$this->pipelines = $result['_embedded']['pipelines'];
			$this->groups = $result['_embedded']['groups'];
			$this->note_types = $result['_embedded']['note_types'];
			$this->task_types = $result['_embedded']['task_types'];
			$this->custom_fields = $result['_embedded']['custom_fields'];
		}
	}

	public function get_users(bool $no_cache = FALSE){
		if (!$no_cache && !empty($this->users)){
			return $this->users;
		}
		$this->get_account_full();
		if (!empty($this->users)){
			return $this->users;
		}
		return FALSE;
	}

	public function get_custom_fields(bool $no_cache = FALSE){
		if (!$no_cache && !empty($this->custom_fields)){
			return $this->custom_fields;
		}
		$this->get_account_full();
		if (!empty($this->custom_fields)){
			return $this->custom_fields;
		}
		return FALSE;
	}

	public function create_custom_field(string $name, int $field_type, int $element_type, 
		string $origin, array $enums = array()){

		$data['add'] = array(
			array(
				'name' => $name,
				'field_type' => $field_type,
				'element_type' => $element_type,
				'origin' => $origin,
			)
		);
		if ($data['add'][0]['field_type'] == 5 && !empty($enums)){
			$data['add'][0]['enums'] = $enums;
		}
		return $this->query(self::FIELDS_URL, TRUE, $data); 
	}

	public function check_field_exists(string $name, int $id = 0, string $entity_type = ''){
		if ($this->get_custom_fields()){
			foreach ($this->custom_fields as $type_name => $field_type){
				if ($entity_type && $type_name != $entity_type){
					continue;
				}
				foreach ($field_type as $field){
					if ($id && isset($field[$id])){
						return $field[$id];
					}
					if ($field['name'] == $name && (!$entity_type)){
						return $field;
					}
				}
			}
		}
		return FALSE;
		
	}

	public function create_lead(string $name, array $contacts = array(), int $company = 0){
		$data = array(
			'add' => array(
				array(
					'name' => $name
				)
			)
		);
		if ($contacts){
			$data['add']['contacts_id'] = $contacts;
		}
		if ($company){
			$data['add']['company_id'] = $company_id;
		}
		return $this->query(self::LEADS_URL, TRUE, $data); 
	}

	public function update_lead(int $id, array $custom_fields){
		$data = array(
			'update' => array(
				array(
					'id' => $id,
					'updated_at' => time(),
					'custom_fields' => $custom_fields 
				)
			)
		);
		return $this->query(self::LEADS_URL, TRUE, $data); 
	}

	public function create_company(string $name){
		$data = array(
			'add' => array(
				array(
					'name' => $name
				)
			)
		);
		return $this->query(self::COMPANIES_URL, TRUE, $data); 
	}

	public function update_company(int $id, array $custom_fields){
		$data = array(
			'update' => array(
				array(
					'id' => $id,
					'updated_at' => time(),
					'custom_fields' => $custom_fields,
				)
			)
		);
		return $this->query(self::COMPANIES_URL, TRUE, $data); 
	}


	public function create_contact(string $name, array $leads = array(),
		array $companies = array(), array $custom_fields = array()){

		$data = array(
			'add' => array(
				array(
					'name' => $name
				)
			)
		);
		if ($leads){
			$data['add'][0]['leads_id'] = implode(',', $leads);
		}
		if ($companies){
			$data['add'][0]['company_id'] = implode(',', $companies);
		}
		if (!empty($custom_fields)){
			$data['add'][0]['custom_fields'] = $custom_fields;
		}

		return $this->query(self::CONTACTS_URL, TRUE, $data); 
	}

	public function update_contact(int $id, array $custom_fields){
		$data = array(
			'update' => array(
				array(
					'id' => $id,
					'updated_at' => time(),
					'custom_fields' => $custom_fields,
				)
			)
		);
		return $this->query(self::CONTACTS_URL, TRUE, $data); 
	}

	public function create_note(int $element_id, int $element_type, int $type, string $text){

		$data = array(
			'add' => array(
				array(
					'element_id' => $element_id,
					'element_type' => $element_type,
					'note_type' => $type,
					'text' => $text,
				)
			)
		);

		return $this->query(self::NOTES_URL, TRUE, $data); 
	}

	public function create_task(int $element_id, int $element_type, int $type, string $text, int $due, int $user_id){

		$data = array(
			'add' => array(
				array(
					'element_id' => $element_id,
					'element_type' => $element_type,
					'note_type' => $type,
					'text' => $text,
					'complete_till_at' => $due,
					'responsible_user_id' => $user_id
				)
			)
		);

		return $this->query(self::TASKS_URL, TRUE, $data); 
	}

	public function close_task(int $id){
		$data = array(
			'update' => array(
				array(
					'id' => $id,
					'updated_at' => time(),
					'is_completed' => TRUE
				)
			)
		);
		return $this->query(self::TASKS_URL, TRUE, $data); 
	}

	public function get_contact_by_id(int $id){
		$data = array(
			'id' => $id
		);

		return $this->query(self::CONTACTS_URL, FALSE, $data); 
	}

	public function get_lead_by_id(int $id){
		$data = array(
			'id' => $id
		);

		return $this->query(self::LEADS_URL, FALSE, $data); 
	}

	public function get_leads_by_id(array $ids){
		$data = array(
			'id' => $ids
		);

		return $this->query(self::LEADS_URL, FALSE, $data); 
	}


	public function get_company_by_id(int $id){
		$data = array(
			'id' => $id
		);

		return $this->query(self::COMPANIES_URL, FALSE, $data); 
	}

	public function get_task_by_id(int $id){
		$data = array(
			'id' => $id
		);

		return $this->query(self::TASKS_URL, FALSE, $data); 
	}

}