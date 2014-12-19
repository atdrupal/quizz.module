(function ($, Drupal, QuizElementList) {

  Drupal.behaviors.quizDdlinesFeedback = {
    attach: function (context) {
      // Initializing the result page; the correct answer part
      $('.quiz-ddlines-correct-answers', context).each(function () {
        // Id is the node id of the question. Will be overwritten in engine.init
        var id = $(this).attr('id');
        var engine = new Engine();
        engine.init(this, id);

        // Load elements if they exists:
        QuizElementList.load(engine, false, id);
      });

      // Initializing the result page; the user answer part
      $('.quiz-ddlines-user-answers', context).each(function () {
        // Id is the node id of the question.
        // Will be overwritten in engine.init
        var id = $(this).attr('id');
        var engine = new Engine();
        engine.init(this, id);

        // Load elements if they exists:
        QuizElementList.load(engine, true, id);
      });
    }
  };

})(jQuery, Drupal, QuizElementList);
