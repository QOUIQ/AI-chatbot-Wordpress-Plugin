jQuery(document).ready(function($) {
    // Show/hide chat window
    $('#chat-icon').on('click', function() {
        $('#chatbot-container').toggle(); // Toggle visibility of the chat window
    });

    $('#chatbot-send').on('click', function() {
        var message = $('#chatbot-input').val();
        if (message.trim() === '') {
            return; // Prevent sending empty messages
        }

        // Clear the input field
        $('#chatbot-input').val('');

        // Append the user's message to the chat window as a bubble
        $('#chatbot-messages').append('<div class="message-bubble user">' + message + '</div>');

        // Send the message via AJAX
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'qwc_send_message', // Action hook
                message: message,
            },
            success: function(response) {
                // Append the AI's response to the chat window as a bubble
                if (response.success) {
                    $('#chatbot-messages').append('<div class="message-bubble ai">' + response.data + '</div>');
                } else {
                    $('#chatbot-messages').append('<div class="message-bubble ai">Error retrieving response.</div>');
                }
            },
            error: function() {
                $('#chatbot-messages').append('<div class="message-bubble ai">Error sending message.</div>');
            }
        });
    });
});
