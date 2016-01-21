<?php
/*
http://localhost/mchat/static/seller.php?sellerId=10&sellerName=Jongkwin#
http://localhost/mchat/static/seller.php?sellerId=1&sellerName=Kobe#
*/
$sellerId = isset($_GET['sellerId']) ? $_GET['sellerId'] : 1;
$sellerName = isset($_GET['sellerName']) ? $_GET['sellerName'] : "Seller - " . $sellerId;
?>
<html>
	<head>
		<title>Seller</title>
		<!-- // <script src="socket.io/socket.io.js"></script> -->
		<!-- // <script src="https://cdn.socket.io/socket.io-1.3.7.js"></script> -->
		<script src="../libs/socket.io-client/socket.io.js"></script>
		<script src="http://code.jquery.com/jquery-1.11.1.js"></script>
		<script src="../libs/mchat.js"></script>

		<script>
		var host = 'https://evo-chat.herokuapp.com:3000';
		var socket = new io(host);
		var currentBuyer = null;
		var buyers = [];
		var seller;
		var h = window.innerHeight;
		var w = window.innerWidth;

		/*
		 * Collection of client message
		 * [{"messages": [<message>], "from": <buyer>}]
		 */
		var messages = [];

		function showChatHistory(buyerId){
			for(i in buyers){
				if(buyers[i].id === buyerId){
					currentBuyer = buyers[i];
					$('#list-' + currentBuyer.id).css("background-color", "#fff");

				}
				else{
					$('#chat-history-' + buyers[i].id).hide();
				}
			}

			$('#chat-history-' + buyerId).show();

			var readMessages = [];
			for(i in messages){
				if(messages[i].from.id === buyerId){
					for(var j = (messages[i].messages.length - 1); j >= 0; j--){
						if(messages[i].messages[j].state === messageState.RECEIVED){
							readMessages.push(messages[i].messages[j]);
						}
						else{
							break;
						}
					}
				}
			}

			socket.emit('chatMessageRead', readMessages);
			
			if(currentBuyer != null){
				socket.emit('historyChat', seller, currentBuyer);

				$('#buyer-name').text(currentBuyer.name);
				$('#buyer-queue > li').css('background-color', '#fff');
				$('#list-' + currentBuyer.id).css('background-color', '#ccc');

				$('#list-' + currentBuyer.id).css("color", "#000");
				$('#list-' + currentBuyer.id + ' > a').css("color", "#000");
			}

			return false;
		}

		function pushNewBuyer(buyer){
			var found = false;
			for(i in buyers){
				if(buyers[i].id === buyer.id){
					found = true;
					break;
				}
			}

			if(!found){
				buyers[buyers.length] = buyer;
				$('#buyer-queue').append(
					'<li id="list-' + buyer.id + '"><a href="#" onclick="showChatHistory('+buyer.id+')">' + buyer.name + '</a> ' +
					'<span id="typing-' + buyer.id + '" style="font-style: italic;display:none;font-size: 9px; color: green;">Typing...</span>' +
					'<br/><div id="list-product-'+ buyer.id + '" style="font-size: 12px;">' +
					'<i>' + buyer.product.title + '</i></div></li>'
 					);

				var ch = h - 70 - 50;
				$('#chat-history-wrapper').append('<div style="display:none;overflow-y: scroll; height: '+ch+';" id="chat-history-' + buyer.id + '"><ul></ul></div>');
			}
		}

		$(document).ready(function(){
			seller = {'id': <?=$sellerId?>, 'name': '<?=$sellerName?>', 'socketId': '', 'type': entityType.SELLER};
			var connectLabel = 'Connect';
			var disconnectLabel = 'Disconnect';

			$('#connect').click(function(){
				if($(this).val() === connectLabel){
					if(socket == null){
						socket = new io('http://localhost:3000');
					}
					socket.emit('memberConnect', seller, null, 'seller');
					$(this).val(disconnectLabel);
				}
				else{
					socket.emit('memberDisconnect', seller, 'seller');
					$(this).val(connectLabel);
				}
			});

			$('#disconnect').click(function(){
				$('#online-wrapper').hide();
				$('#offline-wrapper').show();
			});

			$('#send-message').click(function(){
				sendMessage();
			});

			$('#message').keydown(function(e) {
				if(currentBuyer != null){
    				socket.emit('typing', seller, currentBuyer, 'seller');
    			}
			});

			$('#message').keypress(function(e) {
    			if(e.which == 13) {
        			sendMessage();
    			}
			});

			$('#message').keyup(function(e) {
				if(currentBuyer != null){
					delay(function(){
	      				socket.emit('stop-typing', seller, currentBuyer, 'seller');
	    			}, 1000 );
				}
			});

			// ------------------------ SOCKET LISTENER ----------------------------------

			socket.on('connected', function(member){
				seller.socket = member.socket;
			});

			socket.on('sellerConnected', function(seller){
				$('#online-wrapper').show();
				$('#offline-wrapper').hide();
			});

			socket.on('historyChat', function(messages){
				for(i in messages){
					$('#chat-history-' + messages[i].from.id + ' > ul').append(
						'<li id="message-' + messages[i].id + '">[' + messages[i].from.name + '] ' + messages[i].content + '</li>');

					socket.emit('chatMessageReceived', messages[i]);
				}

				socket.emit('chatMessageRead', messages);
			});

			socket.on('buyerDisconnected', function(buyer){
				// alert('buyer ' + buyer.name + ' disconnected');
			});

			socket.on('openChat', function(cbuyer, buyers){
				pushNewBuyer(cbuyer);
			});

			socket.on('retrieveAllBuyers', function(buyers){
				if(buyers != null){
					for(i in buyers){
						pushNewBuyer(buyers[i].buyer);
					}
				}
			});			

			socket.on('chatMessage', function(message){
				message.state = messageState.RECEIVED;
				var found = false;
				
				for(i in messages){
					if(messages[i].from.id === message.from.id){
						messages[i].messages.push(message);
						found = true;
						break;
					}
				}

				messages.push({"messages": [message], "from": message.from});

				var d = new Date(message.sentByServerDate);
				var hour = d.getHours();
				var minute = d.getMinutes();
				var hm = hour + ':' + minute;

				$('#chat-history-' + message.from.id + ' > ul').append(
					'<li class="chat-him" id="message-' + message.id + '">' + message.content + 
						'<div class="message-time">' + hm + '</div>' +
					'</li>');

				if(currentBuyer == null || currentBuyer.id != message.from.id){
					$('#list-' + message.from.id).css("color", "red");
					$('#list-' + message.from.id + ' > a').css("color", "red");
				}

				socket.emit('chatMessageReceived', message);

				if(currentBuyer != null){
					if(message.from.id === currentBuyer.id){
						var readMessages = [message];
						socket.emit('chatMessageRead', readMessages);
					}
				}

				if(currentBuyer != null){
					var elem = document.getElementById('chat-history-' + currentBuyer.id);
	  				elem.scrollTop = elem.scrollHeight;
	  			}

	  			var ll = 8;
	  			var mWidth = ll * message.content.length;
  				if(mWidth < 50)
  					mWidth = 50;
  				else if(mWidth > 500)
  					mWidth = 500;

  				$('#message-' + message.id).css('width', mWidth);
			});

			socket.on('chatMessageReceived', function(message){
				if(message.state === messageState.RECEIVED){
					$('#message-' + message.id).css('color', '#000');
				}
			});

			socket.on('chatMessageRead', function(messages){
				// @TODO
				// for(i in messages){
				// 	var message = messages[i];
				// 	if(message.state === messageState.READ){
				// 		$('#read-message-' + message.id).show();
				// 	}
				// }
			});

			socket.on('typing', function(seller, buyer, from){
				$('#typing-' + buyer.id).show();
				if(currentBuyer == null || currentBuyer.id != buyer.id){
					// $('#list-' + buyer.id).css("background-color", "red");
				}
			});

			socket.on('stop-typing', function(seller, buyer, from){
				$('#typing-' + buyer.id).hide();
				if(currentBuyer == null || currentBuyer.id != buyer.id){
					// $('#list-' + buyer.id).css("background-color", "red");
				}
			});

			// --------------------- FUNCTIONS ---------------------------

			var sendMessage = function(){
				if(currentBuyer != null){
					var contentMessage = $('#message').val();
					var message = constructMessage(contentMessage, seller, currentBuyer);
					
					socket.emit('chatMessage', message);
					message.state = messageState.SENT_BY_CLIENT;
					message.sentByClientDate = new Date().getTime();

					var d = new Date(message.sentByClientDate);
					var hour = d.getHours();
					var minute = d.getMinutes();
					var hm = hour + ':' + minute;

					$('#chat-history-' + currentBuyer.id + ' > ul ').append(
						'<li class="chat-me"><div id="message-' + message.id + '" class="chat-me-div">' + 
							message.content +
						'<span id="read-message-' + message.id + '" style="color: green;display: none;font-weight: bold;">' + 
						'R</span>' +	
						'<div class="message-time">' + hm + '</div>' + 
						'</div><div style="clear:both;"></div></li>');
					$('#message').val('');
					socket.emit('stop-typing', seller, currentBuyer, 'seller');

					var elem = document.getElementById('chat-history-' + currentBuyer.id);
	  				elem.scrollTop = elem.scrollHeight;

	  				var ll = 8;
	  				var mWidth = ll * message.content.length;
	  				if(mWidth < 50)
	  					mWidth = 50;
	  				else if(mWidth > 500)
	  					mWidth = 500;

	  				$('#message-' + message.id).css('width', mWidth);
				}
			}
		});

		</script>
	</head>
	<body>
		<style>
			body{
				margin: 0px;
				padding: 0px;
				font-family: arial;
			}
			#wrapper{
				padding: 0px;
				margin: 0px;
			}

			#client-list-wrapper{
				position: absolute; 
				left: 0; 
				width: 300px; 
				border-right: 1px #ccc solid;
				margin:0px; 
			}

			#client-list-header{
				width: 100%; 
				height: 70px; 
				border-bottom: 1px #ccc solid;
			}

			#chat-content-wrapper{
				position: absolute; 
				left: 300; 
				margin:0px;
			}

			#chat-history-header{
				height: 70px; 
				border-bottom: 1px #ccc solid;
			}

			#identity{
				font-family: arial;
				font-size: 15px;
				font-weight: bold;
			}

			#identity-wrapper{
				padding: 20px 10px;
			}

			#type-wrapper{
				position: absolute; 
				left: 0px;
			}

			#message{
				padding: 14px 10px;
			}

			#send-message{
				padding: 14px 10px;
			}

			li.chat-me .chat-me-div{
    			border-radius: 5px;
    			margin: 5px 10px 0px 0px;
    			font-family: arial;
    			font-size: 13px;
    			text-align:right;
    			padding:5px 5px;
    			color: #ccc;
    			float:right;
    			border: 1px #b3b3ff solid;
			}

			li.chat-me{
				list-style-type: none;
			}

			li.chat-him{
				list-style-type: none;
				border: 1px #ccc solid;
    			border-radius: 5px;
    			margin: 5px 20px 0px -30px;
    			font-family: arial;
    			font-size: 13px;
    			text-align:left;
    			padding:5px 5px;
			}

			#buyer-queue li{
				list-style-type: none;
				margin: 0px 0px 0px 0px;
				padding: 10px 0px 10px 20px;
				border-bottom: 1px #ccc solid;
			}

			#buyer-queue{
				margin: 0px;
				padding: 0px;
			}

			#buyer-queue li a:link, a:visited{
				text-decoration: none;
				color: #000;
				font-size: 16px;
			}

			.message-time{
    			font-family: arial;
    			font-size: 10px;
    			margin: 4px 0px 0px 0px;
			}

			#buyer-name{
				font-family: arial;
				font-size: 15px;
				font-weight: bold;
				padding: 25px 0px 20px 20px;
			}
		</style>
		
		<div id="wrapper">
			<div id="client-list-wrapper">
				<div id="client-list-header">
					<div id="identity-wrapper">
						<span id="identity"></span>
						<span id="connect-wrapper"><input type="button" id="connect" value="Connect"/></span>
					</div>
				</div>
				<div style="margin: 0px;">
					<ul id="buyer-queue">
					</ul>
				</div>
			</div>
			<div id="chat-content-wrapper">
				<div id="chat-history-header">
					<div id='buyer-name'></div>
				</div>
				<div id="chat-history-wrapper"></div>
				<div id="type-wrapper">
					<input type="text" id="message"/>&nbsp;<input type="button" id="send-message" value="Send"/>
				</div>
			</div>
		</div>

<!-- 		<div id="chat-wrapper" style="display:none">
			<div id="chat-buyer-identity"></div>
			<div style="height: 300px; width: 200px;border: 1px #ccc solid;" id="chat-history"></div>
		</div> -->

		<script>
			var contentWidth = w - 300;
			var topTypeWrapper = h - 50;

			$(document).ready(function(){
				$('#wrapper').css('height', h-2);
				$('#wrapper').css('width', w-2);

				$('#client-list-wrapper').css('height', h-2);

				$('#chat-content-wrapper').css('width', contentWidth);
				$('#chat-content-wrapper').css('height', h-2);
				
				$('#type-wrapper').css('top', topTypeWrapper);
				$('#type-wrapper').css('width', contentWidth);
				$('#identity').text(seller.name);
				$('#message').css('width', w-300-60);
			});
			

		</script>
	</body>
</html>