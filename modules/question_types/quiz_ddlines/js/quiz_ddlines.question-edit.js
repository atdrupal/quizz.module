(function ($, Drupal) {

  Drupal.behaviors.quizDdlinesQuestionEdit = {
    attach: function (context) {
      // Auto adjust canvas size
      $('#quiz-question-edit-ddlines-form #edit-submit:not(.canvas-adjusted)').click(function () {
        QuizElementList.adjustCanvasSize();
        $(this).addClass('canvas-adjusted');
      });

      // Make hotspot radius only accept numbers:
      $('input#edit-hotspot-radius').keypress(function (e) {
        var key_codes = [48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 0, 8];
        if (!($.inArray(e.which, key_codes) >= 0)) {
          e.preventDefault();
        }
      });
    }
  };

})(jQuery, Drupal);
