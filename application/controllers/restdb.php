<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


require APPPATH.'/libraries/REST_Controller.php';
require APPPATH.'/libraries/Db_api.php';

class Restdb extends Db_api {

	/**
	 * Index Page for this controller.
	 *
	 */
	public function index_get($user = null, $db = null, $table = null, $local = null)
	{
				
		if (empty($db)) 	$db = $this->input->get('db', TRUE);
		if (empty($table)) 	$table = $this->input->get('table', TRUE);		
		if (empty($user)) 	$user = $this->input->get('user', TRUE);		
		if (empty($local)) 	$local = $this->input->get('local', TRUE);		

		$format = $this->_detect_output_format(); 		
		//echo $this->request->method; exit;
		
		// if we don't have a request send them to the upload page
		if(empty($db)) redirect('/upload');
		
		
		if (!empty($local) && $local == 'true') {
			
			if(strpos($db, ".$format")) $db = substr($db, 0, strpos($db, ".$format"));					
			
			$query = $this->get_database($user, $db);			
									
			if ($query->num_rows() > 0) {

			   	$db_settings = $query->row(0);
			
				$table = $db_settings->db_name;

				$db_path = $this->config->item('sqlite_data_path') . $db_settings->name_hash . '.db';
				$db_path = (substr($db_path, 0, 1) == '/') ? substr($db_path, 1, strlen($db_path) -1) : $db_path; 				

				$table_blacklist = (!empty($db_settings->table_blacklist)) ? array_map('trim',explode(',', $db_settings->table_blacklist)) : array();
				$column_blacklist = (!empty($db_settings->column_blacklist)) ? array_map('trim',explode(',', $db_settings->column_blacklist)) : array();								

				$config = array($db => array(
															'name' 				=> $db_path,
															'username' 			=> $db_settings->db_username,
															'password' 			=> $db_settings->db_password,
															'server' 			=> $db_settings->db_server,
															'port' 				=> $db_settings->db_port,
															'type' 				=> $db_settings->type,
															'table_blacklist' 	=> $table_blacklist,
															'column_blacklist' 	=> $column_blacklist));				
			}			
							
		
		} else {
			
			if(!empty($table) && strpos($table, ".$format")) $table = substr($table, 0, strpos($table, ".$format"));					
			
			$query = $this->get_database($user, $db);								
												
			if ($query->num_rows() > 0) {						
				
			   	$db_settings = $query->row(0);
						
				$table_blacklist = (!empty($db_settings->table_blacklist)) ? array_map('trim',explode(',', $db_settings->table_blacklist)) : array();
				$column_blacklist = (!empty($db_settings->column_blacklist)) ? array_map('trim',explode(',', $db_settings->column_blacklist)) : array();								
			
				$config = array($db => array(
															'name' 				=> $db_settings->db_name,
															'username' 			=> $db_settings->db_username,
															'password' 			=> $db_settings->db_password,
															'server' 			=> $db_settings->db_server,
															'port' 				=> $db_settings->db_port,
															'type' 				=> $db_settings->type,
															'table_blacklist' 	=> $table_blacklist,
															'column_blacklist' 	=> $column_blacklist));				
			
				if(empty($table)) {
					return $this->show_docs($db, $config, $query->first_row('array'));
				}			
			
			}
						
			
		} 
		
		$this->register_db( $db, $config );		
		//$this->register_custom_sql( 'democracymap', config_item('sql_args') );		
		
		$query = array('db' => $db, 'table' => $table);		
		
		$query = $this->parse_query($query);
		$this->set_db( $query['db'] );
		$results = $this->query( $query );
		
		$this->response($results, 200);
	}
	
	
	
	
	
	
	public function dashboard_get($user = null) {
		
			
		if (empty($user) && !$this->session->userdata('username')) {	
			redirect('login');
		}			
			
		if(empty($user) && $this->session->userdata('username')) {
			$user =	$this->session->userdata('username');	
		}


			// Prepare output data
			$data = array();			
			
			// Get user data
			$query = $this->get_user($user);			
									
			if ($query->num_rows() > 0) {
				$data['user'] = $query->first_row('array');
			}			
			
			// Then check for database entries for that user			
			$query = $this->get_database($user);			
									
			if ($query->num_rows() > 0) {
				$data['connections'] = $query->result_array();
			}		
			
			
			$this->load->view('user_view', $data);
		
		
	}
	
	
	public function show_docs($db, $db_config, $db_settings) {

		$this->register_db( $db, $db_config );	
		$tables = $this->allowed_tables($db);
		
		//var_dump($db_settings); exit;
		
		$data = array('db' => $db_settings, 'tables' => $tables);
		
		$this->load->view('docs_view', $data);
		
		
	}	
	
	
	private function get_database($user_url, $name_url = null) {
		
		$query = array('user_url' => $user_url);		

		if (!empty($name_url)) {
			$query['name_url'] = $name_url;		
		}
				
		return $this->db->get_where('db_connections', $query);				
		
	}
	
	
	private function get_user($user_url) {
		
		$query = array('username_url' => $user_url);		
				
		return $this->db->get_where('users_auth', $query);				
		
	}	
	
	public function add_get() {
		
		$this->load->view('add_view');
		
	}	
	
	
	public function add_post() {
		
		if (empty($user) && !$this->session->userdata('username')) {	
			redirect('login');
		}		
			
		$this->load->helper('restdb'); // used for slugify	
						
		$name_url = slugify($this->input->post('db_name', TRUE));
				
		
			$data = array(
						'db_name'           => 	$this->input->post('db_name', TRUE),
						'name_full'         => 	$this->input->post('name_full', TRUE),
						'name_url'          => 	$name_url,
						'name_hash'         => 	NULL,
						'description'       => 	$this->input->post('description', TRUE),
						'user_id'           => 	1,
						'user_url'          => 	$this->session->userdata('username'),
						'db_username'       => 	$this->input->post('db_username', TRUE),
						'db_password'       => 	$this->input->post('db_password', TRUE),
						'db_server'         => 	$this->input->post('db_server', TRUE),
						'db_port'           => 	$this->input->post('db_port', TRUE),
						'local'             => 	0,
						'type'              => 	$this->input->post('type', TRUE),
						'table_blacklist'   => 	$this->input->post('table_blacklist', TRUE),
						'column_blacklist'  => 	$this->input->post('column_blacklist', TRUE),
					);
		
		$this->db->insert('db_connections', $data);		
		
		
		redirect('/dashboard');
		
	}	
	
	

	public function router_get($user_url = null, $name_url = null, $table_name = null) {								
				
		$this->index_get($user_url, $name_url, $table_name);		
						
	}
	
	public function router_local_get($user_url = null, $name_url = null) {								
		
		$table_name = $name_url;		
		$local = 'true';		
				
		$this->index_get($user_url, $name_url, $table_name, $local);		
					
	}
	
		
	
	
}
