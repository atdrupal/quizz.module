<?php

class QuestionPoolTestCase extends QuizQuestionTestCase {

  protected $questionPlugin = 'question_pool';

  public static function getInfo() {
    return array(
        'name'        => 'Question pool',
        'description' => 'Test cases for pool question type.',
        'group'       => 'Quiz question',
    );
  }

  public function testCreateQuestion() {
    return $this->drupalCreateQuestion(array(
          'type'  => 'question_pool',
          'title' => 'Pool 1 title',
          'body'  => 'Scale 1 body text',
    ));
  }

}
