jQuery(document).ready(function($) {
    $('#chatbot-send').on('click', function() {
        var message = $('#chatbot-input').val();
        if (message.trim() === '') {
            return; // Prevent sending empty messages
        }

        // Clear the input field
        $('#chatbot-input').val('');

        // Append the user's message to the chat window
        $('#chatbot-messages').append('<div>User: ' + message + '</div>');

        // Send the message via AJAX
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'qwc_send_message', // Action hook
                message: message,
                // You may need to pass additional data like conversation UUID, etc.
            },
            success: function(response) {
                // Append the AI's response to the chat window
                if (response.success) {
                    $('#chatbot-messages').append('<div>AI: ' + response.data + '</div>');
                } else {
                    $('#chatbot-messages').append('<div>AI: Error retrieving response.</div>');
                }
            },
            error: function() {
                $('#chatbot-messages').append('<div>AI: Error sending message.</div>');
            }
        });
    });
});
