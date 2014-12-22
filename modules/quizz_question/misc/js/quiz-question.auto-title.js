(function ($, Drupal) {
  var maxLength = Drupal.settings.quiz_max_length;
  var body_query = '#edit-body textarea:eq(1), #edit-quiz-question-body-und-0-value';

  function quizStripTags(str) {
    return str.replace(/<\/?[^>]+>/gi, '');
  }

  function quizUpdateTitle() {
    var body = $(body_query).val();
    if (quizStripTags(body).length > maxLength) {
      $('#edit-title').val(quizStripTags(body).substring(0, maxLength - 3) + "…");
    }
    else {
      $('#edit-title').val(quizStripTags(body).substring(0, maxLength));
    }
  }

  $(document).ready(function () {
    $(body_query).keyup(quizUpdateTitle);

    // Do not use auto title if a title already has been set
    if ($('#edit-title').val().length > 0) {
      $(body_query).unbind("keyup", quizUpdateTitle);
    }

    $('#edit-title').keyup(function () {
      $(body_query).unbind("keyup", quizUpdateTitle);
    });
  });

})(jQuery, Drupal);
