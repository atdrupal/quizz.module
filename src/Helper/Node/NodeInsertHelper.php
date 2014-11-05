<?php

namespace Drupal\quiz\Helper\Node;

class NodeInsertHelper extends NodeHelper {

  public function execute($quiz) {
    // Need to set max_score if this is a cloned node
    $max_score = 0;

    // Copy all the questions belonging to the quiz if this is a new translation.
    if ($quiz->is_new && isset($quiz->translation_source)) {
      quiz()->getQuizHelper()->copyQuestions($quiz);
    }

    // Add references to all the questions belonging to the quiz if this is a cloned quiz (node_clone compatibility)
    if ($quiz->is_new && isset($quiz->clone_from_original_qid)) {
      $old_quiz = quiz_load($quiz->clone_from_original_qid, NULL, TRUE);
      $max_score = $old_quiz->max_score;
      $questions = $old_quiz->getQuestionLoader()->getQuestions();

      // Format the current questions for referencing
      foreach ($questions as $question) {
        $nid = $questions['nid'];
        $questions[$nid]->state = $question->question_status;
        $questions[$nid]->refresh = 0;
      }

      quiz()->getQuizHelper()->setQuestions($quiz, $questions);
    }

    $this->presaveActions($quiz);

    // If the quiz is saved as not randomized we have to make sure that questions belonging to the quiz are saved as not random
    $this->checkNumRandom($quiz);
    $this->checkNumAlways($quiz);

    quiz_controller()->getSettingIO()->updateUserDefaultSettings($quiz);
  }

}
