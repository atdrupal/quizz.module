(function($) {
  Drupal.behaviors.question_pool = {
    attach: function(context, settings) {
      if (typeof settings.question_pool !== "undefined") {
        $("#quiz-question-answering-form").submit();
      }
    }
  }
})(jQuery);