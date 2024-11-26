<!DOCTYPE html>
<head>
  <title>Pusher Test</title>
  <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
  <script>

    // Enable pusher logging - don't include this in production
    // Pusher.logToConsole = true;

    // Initialize Pusher
    var pusher = new Pusher('96a1a9b7ae897a91bf91', {
        cluster: 'ap2',
        encrypted: true
    });
    
    
    // Subscribe to the channel
    var channel = pusher.subscribe('my-channel');
    
    // Bind a callback for the `pusher:subscription_succeeded` event
    channel.bind('pusher:subscription_succeeded', function() {
        console.log('Successfully subscribed to my-channel');
    });
    
    // Bind a callback for the custom `message.sent` event
    channel.bind('chat', function(data) {
        console.log('Received message:', data.message);
        var messages = document.getElementById('messages');
        var messageItem = document.createElement('li');
        messageItem.textContent = data.message;
        messages.appendChild(messageItem);
    });
    
    function sendMessage() {
            var message = document.getElementById('message').value;
            fetch('/send-message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message: message })
            }).then(response => response.json()).then(data => {
                console.log(data.status);
            });
        }
   
  </script>
</head>
<body>
  <h1>Pusher Test</h1>
   <h1>Send a Message</h1>
    <input type="text" id="message" placeholder="Enter your message">
    <button onclick="sendMessage()">Send Message</button>

    <h2>Messages:</h2>
    <ul id="messages"></ul>
</body>