/**
 * qtype crossword plugin
 *
 * @package mod_webgl
 * @copyright  2020 Brain station 23 ltd <>  {@link https://brainstation-23.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery','qtype_crossword/crossword'],
    function ($) {
        return {
            setup: function (data, value) {
                $(document).ready(function () {
                    var across = document.getElementById(data).getAttribute('data-across');
                    var x = document.getElementById(data).getAttribute('data-x');
                    var y = document.getElementById(data).getAttribute('data-y');

                    onBlurFuntion(value, x, y, across);
                });

            }
        };
    });
