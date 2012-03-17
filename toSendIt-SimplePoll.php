<?php
/**
 * @package Simple Polls
 * @version 1.0.3
 */
/*
Plugin Name: Simple Polls
Plugin URI: http://wordpress.org/extend/plugins/simple-poll/
Description: Plugin that allow admin to create infinite polls and registered users to express just one preference per poll.
Author: toSend.it di Luisa Mara
Version: 1.0.3
Author URI: http://tosend.it
*/

// Security issue: you cannot run the script invoking it directly
if(__FILE__ == $_SERVER['SCRIPT_FILENAME']) die(); 

register_activation_hook(__FILE__, 	array('toSendItSimplePoll','register'));
add_action('admin_menu', 			array('toSendItSimplePoll','init'));
add_shortcode('simple-poll', 		array('toSendItSimplePoll','shortcode'));

class toSendItSimplePoll{
	const POLLS = 'sp_polls';
	const RATES = 'sp_rates';
	
	static function init(){
		add_options_page("Simple Polls Manage polls", "SP - Manage polls", 'manage_options', "sp_manage_polls", array(__CLASS__, 'backend'));
		add_options_page("Simple Polls Options", "SP - Options", 'manage_options', "sp_options", array(__CLASS__, 'backendOptions'));
	}
	
	static function register(){
		global $wpdb;
		$prefix = $wpdb->prefix;
		
		$polls = $prefix.self::POLLS;
		$rates = $prefix.self::RATES;
		$sql = "
		CREATE TABLE $polls(
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			question longtext NOT NULL,
			answers longtext NOT NULL,
			since datetime,
			PRIMARY KEY  (id)
		);
		
		CREATE TABLE $rates(
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			user_id mediumint(9),
			poll_id mediumint(9),
			expiration_date datetime,
			answer_index mediumint(9),
			PRIMARY KEY  (id)
	
		);
		";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		$wpdb->show_errors(true);
		dbDelta($sql, true);
	}
	static function loadOptions(){
		$options = get_option('simplePoll');
		if(!is_array($options)) $options = array();
		!isset($options['rate_button']) && $options['rate_button']					= 'Rate this poll!';
		!isset($options['already_rated']) && $options['already_rated'] 				= 'you have rated yet.';
		!isset($options['thankyou']) && $options['thankyou'] 						= 'Thank You, your preference is keeped!';
		!isset($options['poll_results_label']) && $options['poll_results_label'] 	= 'Poll results';
		!isset($options['question_label']) && $options['question_label'] 			= 'Questions';
		!isset($options['answers_label']) && $options['answers_label'] 				= 'Answers';
		!isset($options['answer_structure']) && $options['answer_structure'] 		= '%s with %d rates';
		!isset($options['most_rated']) && $options['most_rated']  					= 'Most rated';
		return $options;
	}
	
	static function saveOptions(){
		$options = self::loadOptions();
		if(isset($_POST) && count($_POST)>0){
			$_POST = stripslashes_deep($_POST);
			$optionsUpdated = false;
			foreach($options as $key => $value){
				
				if(isset($_POST[$key]) && $_POST[$key] != $options[$key]){
					$options[$key] = $_POST[$key];
					$optionsUpdated = true;
				}
				
			}
			if($optionsUpdated) update_option('simplePoll', $options);
		}
	}
	
	static function backendOptions(){
		self::saveOptions();
		$options = self::loadOptions();
		foreach($options as $key => $value){
			$options[$key] = htmlspecialchars($value);
		}
		extract($options);
		?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"><br /></div>
			<h2>Simple Poll Settings</h2>
			<form method="post" action="">
				<fieldset>
					<legend>Poll system labels</legend>
					<p>
						<label for="simple-rate-rate_button">Rate button label:</label>
						<input id="simple-rate-rate_button" type="text" name="rate_button" value="<?php echo $rate_button ?>" />
					</p>
					<p>
						<label for="simple-rate-already_rated">Rate button label:</label>
						<input id="simple-rate-already_rated" type="text" name="already_rated" value="<?php echo $already_rated ?>" />
					</p>
					<p>
						<label for="simple-rate-thankyou">Thanks message:</label>
						<input id="simple-rate-thankyou" type="text" name="thankyou" value="<?php echo $thankyou ?>" />
					</p>
				</fieldset>
				<fieldset>
					<legend>Poll result labels</legend>
					<p>
						<label for="simple-rate-poll_results_label">Main label:</label>
						<input id="simple-rate-poll_results_label" type="text" name="poll_results_label" value="<?php echo $poll_results_label ?>" />
					</p>
					<p>
						<label for="simple-rate-question_label">Question label:</label>
						<input id="simple-rate-question_label" type="text" name="question_label" value="<?php echo $question_label ?>" />
					</p>
					<p>
						<label for="simple-rate-answers_label">Answers label:</label>
						<input id="simple-rate-answers_label" type="text" name="answers_label" value="<?php echo $answers_label ?>" />
					</p>
					<p>
						<label for="simple-rate-answer_structure">Answer structure:</label>
						<input id="simple-rate-answer_structure" type="text" name="answer_structure" value="<?php echo $answer_structure?>" />
					</p>
					<p>
						<label for="simple-rate-most_rated">Most rated label:</label>
						<input id="simple-rate-most_rated" type="text" name="most_rated" value="<?php echo $most_rated ?>" />
					</p>
				</fieldset>
				<p><input type="submit" class="button-primary" value="save" /></p>
			</form>
		</div>
		<?php 
		
	}
	static function backend(){
		global $wpdb;
		
		$polls = $wpdb->prefix.self::POLLS;
		$rates = $wpdb->prefix.self::RATES;
		if(isset($_POST) && count($_POST)>0){
			$_POST = stripslashes_deep($_POST);
			$wpdb->show_errors(true);
			if($_GET['id']=='0'){
			
				$wpdb->insert($polls, $_POST);
			}else{
				$wpdb->update($polls, $_POST, array('id'=> $_GET['id']));
			}
			
			unset($_GET['id']);
		}
		?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"><br /></div><h2>Simple Poll Manager</h2>
			<?php
				
			if(isset($_GET['id']) && is_numeric($_GET['id'])){
				$id = $_GET['id'];
				$sql = "select * from $polls where id=$id";
				
				$row = $wpdb->get_row($sql);
				$sql ="select count(id) voti from $rates where poll_id=$id";
				$rates = $wpdb->get_var($sql, 0,0);
				
				?>
				<form method="post" action="">
					<table>
						<tr>
							<th>
								<label for="sp-question">Question:</label>
							</th>
							<td>
								<input type="text" name="question" id="sp-question" value="<?php echo htmlspecialchars($row->question) ?>" />
							</td>
						</tr>
						<tr>
							<th>
								<label for="sp-since">Expiration date:</label>
							</th>
							
							<td>
								<input type="text" name="since" id="sp-since" value="<?php echo $row->since ?>" />
								<p class="help">
									Specify the poll expiration date in the format YYYY-MM-DD.
								</p>
							</td>
						</tr>
						<?php 
						if($rates==0){
							?>
							<tr>
								<th>
									<label for="sp-answers">Accepted answers:</label>
								</th>
								<td>
									<textarea rows="6" cols="80" name="answers" id="sp-answers"><?php echo htmlspecialchars($row->answers) ?></textarea>
									<p class="help">
										Type one answer per row.
									</p>
								</td>
							</tr>
							<?php 
						}else{
							?>
							<tr>
								<th>
									<strong>Accepted answers:</strong>
								</th>
								<td>
									<?php echo nl2br($row->answers) ?>
									<p class="help">
										You cannot change the answers if users have already expressed their preference
									</p>
								</td>
							</tr>
							<?php 
						}
						?>
					</table>
					<p>
						<input type="submit" class="button-primary" value="Salva" />
					
					</p>
				</form>
				<ul>
					<?php 
					$answers = preg_split("/\n/", $row->answers);
					$sql_rates = "select answer_index, count(answer_index) voti from $rates where poll_id = $id group by answer_index";
					$results = $wpdb->get_results($sql_rates);
					foreach($results as $key => $subRow){
						?>
						<li>
							<?php echo $subRow->voti ?> hanno espresso la preferenza per "<em><?php $answers[$subRow->answer_index] ?></em>
						</li>
						<?php
					}
					?>
				</ul>
				
				<?php
			}else{
				# $sql ="select p.id, p.question, count(r.id) voti from $polls p left join $rates r on p.id = r.poll_id order by expiration_date desc";
				$sql ="select * from $polls p order by since desc";
				$results = $wpdb->get_results($sql);
				?>
				<p>
					<a href="?<?php echo $_SERVER['QUERY_STRING'] ?>&id=0">New poll</a>
				</p>
				<table class="widefat post fixed">
					<tr>
						<th>Shortcode</th>
						<th>Questions</th>
						<th>Total answers</th>
						<th>Expiration</th>
					</tr>
					<tbody>
						<?php
						
						foreach($results as $row){
							$sql = "select count(id) from $rates where poll_id = $row->id";
							$rating = $wpdb->get_var($sql, 0,0); 
							?>
							<tr <?php echo ($row->since>date('Y-m-d H:i:s'))?'class="expired"':'' ?>>
								<td><code>[simple-poll id="<?php echo $row->id ?>"]</code></td>
								<td><a href="?<?php echo $_SERVER['QUERY_STRING'] ?>&id=<?php echo $row->id ?>"><?php echo($row->question); ?></a></td>
								<td>
									<?php echo($rating); ?>
								</td>
								<td>
									<?php echo ($row->since<date('Y-m-d H:i:s'))?'(expired)':$row->since ?>
								</td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
				<?php
			}
			?>
		</div>
		<?php
	}
	
	static function shortcode($arguments){

		$options = self::loadOptions();
		extract($options);
		
		$id = $arguments['id'];
		$uid = get_current_user_id();
		if($uid!=0){
			global $wpdb;
			$polls = $wpdb->prefix.self::POLLS;
			$rates = $wpdb->prefix.self::RATES;
			$sql = "select count(*) from $rates where user_id='$uid' and poll_id='$id'";
			$count = $wpdb->get_var($sql, 0,0);
			$sql = "select * from $polls where id='$id'";
			$data = $wpdb->get_row($sql);
			if($data->since> date('Y-m-d')){
				# It's not expired
				if($count=='0'){
					# We have not voted yet
					
					if(isset($_POST) && count($_POST)>0 && isset($_POST['answer']) && is_numeric($_POST['answer'])){
						$answer = $_POST['answer'];
						
						$data= array(
							'user_id'		=> $uid,
							'poll_id'		=> $id,
							'answer_index'	=> $answer,
							'expiration_date' => date('Y-m-d H:i:s')
						);
						if($wpdb->insert($rates, $data)){
							
							$buffer = $thankyou;	
						}
						return $buffer;
					}else{
						$output = '
							<form method="post" action="" id="simple-poll-%d" class="simple-poll">
								<fieldset>
									<legend>%s</legend>
									%s
								</fieldset>
								<p>
									<input type="submit" value="%s" />
								</p>
							</form>
						';
						$options = preg_split("/\n/",$data->answers);
						
						$optionList = '';
						foreach($options as $index => $option){
							$optionList .="<p>";
							$optionList .= '<input type="radio" name="answer" value="' . $index .'" id="option-'.$index.'" />';
							$optionList .= "<label for=\"option-$index\">$option</label>";
							$optionList .="</p>";
							
						}
						
						return sprintf($output, $data->id, $data->question, $optionList, $rate_button);
					}
				}else{
					return "<h3>$data->question</h3><p>$already_rated</p>";
				}
			}else{
				# I need to show the results
				
				$sql = "select p.id, p.question, p.answers, count(r.id) rating, r.answer_index idx from $polls p left join $rates r on p.id = r.poll_id where p.id=$id group by r.answer_index order by answer_index asc";
				$results = $wpdb->get_results($sql);
				$options = preg_split("/\n/",$data->answers);
				#print_r($results);
				$rates = array();
				for($i = 0; $i<count($results) || $i<count($options); $i++){
					$data = $results[$i];
					
					if(!isset($rates[$i])) 
						$rates[$i] = array('answer' => $options[$i], 'rates' => 0);
					if(isset($data)){	 
						$question = $data->question;
						$rates[$data->idx] = array('answer' => $options[$data->idx], 'rates' => $data->rating);
					}
				}
				
				$output = "<h3>$poll_results_label</h3>";
				$output .= "<dl><dt>$question_label</dt><dd>$question</dd>";
				$output .= "<dt>$answers_label</dt>";
				$currentRate = 0;
				$currentRates=array();
				for($i = 0; $i < count($rates); $i++){
					$rate = $rates[$i];
					$rateText = sprintf($answer_structure,$rate['answer'], $rate['rates']);
					$output .= "<dd>$rateText</dd>";
					if($rate['rates']==$rates[$currentRate]['rates']){
						$currentRates[] = $i;
					}
					if($rate['rates']>$rates[$currentRate]['rates']){
						$currentRate = $i;
						$currentRates = array();
					} 
				}
				$output .= "</dd>"; 
				
				$output .= "<dt class=\"most-rated\">$most_rated</dt>";
				foreach($currentRates as $r){
					$rate = $rates[$r];
					$rateText = sprintf($answer_structure,$rate['answer'], $rate['rates']);
					$output .= "<dd class=\"most-rated\">$rateText</dd>";
				}
				$output .="</dl>";
				return $output;
			}
		}

	}
	
	
}

