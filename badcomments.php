<? 
/*
Plugin Name: Bad Comments
Plugin URI: http://badcomments.chitic.eu
Version: v 2.0
Author: Chitic Stefan-Gabreil
Author URI: http://www.chitic.eu
Description: This plugin will automatically search in a database if a user that posted a comment on your blog has already had a bad behavior regarding the comments, and will alert you about this. Evenmore you can read those comments by visting the website. 

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

global $wpdb;
define('BC_TABLE', $wpdb->prefix . 'badcomments');
define('BC_PATH', WP_PLUGIN_URL . '/badcomments/');
define('C_TABLE', $wpdb->prefix . 'comments');
define('SERVER_ADDRESS',"http://badcomments.chitic.eu/");

if (!class_exists("badcomments")) {
	
    class badcomments {
		
		var $adminOptionsName = "BadCommentsOptionNames";		
		function badcomments() {
			$this->tableCreate();
			add_action('admin_head', array(&$this, 'updateHeader'), 1);
			add_action('admin_menu', array(&$this, 'addAdmin'));
			add_filter('get_comment_text', array(&$this, 'addDetails'));
			register_activation_hook(__FILE__, array(&$this, 'pluginInit'));
			add_action('update_old_coments', array(&$this, 'updateOld'));

        }
		
		function addDetails($comment = ''){
			global  $wpdb;
			$id = get_comment_ID();
			$website =  home_url( '/');
			$toSend = $comment;
			$grade = "not";
			$ip = get_comment_author_IP();
			$url = get_comment_author_url();
			$email = get_comment_author_email();
			$name = get_comment_author();
			$text = htmlspecialchars_decode($comment);	
			$devOptions = $this->getAdminOptions();
			$data=get_comment_date("Y/m/d")." at ".get_comment_time("h:i:s");
			$tableName = BC_TABLE;
			if(is_admin())
			{
				
				$page = "index.php?view=report&ip=".$ip."&url=".$url."&email=".$email."&name=".$name."&text=".$text."&c_id=".$id."&server=".$website."&data=".$data;
				$page =str_replace(" ","%20",$page);
				$page =str_replace("&#039;","'",$page);
				$page  =str_replace("<","!-!",$page);
				$page = str_replace(">","---",$page);
				if($devOptions['api']!='')
					{
					if($devOptions['cat']>=1){
					$file = SERVER_ADDRESS."plus.php?ip=".$ip."&url=".$url."&email=".$email."&name=".$name."&c_id=".$id."&server=".$website."&key=".$devOptions['api'];
					$file =str_replace(" ","%20",$file);
					$file =str_replace("&#039;","'",$file);
					$result = $this->get_web_page($file);
					if($result=="API error")
					{
						$devOptions['api']="";							
						$devOptions['cat']="";
						update_option($this->adminOptionsName, $devOptions);

						}
					}
					}
					
				$toSend2= "&nbsp;&nbsp;<a href=\"".SERVER_ADDRESS.$page."\" target=\"_blank\">Report as injurious</a>";
			}
			$badcomments_list = $wpdb->get_results("SELECT comment_id,grade FROM $tableName WHERE comment_id = '$id'", ARRAY_A);
			$nr = count($badcomments_list);
			if($nr==0)
			{
				
				$page = SERVER_ADDRESS."get.php?ip=".$ip."&url=".$url."&email=".$email."&name=".$name."&text=".$text;
				if($devOptions['api']!='')
					$page.="&key=".$devOptions['api'];
				$page =str_replace(" ","%20",$page);
				$page =str_replace("&#039;","'",$page);
				$result = $this->get_web_page($page);
				if($result=="API error")
						{
							$devOptions['api']="";
							$devOptions['cat']="";
							update_option($this->adminOptionsName, $devOptions);

						}
				elseif($result)
					{
					$wpdb->insert(BC_TABLE, array('comment_id' => $id, 'grade' => $result,), array('%s', '%s'));
					$grade=$result;
					if($grade == "very" || $grade>"80%" || $grade=="100%")
							wp_set_comment_status($id,"spam");
					}
					
			}
			else
			{
				$grade=$badcomments_list[0]["grade"];
			}	
			$toSend .="<br/><hr/>This comment is considered <b>".$grade."</b> injurious".$toSend2;
			
			return $toSend;
		 }
		
		function updateHeader()
		{
			echo "<link href=\"".BC_PATH."/css/forall.css\" media=\"screen\" type=\"text/css\" rel=\"stylesheet\">";
		}
		
		function getAdminOptions() {
			$badCommentsAdminOptions = array('api' => '','cat'=>'');
			$badCommentsOptions = get_option($this->adminOptionsName);
			if (!empty($badCommentsOptions)) {
				foreach ($badCommentsOptions as $key => $option)
					$badCommentsAdminOptions[$key] = $option;
			}				
			update_option($this->adminOptionsName, $badCommentsAdminOptions);
			return $badCommentsAdminOptions;
		}
		
		function check_api($api)
		{
			$res = $this->get_web_page(SERVER_ADDRESS."/checkapi.php?api=".$api);
			return $res;
		}
		
		function printAdminPage()
		{
			$ok="ok";
			$res = array("ok",0);
			$devOptions = $this->getAdminOptions();
			if($devOptions['api']=="")
				{$ok="";
				$res=array();
				}
			if (isset($_POST['update_PluginSettings'])) { 
						if (isset($_POST['api']) && $_POST['api']!=$devOptions['api']) {
							$devOptions['api'] = $_POST['api'];
							$ok=$this->check_api($devOptions['api']);
							$res = explode("}}{{",$ok);
							if($res[0]!="ok") 
								$devOptions['api'] ='';
							else
								{
									$devOptions['cat']=$res[1];
								}
						}
						update_option($this->adminOptionsName, $devOptions);
						
						?>
<div class="updated"><p><strong><?php _e("Settings Updated.", "badcomments");?></strong></p></div>
					<?php
					} ?>
            <div class=wrap>
            <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
            <h2>Bad comments API key</h2>
            <h3>Key:</h3>
            <input type="text" name="api" class="apiKey<? if($res[0]=="ok") echo 2;?>" value="<?php _e(apply_filters('format_to_edit',$devOptions['api']), 'badcomments'); ?>" /><h4><p style=" <? if($res[0]=="ok") echo "color:green;\">You have a valid key"; else echo "color:red;\">".$ok;?></p></h4>
             <h4>To get a API key please visit <a href="http://badcomments.chitic.eu/getkey/" target="_blank">our website</a>. There are also free API keys for individual blogers.</h4>
           
            
            <div class="submit">
            <input type="submit" name="update_PluginSettings" value="<?php _e('Update Settings', 'badcomments') ?>" /></div>
            </form>
         </div>
					<?php
		}
		
		function addAdmin()
		{
			add_plugins_page('Bad Comments configuration', 'Bad Comments Plugin', 9, basename(__FILE__), array(&$this, 'printAdminPage'));
		}
		 
		function tableCreate()
		{
			global $wpdb;
			$tableName = $wpdb->prefix . 'badcomments';
			$tableStructure = $wpdb->get_results("DESCRIBE $tableName", ARRAY_A);
			if($tableStructure['2']['Field'] != 'grade') $wpdb->query("DROP TABLE $tableName");
			if($wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName) 
			{
				$sql = "CREATE TABLE $tableName (
					uid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
					comment_id INTEGER NOT NULL UNIQUE KEY,
					grade VARCHAR (50) NOT NULL
				);";
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				if(!dbDelta($sql)) 
					return false;
				return true;
			}
		 }
		 function pluginInit()
		 {
			$this->tableCreate();
			$this->getAdminOptions();
			wp_schedule_event(time(), 'daily', 'update_old_coments');
		 }
		 function get_web_page( $url )
		{
			$res=@file_get_contents($url);
			return $res;	
		}
		
		 function updateOld()
		 {
			 $devOptions = $this->getAdminOptions();
			 if($devOptions['cat']==3){
			 $website =  home_url( '/');
			 $file = SERVER_ADDRESS."update.php?server=".$website."&key=".$devOptions['api'];
			 $result = $this->get_web_page($file);
			 if($result=="API error")
					{
						$devOptions['api']="";
						$devOptions['cat']="";
						update_option($this->adminOptionsName, $devOptions);

			   }
			 elseif($result!="")
			 {
				 $elements = explode("}}{{",$result);
				 for($i=0;$i<count($elements)-1;$i++)
				 	{
						$this->updateElement($elements[$i]);
					}
			 }	
			}
		 }
		 
		 function updateElement($value)
		 {
			 global $wpdb;
			 $values = explode("]][[",$value);
			 $id = $values[0];
			 $grade =$values[1];
			 if($grade == "very" || $grade>"80%" || $grade=="100%")
				wp_set_comment_status($id,"spam");
			else
				wp_set_comment_status($id,"approve");		 
			 $wpdb->update(BC_TABLE, array('grade' => $grade), array('comment_id'=> $id),array('%s'),array('%s'));
		 }
    }
} 


if (class_exists("badcomments")) {
    $badComments = new badcomments();
}

