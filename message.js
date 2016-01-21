var app = require('express')();
var http = require('http').Server(app);
var io = require('socket.io')(http);

io.on('connection', function(socket){
	console.log('connected');
	
	socket.on('persist', function(message){
		console.log('persist message: ' + message.content + ' from:' + message.from.name + ' to: ' + message.to.name);
	});
});

http.listen(3001, function(){
	console.log('listening on *:3001');
});