<?php
/*
Plugin Name: WPLog
Plugin URI: http://wplog.org
Version: 1.0.0
Description: wordpress logging
*/
if (!class_exists('WP_List_Table')) require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

$wpLogDefaultPerPage = is_multisite() ? absint(get_blog_option(null, 'posts_per_page'))  : absint(get_option('posts_per_page'));

$wpLogPerPage = isset($_REQUEST['per-page']) ? absint($_REQUEST['per-page']) : $wpLogDefaultPerPage;

class WP_Log_Table extends WP_List_Table
{
	public function __construct($args = array())
	{
		if ($args !== false)	parent::__construct($args);
	}

	/**
	 * Prepare the items for the table to process
	 *
	 * @return Void
	 */
	public function prepare_items()
	{
		global $wpLogPerPage;

		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$pagArgs = array(
			'total_items' => $this->record_count(),
		);

		if ($wpLogPerPage > 0) $pagArgs['per_page'] = $wpLogPerPage;

		$this->set_pagination_args($pagArgs);

		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $this->table_data();
	}

	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return Array
	 */
	public function get_columns()
	{
		$columns = array(
			'user_id' => 'User',
			'site_id'       => 'Site',
			'path'    => 'Path',
			'query'    => 'Query',
			'ip'    => 'IP',
			'agent'      => 'Agent',
			'ctime'        => 'Datetime',
		);

		return $columns;
	}

	/**
	 * Define which columns are hidden
	 *
	 * @return Array
	 */
	public function get_hidden_columns()
	{
		return array('site_id');
	}

	/**
	 * Define the sortable columns
	 *
	 * @return Array
	 */
	public function get_sortable_columns()
	{
		return array(
			'site_id' => array('site_id', false),
			'user_id' => array('user_id', false),
			'ip' => array('ip', false),
			'agent' => array('agent', false),
			'path' => array('path', false),
			'query' => array('query', false),
			'ctime' => array('ctime', true)
		);
	}

	/**
	 * Get the table query
	 *
	 * @return Array
	 */
	public function table_data($limit = true)
	{
		global $wpdb;

		$sql = "SELECT l.* from {$wpdb->prefix}log l " . $this->sqlSuffix($limit);

		return $wpdb->get_results(
			$sql,
			ARRAY_A
		);
	}

	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public function record_count()
	{
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}log l " . $this->sqlSuffix(false);

		return $wpdb->get_var($sql);
	}

	private function sqlSuffix($limit = true)
	{
		global $wpdb, $wpLogPerPage;

		$isMySQL = (int)$wpdb->get_var('select 1 || 2') === 12 ? false : true;

		$sql = " WHERE l.site_id = " . esc_sql(get_current_blog_id());

		if (!empty(sanitize_text_field(@$_REQUEST['uid']))) $sql .= " AND l.user_id = " . esc_sql(sanitize_text_field($_REQUEST['uid']));

		if ($isMySQL) {
			if (!empty(sanitize_text_field(@$_REQUEST['s']))) $sql .= " AND CONCAT_WS(l.ctime, ' ', l.ip, ' ', l.agent, ' ', l.path, ' ', l.query) LIKE '%" . esc_sql(sanitize_text_field($_REQUEST['s'])) . "%' ";
		} else {
			if (!empty(sanitize_text_field(@$_REQUEST['s']))) $sql .= " AND l.ctime || ' ' || l.ip || ' ' || l.agent || ' ' || l.path || ' ' || l.query LIKE '%" . esc_sql(sanitize_text_field($_REQUEST['s'])) . "%' ";
		}

		// If orderby is set, use this as the sort column
		$orderby = empty(sanitize_text_field(@$_REQUEST['orderby'])) ? 'ctime' : sanitize_text_field($_REQUEST['orderby']);

		// If order is set use this as the order
		$order = empty(sanitize_text_field(@$_REQUEST['order'])) ? 'DESC' : sanitize_text_field($_REQUEST['order']);

		$orderBySql = sanitize_sql_orderby($orderby . ' ' . $order);

		if ($limit && !empty($orderBySql)) $sql .= ' ORDER BY l.' . esc_sql($orderBySql);
		if ($limit && $wpLogPerPage > 0) $sql .= " LIMIT $wpLogPerPage OFFSET " . (($this->get_pagenum() - 1) * $wpLogPerPage);

		return $sql;
	}

	/**
	 * Define what query to show on each column of the table
	 *
	 * @param  Array $item        Data
	 * @param  String $column_name - Current column name
	 *
	 * @return Mixed
	 */
	public function column_default($item, $column_name)
	{
		$retVal = null;

		if ($column_name === 'user_id') {
			$userData = get_userdata($item[$column_name]);

			$retVal = '';
			if (is_object($userData)) 	$retVal .= '<a href="user-edit.php?user_id=' . $item[$column_name] . '" target="_blank">';
			$retVal .= '<img src="' . get_avatar_url($item[$column_name]) . '" style="vertical-align: middle;" /> ';
			$retVal .= $item[$column_name] > 0 ? $item[$column_name] : 'Guest';
			if (is_object($userData)) $retVal .=  ' (' . $userData->user_login . ')</a>';
		} elseif ($column_name === 'path') {
			$retVal = '<a href="' . esc_attr(esc_url(wp_log_one_liner($item[$column_name]))) . '" target="_blank">' . trim(substr($item[$column_name], 0, 100)) . '</a><a href="javascript:void(0);" onclick="javascript: this.previousSibling.innerText=\'' . esc_attr(str_replace("'", "\'", wp_log_one_liner($item[$column_name]))) . '\'; this.remove();">...</a>';
		} elseif ($column_name === 'query' || $column_name === 'agent') {
			$retVal = trim(substr($item[$column_name], 0, 100)) . '<a href="javascript:void(0);" onclick="javascript: this.parentElement.innerHTML=\'' . esc_attr(str_replace("'", "\'",  wp_log_one_liner($item[$column_name]))) . '\';">...</a>';
		} else {
			$retVal = $item[$column_name];
		}

		return $retVal;
	}
}

function wp_log_one_liner($text)
{
	$line = str_replace(array("\r\n", "\t", "\r", "\n"), ' ', $text);

	return 	trim($line);
}

function wp_log_install()
{
	global $wpdb;

	$table_name = $wpdb->prefix . 'log';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		site_id bigint unsigned DEFAULT 0 NOT NULL,
		user_id bigint unsigned DEFAULT 0 NOT NULL,
		ctime datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		ip varchar(50) NOT NULL,
		agent text NOT NULL,
		path text NOT NULL,
		query longtext NULL,
		KEY site (site_id)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	dbDelta($sql);
}

register_activation_hook(__FILE__, 'wp_log_install');

/** Step 2 (from text above). */
add_action('admin_menu', 'wp_log_menu');

/** Step 1. */
function wp_log_menu()
{
	add_menu_page('WP Log', 'WP Log', 'manage_options', basename(__FILE__, '.php'), 'wp_log_options', admin_url('admin.php?page=' . basename(__FILE__, '.php') . '&svg'));
}

/** Step 3. */
function wp_log_options()
{
	if (!current_user_can('manage_options')) wp_die(__('You do not have sufficient permissions to access this page.'));

	global $wpdb, $wpLogPerPage, $wpLogDefaultPerPage;

	$listTable = new WP_Log_Table();
	$listTable->prepare_items();
?>
	<style>
		.wrap select {
			float: left !important;
			display: inline-block;
			margin-left: 0;
			margin-right: 2px;
		}

		.wrap .search-box {
			float: left !important;
			display: inline-block;
			clear: none !important;
		}

		.wrap .tablenav {
			width: 25%;
			display: inline-block;
			clear: none !important;
			float: right !important;
			padding-top: 0 !important;
		}

		.wrap .tablenav.bottom {
			margin-top: 5px;
		}

		.wrap .top5 {
			margin-top: 10px;
		}

		.wrap .export_button {
			margin: 10px 0 0 5px !important;
			display: inline-block !important;
			float: left !important;
		}

		#the-list * {
			vertical-align: middle;
		}
	</style>
	<div class="wrap">
		<h1><svg class="svg-icon" style="vertical-align: sub;fill: currentColor;overflow: hidden; height:25px;" viewBox="0 0 1024 1024" version="1.1" id="svg1060" inkscape:version="1.1.2 (b8e25be8, 2022-02-05)" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg">
				<defs id="defs1064" />
				<sodipodi:namedview id="namedview1062" pagecolor="#ffffff" bordercolor="#666666" borderopacity="1.0" inkscape:pageshadow="2" inkscape:pageopacity="0.0" inkscape:pagecheckerboard="0" showgrid="false" inkscape:zoom="0.70214844" inkscape:cx="231.43255" inkscape:cy="529.0904" inkscape:window-width="1312" inkscape:window-height="969" inkscape:window-x="0" inkscape:window-y="0" inkscape:window-maximized="0" inkscape:current-layer="svg1060" />
				<path d="M1008.712033 405.439946l-58.319417 57.03643-159.718403-158.428416 58.324417-57.03943c17.729823-17.734823 45.624544-20.279797 60.824392-5.076949l105.213948 102.680973C1028.99683 359.800402 1026.446856 387.690123 1008.712033 405.439946L1008.712033 405.439946 1008.712033 405.439946 1008.712033 405.439946 1008.712033 405.439946zM762.810492 330.650693l160.97839 157.168428L540.98871 865.549345c-1.269987 1.264987-6.364936 6.344937-13.949861 7.604924-1.254987 0-1.254987 0-2.544975 1.264987l-200.267997 62.129379c-5.071949 1.269987-11.411886 1.269987-15.221848-2.544975-3.792962-3.789962-5.062949-8.869911-2.549975-15.219848l63.394366-196.477035c0-1.244988 0-1.244988 1.271987-2.529975 1.257987-6.354936 6.336937-13.939861 8.844912-15.219848l73.529265-67.179328L210.157018 637.377626c-10.151898 0-19.02181-11.434886-19.02181-20.269797 0-10.169898 8.869911-21.559784 19.02181-21.559784l281.389186 0L762.810492 330.650693 762.810492 330.650693zM491.546205 852.874471l-98.884011-97.589024-26.621734 82.399176 41.831582 41.824582L491.546205 852.874471 491.546205 852.874471zM757.730543 392.765072c-5.074949-5.059949-15.224848-5.059949-20.279797 0L437.01675 686.836132c-6.334937 5.059949-6.334937 15.204848 0 20.274797 5.076949 5.069949 15.226848 5.069949 20.286797 0l299.146009-294.064059C762.810492 407.97292 762.810492 399.100009 757.730543 392.765072L757.730543 392.765072 757.730543 392.765072 757.730543 392.765072 757.730543 392.765072zM89.719223 966.96333c-10.126899 0-20.261797-3.809962-20.261797-13.939861L69.457425 151.933481c-1.264987-19.01681 11.411886-15.201848 25.339747-15.201848l69.714303 0 0 26.616734c0 10.146899 11.399886 19.01681 22.814772 19.01681 10.151898 0 22.831772-8.869911 22.831772-19.01681l0-26.616734 103.935961 0 0 26.616734c0 10.146899 11.396886 19.01681 22.814772 19.01681 10.151898 0 22.831772-8.869911 22.831772-19.01681l0-26.616734 95.064049 0 0 26.616734c0 10.146899 11.399886 19.01681 22.819772 19.01681 10.161898 0 22.826772-8.869911 22.826772-19.01681l0-26.616734 95.071049 0 0 26.616734c0 10.146899 11.414886 19.01681 22.824772 19.01681 10.144899 0 22.824772-8.869911 22.824772-19.01681l0-26.616734 79.839202 0c10.149899 0 16.489835 5.054949 16.489835 15.201848l0 131.828682 58.324417-57.03443 0-74.794252c0-41.829582-31.694683-72.244278-74.814252-72.244278l-81.114189 0L639.896721 30.237698c0-10.151898-11.409886-19.01681-22.829772-19.01681-10.134899 0-22.809772 8.864911-22.809772 19.01681l0 49.434506-95.071049 0L499.186128 30.237698c0-10.151898-11.399886-19.01681-22.834772-19.01681-10.146899 0-22.804772 8.864911-22.804772 19.01681l0 49.434506-95.074049 0L358.472535 30.237698c0-10.151898-11.411886-19.01681-22.826772-19.01681-10.151898 0-22.814772 8.864911-22.814772 19.01681l0 49.434506L208.887031 79.672203 208.887031 30.237698c0-10.151898-11.409886-19.01681-22.826772-19.01681-10.149899 0-22.811772 8.864911-22.811772 19.01681l0 49.434506L94.796172 79.672203c-57.03643 0-84.921151 30.411696-84.921151 72.244278l0 799.822002c0 41.809582 38.01662 72.249278 81.131189 72.249278l628.709713 0c43.094569 0 74.789252-30.439696 74.789252-72.249278L794.505175 675.406246l-58.314417 57.03443 0 220.536795c0 10.159898-6.339937 13.95486-16.474835 13.95486M587.867241 296.441036 208.887031 296.441036c-10.149899 0-17.734823 12.661873-17.734823 21.529785 0 10.146899 7.604924 21.549785 17.734823 21.549785l378.98021 0c10.164898 0 17.749823-12.681873 17.749823-21.549785C605.617064 309.103909 598.01714 296.441036 587.867241 296.441036L587.867241 296.441036 587.867241 296.441036 587.867241 296.441036 587.867241 296.441036zM587.867241 442.197578 208.887031 442.197578c-10.149899 0-17.734823 12.661873-17.734823 21.544785 0 10.149899 7.604924 21.529785 17.734823 21.529785l378.98021 0c10.164898 0 17.749823-12.676873 17.749823-21.529785C605.617064 454.859451 598.01714 442.197578 587.867241 442.197578L587.867241 442.197578 587.867241 442.197578 587.867241 442.197578 587.867241 442.197578z" id="path1058" style="fill: black;" />
			</svg>WP Log</h1>
		<form method="get" action="<?php echo esc_attr(esc_url($_SERVER['REQUEST_URI'])); ?>">
			<select name="uid" onchange="this.parentElement.submit()">
				<option value="">- By User -</option>
				<?php
				foreach (get_users_of_blog() as $u) {
				?>
					<option value="<?php echo esc_attr($u->ID); ?>" <?php if ($u->ID == sanitize_text_field(@$_REQUEST['uid'])) { ?> selected<?php } ?>><?php echo esc_html($u->ID); ?> (<?php echo esc_html($u->user_login); ?>)</option>
				<?php
				}
				?>
			</select>
			<?php $listTable->search_box('Search', 'search'); ?>
			<?php $listTable->display(); ?>
			<select name="per-page" onchange="this.parentElement.submit()" class="top5">
				<option value="<?php echo esc_attr($wpLogDefaultPerPage); ?>">- Per Page -</option>
				<?php
				$perPageOptions = array(50,  100,  500, 1000, 'All');

				foreach ($perPageOptions as $p) {
				?>
					<option value="<?php echo esc_attr((int)$p); ?>" <?php if ((int)$p === $wpLogPerPage) { ?> selected<?php } ?>><?php echo esc_html($p); ?></option>
				<?php
				}
				?>
			</select><button type="button" onclick="javascript: window.location=window.location+'&wp_log_export';" class="button export_button">Export</button><button type="button" onclick="javascript: if(confirm('Are you sure you want to truncate (empty) this log?')) { window.location=window.location+'&wp_log_truncate'; }" class="button export_button">Truncate</button>
			<input type="hidden" name="page" value="<?php echo esc_attr(sanitize_text_field($_REQUEST['page'])); ?>" />
		</form>
	</div>
<?php
}

function wp_log()
{
	if (isset($_REQUEST['svg'])) {
		header('Content-Type: image/svg+xml');
		echo <<<EOL
<svg style="padding: 0 0 0 0.75em; margin: -1px 0 0 0;" viewBox="0 0 2048 2048" xmlns="http://www.w3.org/2000/svg"><path d="M1008.712033 405.439946l-58.319417 57.03643-159.718403-158.428416 58.324417-57.03943c17.729823-17.734823 45.624544-20.279797 60.824392-5.076949l105.213948 102.680973C1028.99683 359.800402 1026.446856 387.690123 1008.712033 405.439946L1008.712033 405.439946 1008.712033 405.439946 1008.712033 405.439946 1008.712033 405.439946zM762.810492 330.650693l160.97839 157.168428L540.98871 865.549345c-1.269987 1.264987-6.364936 6.344937-13.949861 7.604924-1.254987 0-1.254987 0-2.544975 1.264987l-200.267997 62.129379c-5.071949 1.269987-11.411886 1.269987-15.221848-2.544975-3.792962-3.789962-5.062949-8.869911-2.549975-15.219848l63.394366-196.477035c0-1.244988 0-1.244988 1.271987-2.529975 1.257987-6.354936 6.336937-13.939861 8.844912-15.219848l73.529265-67.179328L210.157018 637.377626c-10.151898 0-19.02181-11.434886-19.02181-20.269797 0-10.169898 8.869911-21.559784 19.02181-21.559784l281.389186 0L762.810492 330.650693 762.810492 330.650693zM491.546205 852.874471l-98.884011-97.589024-26.621734 82.399176 41.831582 41.824582L491.546205 852.874471 491.546205 852.874471zM757.730543 392.765072c-5.074949-5.059949-15.224848-5.059949-20.279797 0L437.01675 686.836132c-6.334937 5.059949-6.334937 15.204848 0 20.274797 5.076949 5.069949 15.226848 5.069949 20.286797 0l299.146009-294.064059C762.810492 407.97292 762.810492 399.100009 757.730543 392.765072L757.730543 392.765072 757.730543 392.765072 757.730543 392.765072 757.730543 392.765072zM89.719223 966.96333c-10.126899 0-20.261797-3.809962-20.261797-13.939861L69.457425 151.933481c-1.264987-19.01681 11.411886-15.201848 25.339747-15.201848l69.714303 0 0 26.616734c0 10.146899 11.399886 19.01681 22.814772 19.01681 10.151898 0 22.831772-8.869911 22.831772-19.01681l0-26.616734 103.935961 0 0 26.616734c0 10.146899 11.396886 19.01681 22.814772 19.01681 10.151898 0 22.831772-8.869911 22.831772-19.01681l0-26.616734 95.064049 0 0 26.616734c0 10.146899 11.399886 19.01681 22.819772 19.01681 10.161898 0 22.826772-8.869911 22.826772-19.01681l0-26.616734 95.071049 0 0 26.616734c0 10.146899 11.414886 19.01681 22.824772 19.01681 10.144899 0 22.824772-8.869911 22.824772-19.01681l0-26.616734 79.839202 0c10.149899 0 16.489835 5.054949 16.489835 15.201848l0 131.828682 58.324417-57.03443 0-74.794252c0-41.829582-31.694683-72.244278-74.814252-72.244278l-81.114189 0L639.896721 30.237698c0-10.151898-11.409886-19.01681-22.829772-19.01681-10.134899 0-22.809772 8.864911-22.809772 19.01681l0 49.434506-95.071049 0L499.186128 30.237698c0-10.151898-11.399886-19.01681-22.834772-19.01681-10.146899 0-22.804772 8.864911-22.804772 19.01681l0 49.434506-95.074049 0L358.472535 30.237698c0-10.151898-11.411886-19.01681-22.826772-19.01681-10.151898 0-22.814772 8.864911-22.814772 19.01681l0 49.434506L208.887031 79.672203 208.887031 30.237698c0-10.151898-11.409886-19.01681-22.826772-19.01681-10.149899 0-22.811772 8.864911-22.811772 19.01681l0 49.434506L94.796172 79.672203c-57.03643 0-84.921151 30.411696-84.921151 72.244278l0 799.822002c0 41.809582 38.01662 72.249278 81.131189 72.249278l628.709713 0c43.094569 0 74.789252-30.439696 74.789252-72.249278L794.505175 675.406246l-58.314417 57.03443 0 220.536795c0 10.159898-6.339937 13.95486-16.474835 13.95486M587.867241 296.441036 208.887031 296.441036c-10.149899 0-17.734823 12.661873-17.734823 21.529785 0 10.146899 7.604924 21.549785 17.734823 21.549785l378.98021 0c10.164898 0 17.749823-12.681873 17.749823-21.549785C605.617064 309.103909 598.01714 296.441036 587.867241 296.441036L587.867241 296.441036 587.867241 296.441036 587.867241 296.441036 587.867241 296.441036zM587.867241 442.197578 208.887031 442.197578c-10.149899 0-17.734823 12.661873-17.734823 21.544785 0 10.149899 7.604924 21.529785 17.734823 21.529785l378.98021 0c10.164898 0 17.749823-12.676873 17.749823-21.529785C605.617064 454.859451 598.01714 442.197578 587.867241 442.197578L587.867241 442.197578 587.867241 442.197578 587.867241 442.197578 587.867241 442.197578z" id="path1058" style="fill: white;" /></svg>
EOL;
		die();
	}

	if (isset($_REQUEST['wp_log_truncate'])) {
		if (!current_user_can('manage_options')) wp_die(__('You do not have sufficient permissions to access this page.'));

		wp_log_truncate();
		header('Location: ' . str_replace('&wp_log_truncate', '', $_SERVER['REQUEST_URI']));
		die();
	}

	if (isset($_REQUEST['wp_log_export'])) {
		if (!current_user_can('manage_options')) wp_die(__('You do not have sufficient permissions to access this page.'));

		header('Content-type: text/tab-separated-values');
		header("Content-Disposition: attachment;filename=wp_log.csv");

		$contents = '';

		$listTable = new WP_Log_Table(false);

		foreach ($listTable->table_data(false) as $key => $row) {
			if ($key === 0) {
				$line = '';

				foreach (array_keys($row) as $column) {
					$line .=  $column;
					$line .= "\t";
				}

				$line = trim($line) . "\r\n";

				$contents .= $line;
			}

			$line = '';

			foreach ($row as $val) {
				$line .= wp_log_one_liner($val);
				$line .= "\t";
			}

			$line = trim($line) . "\r\n";

			$contents .= $line;
		}

		echo wp_kses_post(trim($contents));

		die();
	}
}

add_action('init', 'wp_log');

add_filter('cron_schedules', 'wp_log_add_cron_interval');

function wp_log_add_cron_interval($schedules)
{
	$schedules['wp_log'] = array(
		'interval' => 30 * 86400,
		'display'  => esc_html__('Every Month (30 Days)'),
	);

	return $schedules;
}

register_deactivation_hook(__FILE__, 'wp_log_deactivate');

function wp_log_deactivate()
{
	wp_unschedule_event(wp_next_scheduled('wp_log_cron_hook'), 'wp_log_cron_hook');
}

register_activation_hook(__FILE__, 'wp_log_activate');

function wp_log_activate()
{
	if (!wp_next_scheduled('wp_log_cron_hook')) wp_schedule_event(time(), 'wp_log', 'wp_log_cron_hook');
}

add_action('wp_log_cron_hook', 'wp_log_cron_exec');

function wp_log_cron_exec()
{
	wp_log_truncate(true);
}

function wp_log_truncate($all = false)
{
	global $wpdb;

	$sql = 'DELETE FROM ' . $wpdb->prefix . 'log';

	if (!$all) $sql .= ' WHERE site_id = ' . esc_sql(get_current_blog_id());

	$wpdb->query($sql);

	if ($all) $wpdb->query('OPTIMIZE TABLE ' . $wpdb->prefix . 'log');
}

add_filter('query', 'wp_logger');

function wp_logger($query)
{
	global $wpdb;

	$exclude = array($wpdb->prefix . 'log');

	if (preg_match('/^(INSERT|UPDATE|DELETE)\s.*?(' . $wpdb->prefix . '[^\s`\']+)/ius', trim($query), $match)) {
		if (defined('DOING_CRON') || in_array($match[2], $exclude)) return $query;

		$wpdb->insert(
			$wpdb->prefix . 'log',
			array(
				'site_id' => get_current_blog_id(),
				'user_id' => get_current_user_id(),
				'ctime' => date('Y-m-d H:i:s'),
				'ip' => $_SERVER['REMOTE_ADDR'],
				'agent' => $_SERVER['HTTP_USER_AGENT'],
				'path' => $_SERVER['REQUEST_URI'],
				'query' =>  $query,
			)
		);
	}

	return $query;
}

function wp_log_uninstall()
{
	global $wpdb;

	$table_name = $wpdb->prefix . 'log';

	$wpdb->query("DROP TABLE $table_name");
}

register_uninstall_hook(__FILE__, 'wp_log_uninstall');
