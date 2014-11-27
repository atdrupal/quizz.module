<?php

namespace Drupal\quizz\Controller;

use Drupal\quizz\Entity\QuizEntity;
use Drupal\quizz\Entity\Result;
use RuntimeException;
use stdClass;

class QuizTakeController {

  /** @var Result */
  private $result;

  /** @var int */
  protected $result_id;

  /** @var QuizEntity */
  protected $quiz;

  /** @var stdClass */
  private $account;

  public function __construct($quiz, $account) {
    $this->quiz = $quiz;
    $this->account = $account;
  }

  public function render() {
    try {
      if (isset($this->quiz->rendered_content)) {
        return $this->quiz->rendered_content;
      }

      $this->initQuizResult();
      if ($this->result_id) {
        drupal_goto($this->getQuestionTakePath());
      }
    }
    catch (\RuntimeException $e) {
      return array(
          'body' => array(
              '#prefix' => '<div class="messages error">',
              '#suffix' => '</div>',
              '#markup' => $e->getMessage()
          )
      );
    }
  }

  private function getQuestionTakePath() {
    $current = $_SESSION['quiz'][$this->quiz->qid]['current'];
    return "quiz/{$this->quiz->qid}/take/{$current}";
  }

  public function initQuizResult() {
    // Inject result from user's session
    if (!empty($_SESSION['quiz'][$this->quiz->qid]['result_id'])) {
      $this->result_id = $_SESSION['quiz'][$this->quiz->qid]['result_id'];
      $this->result = quiz_result_load($this->result_id);
    }

    // Enforce that we have the same quiz version.
    if (($this->result) && ($this->quiz->vid != $this->result->quiz_vid)) {
      $this->quiz = $this->result->getQuiz();
    }

    // Resume quiz progress
    if (!$this->result && $this->quiz->allow_resume) {
      $this->initQuizResume();
    }

    // Start new quiz progress
    if (!$this->result) {
      if (!$this->checkAvailability()) {
        throw new RuntimeException(t('This @quiz is closed.', array('@quiz' => QUIZ_NAME)));
      }

      $this->result = quiz_controller()->getResultGenerator()->generate($this->quiz, $this->account);
      $this->result_id = $this->result->result_id;
      $_SESSION['quiz'][$this->quiz->qid]['result_id'] = $this->result->result_id;
      $_SESSION['quiz'][$this->quiz->qid]['current'] = 1;

      module_invoke_all('quiz_begin', $this->quiz, $this->result->result_id);
    }

    if (TRUE !== $this->quiz->isAvailable($this->account)) {
      throw new RuntimeException(t('This @quiz is not available.', array('@quiz' => QUIZ_NAME)));
    }
  }

  /**
   * If we allow resuming we can load it from the database.
   */
  public function initQuizResume() {
    if (!$result_id = $this->activeResultId($this->account->uid, $this->quiz->qid)) {
      return FALSE;
    }

    $_SESSION['quiz'][$this->quiz->qid]['result_id'] = $result_id;
    $_SESSION['quiz'][$this->quiz->qid]['current'] = 1;
    $this->result = quiz_result_load($result_id);
    $this->quiz = quiz_load($this->result->quiz_qid, $this->result->quiz_vid);
    $this->result_id = $result_id;

    // Resume a quiz from the database.
    drupal_set_message(t('Resuming a previous @quiz in-progress.', array('@quiz' => QUIZ_NAME)), 'status');
  }

  /**
   * Returns the result ID for any current result set for the given quiz.
   *
   * @param int $uid
   * @param int $qid Quiz version ID
   * @param int $now
   *   Timestamp used to check whether the quiz is still open. Default: current
   *   time.
   *
   * @return int
   *   If a quiz is still open and the user has not finished the quiz,
   *   return the result set ID so that the user can continue. If no quiz is in
   *   progress, this will return 0.
   */
  private function activeResultId($uid, $qid, $now = NULL) {
    $sql = 'SELECT result.result_id'
      . ' FROM {quiz_results} result'
      . '   INNER JOIN {quiz_entity_revision} quiz ON result.quiz_vid = quiz.vid'
      . ' WHERE'
      . '   (quiz.quiz_always = :quiz_always OR (:between BETWEEN quiz.quiz_open AND quiz.quiz_close))'
      . '   AND result.quiz_qid = :qid '
      . '   AND result.uid = :uid '
      . '   AND result.time_end IS NULL';

    // Get any quiz that is open, for this user, and has not already been completed.
    return (int) db_query($sql, array(
          ':quiz_always' => 1,
          ':between'     => $now ? $now : REQUEST_TIME,
          ':qid'         => $qid,
          ':uid'         => $uid
      ))->fetchField();
  }

  /**
   * Actions to take place at the start of a quiz.
   *
   * This is called when the quiz entity is viewed for the first time. It ensures
   * that the quiz can be taken at this time.
   *
   * @return
   *   Return quiz_results result_id, or FALSE if there is an error.
   */
  private function checkAvailability() {
    $user_is_admin = entity_access('update', 'quiz_entity', $this->quiz);

    // Make sure this is available.
    if ($this->quiz->quiz_always != 1) {
      // Compare current GMT time to the open and close dates (which should still
      // be in GMT time).
      $now = REQUEST_TIME;

      if ($now >= $this->quiz->quiz_close || $now < $this->quiz->quiz_open) {
        if ($user_is_admin) {
          $msg = t('You are marked as an administrator or owner for this @quiz. While you can take this @quiz, the open/close times prohibit other users from taking this @quiz.', array('@quiz' => QUIZ_NAME));
          drupal_set_message($msg, 'status');
        }
        else {
          $msg = t('This @quiz is not currently available.', array('@quiz' => QUIZ_NAME));
          drupal_set_message($msg, 'status');
          return FALSE; // Can't take quiz.
        }
      }
    }

    // Check to see if this user is allowed to take the quiz again:
    if ($this->quiz->takes > 0) {
      $taken = db_query("SELECT COUNT(*) AS takes FROM {quiz_results} WHERE uid = :uid AND quiz_qid = :qid", array(
          ':uid' => $this->account->uid,
          ':qid' => $this->quiz->qid
        ))->fetchField();
      $allowed_times = format_plural($this->quiz->takes, '1 time', '@count times');
      $taken_times = format_plural($taken, '1 time', '@count times');

      // The user has already taken this quiz.
      if ($taken) {
        if ($user_is_admin) {
          $msg = t('You have taken this @quiz already. You are marked as an owner or administrator for this quiz, so you can take this quiz as many times as you would like.', array('@quiz' => QUIZ_NAME));
          drupal_set_message($msg, 'status');
        }
        // If the user has already taken this quiz too many times, stop the user.
        elseif ($taken >= $this->quiz->takes) {
          $msg = t('You have already taken this @quiz @really. You may not take it again.', array('@quiz', QUIZ_NAME, '@really' => $taken_times));
          drupal_set_message($msg, 'error');
          return FALSE;
        }
        // If the user has taken the quiz more than once, see if we should report
        // this.
        elseif ($this->quiz->show_attempt_stats) {
          $msg = t("You can only take this @quiz @allowed. You have taken it @really.", array('@quiz' => QUIZ_NAME, '@allowed' => $allowed_times, '@really' => $taken_times));
          drupal_set_message($msg, 'status');
        }
      }
    }

    // Check to see if the user is registered, and user alredy passed this quiz.
    if ($this->quiz->show_passed && $this->account->uid && quiz()->getQuizHelper()->isPassed($this->account->uid, $this->quiz->qid, $this->quiz->vid)) {
      $msg = t('You have already passed this @quiz.', array('@quiz' => QUIZ_NAME));
      drupal_set_message($msg, 'status');
    }

    return TRUE;
  }

}
