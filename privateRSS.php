<?php
/*
Plugin Name: Private RSS
Plugin URI: http://webmania.cc/private-rss
Description: You could give the full feed to users who paid for and summary feed for everyone else. Available languages: Hungarian: <a href="http://webmania.cc">rrd</a>, Russian: <a href="http://www.fatcow.com">Fat Cow</a>
Version: 0.2
Author: rrd
Author URI: http://webmania.cc
*/

/*  Copyright 2006-2009  rrd

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    For a copy of the GNU General Public License, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/*
Install:
1. Download the plugin
2. Unzip
3. Upload to your /wp-content/plugins/ folder
4. A PrivateRSS oldalon hozzáadhatsz előfizetőket akik egyedi url alapján megkapják a teljes feed-et nem csak a kivonatot
 
Módosításkor ha az összeget + jellel kezdjük akkor a már meglévő összeget növeli
a bevitt új értékkel


*/

global $table_prefix;

define('PRSS_TABLE', $table_prefix.'private_rss');
define('PRSS_FILE', 'private-rss/privateRSS.php');
define('PRSS_ADMIN_PATH', 'plugins.php?page='.PRSS_FILE);
define('PRSS_ADMIN_URL', get_option('siteurl').'/wp-admin/'.PRSS_ADMIN_PATH);

register_activation_hook( __FILE__, 'prss_setup' );

load_plugin_textdomain('privateRSS', 'wp-content/plugins/private-rss');

add_action('admin_menu', 'prss_admin');
add_action('admin_head', 'prss_head');

add_filter('the_content', 'prss_feed');

// Actions--------------------------------------------------------------------------------------

function prss_setup(){
	global $wpdb;

	//create the table for the plugin
	$sql = 'CREATE TABLE IF NOT EXISTS `' . PRSS_TABLE . '` (
       `id` int(11) NOT NULL auto_increment,
       `mail` varchar(255) NOT NULL,
       `registered` date NOT NULL,
       `due` date NOT NULL,
       `url` varchar(32) NOT NULL,
       `income` decimal(8,2) NOT NULL,
		`deleted` tinyint(1) NOT NULL default 0,
       PRIMARY KEY  (`id`)
	  );';

	if($wpdb->query($sql) === false){
		die(__('Problem creating ', 'privateRSS') . PRSS_TABLE . __(' table', 'privateRSS'));
		}
	
	//add default options or use existing
	$prss_options = get_option('prss_options') ? get_option('prss_options') : array(
		//	'decimals' => 0,	//todo: number_format hívásoknál figyelni
			'order' => 'income',
			'moreText' => 'Read more ...',
			'summaryLength' => 350,
		//	'showPasswordProtected' => false,	//todo: true esetén be kell szedni a password protected postokat is az rss-be
		//	'privateCategory' => 'private',	//todo: ez a kategória slug ami nem jelenik meg az oldalon, csak az rss-ben
		//	'dateFormat' => 'Y-m-d',	//todo: registered és due bevitelnél figyelni kell
			'error_invalidUrl' => __('This feed URL is not valid, so you will get just the summary feed!', 'privateRSS'),
			'error_subscriptionOverdued' => __('Your subscription is overdued. From now you will get just the summary feed!', 'privateRSS')
			);
	//$prss_options_description
	update_option('prss_options', $prss_options);
	}
	
function prss_feed($content){
	//makes the private feed
	global $wpdb, $post;
	if(is_feed()){	//if we are on a feed
		$prss_options = get_option('prss_options');
		$error = false;
		if($_GET['pRSS']){	//something is in the url, if ok we should give the private RSS feed
			//first check if the url is valid and did not overdued
			$sql = 'SELECT *
				FROM ' . PRSS_TABLE . '
				WHERE url = "' . $wpdb->escape($_GET['pRSS']) . '"
				AND deleted != 1';
			$prss = $wpdb->get_row($sql);
			if($prss){
				//the url is valid
				if($prss->due >= date('Y-m-d'))	//give the full feed to the user
					$fullContent = $content;
				else	//the user subscription is overdued
					$error = $prss_options['error_subscriptionOverdued'] . ' <hr />';
			}
			else{
				//the url is invalid give the summary feed with an error message
				$error = $prss_options['error_invalidUrl'] . ' <hr />';
			}
		}
		if(!$_GET['pRSS'] || $error){	//summary feed
			//cut post at more tag's position or if it is not exists at the summaryLength
			$morePos = strpos($content, '<span id="more-') ? strpos($content, '<span id="more-') : $prss_options['summaryLength'];
			$content = $error . substr($content, 0, $morePos);
			$content .= ' <a href="'.get_the_guid().'">' . $prss_options['moreText'] . '</a>';
		}
	}
	return $fullContent ? $fullContent : $content;
}

function prss_admin(){
	add_submenu_page('plugins.php', 'Private RSS', 'Private RSS', 9, PRSS_FILE, 'prss_adminpage');
	}

function prss_head(){
	//insert content to head: css
	print '<link rel="stylesheet" href="'.get_option('siteurl').'/wp-content/plugins/private-rss/privateRSS.css?ver=5" type="text/css" />';
	//todo: remove ver=x
	}

function prss_adminpage(){
	global $wpdb;
	
	$prss_options = get_option('prss_options');
	
	$mod = ($_GET['id'] && $_GET['act'] == 'ed') ? true : false;		//edit
	
	if($_GET['id']){
		//changing the subscriber datas, because of errors or prolongation
		$act = isset($_GET['act']) ? $wpdb->escape($_GET['act']) : false;
		if(!$wpdb->escape($_GET['mail'])){
			$sql = 'SELECT * FROM ' . PRSS_TABLE . ' WHERE id = ' . $wpdb->escape($_GET['id']);
			$_GET = $wpdb->get_row($sql, ARRAY_A);
			}
		}

	?>
	<div class="wrap privaterss">
	<h2>Private RSS</h2>

	<?php
	print '<a href="'.PRSS_ADMIN_URL.'" class="prsssettings">PrivateRSS</a>';
	print '<a href="'.PRSS_ADMIN_URL.'&adm=settings" class="prsssettings">' . __('Settings', 'privateRSS')  . '</a>';
	?>

	<?php
	if($_GET['adm'] == 'settings'){
		//settings page
		$prss_option_descriptions = array(
			'decimals' => __('Decimals', 'privateRSS'),
			'order' => __('Order', 'privateRSS') . ' (mail / registered / due / income)',
			'moreText' => __('More text', 'privateRSS'),
			'summaryLength' => __('Summary lenght', 'privateRSS'),
			'showPasswordProtected' => __('Show password protected', 'privateRSS'),
			'privateCategory' => __('Private category', 'privateRSS'),
			'dateFormat' => __('Date format', 'privateRSS'),
			'error_invalidUrl' => __('Invalid URL', 'privateRSS'),
			'error_subscriptionOverdued' => __('Subscription overdued', 'privateRSS')
			);
		print '<h3>' . __('Settings', 'privateRSS') . '</h3>';
		
		if($_GET['feedburner_url']){
			//the settings are modified
			//feedburner settings
			$feedburner_settings = array(
				'feedburner_url' => $wpdb->escape($_GET['feedburner_url']),
				'feedburner_comments_url' => $wpdb->escape($_GET['feedburner_comments_url']),
				);
			update_option('feedburner_settings', $feedburner_settings);
			
			//private RSS options
			foreach($prss_options as $key => $option){
				$prss_options[$key] = $wpdb->escape($_GET[$key]);
			}
			update_option('prss_options', $prss_options);
		}
		
		$feedburner_settings = get_option('feedburner_settings');
		?>
		<form action="<?php echo(PRSS_ADMIN_PATH); ?>" method='get' class="prsssettingsform">
			<input type="hidden" id="page" name="page" value="<?php print PRSS_FILE; ?>" />
			<input type="hidden" id="adm" name="adm" value="settings" />

			<label for="feedburner_url"><?php print 'Feedburner URL'; ?></label>
			<input type="text" id="feedburner_url" name="feedburner_url" value="<?php print $feedburner_settings['feedburner_url']; ?>" />
			
			<label for="feedburner_comments_url"><?php print 'Feedburner Comments URL'; ?></label>
			<input type="text" id="feedburner_comments_url" name="feedburner_comments_url" value="<?php print $feedburner_settings['feedburner_comments_url']; ?>" />

			<?php
			foreach($prss_options as $key => $option){
				print '<label for="'.$key.'">' . $prss_option_descriptions[$key] . '</label>';
				print '<input type="text" id="'.$key.'" name="'.$key.'" value="'.$option.'" />';
				}
			?>

			<input type="submit" value="<?php print __('Change', 'privateRSS'); ?>">
		</form>
		<?php
	}
	else{
		//check if options->read is full text not summary
		if(get_option('rss_use_excerpt')){
			print '<div class="error fade">';
			print '<p>';
			print __('<strong>ERROR</strong>: You have the "<i>For each article in a feed, show</i>" field set to <b>Summary</b> in the <a href="options-reading.php">Options -> Reading page</a>. You must change this to <b>Full text</b> for PrivateRSS to work.', 'privateRSS');
			print '</p>';
			print '</div>';
		}
	
		//if feedburner is active the user shoud switch it off
		$plugin_file = 'FeedBurner_FeedSmith_Plugin.php';
		if(is_plugin_active($plugin_file)){
			print '<div class="error fade">';
			print '<p>';
			print __('<strong>ERROR</strong>: The "<i>Feedburner / Feedsmith</i>" plugin is <b>active</b>.', 'privateRSS');
			$link = '<a href="' . wp_nonce_url('plugins.php?action=deactivate&amp;plugin=' . $plugin_file, 'deactivate-plugin_' . $plugin_file) . '" title="' . __('Deactivate this plugin') . '">' . __('Deactivate', 'privateRSS') . '</a>';
			printf(__('Please %s it for PrivateRSS to work. PrivateRSS will do the same for you as the Feedburner Feedsmith plugin.', 'privateRSS'), $link);
			print '</p>';
			print '</div>';
		}
		?>
		<h3><?php print __('New Subscriber', 'privateRSS'); ?></h3>
		<form action="<?php echo(PRSS_ADMIN_PATH); ?>" method='get'>
			<input type="hidden" id="page" name="page" value="<?php print PRSS_FILE; ?>" />
			<input type="hidden" id="id" name="id" value="<?php print $mod ? $wpdb->escape($_GET['id']) : 0; ?>" />
			<input type="hidden" id="act" name="act" value="<?php print $act; ?>" />
			<label for="mail"><?php print __('E-mail', 'privateRSS'); ?></label>
			<input type="text" id="mail" name="mail" value="<?php print $mod ? $wpdb->escape($_GET['mail']) : ''; ?>" />
			<label for="registered"><?php print __('Registered', 'privateRSS'); ?></label>
			<input type="text" id="registered" name="registered" value="<?php print $mod ? $wpdb->escape($_GET['registered']) : date('Y-m-d'); ?>" />
			<label for="due"><?php print __('Subscribtion end', 'privateRSS'); ?></label>
			<input type="text" id="due" name="due" value="<?php print $mod ? $wpdb->escape($_GET['due']) : date('Y-m-d', time()+365*24*60*60); ?>" />
			<label for="income"><?php print __('Income', 'privateRSS'); ?></label>
			<input type="text" id="income" name="income" value="<?php print $mod ? number_format($wpdb->escape($_GET['income']), $prss_options['decimals'], ',', '') : ''; ?>" />
			<input type="submit" value="<?php print $mod ? __('Edit', 'privateRSS') : __('New Subscriber', 'privateRSS'); ?>">
		</form>
		<?php
			if($_GET['mail'] && !$_GET['id']){
				//add new subsriber to the database
				//check if the user already in the database
				$sql = 'SELECT mail FROM '.PRSS_TABLE.' WHERE mail = "'.$wpdb->escape($_GET['mail']).'" AND deleted != 1';
				if($wpdb->get_var($sql))
		         print $wpdb->escape($_GET['mail']) . __(' is already registered subscriber. You could modify it if you want.', 'privateRSS');
				else{	//really a new user
					$url = md5($wpdb->escape($_GET['mail']).time());
					$sql = "INSERT INTO `".PRSS_TABLE."` (`mail` ,`registered` ,`due`, `url`, `income`)
							VALUES ('".$wpdb->escape($_GET['mail'])."', '".$wpdb->escape($_GET['registered'])."', '".$wpdb->escape($_GET['due'])."', '".$url."', '".$wpdb->escape($_GET['income'])."')";
					$wpdb->query($sql);
					$additional_header='Content-type: text/plain; charset=utf-8';
					mail($wpdb->escape($_GET['mail']), __('Successfull subscription', 'privateRSS'), sprintf(__("You have successfully subscribed to %s. \nYou could access your private RSS feed by this url: %s till %s. \n\nThank you for your subscription.", 'privateRSS'), get_option('siteurl'), get_option('siteurl').'/feed/?pRSS='.$url, $wpdb->escape($_GET['due'])), $additional_header);
					}
				}
			if($act == 'ed'){
				//changing the subscriber datas, because of errors or prolongation
				//if the amount starts with an + we just add this amount the the saved one
				if(strpos($wpdb->escape($_GET['income']), '+') === 0)
					$add = 'income + ';
				else
					$add = '';
				$sql = 'UPDATE `' . PRSS_TABLE . '`
					SET 
					`mail` = "'.$wpdb->escape($_GET['mail']).'",
					`registered` = "'.$wpdb->escape($_GET['registered']).'",
					`due` = "'.$wpdb->escape($_GET['due']).'",
					`income` = '.$add . $wpdb->escape($_GET['income']).'
					WHERE `id` = '.$wpdb->escape($_GET['id']);
				$wpdb->query($sql);
				}
			elseif($act == 'del'){
				$sql = 'UPDATE `' . PRSS_TABLE . '`
					SET 
					`deleted` = 1
					WHERE `id` = '.$wpdb->escape($_GET['id']);
				$wpdb->query($sql);
				}
		?>
	
		<h3><?php print __('Subscribers', 'privateRSS'); ?></h3>
		<table class="widefat">
			<thead>
			<tr>
				<?php
					$asc = $wpdb->escape($_GET['asc']) ? '' : '&asc=ASC';
					print '<th>'.__('Action', 'privateRSS').'</td>
		         <th>
						<a href="'.PRSS_ADMIN_URL.'&order=mail'.$asc.'" title="'.__('Change order','privateRSS').'">'.__('Subscribers', 'privateRSS').'</a>
						<a href="'.PRSS_ADMIN_URL.'" title="'.__('Add new','privateRSS').'">('.__('Add new', 'privateRSS').')</a>
					</th>
		         <th><a href="'.PRSS_ADMIN_URL.'&order=registered'.$asc.'" title="'.__('Change order','privateRSS').'">'.__('Registered', 'privateRSS').'</a></th>
		         <th><a href="'.PRSS_ADMIN_URL.'&order=due'.$asc.'" title="'.__('Change order','privateRSS').'">'.__('Subscribtion end', 'privateRSS').'</a></th>
		         <th><a href="'.PRSS_ADMIN_URL.'&order=income'.$asc.'" title="'.__('Change order','privateRSS').'">'.__('Income', 'privateRSS').'</a></th>
		         <th>URL</th>';
				?>
			</tr>
			</thead>
		<?php
		//list of subscribers
		$order = $_GET['order'] ? $wpdb->escape($_GET['order']) : $prss_options['order'];
		$asc = $asc ? 'DESC' : 'ASC';
		$sql = "SELECT * FROM " . PRSS_TABLE . " WHERE deleted != 1 ORDER BY $order $asc";
		$users = $wpdb->get_results($sql, ARRAY_A);
		$i = 0;
		if($users){
		   foreach($users as $user){
		   	if($user['due'] <= date('Y-m-d'))
		   		print '<tr class="prsstroverdued">';
		   	elseif($i % 2) print '<tr class="prsstra">';
		   	else print '<tr>';
		   	print '<td>
		         <a href="'.PRSS_ADMIN_URL.'&id='.$user['id'].'&act=ed">'.__('Edit', 'privateRSS').'</a>
					<a href="'.PRSS_ADMIN_URL.'&id='.$user['id'].'&act=del">'.__('Delete', 'privateRSS').'</a>
		      </td>
		      <td>'.$user['mail'].'</td>
		      <td>'.$user['registered'].'</td>
		      <td>'.$user['due'].'</td>
		      <td class="prssincome">'.number_format($user['income'], $prss_options['decimals'], ',', '.').'</td>
		      <td><a href="'.get_option('siteurl').'/feed/?pRSS='.$user['url'].'">'.$user['url'].'</a></td>
		      </tr>';
		   	$i++;
		   	}
		   }
		print '</table>';
		print '</div>';
	}
}

//------------feedburner redirect---------------------
/*
concept is taken form Feedburner FeedSmith plugin version 2.3.1
methods: prss_feed_redirect, prss_check_url
*/
if (!preg_match("/feedburner|feedvalidator/i", $_SERVER['HTTP_USER_AGENT'])) {
	//if not feedburner or feedvalidator comes we should redirect
	add_action('template_redirect', 'prss_feed_redirect');
	add_action('init','prss_check_url');
}

function prss_check_url(){
	$feedburner_settings = get_option('feedburner_settings');
	switch (basename($_SERVER['PHP_SELF'])) {
		case 'wp-rss.php':
		case 'wp-rss2.php':
		case 'wp-atom.php':
		case 'wp-rdf.php':
			if (trim($feedburner_settings['feedburner_url']) != '') {
				if (function_exists('status_header')) status_header( 302 );
				header("Location:" . trim($feedburner_settings['feedburner_url']));
				header("HTTP/1.1 302 Temporary Redirect");
				exit();
			}
			break;
		case 'wp-commentsrss2.php':
			if (trim($feedburner_settings['feedburner_comments_url']) != '') {
				if (function_exists('status_header')) status_header( 302 );
				header("Location:" . trim($feedburner_settings['feedburner_comments_url']));
				header("HTTP/1.1 302 Temporary Redirect");
				exit();
			}
			break;
	}
}

function prss_feed_redirect() {
	global $wp, $feed, $withcomments;
	$feedburner_settings = get_option('feedburner_settings');
	if(is_feed() &&
		$feed != 'comments-rss2' &&
		!is_single() &&
		$wp->query_vars['category_name'] == '' &&
		($withcomments != 1) &&
		trim($feedburner_settings['feedburner_url']) != ''
		&& !$_GET['pRSS']		//changed for private RSS
		){
		if (function_exists('status_header')) status_header( 302 );
		header("Location:" . trim($feedburner_settings['feedburner_url']));
		header("HTTP/1.1 302 Temporary Redirect");
		exit();
	}
	elseif(is_feed()
			 && ($feed == 'comments-rss2' || $withcomments == 1)
			 && trim($feedburner_settings['feedburner_comments_url']) != ''
			 ) {
		if (function_exists('status_header')) status_header( 302 );
		header("Location:" . trim($feedburner_settings['feedburner_comments_url']));
		header("HTTP/1.1 302 Temporary Redirect");
		exit();
	}
}
?>
