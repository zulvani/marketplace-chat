/*
 * Target
 * 1. When seller was disconnected on the conversation and then seller reconnected, buyer list should be retrieved and 
 *    show in seller's buyer list.  
 */

var messageState = {
	'CREATED':            1, // client machine time where this message object constructed
	'SENT_BY_CLIENT':     2, // message has been sent by client (this value is based on client machine time, so please do not use for any decision, use it just as information)
	'RECEIVED_BY_SERVER': 3, // message has been received by server
	'SENT_BY_SERVER':     4, // message has been sent by server to receiver
	'RECEIVED':           5, // message has been received by buyer / seller
	'READ':               6  // message has been read by buyer / seller
}

var entityType = {
	'SELLER': 1,
	'BUYER': 2
}

var app = require('express')();
var http = require('http').Server(app);
var io = require('socket.io')(http);
var host = 'https://evo-chat-persist.herokuapp.com';
var ioClient = require('socket.io-client')(host);

var sockets = [];
var members = [];
var sellerBuyers = [];

var logAllSockets = function(){
	for(i in sockets){
		console.log('socket: ' + sockets[i].socket.id);
	}
}

var getSocket = function(memberId){
	for(s in sockets){
		if(sockets[s].member.id === memberId){
			return sockets[s].socket;
		}
	}
	return null;
}

var removeSocket = function(socket){
	var member = null;
	var pos = -1;
	for(i in sockets){
		if(sockets[i].socket.id === socket.id){
			pos = i;
			member = sockets[i].member;
			break;
		}
	}
	if(pos >= 0){
		sockets.splice(pos, 1);
	}
	return member;
}

var disconnect = function(socket){
	member = removeSocket(socket);
	if(member != null){
		if(member.type === entityType.SELLER){
			io.to(member.id).emit('updateSellerStatus', false);
			console.log('seller disconnected ---: ' + socket.id);
		}
		else if(member.type === entityType.BUYER){
			console.log('buyer disconnected ---: ' + socket.id);
			removeBuyerFromSeller(member);
		}
	}
}

var removeBuyerFromSeller = function(member){
	for(i in sellerBuyers){
		for(j in sellerBuyers[i].buyers){
			if(sellerBuyers[i].buyers[j].buyer.id === member.id){
				sellerBuyers[i].buyers.splice(j, 1);
				var sellerSocket = getSocket(sellerBuyers[i].seller.id);
				if(sellerSocket != null){
					sellerSocket.emit('buyerDisconnected', member);
				}
			}
		}
	}
}

var pushBuyerToSeller = function(seller, buyer, socket){
	var found = false;
	var buyerObj = {"buyer": buyer};

	for(i in sellerBuyers){
		if(sellerBuyers[i].seller.id === seller.id){
			sellerBuyers[i].buyers.push(buyerObj);
			found = true;
			break;
		}
	}
	sellerBuyers.push({"seller": seller, "buyers": [buyerObj]});
}

var pullBuyerFromSeller = function(seller){
	var buyers = [];
	for(i in sellerBuyers){
		if(sellerBuyers[i].seller.id === seller.id){
			return sellerBuyers[i].buyers;
		}
	}
	return null;
}

var pullHistoryMessages = function(member){
	var messages = [];
	for(i in members){
		if(members[i].member.id === member.id){
			return members[i].messages;
		}
	}
	return null;
}

var getUnsentMessages = function(member, from){
	for(i in members){
		if(members[i].member.id === member.id){
			for(j in members[i].messages){
				if(members[i].messages[j].from.id === from.id){
					return members[i].messages[j].messages;
				}
			}
		}
	}

	return null;
}

app.get('/ex', function(req, res){
	res.sendFile(__dirname + '/static/ex.html');
});

// app.get('/buyer', function(req, res){
// 	res.sendFile(__dirname + '/static/buyer.html');
// });

io.on('connection', function(socket){
	socket.on('disconnect', function(){
		disconnect(socket);
	});

	socket.on('memberConnect', function(seller, buyer, type){
		console.log('connected: ' + socket.id + '; type: ' + type);
		socket.join(seller.id);
		
		if(type === 'seller'){
			seller.socketId = socket.id;
			sockets.push({'member': seller, 'socket': socket});
			members.push({'member': seller, 'messages': []});

			// @TODO
			// Make more simple, I think we can use only one emit from the two following 
			io.to(seller.id).emit('sellerConnected', seller);
			io.to(seller.id).emit('updateSellerStatus', true);

			var buyers = pullBuyerFromSeller(seller);
			if(buyers != null){
				console.log('buyers length: ' + buyers.length);
			}
			for(i in buyers){
				console.log('current buyer: ' + buyers[i].name);
			}
			if(buyers != null){
				socket.emit('retrieveAllBuyers', buyers);
			}

			socket.emit('connected', seller);
		}
		else if(type === 'buyer'){
			buyer.socketId = socket.id;
			sockets.push({'member': buyer, 'socket': socket});
			members.push({'member': buyer, 'messages': []});
			socket.emit('memberConnected', buyer);	
		}

		logAllSockets();
	});

	socket.on('memberDisconnect', function(seller, type){
		// socket.disconnect(); // force socket to disconnect
		disconnect(socket);
	});

	socket.on('isSellerOnline', function(seller){
		var online = getSocket(seller.id) != null;
		io.emit('updateSellerStatus', online);
	});

	socket.on('openChat', function(seller, buyer){
		var sellerSocket = getSocket(seller.id);
		var buyerSocket = getSocket(buyer.id);

		console.log('open chat buyer socket id: ' + buyer.socketId);
		pushBuyerToSeller(seller, buyer, socket);
		sellerSocket.emit('openChat', buyer, sellerBuyers);

		var messages = getUnsentMessages(buyer, seller);
		buyerSocket.emit('openChat', messages);
	});

	socket.on('historyChat', function(seller, buyer){
		var sellerSocket = getSocket(seller.id);
		var buyerSocket = getSocket(buyer.id);

		console.log('history chatttttt:' + seller.id);

		messages = getUnsentMessages(seller, buyer);
		sellerSocket.emit('historyChat', messages);
	});

	socket.on('chatMessage', function(message){
		var receiverSocket = getSocket(message.to.id);
		message.state = messageState.RECEIVED_BY_SERVER;
		message.receivedByServerDate = new Date().getTime();

		if(receiverSocket != null){
			message.state = messageState.SENT_BY_SERVER;
			message.sentByServerDate = new Date().getTime();
			receiverSocket.emit('chatMessage', message);
		}

		// save message to history 
		for(i in members){
			if(members[i].member.id === message.to.id || members[i].member.id === message.from.id){
				var found = false;
				for(j in members[i].messages){
					if(members[i].messages[j].from.id === message.from.id || members[i].messages[j].from.id === message.to.id){
						members[i].messages[j].messages.push(message);
						found = true;
						break;
					}
				}

				if(found === false){
					var messageObj = {"from": message.from, "messages": [message]};
					members[i].messages.push(messageObj);
				}
			}
		}
	});

	socket.on('chatMessageReceived', function(message){
		message.state = messageState.RECEIVED;
		message.receivedDate = new Date().getTime();

		var receiverSocket = getSocket(message.from.id);
		if(receiverSocket != null){
			receiverSocket.emit('chatMessageReceived', message);
		}

		// remove received message from message history
		for(i in members){
			if(members[i].member.id === message.to.id || members[i].member.id === message.from.id){
				for(j in members[i].messages){
					if(members[i].messages[j].from.id === message.from.id || members[i].messages[j].from.id === message.to.id){
						for(k in members[i].messages[j].messages){
							if(members[i].messages[j].messages[k].id === message.id){
								members[i].messages.splice(j, 1);
								ioClient.emit('persist', message);
								break;
							}
						}
					}
				}
			}
		}
	});

	socket.on('chatMessageRead', function(messages){
		var receiverSocket = null;

		for(i in messages){
			if(receiverSocket == null){
				receiverSocket = getSocket(messages[i].from.id);
			}
			messages[i].state = messageState.READ;
			messages[i].readDate = new Date().getTime();
		}

		if(receiverSocket != null){
			receiverSocket.emit('chatMessageRead', messages);
		}
	});

	// ------------------- TYPING -------------------------

	// @TODO
	// We able to make this algoritm more simple
	socket.on('typing', function(seller, buyer, from){
		var bs = null; // buyer socket
		var ss = null; // seller socket

		if(from == 'buyer'){
			ss = getSocket(seller.id);
			if(ss != null){
				ss.emit('typing', seller, buyer, from);
			}
		}
		else{
			bs = getSocket(buyer.id);
			if(bs != null){
		 		bs.emit('typing', seller, buyer, from);
		 	}
		 }
	});

	// @TODO
	// We able to make this algoritm more simple
	socket.on('stop-typing', function(seller, buyer, from){
		var bs = null; // buyer socket
		var ss = null; // seller socket

		if(from == 'buyer'){
			ss = getSocket(seller.id);
			if(ss != null){
				ss.emit('stop-typing', seller, buyer, from);
			}
		}
		else{
			bs = getSocket(buyer.id);
			if(bs != null){
		 		bs.emit('stop-typing', seller, buyer, from);
		 	}
		 }
	});
});

http.listen(process.env.PORT || 3000, function(){
	console.log('listening on *:' + process.env.PORT);
});