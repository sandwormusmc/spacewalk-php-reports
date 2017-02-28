<?php
	$db_hostname="somewhereovertherainbow.yourdomain.com";
	$db_user="somepostgresuser";
	$db_pass="somepostgrespass";
	$sw_url="https://somespacewalkurl";
?>
<!DOCTYPE html>
<html>
<head><title>Some Spacewalk reports</title></head>
<link href='https://fonts.googleapis.com/css?family=Roboto:400,700,900' rel='stylesheet' type='text/css'>
<link rel="stylesheet" type="text/css" href="./css/main.css">
<script src="https://code.jquery.com/jquery-2.2.4.min.js" type="text/javascript"></script>
<script src="https://code.jquery.com/jquery-1.12.4.min.js" type="text/javascript"></script>
<script type="text/javascript">
function showIt(id){
	$('#'+id).toggle('slide',{direction:'up'},1500);
}
$( document ).ready(function() {
	$('.value').each(function(){
		$(this).click(function(){
			var temp=$(this).text();
			$(this).fadeOut(function(el){ $(this).text($(this).attr('title')); }).fadeIn();
			$(this).attr('title',temp);
		});
	});
	var stickyEl = $('#main-header');
	var elTop = stickyEl.offset().top;
	var thisWindow = $(window);
	var hideIt = $('.hide-til-click');
	thisWindow.scroll(function(){
		if(($(document).height()>$(window).height()+100) && (!$(stickyEl).hasClass('sticky'))){
			stickyEl.toggleClass('sticky',(thisWindow.scrollTop()+100>elTop));
		} else if((thisWindow.scrollTop()==0)&&$(stickyEl.hasClass('sticky'))) {
                        stickyEl.toggleClass('sticky');
		}
	});
});
</script>
<body>
<?php
	if(isset($_GET['refresh'])){
		$refresh=$_GET['refresh'];
		if($refresh<5){
			$refresh=5;
		}
		echo '<meta http-equiv="refresh" content="'.$refresh.'">';
	}
?>
<table id='results'>
<?php
	//print_r($_GET);
	if(isset($_GET['limit'])){
		$limit=htmlspecialchars($_GET['limit']);
	}
	if(isset($_GET['group'])){
		$group=str_replace('*','%',htmlspecialchars($_GET['group']));
	}
	if(isset($_GET['late'])){
		$late=true;
	}
	if(isset($_GET['desc'])){
		$descfilter=str_replace('*','%',htmlspecialchars($_GET['desc']));
	}
	if(isset($_GET['host'])){
		$hostfilter=str_replace('*','%',htmlspecialchars($_GET['host']));
	}
	if(isset($_GET['time'])){
		$timefilter=strtolower(htmlspecialchars($_GET['time']));
	}
	if(isset($_GET['status'])){
		$statusfilter=strtolower(htmlspecialchars($_GET['status']));
	}
	if(isset($_GET['osad'])){
		$osadfilter=strtolower(htmlspecialchars($_GET['osad']));
	}
	if(isset($_GET['result'])){
		$resultfilter=str_replace('*','%',strtolower(htmlspecialchars($_GET['result'])));
	}
	date_default_timezone_set('America/Chicago');
	if($db_hostname==="somewhereovertherainbow.yourdomain.com"){
		die('<span class="error">You must change the value of db_hostname first!  Also check values for db_user, db_pass, and so on...</span>');
	}
	#if your postgres instance uses SSL, add "sslmode=require" to the connection string below
	$conn_string="host=$db_hostname port=5432 dbname=rhnschema user=$db_user password=$db_pass";
	$db=pg_connect($conn_string);
	#$query="SELECT Server.name AS \"FQDN\", SpacewalkGroup.name AS \"Patch Group\", ActionOverview.name AS \"Action Description\", ActionToServer.result_msg AS \"Result\", ActionStatus.name AS \"Action Status\", Server.id AS \"Spacewalk ID\", Server.os AS \"O/S Name\", Server.release AS \"O/S Version\", Action.earliest_action AS \"Scheduled for\", ActionToServer.created AS \"Action created on\", ActionToServer.pickup_time AS \"Action picked up on\", ActionToServer.completion_time AS \"Completion Time\" FROM rhnserver Server, rhnservergroup SpacewalkGroup, rhnservergroupmembers SpacewalkGroupMembers, rhnactionstatus ActionStatus, rhnactionoverview ActionOverview, rhnserveraction ActionToServer, rhnaction Action WHERE ActionOverview.earliest_action > '".date('Y-m-01')." 00:00:00-06' AND ActionOverview.earliest_action < '".date('Y-m-d', strtotime('+1 days'))." 23:59:00-06' AND ActionToServer.action_id=Action.id AND ActionOverview.action_id=ActionToServer.action_id AND ActionToServer.server_id=Server.id AND ActionToServer.status=ActionStatus.id AND Server.id=SpacewalkGroupMembers.server_id AND SpacewalkGroup.id=SpacewalkGroupMembers.server_group_id AND SpacewalkGroup.group_type IS NULL AND SpacewalkGroup.name LIKE 'patch-%week_%'";
	$querybegin="SELECT";
	if(!empty($group)){
		$querybegin.=" ServerGroup.name AS \"Group name\",";
	}
	$query=$querybegin." Server.name AS \"FQDN\", PushClient.state_id AS \"OSAD\", ActionOverview.name AS \"Action Description\", ActionToServer.result_msg AS \"Result\", ActionStatus.name AS \"Action Status\", Server.id AS \"Spacewalk ID\", Server.os AS \"O/S Name\", Server.release AS \"O/S Version\", ActionToServer.created AS \"Action created on\", Action.earliest_action AS \"Scheduled for\", ActionToServer.pickup_time AS \"Action picked up on\", ActionToServer.completion_time AS \"Completion Time\", EXTRACT(EPOCH FROM ActionToServer.pickup_time - Action.earliest_action) as \"Action time delta\" FROM";
	if(!empty($group)) {
		$query.=" rhnservergroupmembers ServerGroupMembers, rhnservergroup ServerGroup, ";
	}
	$query.=" rhnpushclient PushClient, rhnserver Server, rhnactionstatus ActionStatus, rhnactionoverview ActionOverview, rhnserveraction ActionToServer, rhnaction Action WHERE ActionToServer.action_id=Action.id AND ActionOverview.action_id=ActionToServer.action_id AND ActionToServer.server_id=Server.id AND ActionToServer.status=ActionStatus.id AND PushClient.server_id=Server.id";
	if(!empty($timefilter)){
		$num=intval(preg_replace('/[^0-9]+/','',$timefilter),10);
		switch(true){
			case preg_match('/-([0-9]){1,}d(ay)*(s)*/',$timefilter):
			  $since=date('Y-m-d', strtotime('-'.$num.' days')).' 00:00:00-06';
			  break;
			case preg_match('/\-([0-9]){1,}w(eek)*(s)*/',$timefilter):
			  $since=date('Y-m-d', strtotime('-'.$num.' weeks')).' 00:00:00-06';
			  break;
			case preg_match('/-[0-9]{1,}m(onth)*(s)*/',$timefilter):
			  $since=date('Y-m-d', strtotime('-'.$num.' months')).' 00:00:00-06';
			  break;
			default:
			  $since=date('Y-m-d', strtotime('-7 days')).' 00:00:00-06';
			  break;
		}
	} else if(!empty($hostfilter) || !empty($group)){
		$since=date('Y-m-d', strtotime('-1 month')).' 00:00:00-06';
	} else {
		$since=date('Y-m-d', strtotime('-1 days')).' 00:00:00-06';
		//$since=date('Y-m-01').' 00:00:00-06';
	}
        $query.=" AND ActionOverview.earliest_action > '$since' AND ActionOverview.earliest_action < '".date('Y-m-d', strtotime('+1 days'))." 23:59:00-06'";
	if(!empty($hostfilter)){
		$query.=" AND Server.name ILIKE '%$hostfilter%'";
	}
	if(!empty($group)){
		$query.=" AND ServerGroup.name ILIKE '%$group%' AND ServerGroupMembers.server_group_id=ServerGroup.id AND Server.id=ServerGroupMembers.server_id";
	}
	if(!empty($descfilter)){
		$query.=" AND LOWER(ActionOverview.name) ILIKE '%$descfilter%'";
	}
	if(!empty($statusfilter)){
		$query.=" AND LOWER(ActionStatus.name) ILIKE '%$statusfilter%'";
	}
	if(isset($osadfilter)){
		$query.=" AND PushClient.state_id ILIKE '%$osadfilter%'";
	}
	if($late===TRUE){
		$query.=" AND EXTRACT(EPOCH FROM ActionToServer.pickup_time - Action.earliest_action) > 300";
	}
	if(!empty($resultfilter)){
		$query.=" AND ActionToServer.result_msg ILIKE '%$resultfilter%'";
	}
	$queryend=' ORDER BY Server.name, ActionOverview.earliest_action, ActionToServer.pickup_time, ActionOverview.name, ActionStatus.name ASC';
	if(!empty($limit)){
		$queryend.=" LIMIT ${limit};";
	} else {
		$queryend.=';';
	}
	$query.=$queryend;
	//print_r($query);
	$result=pg_query($db,$query) or print_r(pg_last_error($db));
	echo '<tr class="column_header_top_half">';
	echo '<th colspan='.(pg_num_fields($result)).'><h1 class="main_title">Monthly patch status since '.$since.'</h1></th>';
	echo '</tr>';
	echo '<tr class="column_header_bot_half" id="main-header">';
	echo '<td class="column_header">IP</td>';
	for($i=0;$i<pg_num_fields($result);$i++){
		$colheader=pg_field_name($result,$i);
		if($colheader==='Spacewalk ID'){
			continue;
		} else if($colheader=='FQDN'){
			echo '<td class="column_header">FQDN</td>';
		} else {
			echo '<td class="column_header">'.pg_field_name($result,$i).'</td>';
		}
	}
	echo '</tr>';
	$colDesc='Total number of actions that have been scheduled';
	if(!empty($statusfilter)){
		$colDesc.=' and '.$statusfilter.' ';
	}
	$colDesc.=' since ';
	?>
	<tr id="inline-docs">
		<td colspan="<?php echo pg_num_fields($result); ?>">
			<div id="inline-docs-header">Inline documentation: <a href="#" onclick="showIt('inline-docs-vars');">variables</a></div>
			<div id="inline-docs-vars" style="display: none;">Available variables:<br>
				<ul>
					<li><b>note</b>: matches should be entered in the URL (e.g. ?time=-1w&desc=errata&limit=5)</li>
					<li><b>note</b>: matches automatically do substring matches, for matches inside of a search value, use *</li>
					<li><b>note</b>: all matches listed below are case insensitive</li>
				</ul>
				<ol>
					<li><b>desc</b>: match against action description column, <b>example</b>: CESA-2016:2098 (for specific errata), errata (for all errata updates), libxml2 (for updates by package name)</li>
					<li><b>group</b>: only list systems in a specific Spacewalk system group</li>
					<li><b>host</b>: only include systems matching host</li>
					<li><b>late</b>: only list systems that picked up actions 5 minutes or later than they were scheduled, values</b>: 1/0</li>
					<li><b>limit</b>: results are limited to X rows, values</b>: any numeric</li>
					<li><b>refresh</b>: set auto refresh of the page to X seconds, min 5 seconds, anything less than 5 will be reset to 5, values</b>: any numeric</li>
					<li><b>result</b>: match against result column, values</b>: error/failed/succeeded/already installed</li>
					<li><b>osad</b>: matches OSAD status, values</b>: ready/not ready</li>
					<li><b>status</b>: matches against status of the action, values</b>: queued/completed/failed/picked up</li>
					<li><b>time</b>: only lists actions within the past X days/weeks/months, values</b>: use "-" for previous days/weeks/months, <b>example</b>: -1w for the past week, -2months for the past 2 months (-2m works also), 2d for actions scheduled for the next two days</li>
			</div>
		</td>
	</tr>
	<?php
	echo '<tr><td colspan="'.pg_num_fields($result).'"><div style="display: none;" id="total">'.$colDesc.$since.': '.pg_num_rows($result).'</div><div id="envcount" style="display: none;">Environment count: </div></td></tr>';
	$rownum=0;
	$envCount=array('dev'=>0,'prod'=>0,'qa'=>0,'stag'=>0,'stress'=>0);
	$fqdns=array();
	while($row=pg_fetch_assoc($result)){
		echo '<tr>';
		$elOpt='class="class-ip"';
		echo "<td ${elOpt}>".gethostbyname($row['FQDN']).'</td>';
		foreach($row as $key=>$value){
			$elID=preg_replace('/( |\/)/','_',$key).'-'.$row['Spacewalk ID'].'-'.$rownum++;
			$elOpt='class="class-'.preg_replace('/( |\/)/','_',$key).'"';
			switch($key) {
				case 'Spacewalk ID':
					$value='';
					break;
				case 'FQDN':
					$shortValue='<a href="https://'.$sw_url.'/rhn/systems/details/Overview.do?sid='.$row['Spacewalk ID'].'">'.$value.'</a>';
					$value=$row['Spacewalk ID'];
					if(!in_array($row['FQDN'],$fqdns)){
						array_push($fqdns,$row['FQDN']);
					}
					break;
				case 'Action time delta':
					$value='raw time: '.$row['Action time delta'].' seconds';
					if($row['Action time delta']<30) {
						$color='#090';
					} else if($row['Action time delta']<600) {
						$color='#FA0';
					} else if($row['Action time delta']<6800) {
						$color='#900';
					} else {
						$color='#F00';
					}
					$shortValue='<font color="'.$color.'">'.round($row['Action time delta']/60).' mins</font>';
					break;
				case 'OSAD':
					if($row['OSAD']=='1'){
						$value='Ready to receive actions';
						$shortValue='<font style="color: #090;">Ready</font>';
					} else {
						$value='Not ready to receive actions - try sudo /sbin/service osad restart';
						$shortValue='<font style="color: #900;">Not ready</font>';
					}
					break;
				default:
					(strlen($value))>75?$shortValue=substr($value,0,74).'...':$shortValue=$value;
					break;
			}
			if($value!==''){
				$value='<span class="value" id="'.$elID.'" title="'.$value.'">'.$shortValue.'</span>';
				echo "<td ${elOpt}>$value</td>";
			}
			unset($shortValue);
		}
		echo '</tr>';
		//print_r($row);
	}
	pg_close($db);
	$suffix='your-dns-suffix-here.com';
	foreach($fqdns as $fqdn){
		if(preg_match('/^.*\.dev\.'.$suffix.'/',$fqdn)){
			$envCount['dev']+=1;
		} else if(preg_match('/^.*\.qa\.'.$suffix.'/',$fqdn)){
			$envCount['qa']+=1;
		} else if(preg_match('/^.*\.prod\.'.$suffix.'/',$fqdn)){
			$envCount['prod']+=1;
		} else if(preg_match('/^.*\.stag\.'.$suffix.'/',$fqdn)){
			$envCount['stag']+=1;
		} else if(preg_match('/^.*\.stress\.'.$suffix.'/',$fqdn)){
			$envCount['stress']+=1;
		}
	}
?>
<script type="text/javascript">
$('#total').show('slide',{direction:'down'},1000);
$('#envcount').append('<?php 
	$count=0;
	foreach($envCount as $key=>$value){
	$count++;
	echo $key.': '.$envCount[$key];
	if($count<sizeof($envCount)){
		echo ', ';
	}
}?>').show('slide',{direction:'left'},1000);
</script>
</table>
</body>
</html>
