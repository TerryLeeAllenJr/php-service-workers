var express = require('express'),
    http = require('http');
var app = express();
var server = http.createServer(app);
var io = require('socket.io').listen(server);


var redis = require("redis"),
    client = redis.createClient();

console.log('socket.js running...');
io.on('connection', function(socket){

    // Gets client connection info.
    socket.on('clientCheckIn',function(message){
        console.log(message.clientID+' has connected!');
    });

    socket.on('io', function(message){
        client.rpush( ["io",JSON.stringify(message)], function(err, reply){
            console.log(reply);
        });
    });
    // Passes all update info on based on the passed in parameter.
    socket.on('update', function(message) {
        io.emit(message.message,message.data);
    });
    socket.on('newContent',function(message){
        io.emit('newEditorialContent',message);
    });

    socket.on('disconnect', function(){

    });

});

server.listen(3000, function(){ });