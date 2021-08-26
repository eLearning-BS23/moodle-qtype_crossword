define(['jquery'],
    function ($) {
        return {
            setup: function (data, value) {
                $(document).ready(function () {
                    var id = 'menu' + data;
                    var across = document.getElementById(data).getAttribute('data-across');
                    var x = document.getElementById(data).getAttribute('data-x');
                    var y = document.getElementById(data).getAttribute('data-y');

                    onBlurFuntion(value, x, y, across);
                });

            }
        };
    });
