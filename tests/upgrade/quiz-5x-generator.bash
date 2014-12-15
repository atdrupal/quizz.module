# Install site
drush si -y testing
drush dl -y quiz-7.x-5.x --package-handler=git_drupalorg
drush en -y locale ctools field field_sql_storage file filter image node system text user entity views views_bulk_operations
drush en -y quiz_page quiz quiz_question quiz_ddlines long_answer matching quiz_directions multichoice scale short_answer truefalse

DRUPAL_ROOT=`drush ev 'echo DRUPAL_ROOT;'`

echo "Config admin/quiz/settings/questions_settings…"
drush dpost admin/quiz/settings/questions_settings '{
  "long_answer_default_max_score" : "20",
  "quiz_matching_form_size": "20",
  "multichoice_def_scoring": "1",
  "quiz_ddlines_canvas_width": 777,
  "quiz_ddlines_canvas_height": "555",
  "quiz_ddlines_hotspot_radius": "11",
  "quiz_ddlines_pointer_radius": "7",
  "quiz_ddlines_feedback_correct": "DDLines feedback for correct",
  "quiz_ddlines_feedback_wrong": "DDLines feedback for incorrect",
  "scale_max_num_of_alts": "11",
  "short_answer_default_max_score": "7"
}' > /dev/null

echo "Config admin/quiz/settings/config…"
drush dpost admin/quiz/settings/config '{
  "quiz_auto_revisioning": "FALSE",
  "quiz_durod": "1",
  "quiz_default_close": 31,
  "quiz_max_result_options": 7,
  "quiz_remove_partial_quiz_record": "604800",
  "quiz_autotitle_length": "77",
  "quiz_pager_start": "77",
  "quiz_pager_siblings": "7",
  "quiz_name": "Super quiz",
  "quiz_email_results": "TRUE",
  "quiz_email_results_subject_taker": "!title Results Notice from !sitename",
  "quiz_email_results_body_taker": "*****",
  "quiz_email_results_body": "*****"
}' > /dev/null

echo "Config admin/quiz/settings/quiz-form…"
drush dpost admin/quiz/settings/quiz-form '{
  "allow_resume": "FALSE",
  "allow_skipping": "1",
  "allow_jumping": "1",
  "allow_change": "FALSE",
  "backwards_navigation": "FALSE",
  "repeat_until_correct": "TRUE",
  "build_on_last": "correct",
  "mark_doubtful": "1",
  "show_passed": "FALSE",
  "randomization": "1",
  "review_options[question][quiz_question_view_full]": "quiz_question_view_full",
  "review_options[question][quiz_question_view_teaser]": "quiz_question_view_teaser",
  "review_options[question][attempt]": "attempt",
  "review_options[question][choice]": "choice",
  "review_options[question][correct]": "correct",
  "review_options[question][score]": "score",
  "review_options[question][answer_feedback]": "answer_feedback",
  "review_options[question][question_feedback]": "question_feedback",
  "review_options[question][solution]": "solution",
  "review_options[question][quiz_feedback]": "quiz_feedback",
  "review_options[end][quiz_question_view_full]": "",
  "review_options[end][quiz_question_view_teaser]": "quiz_question_view_teaser",
  "review_options[end][attempt]": "attempt",
  "review_options[end][choice]": "choice",
  "review_options[end][correct]": "correct",
  "review_options[end][score]": "score",
  "review_options[end][answer_feedback]": "answer_feedback",
  "review_options[end][question_feedback]": "question_feedback",
  "review_options[end][solution]": "solution",
  "review_options[end][quiz_feedback]": "quiz_feedback",
  "takes": "7",
  "show_attempt_stats": "FALSE",
  "keep_results": "2",
  "time_limit": "777",
  "pass_rate": "77",
  "summary_pass[value]": "*****",
  "summary_default[value]": "*****"
}' > /dev/null

echo "Creating some quizzes…"
drush dpost node/add/quiz '{"title": "Quiz 1", "body[und][0][value]": "Quiz 1 body"}' > /dev/null
drush dpost node/add/quiz '{"title": "Quiz 2", "body[und][0][value]": "Quiz 2 body"}' > /dev/null
drush dpost node/add/quiz '{"title": "Quiz 3", "body[und][0][value]": "Quiz 3 body"}' > /dev/null
quiz_ids=`drush sqlq 'SELECT nid FROM node WHERE type="quiz"'`

echo "Create revision for each quiz"
for quiz_id in $quiz_ids
do
  drush dpost node/$quiz_id/edit '{"revision": 1, "log": "Dummy text"}' > /dev/null
done

echo "Creating some questions…"
rand=`drush sqlq 'SELECT ROUND(10 * RAND());'`
for i in $(eval echo "{1..$rand}");
do
  dummy_file=$DRUPAL_ROOT'/misc/druplicon.png'
  drush dpost node/add/quiz-ddlines '{"title": "ddline question '$i'", "body[und][0][value]": "Dummy question quiz-ddlines", "feedback[value]": "Dummy feedback", "files[field_image_und_0]": "'dummy_file'"}' > /dev/null
done

rand=`drush sqlq 'SELECT ROUND(10 * RAND());'`
for i in $(eval echo "{1..$rand}");
do
  drush dpost node/add/long-answer '{"title": "long-answer question '$i'", "body[und][0][value]": "Dummy question long-answer", "feedback[value]": "Dummy feedback", "rubric": "Dummy rubric"}' > /dev/null
done

rand=`drush sqlq 'SELECT ROUND(10 * RAND());'`
for i in $(eval echo "{1..$rand}");
do
  drush dpost node/add/matching '{"title": "matching question '$i'", "body[und][0][value]": "Dummy question matching", "feedback[value]": "Dummy feedback", "choice_penalty": 1, "match[1][question]": "Say 1", "match[1][answer]": "1", "match[1][feedback]": "Should say 1", "match[2][question]": "Say 2", "match[2][answer]": "2", "match[2][feedback]": "Should say 2"}' > /dev/null
done

rand=`drush sqlq 'SELECT ROUND(10 * RAND());'`
for i in $(eval echo "{1..$rand}");
do
  drush dpost node/add/multichoice '{"title": "multichoice question '$i'", "body[und][0][value]": "Dummy question multichoice", "feedback[value]": "Dummy feedback", "alternatives[0][answer][value]": "Say TRUE", "alternatives[0][correct]": "1", "alternatives[1][answer][value]": "Say FALSE", "alternatives[1][correct]": "1"}' > /dev/null
done

rand=`drush sqlq 'SELECT ROUND(10 * RAND());'`
for i in $(eval echo "{1..$rand}");
do
  drush dpost node/add/quiz-page '{"title": "page question '$i'", "body[und][0][value]": "Dummy question quiz-page", "feedback[value]": "Dummy feedback"}' > /dev/null
done

rand=`drush sqlq 'SELECT ROUND(10 * RAND());'`
for i in $(eval echo "{1..$rand}");
do
  drush dpost node/add/scale '{"title": "scale question '$i'", "body[und][0][value]": "Dummy question scale", "feedback[value]": "Dummy feedback", "presets": "1", "alternative0": "Execellent", "alternative1": "Very good", "alternative2": "Good", "alternative3": "OK"}' > /dev/null
done

rand=`drush sqlq 'SELECT ROUND(10 * RAND());'`
for i in $(eval echo "{1..$rand}");
do
  drush dpost node/add/short-answer '{"title": "short-answer question '$i'", "body[und][0][value]": "Dummy question short-answer", "feedback[value]": "Dummy feedback", "correct_answer_evaluation": "1", "correct_answer": "A"}' > /dev/null
done

rand=`drush sqlq 'SELECT ROUND(10 * RAND());'`
for i in $(eval echo "{1..$rand}");
do
  drush dpost node/add/truefalse '{"title": "truefalse question '$i'", "body[und][0][value]": "Dummy question truefalse", "feedback[value]": "Dummy feedback", "correct_answer": "1"}' > /dev/null
done

echo "Adding questions to quiz…"
for quiz_id in $quiz_ids
do
  rand=`drush sqlq 'SELECT ROUND(10 * RAND());'`
  question_ids=`drush sqlq 'SELECT nid FROM node WHERE type <> "quiz" ORDER BY RAND()  LIMIT '$rand`
  for question_id in $question_ids
  do
    drush ev '$_GET["q"] = "node/'$quiz_id'"; $question = node_load('$question_id'); quiz_add_question_to_quiz($question);'
  done
done

echo "Dumping database & compress it…"
rm -f /tmp/quiz-*
drush scr ../../scripts/dump-database-d7.sh > /tmp/quiz-5x.php
sed -i -e "s,#\!/usr/bin/env php,,g" /tmp/quiz-5x.php
gzip -9 /tmp/quiz-5x.php

echo "Done!"
