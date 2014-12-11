(function ($, Drupal) {

  /**
   * The answer has been converted to upper case becaue the keys entered are
   * displayed as upper case. Hence to avoid case insensitive part, I have made
   * it to upper case.
   */
  var onKeyUp = function (e) {
    var id = this.id;
    var count = $('#' + id).val().length;
    var present_class = $('#' + id).attr('class').replace('form-text', '').trim();
    var field_answer = Drupal.settings.answer[present_class].toUpperCase();
    var value = String.fromCharCode(e.keyCode);

    if (value == field_answer.charAt(count - 1)) {
      e.preventDefault();
      return false;
    }

    $('#' + id).val(function (index, value) {
      return value.substr(0, value.length - 1);
    });
  };

  Drupal.behaviors.cloze = {
    attach: function (context) {
      $('.answering-form .cloze-question input', context).keyup(onKeyUp);
    }
  };

})(jQuery, Drupal);
