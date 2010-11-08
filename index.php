<?php
session_start();

$consumer_key = 'FrHOihK1aFQOPdFLRqGQ';
$consumer_secret = 'BeRNCTK3c1hr6A8jvUe3HbVtBEMQvlUVsE3xH4AI62A';
  
include 'HTTP/OAuth/Consumer.php';
$consumer = new HTTP_OAuth_Consumer($consumer_key, $consumer_secret);
 
$http_request = new HTTP_Request2();
$http_request->setConfig('ssl_verify_peer', false);
$consumer_request = new HTTP_OAuth_Consumer_Request;
$consumer_request->accept($http_request);
$consumer->accept($consumer_request);

if( isset($_GET['oauth_verifier'])){
	$verifier = $_GET['oauth_verifier'];
	$consumer->setToken($_SESSION['request_token']);
	$consumer->setTokenSecret($_SESSION['request_token_secret']);
	$consumer->getAccessToken('https://twitter.com/oauth/access_token', $verifier);

	$_SESSION['access_token'] = $consumer->getToken();
	$_SESSION['access_token_secret'] = $consumer->getTokenSecret();
}

if( isset($_GET['api_type'] ) ){
	$consumer->setToken($_SESSION['access_token']);
	$consumer->setTokenSecret($_SESSION['access_token_secret']);

	$url = 'http://api.twitter.com/1/';
	$type = $_GET['api_type'];
	unset( $_GET['api_type'] );
	
	//何故かcallbackというプロパティがsendRequestの段階で無視される為、別途処理する。
	$callback = $_GET['callback'];
	unset( $_GET['callback'] );

	switch( $type ){
		case 'statuses_followers':
			$url .= 'statuses/followers.json';
			$data = $_GET;
			$method = 'GET';
			break;
		case 'friendships_create':
			$url .= 'friendships/create.json';
			$data = $_GET;
			$method = 'POST';
			break;
		case 'friendships_destroy':
			$url .= 'friendships/destroy.json';
			$data = $_GET;
			$method = 'POST';
			break;
		default:
			header("HTTP/1.0 404 Not Found");
			header("Content-type: application/json; charset=utf-8 ");
			print '{"request":'.$_GET['api_type'].',"error":"This api requires no support."}';
			exit;
			break;
	}

//print $url;
//print_r($data);
//exit();
	$response = $consumer->sendRequest( $url , $data , $method );
	
	header("HTTP/".$response->getVersion()." ".$response->getStatus()." ".$response->getReasonPhrase());
	header("Content-type: application/json; charset=utf-8 ");
	
	print $callback.'('.$response->getBody().');';

	exit;
}
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title>re follow</title>
<?php
if( !isset($_SESSION['access_token']) ){
	$callback = 'http://tyo.ro/re_follow/';
	$consumer->getRequestToken('https://twitter.com/oauth/request_token', $callback);

	$_SESSION['request_token'] = $consumer->getToken();
	$_SESSION['request_token_secret'] = $consumer->getTokenSecret();

	$auth_url = $consumer->getAuthorizeUrl('https://twitter.com/oauth/authorize');

?>
</head>
<body>
API利用の為にTwitterでoAuthの承認を行なってください。<br />
<a href="https://twitter.com/oauth/authorize?oauth_token=<?php echo $_SESSION['request_token']; ?>">認証ページへ</a>

<?php
}else{
?>
<style type="text/css">
select {
	width:220px;
}
</style>
<script type="text/javascript" src="./js/jquery.js"></script>
<script type="text/javascript" src="./js/jquery.selectboxes.js"></script>
<script type="text/javascript" >

var time_zone_list = { Tokyo:'Tokyo', Osaka:'Osaka', Sapporo:'Sapporo' };
var threshold_val = 3;
var my_url = 'http://tyo.ro/re_follow/';
var error_func = function(r,t,e){ alert( 'error:' + t );};
var log_str="";
var user_list = { 're_follow':new Array(), 'no_follow':new Array(), 'following':new Array(), 'spam_follow':new Array()  };
var all_users = new Array();

$( function(){
	$('#get_list').click(function(){
		var user = $('#nick').val();
		var page = 0;
		$('#progress').css('display','block');
		$('#progress_msg').text('1-100件を読み込み中');
		if( user.length ){
			$('#get_list').attr( 'disabled', 'disabled' );
			$.ajax({
				//url: 'http://twitter.com/statuses/followers.json',
				//data: { id : user },
				url: my_url,
				data: { id : user, api_type : 'statuses_followers', cursor:-1 },
				dataType: 'jsonp',
				success:function( json_data ){
					$('#progress_msg').text((page*100+1)+'-'+((page+1)*100)+'件を処理中');
					var cnt = 0;
					var list_id = "";
					$.each( json_data.users, function(i,val){
                    	if( !$("#force_check").attr('checked') && val.following){
                        	return false;
						}
						list_id = 'following';
						if( !val.following ){
                        	list_id = 're_follow';
                        	if( $("#spam_check").attr('checked') ){
                       			spam_cnt = spam_check( val );
                            	if( spam_cnt > threshold_val ){
                                	list_id = 'no_follow';
                                	send_log( val.name+"はspam判定の閾値を越えている為、no followリストに追加します。" );
                            	}
                        	}
						}else{
							if( $("#spam_check").attr('checked') ){
								spam_cnt = spam_check( val );
								if( spam_cnt > threshold_val ){
									list_id = 'spam_follow';
									send_log( val.name+"はspam判定の閾値を越えている為、spam followリストに追加します。");
								}
							}
						}
                        //$("#"+list_id).addOption( val.id, val.name );
						user_list[ list_id ][ val.id ] = val.name;
						all_users[ val.id ] = val;
                        cnt++;
                	});
                	if( cnt == 100 && !( $("#force_check").attr('checked') && $("#range").val()-1 <= page  ) ){
						page++;
						$.ajax({ url:my_url, data:{ id:user,api_type:'statuses_followers',cursor:json_data.next_cursor_str},
							dataType:'jsonp',success:arguments.callee,error:error_func});
						$('#progress_msg').text((page*100+1)+'-'+((page+1)*100)+'件を読み込み中');
					}else if( cnt ){
						$("#re_follow").addOption( user_list['re_follow'] );
						$("#no_follow").addOption( user_list['no_follow'] );
						$("#following").addOption( user_list['following'] );
						$("#spam_follow").addOption( user_list['spam_follow'] );

						$('#progress').css('display','none');
						send_log('check complete!',true);
						$('#execute').attr( 'disabled', '' );
						$('#execute2').attr( 'disabled', '' );
					}
                	else{
						$('#progress').css('display','none');
						$('#progress_msg').text('');
						alert( 'new follower not found...' );
					}
             	},
				error:error_func
			});
		}else{ alert('nick name not found...'); }
	});

	$('#execute').click(function(){
		var follow_list = $("#re_follow").attr('options');
		if( follow_list.length ){
			$.each( follow_list, function( i, val ){
				$.ajax({
					//url: 'http://twitter.com/friendships/create.json',
					//data:{ id: val.value },
					url: my_url,
					data: { user_id : val.value , api_type : 'friendships_create' },
					//type:'POST',
					dataType:'jsonp',
					success:function( json_data ){
						$("#re_follow").removeOption( val.value );
						send_log('follow compleate '+json_data.name);
					},
					error:error_func
				});
				//sleep
			});
		}else{ alert('re follow list is empty.'); }
	});
	
	$('#execute2').click(function(){
		var unfollow_list = $("#spam_follow").attr('options');
		if( unfollow_list.length ){
			$.each( unfollow_list, function( i, val ){
				$.ajax({
					//url: 'http://twitter.com/friendships/create.json',
					//data:{ id: val.value },
					url: my_url,
					data: { user_id : val.value , api_type : 'friendships_destroy' },
					//type:'POST',
					dataType:'jsonp',
					success:function( json_data ){
						$("#spam_follow").removeOption( val.value );
						send_log('follow compleate '+json_data.name);
					},
					error:error_func
				});
				//sleep
			});
		}else{ alert('re follow list is empty.'); }
	});

	$("#right").click(function(){
		$("#re_follow").copyOptions( "#no_follow" );
		$("#re_follow").removeOption( $("#re_follow").selectedValues() );
	});

	$("#left").click(function(){
		$("#no_follow").copyOptions( "#re_follow" );
		$("#no_follow").removeOption( $("#no_follow").selectedValues() );
	});

	$("#right2").click(function(){
		$("#following").copyOptions( "#spam_follow" );
		$("#following").removeOption( $("#following").selectedValues() );
	});

	$("#left2").click(function(){
		$("#spam_follow").copyOptions( "#following" );
		$("#spam_follow").removeOption( $("#spam_follow").selectedValues() );
	});

	var click_draw = function( elm ){
		//alert('hoge');
		//alert($(elm).value);
		user = all_users[ $(this).val() ];
		//console.debug(all_users[ $(this).val() ]);
		$("#user_prof").html(
			'name:<a target="_blank" href="http://twitter.com/' + user.screen_name + '">'+user.name +' ( ' + user.screen_name + ' ) </a><br />'+
			'<img src="'+user.profile_background_image_url+'" width=64 height=64 /><br/>'+
			'bio:'+user.description
		);
	}
	$("#re_follow").change(click_draw);
	$("#no_follow").change(click_draw);
	$("#following").change(click_draw);
	$("#spam_follow").change(click_draw);
});

function zen_check(str){
    for(var i=0; i<str.length; i++){
        var len=escape(str.charAt(i)).length;
        if(len>=4){
            return true;
        }
    }
    return false;
}

function bio_check(str){
	var ng_word = new Array( 'ネットビジネス','弁護士','稼ぐ','年収','割引','マーケティング','クーポン','ご紹介' );
	for( var i=0; i<ng_word.length ; i++ ){
		if( str.indexOf( ng_word[i] ) >= 0 ){
			return false;
		}
	}
	return true;
}

function send_log(str,flash){
	log_str =  str + "\n" + log_str;
	if( flash ){
		$("#log").text( log_str );
	}
}

function spam_check( val ){
	var spam_cnt = 0;
	var name = val.name+"("+val.id+")";
	if( val.description == null || val.description == '' ){
		spam_cnt++;
		send_log( name+":bioが空です。 spam cnt:"+spam_cnt);
	}else if( !zen_check( unescape(val.description) ) ){ 
		spam_cnt++;
		send_log( name+":bioに全角文字が含まれません。spam cnt:"+spam_cnt);
	}else if( !bio_check( unescape(val.description) ) ){ 
		spam_cnt +=4;
		send_log( name+":bioに除外指定文字が含まれています。 spam cnt:"+spam_cnt);
	}

	if( val.location== null || val.location== '' ){
		spam_cnt++;
		send_log( name+":locationが空です。 spam cnt:"+spam_cnt);
	}else if( !zen_check( unescape(val.location) ) ){
		spam_cnt++;
		send_log( name+":locationに全角文字が含まれません。("+val.location+") spam cnt:"+spam_cnt);
	}

	if( val.time_zone == null || val.time_zone== '' ){
		spam_cnt++;
		send_log( name+":time_zoneが空です。 spam cnt:"+spam_cnt);
	}else if( !( val.time_zone in time_zone_list ) ){
		spam_cnt++;
		send_log( name+":time_zoneが日本の物ではありません。("+val.time_zone+")spam cnt:"+spam_cnt);
	}

	if( val.lang== null || val.lang== '' ){
		spam_cnt++;
		send_log( name+":langが空です。 spam cnt:"+spam_cnt);
	}else if( val.lang != 'ja' ){
		spam_cnt++;
		send_log( name+":言語設定が日本語ではありません。("+val.lang+") spam cnt:"+spam_cnt);
	}
	if( val.protected ){
	}else if( val.status == null || val.status.text == null || val.status.text == "" ){
		spam_cnt += 3;
		send_log( name+":最新の発言がありません。spam cnt:"+spam_cnt);
	}else if( !zen_check( unescape(val.status.text) ) ){
		spam_cnt++;
		send_log( name+":最新の発言に全角文字が含まれません。spam cnt:"+spam_cnt);
	}

	return spam_cnt;
}
</script>
</head>
<body>
id:<input type="edit" id="nick" />&nbsp;
<label><input type="checkbox" id="spam_check" >spam check</label><br />
<input type="button" id="get_list" value="followers get" ><br />
<label><input type="checkbox" id="force_check" >force check</label>
<input type="edit" id="range" size="2" value="1" />page (Pages 1 are 100 user. )<br />
<div id="progress" style="display:none;">
	<img src="./img/loading.gif">
	<span id="progress_msg" ></span>
</div>
<hr/>
<table>
	<tr>
		<th>re follow</th>
		<th>&nbsp;</th>
		<th>no follow</th>
		<td rowspan="5">
			<div id="user_prof"></div>
		</td>
	<tr>
		<td><select multiple=true id="re_follow" size="7" ></select></td>
		<td>
			<input type="button" id="right" value="->" /><br/>
			<input type="button" id="left" value="<-" />
		</td>
		<td><select multiple=true id="no_follow" size="7" ></select></td>
	</tr>
	<tr>
		<td colspan="3">
			<input type="button" id="execute" value="re follow execute" disabled="disabled" />
		</td>
	</tr>
	<tr>
		<th>following</th>
		<th>&nbsp;</th>
		<th>spam follow</th>
	</tr>
	<tr>
		<td><select multiple=true id="following" size="7" ></select></td>
		<td>
			<input type="button" id="right2" value="->" /><br/>
			<input type="button" id="left2" value="<-" />
		</td>
		<td><select multiple=true id="spam_follow" size="7" ></select></td>
	</tr>
	<tr>
		<td colspan="3">
			<input type="button" id="execute2" value="spam unfollow" disabled="disabled" />
		</td>
	</tr>
</table>
<hr/>
log<br />
<textarea rows="4" cols="120" id="log" readonly></textarea>
<?php } ?>
</body>
</html>
