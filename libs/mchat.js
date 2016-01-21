var delay = (function(){
  	var timer = 0;
	return function(callback, ms){
		clearTimeout (timer);
		timer = setTimeout(callback, ms);
	};
})();

var generateMessageId = function(member){
	var mtime = new Date().getTime();
	var rand = Math.floor((Math.random() * 99999) + 1);
	return mtime.toString() + rand.toString() + member.id;
};

var delayTypeTime = 1000;

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

var constructMessage = function(message, from, to){
	var messageTime = new Date().getTime();
	var messageId = generateMessageId(from);
	return {
		'id': messageId, 
		'content': message, 
		'from': from, 
		'to': to, 
		'time': messageTime, 
		'state': messageState.CREATED,
		'createdDate': new Date().getTime(),
		'sentByClientDate': 0,
		'receivedByServerDate': 0,
		'sentByServerDate': 0,
		'receivedDate': 0,
		'readDate': 0,
	};
}