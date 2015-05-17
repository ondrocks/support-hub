<?php

class shub_envato extends SupportHub_network {

	public $friendly_name = "envato";

	public function init(){
		if(isset($_GET[_support_hub_envato_LINK_REWRITE_PREFIX]) && strlen($_GET[_support_hub_envato_LINK_REWRITE_PREFIX]) > 0){
			// check hash
			$bits = explode(':',$_GET[_support_hub_envato_LINK_REWRITE_PREFIX]);
			if(defined('AUTH_KEY') && isset($bits[1])){
				$shub_envato_message_link_id = (int)$bits[0];
				if($shub_envato_message_link_id > 0){
					$correct_hash = substr(md5(AUTH_KEY.' envato link '.$shub_envato_message_link_id),1,5);
					if($correct_hash == $bits[1]){
						// link worked! log a visit and redirect.
						$link = shub_get_single('shub_envato_message_link','shub_envato_message_link_id',$shub_envato_message_link_id);
						if($link){
							if(!preg_match('#^http#',$link['link'])){
								$link['link'] = 'http://'.trim($link['link']);
							}
							shub_update_insert('shub_envato_message_link_click_id',false,'shub_envato_message_link_click',array(
								'shub_envato_message_link_id' => $shub_envato_message_link_id,
								'click_time' => time(),
								'ip_address' => $_SERVER['REMOTE_ADDR'],
								'user_agent' => $_SERVER['HTTP_USER_AGENT'],
								'url_referrer' => $_SERVER['HTTP_REFERER'],
							));
							header("Location: ".$link['link']);
							exit;
						}
					}
				}
			}
		}
	}

	public function init_menu(){

	}

	public function page_assets($from_master=false){
		if(!$from_master)SupportHub::getInstance()->inbox_assets();

		wp_register_style( 'support-hub-envato-css', plugins_url('networks/envato/shub_envato.css',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array(), '1.0.0' );
		wp_enqueue_style( 'support-hub-envato-css' );
		wp_register_script( 'support-hub-envato', plugins_url('networks/envato/shub_envato.js',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array( 'jquery' ), '1.0.0' );
		wp_enqueue_script( 'support-hub-envato' );

	}

	public function settings_page(){
		include( dirname(__FILE__) . '/envato_settings.php');
	}



	private $accounts = array();

	private function reset() {
		$this->accounts = array();
	}


	public function compose_to(){
		$accounts = $this->get_accounts();
	    if(!count($accounts)){
		    _e('No accounts configured', 'support_hub');
	    }
		foreach ( $accounts as $account ) {
			$envato_account = new shub_envato_account( $account['shub_envato_id'] );
			echo '<div class="envato_compose_account_select">' .
				     '<input type="checkbox" name="compose_envato_id[' . $account['shub_envato_id'] . '][share]" value="1"> ' .
				     ($envato_account->get_picture() ? '<img src="'.$envato_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $envato_account->get( 'envato_name' ) ) . ' (status update)</span>' .
				     '</div>';
			/*echo '<div class="envato_compose_account_select">' .
				     '<input type="checkbox" name="compose_envato_id[' . $account['shub_envato_id'] . '][blog]" value="1"> ' .
				     ($envato_account->get_picture() ? '<img src="'.$envato_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $envato_account->get( 'envato_name' ) ) . ' (blog post)</span>' .
				     '</div>';*/
			$groups            = $envato_account->get( 'groups' );
			foreach ( $groups as $envato_group_id => $group ) {
				echo '<div class="envato_compose_account_select">' .
				     '<input type="checkbox" name="compose_envato_id[' . $account['shub_envato_id'] . '][' . $envato_group_id . ']" value="1"> ' .
				     ($envato_account->get_picture() ? '<img src="'.$envato_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $group->get( 'group_name' ) ) . ' (group)</span>' .
				     '</div>';
			}
		}


	}
	public function compose_message($defaults){
		?>
		<textarea name="envato_message" rows="6" cols="50" id="envato_compose_message"><?php echo isset($defaults['envato_message']) ? esc_attr($defaults['envato_message']) : '';?></textarea>
		<?php
	}

	public function compose_type($defaults){
		?>
		<input type="radio" name="envato_post_type" id="envato_post_type_normal" value="normal" checked>
		<label for="envato_post_type_normal">Normal Post</label>
		<table>
		    <tr>
			    <th class="width1">
				    Subject
			    </th>
			    <td class="">
				    <input name="envato_title" id="envato_compose_title" type="text" value="<?php echo isset($defaults['envato_title']) ? esc_attr($defaults['envato_title']) : '';?>">
				    <span class="envato-type-normal envato-type-option"></span>
			    </td>
		    </tr>
		    <tr>
			    <th class="width1">
				    Picture
			    </th>
			    <td class="">
				    <input type="text" name="envato_picture_url" value="<?php echo isset($defaults['envato_picture_url']) ? esc_attr($defaults['envato_picture_url']) : '';?>">
				    <br/><small>Full URL (eg: http://) to the picture to use for this link preview</small>
				    <span class="envato-type-normal envato-type-option"></span>
			    </td>
		    </tr>
	    </table>
		<?php
	}


	public function get_accounts() {
		$this->accounts = shub_get_multiple( 'shub_envato', array(), 'shub_envato_id' );
		return $this->accounts;
	}


	private function get_url($url, $post_data = false){
		// get feed from fb:

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
		if($post_data){
			curl_setopt($ch, CURLOPT_POST,true);
			curl_setopt($ch, CURLOPT_POSTFIELDS,$post_data);
		}
		$data = curl_exec($ch);
		$feed = @json_decode($data,true);
		//print_r($feed);
		return $feed;

	}
	public function get_paged_data($data,$pagination){

	}

	public static function format_person($data,$envato_account){
		$return = '';
		if($data && isset($data['id'])){
			$return .= '<a href="http://www.envato.com/x/profile/' . $envato_account->get('envato_app_id').'/'.$data['id'].'" target="_blank">';
		}
		if($data && isset($data['firstName'])){
			$return .= htmlspecialchars($data['firstName']);
		}
		if($data && isset($data['id'])){
			$return .= '</a>';
		}
		return $return;
	}

	private $all_messages = false;
	public function load_all_messages($search=array(),$order=array()){
		$sql = "SELECT m.*, m.last_active AS `message_time`, mr.read_time FROM `"._support_hub_DB_PREFIX."shub_envato_message` m ";
		$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_envato_message_read` mr ON m.shub_envato_message_id = mr.shub_envato_message_id";
		$sql .= " WHERE 1 ";
		if(isset($search['status']) && $search['status'] !== false){
			$sql .= " AND `status` = ".(int)$search['status'];
		}
		if(isset($search['shub_envato_group_id']) && $search['shub_envato_group_id'] !== false){
			$sql .= " AND `shub_envato_group_id` = ".(int)$search['shub_envato_group_id'];
		}
		if(isset($search['shub_message_id']) && $search['shub_message_id'] !== false){
			$sql .= " AND `shub_message_id` = ".(int)$search['shub_message_id'];
		}
		if(isset($search['shub_envato_id']) && $search['shub_envato_id'] !== false){
			$sql .= " AND `shub_envato_id` = ".(int)$search['shub_envato_id'];
		}
		if(isset($search['generic']) && !empty($search['generic'])){
			$sql .= " AND `summary` LIKE '%".mysql_real_escape_string($search['generic'])."%'";
		}
		$sql .= " ORDER BY `last_active` DESC ";
		//$this->all_messages = query($sql);
		global $wpdb;
		$this->all_messages = $wpdb->get_results($sql, ARRAY_A);
		return $this->all_messages;
	}
	public function get_next_message(){
		return !empty($this->all_messages) ? array_shift($this->all_messages) : false;
		/*if(mysql_num_rows($this->all_messages)){
			return mysql_fetch_assoc($this->all_messages);
		}
		return false;*/
	}


	// used in our Wp "outbox" view showing combined messages.
	public function get_message_details($shub_message_id){
		if(!$shub_message_id)return array();
		$messages = $this->load_all_messages(array('shub_message_id'=>$shub_message_id));
		// we want data for our colum outputs in the WP table:
		/*'shub_column_time'    => __( 'Date/Time', 'support_hub' ),
	    'shub_column_social' => __( 'Social Accounts', 'support_hub' ),
		'shub_column_summary'    => __( 'Summary', 'support_hub' ),
		'shub_column_links'    => __( 'Link Clicks', 'support_hub' ),
		'shub_column_stats'    => __( 'Stats', 'support_hub' ),
		'shub_column_action'    => __( 'Action', 'support_hub' ),*/
		$data = array(
			'shub_column_social' => '',
			'shub_column_summary' => '',
			'shub_column_links' => '',
		);
		$link_clicks = 0;
		foreach($messages as $message){
			$envato_message = new shub_envato_message(false, false, $message['shub_envato_message_id']);
			$data['message'] = $envato_message;
			$data['shub_column_social'] .= '<div><img src="'.plugins_url('networks/envato/envato-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="envato_icon small"><a href="'.$envato_message->get_link().'" target="_blank">'.htmlspecialchars( $envato_message->get('envato_group') ? $envato_message->get('envato_group')->get( 'group_name' ) : 'Share' ) .'</a></div>';
			$data['shub_column_summary'] .= '<div><img src="'.plugins_url('networks/envato/envato-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="envato_icon small"><a href="'.$envato_message->get_link().'" target="_blank">'.htmlspecialchars( $envato_message->get_summary() ) .'</a></div>';
			// how many link clicks does this one have?
			$sql = "SELECT count(*) AS `link_clicks` FROM ";
			$sql .= " `"._support_hub_DB_PREFIX."shub_envato_message` m ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_envato_message_link` ml USING (shub_envato_message_id) ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_envato_message_link_click` lc USING (shub_envato_message_link_id) ";
			$sql .= " WHERE 1 ";
			$sql .= " AND m.shub_envato_message_id = ".(int)$message['shub_envato_message_id'];
			$sql .= " AND lc.shub_envato_message_link_id IS NOT NULL ";
			$sql .= " AND lc.user_agent NOT LIKE '%Google%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Yahoo%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%envatoexternalhit%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Meta%' ";
			$res = shub_qa1($sql);
			$link_clicks = $res && $res['link_clicks'] ? $res['link_clicks'] : 0;
			$data['shub_column_links'] .= '<div><img src="'.plugins_url('networks/envato/envato-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="envato_icon small">'. $link_clicks  .'</div>';
		}
		if(count($messages) && $link_clicks > 0){
			//$data['shub_column_links'] = '<div><img src="'.plugins_url('networks/envato/envato-logo.png', _DTBAKER_SUPPORTHUB_CORE_FILE_).'" class="envato_icon small">'. $link_clicks  .'</div>';
		}
		return $data;

	}


	public function get_unread_count($search=array()){
		if(!get_current_user_id())return 0;
		$sql = "SELECT count(*) AS `unread` FROM `"._support_hub_DB_PREFIX."shub_envato_message` m ";
		$sql .= " WHERE 1 ";
		$sql .= " AND m.shub_envato_message_id NOT IN (SELECT mr.shub_envato_message_id FROM `"._support_hub_DB_PREFIX."shub_envato_message_read` mr WHERE mr.user_id = '".(int)get_current_user_id()."' AND mr.shub_envato_message_id = m.shub_envato_message_id)";
		$sql .= " AND m.`status` = "._shub_MESSAGE_STATUS_UNANSWERED;
		if(isset($search['shub_envato_group_id']) && $search['shub_envato_group_id'] !== false){
			$sql .= " AND m.`shub_envato_group_id` = ".(int)$search['shub_envato_group_id'];
		}
		if(isset($search['shub_envato_id']) && $search['shub_envato_id'] !== false){
			$sql .= " AND m.`shub_envato_id` = ".(int)$search['shub_envato_id'];
		}
		$res = shub_qa1($sql);
		return $res ? $res['unread'] : 0;
	}


	public function output_row($message, $settings){
		$envato_message = new shub_envato_message(false, false, $message['shub_envato_message_id']);
		    $comments         = $envato_message->get_comments();
		?>
		<tr class="<?php echo isset($settings['row_class']) ? $settings['row_class'] : '';?> envato_message_row <?php echo !isset($message['read_time']) || !$message['read_time'] ? ' message_row_unread' : '';?>"
	        data-id="<?php echo (int) $message['shub_envato_message_id']; ?>">
		    <td class="shub_column_social">
			    <img src="<?php echo plugins_url('networks/envato/envato-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="envato_icon">
			    <a href="<?php echo $envato_message->get_link(); ?>"
		           target="_blank"><?php
				    echo htmlspecialchars( $envato_message->get('envato_group') ? $envato_message->get('envato_group')->get( 'group_name' ) : 'Share' ); ?></a> <br/>
			    <?php echo htmlspecialchars( $envato_message->get_type_pretty() ); ?>
		    </td>
		    <td class="shub_column_time"><?php echo shub_print_date( $message['message_time'], true ); ?></td>
		    <td class="shub_column_from">
			    <?php
		        // work out who this is from.
		        $from = $envato_message->get_from();
			    ?>
			    <div class="shub_from_holder shub_envato">
			    <div class="shub_from_full">
				    <?php
					foreach($from as $id => $from_data){
						?>
						<div>
							<a href="<?php echo $from_data['link'];?>" target="_blank"><img src="<?php echo $from_data['image'];?>" class="shub_from_picture"></a> <?php echo htmlspecialchars($from_data['name']); ?>
						</div>
						<?php
					} ?>
			    </div>
		        <?php
		        reset($from);
		        if(isset($from_data)) {
			        echo '<a href="' . $from_data['link'] . '" target="_blank">' . '<img src="' . $from_data['image'] . '" class="shub_from_picture"></a> ';
			        echo '<span class="shub_from_count">';
			        if ( count( $from ) > 1 ) {
				        echo '+' . ( count( $from ) - 1 );
			        }
			        echo '</span>';
		        }
		        ?>
			    </div>
		    </td>
		    <td class="shub_column_summary">
			    <span style="float:right;">
				    <?php echo count( $comments ) > 0 ? '('.count( $comments ).')' : ''; ?>
			    </span>
			    <div class="envato_message_summary<?php echo !isset($message['read_time']) || !$message['read_time'] ? ' unread' : '';?>"> <?php
				    $summary = $envato_message->get_summary();
				    echo $summary;
				    ?>
			    </div>
		    </td>
			<!--<td></td>-->
		    <td nowrap class="shub_column_action">

			        <a href="<?php echo $envato_message->link_open();?>" class="socialenvato_message_open shub_modal button" data-modaltitle="<?php echo htmlspecialchars($summary);?>" data-socialenvatomessageid="<?php echo (int)$envato_message->get('shub_envato_message_id');?>"><?php _e( 'Open' );?></a>

				    <?php if($envato_message->get('status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
					    <a href="#" class="socialenvato_message_action  button"
					       data-action="set-unanswered" data-id="<?php echo (int)$envato_message->get('shub_envato_message_id');?>"><?php _e( 'Inbox' ); ?></a>
				    <?php }else{ ?>
					    <a href="#" class="socialenvato_message_action  button"
					       data-action="set-answered" data-id="<?php echo (int)$envato_message->get('shub_envato_message_id');?>"><?php _e( 'Archive' ); ?></a>
				    <?php } ?>
		    </td>
	    </tr>
		<?php
	}

	public function init_js(){
		?>
		    ucm.social.envato.api_url = ajaxurl;
		    ucm.social.envato.init();
		<?php
	}

	public function handle_process($process, $options = array()){
		switch($process){
			case 'send_shub_message':
				check_admin_referer( 'shub_send-message' );
				$message_count = 0;
				if(isset($options['shub_message_id']) && (int)$options['shub_message_id'] > 0 && isset($_POST['envato_message']) && !empty($_POST['envato_message'])){
					// we have a social message id, ready to send!
					// which envato accounts are we sending too?
					$envato_accounts = isset($_POST['compose_envato_id']) && is_array($_POST['compose_envato_id']) ? $_POST['compose_envato_id'] : array();
					foreach($envato_accounts as $envato_account_id => $send_groups){
						$envato_account = new shub_envato_account($envato_account_id);
						if($envato_account->get('shub_envato_id') == $envato_account_id){
							/* @var $available_groups shub_envato_group[] */
				            $available_groups = $envato_account->get('groups');
							if($send_groups){
							    foreach($send_groups as $envato_group_id => $tf){
								    if(!$tf)continue;// shouldnt happen
								    switch($envato_group_id){
									    case 'share':
										    // doing a status update to this envato account
											$envato_message = new shub_envato_message($envato_account, false, false);
										    $envato_message->create_new();
										    $envato_message->update('shub_envato_group_id',0);
							                $envato_message->update('shub_message_id',$options['shub_message_id']);
										    $envato_message->update('shub_envato_id',$envato_account->get('shub_envato_id'));
										    $envato_message->update('summary',isset($_POST['envato_message']) ? $_POST['envato_message'] : '');
										    $envato_message->update('title',isset($_POST['envato_title']) ? $_POST['envato_title'] : '');
										    $envato_message->update('link',isset($_POST['envato_link']) ? $_POST['envato_link'] : '');
										    if(isset($_POST['track_links']) && $_POST['track_links']){
												$envato_message->parse_links();
											}
										    $envato_message->update('type','share');
										    $envato_message->update('data',json_encode($_POST));
										    $envato_message->update('user_id',get_current_user_id());
										    // do we send this one now? or schedule it later.
										    $envato_message->update('status',_shub_MESSAGE_STATUS_PENDINGSEND);
										    if(isset($options['send_time']) && !empty($options['send_time'])){
											    // schedule for sending at a different time (now or in the past)
											    $envato_message->update('last_active',$options['send_time']);
										    }else{
											    // send it now.
											    $envato_message->update('last_active',0);
										    }
										    if(isset($_FILES['envato_picture']['tmp_name']) && is_uploaded_file($_FILES['envato_picture']['tmp_name'])){
											    $envato_message->add_attachment($_FILES['envato_picture']['tmp_name']);
										    }
											$now = time();
											if(!$envato_message->get('last_active') || $envato_message->get('last_active') <= $now){
												// send now! otherwise we wait for cron job..
												if($envato_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
										            $message_count ++;
												}
											}else{
										        $message_count ++;
												if(isset($_POST['debug']) && $_POST['debug']){
													echo "Message will be sent in cron job after ".shub_print_date($envato_message->get('last_active'),true);
												}
											}
										    break;
									    case 'blog':
											// doing a blog post to this envato account
											// not possible through api

										    break;
									    default:
										    // posting to one of our available groups:

										    // see if this is an available group.
										    if(isset($available_groups[$envato_group_id])){
											    // push to db! then send.
											    $envato_message = new shub_envato_message($envato_account, $available_groups[$envato_group_id], false);
											    $envato_message->create_new();
											    $envato_message->update('shub_envato_group_id',$available_groups[$envato_group_id]->get('shub_envato_group_id'));
								                $envato_message->update('shub_message_id',$options['shub_message_id']);
											    $envato_message->update('shub_envato_id',$envato_account->get('shub_envato_id'));
											    $envato_message->update('summary',isset($_POST['envato_message']) ? $_POST['envato_message'] : '');
											    $envato_message->update('title',isset($_POST['envato_title']) ? $_POST['envato_title'] : '');
											    if(isset($_POST['track_links']) && $_POST['track_links']){
													$envato_message->parse_links();
												}
											    $envato_message->update('type','group_post');
											    $envato_message->update('link',isset($_POST['link']) ? $_POST['link'] : '');
											    $envato_message->update('data',json_encode($_POST));
											    $envato_message->update('user_id',get_current_user_id());
											    // do we send this one now? or schedule it later.
											    $envato_message->update('status',_shub_MESSAGE_STATUS_PENDINGSEND);
											    if(isset($options['send_time']) && !empty($options['send_time'])){
												    // schedule for sending at a different time (now or in the past)
												    $envato_message->update('last_active',$options['send_time']);
											    }else{
												    // send it now.
												    $envato_message->update('last_active',0);
											    }
											    if(isset($_FILES['envato_picture']['tmp_name']) && is_uploaded_file($_FILES['envato_picture']['tmp_name'])){
												    $envato_message->add_attachment($_FILES['envato_picture']['tmp_name']);
											    }
												$now = time();
												if(!$envato_message->get('last_active') || $envato_message->get('last_active') <= $now){
													// send now! otherwise we wait for cron job..
													if($envato_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
											            $message_count ++;
													}
												}else{
											        $message_count ++;
													if(isset($_POST['debug']) && $_POST['debug']){
														echo "Message will be sent in cron job after ".shub_print_date($envato_message->get('last_active'),true);
													}
												}

										    }else{
											    // log error?
										    }
								    }
							    }
						    }
						}
					}
				}
				return $message_count;
				break;
			case 'save_envato':
				$shub_envato_id = isset($_REQUEST['shub_envato_id']) ? (int)$_REQUEST['shub_envato_id'] : 0;
				check_admin_referer( 'save-envato'.$shub_envato_id );
				$envato = new shub_envato_account($shub_envato_id);
		        if(isset($_POST['butt_delete'])){
	                $envato->delete();
			        $redirect = 'admin.php?page=support_hub_settings&tab=envato';
		        }else{
			        $envato->save_data($_POST);
			        $shub_envato_id = $envato->get('shub_envato_id');
			        if(isset($_POST['butt_save_reconnect'])){
				        $redirect = $envato->link_connect();
			        }else {
				        $redirect = $envato->link_edit();
			        }
		        }
				header("Location: $redirect");
				exit;

				break;
		}
	}

	public function handle_ajax($action, $support_hub_wp){
		switch($action){
			case 'send-message-reply':
				if (!headers_sent())header('Content-type: text/javascript');
				if(isset($_REQUEST['envato_id']) && !empty($_REQUEST['envato_id']) && isset($_REQUEST['id']) && (int)$_REQUEST['id'] > 0) {
					$shub_envato_message = new shub_envato_message( false, false, $_REQUEST['id'] );
					if($shub_envato_message->get('shub_envato_message_id') == $_REQUEST['id']){
						$return  = array();
						$message = isset( $_POST['message'] ) && $_POST['message'] ? $_POST['message'] : '';
						$envato_id = isset( $_REQUEST['envato_id'] ) && $_REQUEST['envato_id'] ? $_REQUEST['envato_id'] : false;
						$debug = isset( $_POST['debug'] ) && $_POST['debug'] ? $_POST['debug'] : false;
						if ( $message ) {
							if($debug)ob_start();
							$shub_envato_message->send_reply( $envato_id, $message, $debug );
							if($debug){
								$return['message'] = ob_get_clean();
							}else {
								//set_message( _l( 'Message sent and conversation archived.' ) );
								$return['redirect'] = 'admin.php?page=support_hub_main';

							}
						}
						echo json_encode( $return );
					}

				}
				break;
			case 'modal':
				if(isset($_REQUEST['socialenvatomessageid']) && (int)$_REQUEST['socialenvatomessageid'] > 0) {
					$shub_envato_message = new shub_envato_message( false, false, $_REQUEST['socialenvatomessageid'] );
					if($shub_envato_message->get('shub_envato_message_id') == $_REQUEST['socialenvatomessageid']){

						$shub_envato_id = $shub_envato_message->get('envato_account')->get('shub_envato_id');
						$shub_envato_message_id = $shub_envato_message->get('shub_envato_message_id');
						include( trailingslashit( $support_hub_wp->dir ) . 'networks/envato/envato_message.php');
					}

				}
				break;
			case 'set-answered':
				if (!headers_sent())header('Content-type: text/javascript');
				if(isset($_REQUEST['shub_envato_message_id']) && (int)$_REQUEST['shub_envato_message_id'] > 0){
					$shub_envato_message = new shub_envato_message(false, false, $_REQUEST['shub_envato_message_id']);
					if($shub_envato_message->get('shub_envato_message_id') == $_REQUEST['shub_envato_message_id']){
						$shub_envato_message->update('status',_shub_MESSAGE_STATUS_ANSWERED);
						?>
						jQuery('.socialenvato_message_action[data-id=<?php echo (int)$shub_envato_message->get('shub_envato_message_id'); ?>]').parents('tr').first().hide();
						<?php
					}
				}
				break;
			case 'set-unanswered':
				if (!headers_sent())header('Content-type: text/javascript');
				if(isset($_REQUEST['shub_envato_message_id']) && (int)$_REQUEST['shub_envato_message_id'] > 0){
					$shub_envato_message = new shub_envato_message(false, false, $_REQUEST['shub_envato_message_id']);
					if($shub_envato_message->get('shub_envato_message_id') == $_REQUEST['shub_envato_message_id']){
						$shub_envato_message->update('status',_shub_MESSAGE_STATUS_UNANSWERED);
						?>
						jQuery('.socialenvato_message_action[data-id=<?php echo (int)$shub_envato_message->get('shub_envato_message_id'); ?>]').parents('tr').first().hide();
						<?php
					}
				}
				break;
		}
		return false;
	}


	public function run_cron( $debug = false ){
		if($debug)echo "Starting envato Cron Job \n";
		$accounts = $this->get_accounts();
		foreach($accounts as $account){
			$shub_envato_account = new shub_envato_account( $account['shub_envato_id'] );
			$shub_envato_account->run_cron($debug);
			$groups = $shub_envato_account->get('groups');
			/* @var $groups shub_envato_group[] */
			foreach($groups as $group){
				$group->run_cron($debug);
			}
		}
		if($debug)echo "Finished envato Cron Job \n";
	}

	public function get_install_sql() {

		global $wpdb;

		$sql = <<< EOT



CREATE TABLE {$wpdb->prefix}shub_envato (
  shub_envato_id int(11) NOT NULL AUTO_INCREMENT,
  envato_name varchar(50) NOT NULL,
  last_checked int(11) NOT NULL DEFAULT '0',
  import_stream int(11) NOT NULL DEFAULT '0',
  post_stream int(11) NOT NULL DEFAULT '0',
  envato_data text NOT NULL,
  envato_token varchar(255) NOT NULL,
  envato_app_id varchar(255) NOT NULL,
  envato_app_secret varchar(255) NOT NULL,
  machine_id varchar(255) NOT NULL,
  PRIMARY KEY  shub_envato_id (shub_envato_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_envato_message (
  shub_envato_message_id int(11) NOT NULL AUTO_INCREMENT,
  shub_envato_id int(11) NOT NULL,
  shub_message_id int(11) NOT NULL DEFAULT '0',
  shub_envato_group_id int(11) NOT NULL,
  envato_id varchar(255) NOT NULL,
  summary text NOT NULL,
  title text NOT NULL,
  last_active int(11) NOT NULL DEFAULT '0',
  comments text NOT NULL,
  type varchar(20) NOT NULL,
  link varchar(255) NOT NULL,
  data text NOT NULL,
  status tinyint(1) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_envato_message_id (shub_envato_message_id),
  KEY shub_envato_id (shub_envato_id),
  KEY shub_message_id (shub_message_id),
  KEY last_active (last_active),
  KEY shub_envato_group_id (shub_envato_group_id),
  KEY envato_id (envato_id),
  KEY status (status)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_envato_message_read (
  shub_envato_message_id int(11) NOT NULL,
  read_time int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_envato_message_id (shub_envato_message_id,user_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_envato_message_comment (
  shub_envato_message_comment_id int(11) NOT NULL AUTO_INCREMENT,
  shub_envato_message_id int(11) NOT NULL,
  envato_id varchar(255) NOT NULL,
  time int(11) NOT NULL,
  message_from text NOT NULL,
  message_to text NOT NULL,
  comment_text text NOT NULL,
  data text NOT NULL,
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_envato_message_comment_id (shub_envato_message_comment_id),
  KEY shub_envato_message_id (shub_envato_message_id),
  KEY envato_id (envato_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_envato_message_link (
  shub_envato_message_link_id int(11) NOT NULL AUTO_INCREMENT,
  shub_envato_message_id int(11) NOT NULL DEFAULT '0',
  link varchar(255) NOT NULL,
  PRIMARY KEY  shub_envato_message_link_id (shub_envato_message_link_id),
  KEY shub_envato_message_id (shub_envato_message_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_envato_message_link_click (
  shub_envato_message_link_click_id int(11) NOT NULL AUTO_INCREMENT,
  shub_envato_message_link_id int(11) NOT NULL DEFAULT '0',
  click_time int(11) NOT NULL,
  ip_address varchar(20) NOT NULL,
  user_agent varchar(100) NOT NULL,
  url_referrer varchar(255) NOT NULL,
  PRIMARY KEY  shub_envato_message_link_click_id (shub_envato_message_link_click_id),
  KEY shub_envato_message_link_id (shub_envato_message_link_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_envato_group (
  shub_envato_group_id int(11) NOT NULL AUTO_INCREMENT,
  shub_envato_id int(11) NOT NULL,
  group_name varchar(50) NOT NULL,
  last_message int(11) NOT NULL DEFAULT '0',
  last_checked int(11) NOT NULL,
  group_id varchar(255) NOT NULL,
  envato_token varchar(255) NOT NULL,
  PRIMARY KEY  shub_envato_group_id (shub_envato_group_id),
  KEY shub_envato_id (shub_envato_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

EOT;
		return $sql;
	}

}