<?php

class Update extends Controller {
	function index()
	{
		$this->load->library('general');

		/* Common functionality from Startup library */

		/* Reading database settings ini file */
		if ($this->session->userdata('active_account'))
		{
			/* Fetching database label details from session and checking the database ini file */
			if ( ! $active_account = $this->general->check_account($this->session->userdata('active_account')))
			{
				$this->session->unset_userdata('active_account');
				redirect('user/account');
				return;
			}

			/* Preparing database settings */
			$db_config['hostname'] = $active_account['db_hostname'];
			$db_config['hostname'] .= ":" . $active_account['db_port'];
			$db_config['database'] = $active_account['db_name'];
			$db_config['username'] = $active_account['db_username'];
			$db_config['password'] = $active_account['db_password'];
			$db_config['dbdriver'] = "mysql";
			$db_config['dbprefix'] = "";
			$db_config['pconnect'] = FALSE;
			$db_config['db_debug'] = FALSE;
			$db_config['cache_on'] = FALSE;
			$db_config['cachedir'] = "";
			$db_config['char_set'] = "utf8";
			$db_config['dbcollat'] = "utf8_general_ci";
			$this->load->database($db_config, FALSE, TRUE);

			/* Checking for valid database connection */
			if ( ! $this->db->conn_id)
			{
				$this->session->unset_userdata('active_account');
				$this->messages->add('Error connecting to database server. Check whether database server is running.', 'error');
				redirect('user/account');
				return;
			}
			/* Check for any database connection error messages */
			if ($this->db->_error_message() != "")
			{
				$this->session->unset_userdata('active_account');
				$this->messages->add('Error connecting to database server. ' . $this->db->_error_message(), 'error');
				redirect('user/account');
				return;
			}
		} else {
			$this->messages->add('Select a account.', 'error');
			redirect('user/account');
			return;
		}

		/* Loading account data */
		$this->db->from('settings')->where('id', 1)->limit(1);
		$account_q = $this->db->get();
		if ( ! ($account_d = $account_q->row()))
		{
			$this->messages->add('Invalid account settings.', 'error');
			redirect('user/account');
			return;
		}
		$data['account'] = $account_d;

		$cur_db_version = $account_d->database_version;
		$required_db_version = $this->config->item('required_database_version');

		if ($_POST)
		{
			while ($cur_db_version < $required_db_version)
			{
				$cur_db_version += 1;
				/* calling update function as object method */
				if (!call_user_func(array($this, '_update_to_db_version_' . $cur_db_version)))
				{
					$this->template->load('user_template', 'update/index');
					return;
				}
			}
			$this->messages->add('Done updating account database. Click ' . anchor('', 'here', array('title' => 'Click here to go back to accounts')) . ' to go back to accounts.', 'success');
		}
		$this->template->load('user_template', 'update/index', $data);
		return;
	}

	function _update_to_db_version_4()
	{
		$update_account = <<<QUERY
UPDATE ledgers SET type = '1' WHERE type = 'B';
UPDATE ledgers SET type = '0' WHERE type = 'N';
ALTER TABLE ledgers CHANGE type type INT(2) NOT NULL DEFAULT '0';
ALTER TABLE voucher_types ADD inventory_entry_type INT(2) NOT NULL DEFAULT '1';
ALTER TABLE voucher_types CHANGE bank_cash_ledger_restriction bank_cash_ledger_restriction INT(2) NOT NULL DEFAULT '1';
ALTER TABLE voucher_items ADD inventory_type INT(1) NOT NULL;
ALTER TABLE voucher_items ADD inventory_rate VARCHAR(15) NOT NULL;
ALTER TABLE settings CHANGE manage_stocks manage_inventory INT(1) NOT NULL; 
RENAME TABLE voucher_types TO entry_types;
CREATE TABLE IF NOT EXISTS inventory_units (
  id int(11) NOT NULL AUTO_INCREMENT,
  symbol varchar(15) NOT NULL,
  name varchar(100) NOT NULL,
  decimal_places int(2) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;
CREATE TABLE IF NOT EXISTS inventory_groups (
  id int(11) NOT NULL AUTO_INCREMENT,
  parent_id varchar(11) NOT NULL,
  name varchar(100) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;
CREATE TABLE IF NOT EXISTS inventory_items (
  id int(11) NOT NULL AUTO_INCREMENT,
  stock_group_id int(11) NOT NULL,
  stock_unit_id int(11) NOT NULL,
  name varchar(100) NOT NULL,
  costing_method int(2) NOT NULL,
  op_balance_quantity float NOT NULL,
  op_balance_rate_per_unit decimal(15,2) NOT NULL,
  op_balance_total_value decimal(15,2) NOT NULL,
  default_sell_price decimal(15,2) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;
CREATE TABLE IF NOT EXISTS inventory_entry_items (
  id int(11) NOT NULL AUTO_INCREMENT,
  voucher_id int(11) NOT NULL,
  inventory_item_id int(11) NOT NULL,
  quantity float NOT NULL,
  rate_per_unit decimal(15,2) NOT NULL DEFAULT '0.00',
  discount varchar(15) NOT NULL,
  total decimal(15,2) NOT NULL DEFAULT '0.00',
  type int(2) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;
ALTER TABLE vouchers CHANGE voucher_type entry_type INT(5) NOT NULL;
ALTER TABLE voucher_items CHANGE voucher_id entry_id INT(11) NOT NULL;
ALTER TABLE inventory_entry_items CHANGE voucher_id entry_id INT(11) NOT NULL;
RENAME TABLE voucher_items TO entry_items;
RENAME TABLE vouchers TO entries;
QUERY;

		$update_account_array = explode(";", $update_account);
		foreach($update_account_array as $row)
		{
			if (strlen($row) < 5)
				continue;
			$this->db->query($row);
			if ($this->db->_error_message() != "")
			{
				$this->messages->add('Error updating account database. ' . $this->db->_error_message(), 'error');
				return FALSE;
			}
		}
		/* Updating version number */
		$update_data = array(
			'database_version' => 4,
		);
		if (!$this->db->where('id', 1)->update('settings', $update_data))
		{
			$this->messages->add('Error updating settings table with correct database version.', 'error');
			return FALSE;
		}
		$this->messages->add('Updated database version to 4.', 'success');
		return TRUE;
	}
}

/* End of file update.php */
/* Location: ./system/application/controllers/update.php */
