define(['jquery'],
    function ($) {
        return {
            setup: function onBlurFuntion(data, x, y, across) {

                var answer = data.toLowerCase();

                if (answer) {
                    var across = across;

                    var x = parseInt(x, 10);
                    var y = parseInt(y, 10);

                    if (across && across != 'false') {
                        for (var i = 0; i < answer.length; i++) {
                            var newheight = y + i;
                            var letterposition = 'letter-position-' + x + '-' + newheight;
                            $('#' + letterposition).text(answer[i]);
                        }
                    } else {
                        for (var i = 0; i < answer.length; i++) {
                            var newwidth = x + i;
                            var letterposition = 'letter-position-' + newwidth + '-' + y;
                            $('#' + letterposition).text(answer[i]);
                        }
                    }

                    // $('#' + word + '-listing').addClass('strikeout');
                    // $('#' + word + '-listing').attr('data-solved', true);

                    $('#answer-form').hide();
                } else {
                    if (!$('#answer-results').is(':visible')) {
                        $('#answer-results').show();
                        $('#answer-results').html('Incorrect Answer, Please Try Again');
                    }
                }

                return false;
                
            }
        };
    });
