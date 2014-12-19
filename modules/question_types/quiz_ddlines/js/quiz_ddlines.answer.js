(function ($, Drupal, QuizElementList) {

  Drupal.behaviors.quizDdlinesAnswer = {
    attach: function (context) {
      $('.image-preview', context).each(function () {
        if (this.initialized) {
          return;
        }
        var self = this;
        this.initialized = true;

        // Initialize
        var engine = new Engine();

        // Need to wait for IE to make image beeing displayed
        // This is not a great solution, but it does work.
        setTimeout(function () {
          engine.init(self);

          // Load elements if they exists:
          QuizElementList.load(engine);

          // Show helptext if no alternatives have been added:
          if (engine.isNew()) {
            engine.addHelpText();
          }
        }, ($.browser.msie ? 1000 : 0));
      });
    }
  };

})(jQuery, Drupal, QuizElementList);
